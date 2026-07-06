<?php

namespace App\Console\Commands;

use App\Enums\RentPaymentStatus;
use App\Models\Client;
use App\Models\RentCharge;
use App\Models\RentPayment;
use App\Models\RentalBuilding;
use App\Models\SaleBuilding;
use App\Models\Tenant;
use App\Models\TenantWaterBill;
use App\Services\Legacy\LegacyBalanceValidator;
use App\Services\Rental\TenantBalanceCalculator;
use App\Services\Sales\ClientBalanceCalculator;
use Illuminate\Console\Command;

class ValidateLegacyImport extends Command
{
    protected $signature = 'legacy:validate
                            {file : Path to the legacy MySQL SQL dump used for import}
                            {--samples=5 : Number of tenant/client records to spot-check (0 = all)}
                            {--tenant= : Validate a specific legacy tenant ClientID}
                            {--client= : Validate a specific legacy client ClientID}
                            {--tolerance=0.01 : Maximum allowed difference for monetary totals}';

    protected $description = 'Validate imported data against legacy dump counts and financial totals';

    public function handle(
        LegacyBalanceValidator $validator,
        TenantBalanceCalculator $tenantBalances,
        ClientBalanceCalculator $clientBalances,
    ): int {
        $path = $this->argument('file');

        if (! is_readable($path)) {
            $this->error("File not readable: {$path}");

            return self::FAILURE;
        }

        if (! RentalBuilding::query()->whereNotNull('legacy_id')->exists()) {
            $this->error('No imported legacy rental data found. Run legacy:import first.');

            return self::FAILURE;
        }

        $data = $validator->parseDump($path);
        $sampleSize = max(0, (int) $this->option('samples'));
        $tolerance = (string) $this->option('tolerance');

        $this->info('Validating legacy import against dump: '.$path);
        $this->newLine();

        $countOk = $this->validateCounts($validator, $data);
        $tenantOk = $this->validateTenantTotals($validator, $tenantBalances, $data, $sampleSize, $tolerance);
        $clientOk = SaleBuilding::query()->whereNotNull('legacy_id')->exists()
            ? $this->validateClientTotals($validator, $clientBalances, $data, $sampleSize, $tolerance)
            : true;

        $this->newLine();
        if ($countOk && $tenantOk && $clientOk) {
            $this->info('Validation passed.');

            return self::SUCCESS;
        }

        $this->error('Validation failed — review mismatches above.');

        return self::FAILURE;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $data
     */
    private function validateCounts(LegacyBalanceValidator $validator, array $data): bool
    {
        $this->info('Entity counts (dump vs database):');

        $expected = $validator->expectedImportCounts($data);
        $actual = [
            'rental_buildings' => RentalBuilding::query()->whereNotNull('legacy_id')->count(),
            'rental_units' => \App\Models\RentalUnit::query()->whereNotNull('legacy_id')->count(),
            'tenants' => Tenant::query()->whereNotNull('legacy_id')->count(),
            'rent_charges' => RentCharge::query()->whereNotNull('legacy_id')->count(),
            'rent_payments' => RentPayment::query()->whereNotNull('legacy_id')->count(),
            'tenant_water_bills' => TenantWaterBill::query()->whereNotNull('legacy_id')->count(),
            'sale_buildings' => SaleBuilding::query()->whereNotNull('legacy_id')->count(),
            'sale_units' => \App\Models\SaleUnit::query()->whereNotNull('legacy_id')->count(),
            'clients' => Client::query()->whereNotNull('legacy_id')->count(),
            'sales_payments' => \App\Models\SalesPayment::query()->whereNotNull('legacy_id')->count(),
        ];

        $partialEntities = ['tenants', 'clients', 'rent_charges', 'tenant_water_bills', 'sales_payments'];
        $rows = [];
        $ok = true;

        foreach ($expected as $entity => $dumpCount) {
            if ($dumpCount === 0) {
                continue;
            }

            $dbCount = $actual[$entity] ?? 0;
            $delta = $dbCount - $dumpCount;

            if (in_array($entity, $partialEntities, true)) {
                $status = $dbCount > 0 && $dbCount <= $dumpCount ? 'partial' : ($dbCount === $dumpCount ? 'OK' : 'check');
            } else {
                $status = $dbCount === $dumpCount ? 'OK' : 'FAIL';
                if ($status === 'FAIL') {
                    $ok = false;
                }
            }

            $rows[] = [
                $entity,
                (string) $dumpCount,
                (string) $dbCount,
                (string) $delta,
                $status,
            ];
        }

        $this->table(['Entity', 'Dump rows', 'Imported', 'Delta', 'Status'], $rows);
        $this->line('Partial entities may import fewer rows when legacy references are orphaned.');

        return $ok;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $data
     */
    private function validateTenantTotals(
        LegacyBalanceValidator $validator,
        TenantBalanceCalculator $tenantBalances,
        array $data,
        int $sampleSize,
        string $tolerance,
    ): bool {
        $this->newLine();
        $this->info('Tenant financial spot-check (dump totals vs imported rows):');

        $legacyIds = $this->option('tenant')
            ? [(int) $this->option('tenant')]
            : $validator->sampleLegacyTenantIds($data, $sampleSize);

        if ($legacyIds === []) {
            $this->warn('No tenants in dump to validate.');

            return true;
        }

        $rows = [];
        $ok = true;

        foreach ($legacyIds as $legacyId) {
            $tenant = Tenant::query()->where('legacy_id', $legacyId)->first();
            $legacy = $validator->legacyTenantTotals($data, $legacyId);
            $legacyRow = collect($data['tenants'] ?? [])->first(fn ($row) => (int) ($row['ClientID'] ?? 0) === $legacyId);
            $name = $legacyRow['ClientName'] ?? "Tenant {$legacyId}";

            if ($tenant === null || $legacy === null) {
                $rows[] = [$legacyId, $name, '—', '—', '—', '—', 'missing', 'FAIL'];
                $ok = false;

                continue;
            }

            $importedCharged = bcadd((string) RentCharge::query()->where('tenant_id', $tenant->id)->sum('total_amount'), '0', 2);
            $importedPaid = bcadd((string) RentPayment::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', RentPaymentStatus::Active)
                ->sum('amount'), '0', 2);
            $importedDiscount = bcadd((string) RentPayment::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', RentPaymentStatus::Active)
                ->sum('discount'), '0', 2);

            $chargedMatch = $this->withinTolerance($legacy['expected_imported_charged'], $importedCharged, $tolerance);
            $paidMatch = $this->withinTolerance($legacy['paid'], $importedPaid, $tolerance);
            $discountMatch = $this->withinTolerance($legacy['discount'], $importedDiscount, $tolerance);
            $match = $chargedMatch && $paidMatch && $discountMatch;

            if (! $match) {
                $ok = false;
            }

            $importedBalance = $tenantBalances->calculate($tenant);

            $rows[] = [
                $legacyId,
                $name,
                $legacy['expected_imported_charged'].' → '.$importedCharged,
                $legacy['paid'].' → '.$importedPaid,
                $legacy['balance'].' → '.$importedBalance,
                $match ? 'OK' : 'MISMATCH',
            ];
        }

        $this->table(
            ['Legacy ID', 'Name', 'Charged (dump→db)', 'Paid (dump→db)', 'Balance (legacy→app)', 'Status'],
            $rows,
        );
        $this->line('Charged compares legacy charge + water_bill totals to imported rent_charges.');
        $this->line('Balance column is informational — greenfield allocates payments across water, service, and rent.');

        return $ok;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $data
     */
    private function validateClientTotals(
        LegacyBalanceValidator $validator,
        ClientBalanceCalculator $clientBalances,
        array $data,
        int $sampleSize,
        string $tolerance,
    ): bool {
        $this->newLine();
        $this->info('Client financial spot-check (dump totals vs imported rows):');

        $legacyIds = $this->option('client')
            ? [(int) $this->option('client')]
            : $validator->sampleLegacyClientIds($data, $sampleSize);

        if ($legacyIds === []) {
            $this->warn('No clients in dump to validate.');

            return true;
        }

        $rows = [];
        $ok = true;

        foreach ($legacyIds as $legacyId) {
            $client = Client::query()->where('legacy_id', $legacyId)->first();
            $legacy = $validator->legacyClientTotals($data, $legacyId);
            $legacyRow = collect($data['clients'] ?? [])->first(fn ($row) => (int) ($row['ClientID'] ?? 0) === $legacyId);
            $name = $legacyRow['ClientName'] ?? "Client {$legacyId}";

            if ($client === null || $legacy === null) {
                $rows[] = [$legacyId, $name, '—', '—', '—', 'missing', 'FAIL'];
                $ok = false;

                continue;
            }

            $importedPaid = bcadd((string) $client->payments()->where('status', 'active')->sum('amount'), '0', 2);
            $importedDeposit = bcadd((string) $client->deposit, '0', 2);
            $paidMatch = $this->withinTolerance($legacy['paid'], $importedPaid, $tolerance);
            $depositMatch = $this->withinTolerance($legacy['deposit'], $importedDeposit, $tolerance);
            $agreedMatch = $this->withinTolerance($legacy['agreed'], bcadd((string) $client->agreed_sale_price, '0', 2), $tolerance);
            $match = $paidMatch && $depositMatch && $agreedMatch;

            if (! $match) {
                $ok = false;
            }

            $importedBalance = $clientBalances->calculate($client);

            $rows[] = [
                $legacyId,
                $name,
                $legacy['agreed'],
                $legacy['paid'].' → '.$importedPaid,
                $legacy['balance'].' → '.$importedBalance,
                $match ? 'OK' : 'MISMATCH',
            ];
        }

        $this->table(
            ['Legacy ID', 'Name', 'Agreed price', 'Paid (dump→db)', 'Balance (legacy→app)', 'Status'],
            $rows,
        );

        return $ok;
    }

    private function withinTolerance(string $expected, string $actual, string $tolerance): bool
    {
        $delta = bcsub($actual, $expected, 2);
        if ($delta[0] === '-') {
            $delta = substr($delta, 1);
        }

        return bccomp($delta, $tolerance, 2) <= 0;
    }
}
