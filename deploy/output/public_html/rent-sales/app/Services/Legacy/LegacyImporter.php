<?php

namespace App\Services\Legacy;

use App\Enums\EmployeeStatus;
use App\Enums\RentPaymentStatus;
use App\Enums\RentalUnitStatus;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WaterBillStatus;
use App\Models\BuildingElectricityBill;
use App\Models\BuildingWaterUtilityBill;
use App\Models\Employee;
use App\Models\PayrollEntry;
use App\Models\RentalBuilding;
use App\Models\RentalExpense;
use App\Models\RentalUnit;
use App\Models\RentCharge;
use App\Models\RentPayment;
use App\Models\Shareholder;
use App\Models\ShareholderBill;
use App\Models\Tenant;
use App\Models\TenantMoveOut;
use App\Models\TenantWaterBill;
use App\Models\User;
use App\Services\Rental\WaterBillService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class LegacyImporter
{
    private LegacyImportReport $report;

    /** @var array<string, list<array<string, mixed>>> */
    private array $data = [];

    /** @var array<int, int> */
    private array $userIds = [];

    /** @var array<string, int> */
    private array $userIdsByUsername = [];

    /** @var array<string, int> */
    private array $userIdsByName = [];

    /** @var array<int, int> */
    private array $rentalBuildingIds = [];

    /** @var array<int, int> */
    private array $rentalUnitIds = [];

    /** @var array<int, int> */
    private array $tenantIds = [];

    /** @var array<int, int> */
    private array $employeeIds = [];

    /** @var array<int, int> */
    private array $shareholderIds = [];

    /** @var array<int, int> */
    private array $saleBuildingIds = [];

    /** @var array<int, int> */
    private array $saleUnitIds = [];

    /** @var array<int, int> */
    private array $clientIds = [];

    /** @var array<int, int> */
    private array $waterBillIds = [];

    /** @var array<int, string> */
    private array $waterBillStatusByLegacyId = [];

    private ?int $fallbackUserId = null;

    public function __construct(
        private readonly LegacySqlParser $parser,
        private readonly LegacyMonthMapper $monthMapper,
    ) {
        $this->report = new LegacyImportReport;
    }

    public function report(): LegacyImportReport
    {
        return $this->report;
    }

    public function import(string $path, bool $dryRun = false, bool $fresh = false, bool $skipSales = false): LegacyImportReport
    {
        $this->report = new LegacyImportReport;
        $this->data = $this->parser->parseFile($path);

        if (! $dryRun && ! $fresh && RentalBuilding::query()->whereNotNull('legacy_id')->exists()) {
            throw new RuntimeException(
                'Legacy rental data already imported. Use --fresh to replace domain data or truncate manually.',
            );
        }

        if ($fresh && ! $dryRun) {
            $this->truncateDomainTables();
        }

        $this->indexWaterBillStatuses();

        $callback = function () use ($dryRun, $skipSales): void {
            $this->importUsers($dryRun);
            $this->resolveFallbackUser();
            $this->importRentalBuildings($dryRun);
            $this->importRentalUnits($dryRun);
            $this->importTenants($dryRun);
            $this->importTenantMoveOuts($dryRun);
            $this->importEmployees($dryRun);
            $this->importRentCharges($dryRun);
            $this->importWaterBills($dryRun);
            $this->importRentPayments($dryRun);
            $this->importKenyaWater($dryRun);
            $this->importElectricity($dryRun);
            $this->importRentalExpenses($dryRun);
            $this->importPayroll($dryRun);
            $this->importShareholders($dryRun);
            $this->importShareholderBills($dryRun);

            if (! $skipSales) {
                $this->importSaleBuildings($dryRun);
                $this->importSaleUnits($dryRun);
                $this->importClients($dryRun);
                $this->importSalesPayments($dryRun);
                $this->importSalesExpenses($dryRun);
            }
        };

        if ($dryRun) {
            $callback();
        } else {
            DB::transaction($callback);
        }

        return $this->report;
    }

    private function truncateDomainTables(): void
    {
        DB::statement('
            TRUNCATE TABLE
                charge_adjustments,
                charge_batch_items,
                charge_batches,
                rent_charges,
                rent_payments,
                tenant_electricity_bills,
                tenant_water_bills,
                tenant_move_outs,
                tenants,
                rental_units,
                rental_buildings,
                building_electricity_bills,
                building_water_utility_bills,
                rental_expenses,
                payroll_entries,
                employees,
                shareholder_bills,
                shareholders,
                sales_expenses,
                sales_payments,
                clients,
                sale_units,
                sale_buildings
            RESTART IDENTITY CASCADE
        ');
    }

    private function indexWaterBillStatuses(): void
    {
        foreach ($this->data['water_bill_new'] ?? [] as $row) {
            $legacyId = (int) $row['id'];
            $status = strtolower((string) ($row['status'] ?? 'pending'));
            $this->waterBillStatusByLegacyId[$legacyId] = $status === 'paid'
                ? WaterBillStatus::Paid->value
                : WaterBillStatus::Pending->value;
        }
    }

    private function importUsers(bool $dryRun): void
    {
        foreach ($this->data['users'] ?? [] as $row) {
            $legacyId = (int) $row['id'];
            $username = (string) $row['username'];
            $role = $this->mapUserRole((string) $row['type']);
            $isManager = in_array(strtolower((string) $row['type']), ['admin', 'all'], true);
            $status = strtolower((string) $row['status']) === 'active'
                ? UserStatus::Active
                : UserStatus::Inactive;

            $attributes = [
                'name' => (string) $row['name'],
                'password' => Hash::make((string) $row['password']),
                'role' => $role,
                'status' => $status,
                'is_manager' => $isManager,
            ];

            if ($dryRun) {
                $this->userIds[$legacyId] = $legacyId;
                $this->userIdsByUsername[strtolower($username)] = $legacyId;
                $this->userIdsByName[strtolower((string) $row['name'])] = $legacyId;
                $this->report->increment('users');
                continue;
            }

            $user = User::query()->updateOrCreate(
                ['username' => $username],
                $attributes,
            );

            $this->userIds[$legacyId] = $user->id;
            $this->userIdsByUsername[strtolower($username)] = $user->id;
            $this->userIdsByName[strtolower((string) $row['name'])] = $user->id;
            $this->report->increment('users');
        }
    }

    private function mapUserRole(string $legacyType): UserRole
    {
        return match (strtolower($legacyType)) {
            'sales' => UserRole::Sales,
            'admin', 'all' => UserRole::Admin,
            default => UserRole::Rental,
        };
    }

    private function resolveFallbackUser(): void
    {
        if ($this->fallbackUserId !== null) {
            return;
        }

        if ($this->userIds !== []) {
            $this->fallbackUserId = reset($this->userIds);

            return;
        }

        $this->fallbackUserId = User::query()
            ->where('is_manager', true)
            ->orderBy('id')
            ->value('id')
            ?? User::query()->orderBy('id')->value('id');

        if ($this->fallbackUserId === null) {
            throw new RuntimeException('No users available for attribution.');
        }
    }

    private function importRentalBuildings(bool $dryRun): void
    {
        foreach ($this->data['categories'] ?? [] as $row) {
            $legacyId = (int) $row['id'];

            if ($dryRun) {
                $this->rentalBuildingIds[$legacyId] = $legacyId;
                $this->report->increment('rental_buildings');
                continue;
            }

            $building = RentalBuilding::query()->create([
                'legacy_id' => $legacyId,
                'name' => (string) $row['name'],
            ]);

            $this->rentalBuildingIds[$legacyId] = $building->id;
            $this->report->increment('rental_buildings');
        }
    }

    private function importRentalUnits(bool $dryRun): void
    {
        foreach ($this->data['houses'] ?? [] as $row) {
            $legacyId = (int) $row['aid'];
            $buildingLegacyId = (int) $row['category_id'];
            $buildingId = $this->rentalBuildingIds[$buildingLegacyId] ?? null;

            if ($buildingId === null) {
                $this->report->skip('rental_units');
                $this->report->warn("Skipped house {$legacyId}: unknown building {$buildingLegacyId}.");

                continue;
            }

            $status = strtolower((string) $row['status']) === 'occupied'
                ? RentalUnitStatus::Occupied
                : RentalUnitStatus::Vacant;

            if ($dryRun) {
                $this->rentalUnitIds[$legacyId] = $legacyId;
                $this->report->increment('rental_units');
                continue;
            }

            $unit = RentalUnit::query()->create([
                'legacy_id' => $legacyId,
                'rental_building_id' => $buildingId,
                'house_number' => (string) $row['house_no'],
                'floor' => (string) $row['floor'],
                'description' => (string) $row['description'],
                'monthly_rent' => $this->money($row['price']),
                'status' => $status,
            ]);

            $this->rentalUnitIds[$legacyId] = $unit->id;
            $this->report->increment('rental_units');
        }
    }

    private function importTenants(bool $dryRun): void
    {
        $tenantsWithWater = $this->tenantIdsWithWaterBills();

        foreach ($this->data['tenants'] ?? [] as $row) {
            $legacyId = (int) $row['ClientID'];
            $buildingId = $this->rentalBuildingIds[(int) $row['HouseID']] ?? null;
            $unitLegacyId = (int) trim((string) $row['apartmentNo']);
            $unitId = $this->rentalUnitIds[$unitLegacyId] ?? null;

            if ($buildingId === null || $unitId === null) {
                $this->report->skip('tenants');
                $this->report->warn("Skipped tenant {$legacyId}: unresolved building or unit.");

                continue;
            }

            $status = strtolower((string) $row['status']) === 'active'
                ? TenantStatus::Active
                : TenantStatus::Inactive;

            $payload = [
                'legacy_id' => $legacyId,
                'rental_building_id' => $buildingId,
                'rental_unit_id' => $unitId,
                'name' => (string) $row['ClientName'],
                'phone' => (string) $row['phone'],
                'gender' => $this->nullableString($row['Gender'] ?? null),
                'email' => $this->nullableString($row['Email'] ?? null),
                'passport_or_id' => $this->nullableString($row['Passport'] ?? null),
                'deposit' => $this->money($row['Deposit'] ?? 0),
                'service_amount' => $this->money($row['service_amount'] ?? 0),
                'requires_water_metering' => in_array($legacyId, $tenantsWithWater, true),
                'requires_electricity_metering' => false,
                'next_of_kin_name' => $this->nullableString($row['nextname'] ?? null),
                'next_of_kin_address' => $this->nullableString($row['anddress'] ?? null),
                'next_of_kin_id' => $this->nullableString($row['npassid'] ?? null),
                'next_of_kin_phone' => $this->nullableString($row['nphone'] ?? null),
                'start_date' => $this->nullableDate($row['starteddate'] ?? null),
                'status' => $status,
                'created_by' => $this->resolveUserId($row['username'] ?? null),
            ];

            if ($dryRun) {
                $this->tenantIds[$legacyId] = $legacyId;
                $this->report->increment('tenants');
                continue;
            }

            $tenant = Tenant::query()->create($payload);
            $this->tenantIds[$legacyId] = $tenant->id;
            $this->report->increment('tenants');
        }
    }

    /**
     * @return list<int>
     */
    private function tenantIdsWithWaterBills(): array
    {
        $ids = [];
        foreach ($this->data['water_bill'] ?? [] as $row) {
            $ids[] = (int) $row['tenant_id'];
        }

        return array_values(array_unique($ids));
    }

    private function importTenantMoveOuts(bool $dryRun): void
    {
        foreach ($this->data['moved_out'] ?? [] as $row) {
            $legacyId = (int) $row['id'];
            $tenantId = $this->tenantIds[(int) $row['tenant_id']] ?? null;
            $buildingId = $this->rentalBuildingIds[(int) $row['houseid']] ?? null;
            $unitId = $this->rentalUnitIds[(int) $row['aptno']] ?? null;

            if ($tenantId === null || $buildingId === null || $unitId === null) {
                $this->report->skip('tenant_move_outs');
                $this->report->warn("Skipped move-out {$legacyId}: unresolved references.");

                continue;
            }

            if ($dryRun) {
                $this->report->increment('tenant_move_outs');
                continue;
            }

            TenantMoveOut::query()->create([
                'legacy_id' => $legacyId,
                'tenant_id' => $tenantId,
                'rental_building_id' => $buildingId,
                'rental_unit_id' => $unitId,
                'refund_amount' => $this->money($row['refund'] ?? 0),
                'reason' => (string) ($row['reason'] ?? ''),
                'moved_out_at' => $this->nullableDate($row['action_date'] ?? null) ?? now()->toDateString(),
                'recorded_by' => $this->resolveUserId($row['username'] ?? null),
            ]);

            $this->report->increment('tenant_move_outs');
        }
    }

    private function importEmployees(bool $dryRun): void
    {
        foreach ($this->data['employee'] ?? [] as $row) {
            $legacyId = (int) $row['empid'];
            $buildingId = isset($row['houseid']) ? ($this->rentalBuildingIds[(int) $row['houseid']] ?? null) : null;
            $status = strtolower((string) $row['status']) === 'current'
                ? EmployeeStatus::Current
                : EmployeeStatus::Former;

            if ($dryRun) {
                $this->employeeIds[$legacyId] = $legacyId;
                $this->report->increment('employees');
                continue;
            }

            $employee = Employee::query()->create([
                'legacy_id' => $legacyId,
                'rental_building_id' => $buildingId,
                'name' => (string) ($row['empname'] ?? 'Unknown'),
                'address' => $this->nullableString($row['address'] ?? null),
                'salary' => $this->money($row['salary'] ?? 0),
                'phone' => $this->nullableString($row['phone'] ?? null),
                'position' => (string) ($row['position'] ?? ''),
                'status' => $status,
            ]);

            $this->employeeIds[$legacyId] = $employee->id;
            $this->report->increment('employees');
        }
    }

    private function importRentCharges(bool $dryRun): void
    {
        foreach ($this->data['charge'] ?? [] as $row) {
            $legacyId = (int) $row['id'];
            $tenantId = $this->tenantIds[(int) $row['clientid']] ?? null;
            $unitId = $this->rentalUnitIds[(int) $row['apartmentNo']] ?? null;
            $buildingId = $this->rentalBuildingIds[(int) $row['houseid']] ?? null;

            if ($tenantId === null || $unitId === null || $buildingId === null) {
                $this->report->skip('rent_charges');
                $this->report->warn("Skipped charge {$legacyId}: unresolved references.");

                continue;
            }

            $period = $this->monthMapper->fromTimestamp((string) $row['charge_date']);
            $purpose = (string) ($row['purpose'] ?? 'Rent + service');

            if ($dryRun) {
                $this->report->increment('rent_charges');
                continue;
            }

            if (in_array($purpose, ['Rent + service', 'Water', 'Electricity'], true)) {
                $duplicate = RentCharge::query()
                    ->where('tenant_id', $tenantId)
                    ->where('billing_month', $period['month'])
                    ->where('billing_year', $period['year'])
                    ->where('purpose', $purpose)
                    ->exists();

                if ($duplicate) {
                    $this->report->skip('rent_charges');
                    $this->report->warn("Skipped charge {$legacyId}: duplicate {$purpose} for tenant {$tenantId}.");

                    continue;
                }
            }

            RentCharge::query()->create([
                'legacy_id' => $legacyId,
                'tenant_id' => $tenantId,
                'rental_unit_id' => $unitId,
                'rental_building_id' => $buildingId,
                'billing_month' => $period['month'],
                'billing_year' => $period['year'],
                'rent_amount' => $this->money($row['rent'] ?? 0),
                'service_amount' => $this->money($row['service'] ?? 0),
                'total_amount' => $this->money($row['total'] ?? 0),
                'purpose' => $purpose,
                'charged_at' => (string) $row['charge_date'],
            ]);

            $this->report->increment('rent_charges');
        }
    }

    private function importWaterBills(bool $dryRun): void
    {
        foreach ($this->data['water_bill'] ?? [] as $row) {
            $legacyId = (int) $row['id'];
            $tenantLegacyId = (int) $row['tenant_id'];
            $tenantId = $this->tenantIds[$tenantLegacyId] ?? null;
            $buildingId = $this->rentalBuildingIds[(int) $row['houseid']] ?? null;

            if ($tenantId === null) {
                $this->report->skip('tenant_water_bills');
                $this->report->warn("Skipped water bill {$legacyId}: tenant {$tenantLegacyId} not found.");

                continue;
            }

            if ($buildingId === null) {
                $this->report->skip('tenant_water_bills');
                $this->report->warn("Skipped water bill {$legacyId}: building not found.");

                continue;
            }

            $period = $this->monthMapper->fromMonthYear(
                (string) ($row['month_id'] ?? ''),
                $row['year_id'] ?? null,
            );

            if ($period === null) {
                $this->report->skip('tenant_water_bills');
                $this->report->warn("Skipped water bill {$legacyId}: invalid billing period.");

                continue;
            }

            $statusValue = $this->waterBillStatusByLegacyId[$legacyId] ?? WaterBillStatus::Pending->value;
            $amount = $this->money($row['amount'] ?? 0);

            if ($dryRun) {
                $this->waterBillIds[$legacyId] = $legacyId;
                $this->report->increment('tenant_water_bills');
                $this->report->increment('rent_charges_water');
                continue;
            }

            $bill = TenantWaterBill::query()->create([
                'legacy_id' => $legacyId,
                'tenant_id' => $tenantId,
                'rental_building_id' => $buildingId,
                'billing_month' => $period['month'],
                'billing_year' => $period['year'],
                'previous_reading' => (int) ($row['pr'] ?? 0),
                'current_reading' => (int) ($row['cr'] ?? 0),
                'consumption' => (int) ($row['consumption'] ?? 0),
                'rate' => $this->money($row['rate'] ?? 0),
                'fixed_fee' => $this->money($row['charged_fee'] ?? 0),
                'amount' => $amount,
                'amount_paid' => $statusValue === WaterBillStatus::Paid->value ? $amount : null,
                'status' => $statusValue,
                'remark' => $this->nullableString($row['remark'] ?? null),
                'created_by' => $this->resolveUserId($row['username'] ?? null),
            ]);

            $this->waterBillIds[$legacyId] = $bill->id;

            $unitId = Tenant::query()->whereKey($tenantId)->value('rental_unit_id');

            RentCharge::query()->create([
                'legacy_id' => null,
                'tenant_id' => $tenantId,
                'rental_unit_id' => $unitId,
                'rental_building_id' => $buildingId,
                'billing_month' => $period['month'],
                'billing_year' => $period['year'],
                'rent_amount' => '0.00',
                'service_amount' => '0.00',
                'total_amount' => $amount,
                'purpose' => WaterBillService::CHARGE_PURPOSE,
                'tenant_water_bill_id' => $bill->id,
                'charged_at' => (string) ($row['date_created'] ?? now()),
            ]);

            $this->report->increment('tenant_water_bills');
            $this->report->increment('rent_charges_water');
        }
    }

    private function importRentPayments(bool $dryRun): void
    {
        foreach ($this->data['payments'] ?? [] as $row) {
            $legacyId = (int) $row['id'];
            $tenantId = $this->tenantIds[(int) $row['tenant_id']] ?? null;
            $buildingId = $this->rentalBuildingIds[(int) $row['houseid']] ?? null;

            if ($tenantId === null || $buildingId === null) {
                $this->report->skip('rent_payments');
                $this->report->warn("Skipped payment {$legacyId}: unresolved references.");

                continue;
            }

            if ($dryRun) {
                $this->report->increment('rent_payments');
                continue;
            }

            RentPayment::query()->create([
                'legacy_id' => $legacyId,
                'tenant_id' => $tenantId,
                'rental_building_id' => $buildingId,
                'amount' => $this->money($row['amount'] ?? 0),
                'discount' => $this->money($row['discount'] ?? 0),
                'invoice_reference' => $this->nullableString($row['invoice'] ?? null),
                'paid_at' => (string) $row['date_created'],
                'status' => RentPaymentStatus::Active,
                'created_by' => $this->resolveUserId($row['username'] ?? null),
            ]);

            $this->report->increment('rent_payments');
        }
    }

    private function importKenyaWater(bool $dryRun): void
    {
        foreach ($this->aggregateKenyaWaterRows() as $aggregated) {
            if ($dryRun) {
                $this->report->increment('building_water_utility_bills');
                continue;
            }

            BuildingWaterUtilityBill::query()->create([
                'legacy_id' => $aggregated['legacy_id'],
                'rental_building_id' => $aggregated['rental_building_id'],
                'billing_month' => $aggregated['billing_month'],
                'billing_year' => $aggregated['billing_year'],
                'amount' => $aggregated['amount'],
                'remark' => $aggregated['remark'],
                'billed_at' => $aggregated['billed_at'],
                'created_by' => $aggregated['created_by'],
            ]);

            $this->report->increment('building_water_utility_bills');
        }
    }

    /**
     * Legacy kenya_water allows multiple rows per building/month (Borehole + Gaanjoo).
     * Greenfield enforces one row per period — merge amounts and remarks.
     *
     * @return list<array{
     *     legacy_id: int,
     *     rental_building_id: int,
     *     billing_month: int,
     *     billing_year: int,
     *     amount: string,
     *     remark: ?string,
     *     billed_at: string,
     *     created_by: ?int
     * }>
     */
    private function aggregateKenyaWaterRows(): array
    {
        /** @var array<string, array{legacy_id: int, rental_building_id: int, billing_month: int, billing_year: int, amount: float, remarks: list<string>, billed_at: string, created_by: ?int, source_ids: list<int>}> $groups */
        $groups = [];

        foreach ($this->data['kenya_water'] ?? [] as $row) {
            $legacyId = (int) $row['id'];
            $buildingId = $this->rentalBuildingIds[(int) $row['house_id']] ?? null;

            if ($buildingId === null) {
                $this->report->skip('building_water_utility_bills');
                $this->report->warn("Skipped kenya_water {$legacyId}: building not found.");

                continue;
            }

            $period = $this->monthMapper->fromMonthYear(
                (string) ($row['month_id'] ?? ''),
                $row['year_id'] ?? null,
            );

            if ($period === null) {
                $this->report->skip('building_water_utility_bills');
                $this->report->warn("Skipped kenya_water {$legacyId}: invalid billing period.");

                continue;
            }

            $key = "{$buildingId}:{$period['month']}:{$period['year']}";
            $billedAt = date('Y-m-d', strtotime((string) $row['date_created']));
            $remark = $this->nullableString($row['remark'] ?? null);

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'legacy_id' => $legacyId,
                    'rental_building_id' => $buildingId,
                    'billing_month' => $period['month'],
                    'billing_year' => $period['year'],
                    'amount' => (float) ($row['amount'] ?? 0),
                    'remarks' => $remark !== null ? [$remark] : [],
                    'billed_at' => $billedAt,
                    'created_by' => $this->resolveUserId($row['username'] ?? null),
                    'source_ids' => [$legacyId],
                ];

                continue;
            }

            $groups[$key]['amount'] += (float) ($row['amount'] ?? 0);
            $groups[$key]['legacy_id'] = min($groups[$key]['legacy_id'], $legacyId);
            $groups[$key]['source_ids'][] = $legacyId;

            if ($remark !== null && ! in_array($remark, $groups[$key]['remarks'], true)) {
                $groups[$key]['remarks'][] = $remark;
            }

            if ($billedAt < $groups[$key]['billed_at']) {
                $groups[$key]['billed_at'] = $billedAt;
            }
        }

        $aggregated = [];

        foreach ($groups as $group) {
            if (count($group['source_ids']) > 1) {
                $this->report->warn(sprintf(
                    'Merged kenya_water rows %s into one building utility bill for %d/%d.',
                    implode(', ', $group['source_ids']),
                    $group['billing_month'],
                    $group['billing_year'],
                ));
            }

            $aggregated[] = [
                'legacy_id' => $group['legacy_id'],
                'rental_building_id' => $group['rental_building_id'],
                'billing_month' => $group['billing_month'],
                'billing_year' => $group['billing_year'],
                'amount' => $this->money($group['amount']),
                'remark' => $group['remarks'] === [] ? null : implode('; ', $group['remarks']),
                'billed_at' => $group['billed_at'],
                'created_by' => $group['created_by'],
            ];
        }

        return $aggregated;
    }

    private function importElectricity(bool $dryRun): void
    {
        foreach ($this->data['electricity'] ?? [] as $row) {
            $legacyId = (int) $row['eid'];
            $buildingId = $this->rentalBuildingIds[(int) $row['houseid']] ?? null;

            if ($buildingId === null) {
                $this->report->skip('building_electricity_bills');
                $this->report->warn("Skipped electricity {$legacyId}: building not found.");

                continue;
            }

            $period = $this->monthMapper->fromMonthYear(
                (string) ($row['month'] ?? ''),
                $row['year'] ?? null,
            );

            if ($period === null) {
                $this->report->skip('building_electricity_bills');
                $this->report->warn("Skipped electricity {$legacyId}: invalid billing period.");

                continue;
            }

            if ($dryRun) {
                $this->report->increment('building_electricity_bills');
                continue;
            }

            BuildingElectricityBill::query()->create([
                'legacy_id' => $legacyId,
                'rental_building_id' => $buildingId,
                'billing_month' => $period['month'],
                'billing_year' => $period['year'],
                'amount' => $this->money($row['amount'] ?? 0),
                'remark' => $this->nullableString($row['remark'] ?? null),
                'billed_at' => (string) ($row['date'] ?? now()->toDateString()),
            ]);

            $this->report->increment('building_electricity_bills');
        }
    }

    private function importRentalExpenses(bool $dryRun): void
    {
        foreach ($this->data['expenses'] ?? [] as $row) {
            $legacyId = (int) $row['expid'];
            $buildingId = $this->rentalBuildingIds[(int) $row['house_id']] ?? null;

            if ($buildingId === null) {
                $this->report->skip('rental_expenses');
                $this->report->warn("Skipped expense {$legacyId}: building not found.");

                continue;
            }

            if ($dryRun) {
                $this->report->increment('rental_expenses');
                continue;
            }

            RentalExpense::query()->create([
                'legacy_id' => $legacyId,
                'rental_building_id' => $buildingId,
                'name' => (string) $row['expensename'],
                'amount' => $this->money($row['amountpaid'] ?? 0),
                'description' => $this->nullableString($row['description'] ?? null),
                'expense_date' => (string) ($row['expensedate'] ?? now()),
            ]);

            $this->report->increment('rental_expenses');
        }
    }

    private function importPayroll(bool $dryRun): void
    {
        foreach ($this->data['payroll'] ?? [] as $row) {
            $legacyId = (int) $row['payrollid'];
            $employeeId = $this->employeeIds[(int) $row['empid']] ?? null;
            $buildingId = $this->rentalBuildingIds[(int) $row['house_id']] ?? null;
            $period = $this->monthMapper->fromMonthYear(
                (string) ($row['month_id'] ?? ''),
                $row['year_id'] ?? null,
            );

            if ($employeeId === null || $buildingId === null || $period === null) {
                $this->report->skip('payroll_entries');
                $this->report->warn("Skipped payroll {$legacyId}: unresolved employee, building, or period.");

                continue;
            }

            if ($dryRun) {
                $this->report->increment('payroll_entries');
                continue;
            }

            PayrollEntry::query()->create([
                'legacy_id' => $legacyId,
                'employee_id' => $employeeId,
                'rental_building_id' => $buildingId,
                'billing_month' => $period['month'],
                'billing_year' => $period['year'],
                'salary_amount' => $this->money($row['salary'] ?? 0),
                'paid_at' => (string) ($row['date'] ?? now()),
                'created_by' => $this->resolveUserId($row['username'] ?? null),
            ]);

            $this->report->increment('payroll_entries');
        }
    }

    private function importShareholders(bool $dryRun): void
    {
        foreach ($this->data['shareholders'] ?? [] as $row) {
            $legacyId = (int) $row['id'];

            if ($dryRun) {
                $this->shareholderIds[$legacyId] = $legacyId;
                $this->report->increment('shareholders');
                continue;
            }

            $shareholder = Shareholder::query()->create([
                'legacy_id' => $legacyId,
                'name' => (string) $row['name'],
                'phone' => $this->nullableString($row['phone'] ?? null),
                'address' => $this->nullableString($row['address'] ?? null),
            ]);

            $this->shareholderIds[$legacyId] = $shareholder->id;
            $this->report->increment('shareholders');
        }
    }

    private function importShareholderBills(bool $dryRun): void
    {
        foreach ($this->data['shareholders_bill'] ?? [] as $row) {
            $legacyId = (int) $row['bill_id'];
            $shareholderId = $this->shareholderIds[(int) $row['shareholder_id']] ?? null;
            $buildingId = $this->rentalBuildingIds[(int) $row['house_id']] ?? null;

            if ($shareholderId === null || $buildingId === null) {
                $this->report->skip('shareholder_bills');
                $this->report->warn("Skipped shareholder bill {$legacyId}: unresolved references.");

                continue;
            }

            if ($dryRun) {
                $this->report->increment('shareholder_bills');
                continue;
            }

            ShareholderBill::query()->create([
                'legacy_id' => $legacyId,
                'shareholder_id' => $shareholderId,
                'rental_building_id' => $buildingId,
                'amount' => $this->money($row['amount'] ?? 0),
                'remark' => $this->nullableString($row['remark'] ?? null),
                'bill_date' => (string) ($row['bill_date'] ?? now()->toDateString()),
            ]);

            $this->report->increment('shareholder_bills');
        }
    }

    private function importSaleBuildings(bool $dryRun): void
    {
        foreach ($this->data['buildings'] ?? [] as $row) {
            $legacyId = (int) $row['id'];

            if ($dryRun) {
                $this->saleBuildingIds[$legacyId] = $legacyId;
                $this->report->increment('sale_buildings');
                continue;
            }

            $id = DB::table('sale_buildings')->insertGetId([
                'legacy_id' => $legacyId,
                'name' => (string) $row['name'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->saleBuildingIds[$legacyId] = $id;
            $this->report->increment('sale_buildings');
        }
    }

    private function importSaleUnits(bool $dryRun): void
    {
        foreach ($this->data['forsale_apt'] ?? [] as $row) {
            $legacyId = (int) $row['aid'];
            $buildingId = $this->saleBuildingIds[(int) $row['category_id']] ?? null;

            if ($buildingId === null) {
                $this->report->skip('sale_units');
                $this->report->warn("Skipped sale unit {$legacyId}: building not found.");

                continue;
            }

            $status = strtolower((string) $row['status']) === 'sold' ? 'sold' : 'available';

            if ($dryRun) {
                $this->saleUnitIds[$legacyId] = $legacyId;
                $this->report->increment('sale_units');
                continue;
            }

            $id = DB::table('sale_units')->insertGetId([
                'legacy_id' => $legacyId,
                'sale_building_id' => $buildingId,
                'house_number' => (string) $row['house_no'],
                'floor' => (string) $row['floor'],
                'description' => (string) $row['description'],
                'list_price' => $this->money($row['price'] ?? 0),
                'status' => $status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->saleUnitIds[$legacyId] = $id;
            $this->report->increment('sale_units');
        }
    }

    private function importClients(bool $dryRun): void
    {
        foreach ($this->data['clients'] ?? [] as $row) {
            $legacyId = (int) $row['ClientID'];
            $buildingId = $this->saleBuildingIds[(int) $row['HouseID']] ?? null;
            $unitId = $this->saleUnitIds[(int) $row['apartmentNo']] ?? null;

            if ($buildingId === null || $unitId === null) {
                $this->report->skip('clients');
                $this->report->warn("Skipped client {$legacyId}: unresolved building or unit.");

                continue;
            }

            $status = strtolower((string) $row['status']) === 'disabled' ? 'disabled' : 'active';

            if ($dryRun) {
                $this->clientIds[$legacyId] = $legacyId;
                $this->report->increment('clients');
                continue;
            }

            $id = DB::table('clients')->insertGetId([
                'legacy_id' => $legacyId,
                'sale_building_id' => $buildingId,
                'sale_unit_id' => $unitId,
                'name' => (string) $row['ClientName'],
                'phone' => (string) $row['phone'],
                'gender' => $this->nullableString($row['Gender'] ?? null),
                'email' => $this->nullableString($row['Email'] ?? null),
                'passport_or_id' => $this->nullableString($row['Passport'] ?? null),
                'agreed_sale_price' => $this->money($row['PassImage'] ?? 0),
                'voucher_number' => null,
                'deposit' => $this->money($row['Deposit'] ?? 0),
                'next_of_kin_name' => $this->nullableString($row['nextname'] ?? null),
                'next_of_kin_address' => $this->nullableString($row['anddress'] ?? null),
                'next_of_kin_id' => $this->nullableString($row['npassid'] ?? null),
                'next_of_kin_phone' => $this->nullableString($row['nphone'] ?? null),
                'registration_date' => $this->nullableDate($row['starteddate'] ?? null),
                'status' => $status,
                'created_by' => $this->resolveUserId($row['createby'] ?? null),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->clientIds[$legacyId] = $id;
            $this->report->increment('clients');
        }
    }

    private function importSalesPayments(bool $dryRun): void
    {
        $this->importSalesPaymentRows($this->data['cpayments'] ?? [], 'active', $dryRun);
        $this->importSalesPaymentRows($this->data['cpayments_del'] ?? [], 'cancelled', $dryRun);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function importSalesPaymentRows(array $rows, string $status, bool $dryRun): void
    {
        foreach ($rows as $row) {
            $legacyId = (int) $row['id'];
            $clientId = $this->clientIds[(int) $row['tenant_id']] ?? null;
            $buildingId = $this->saleBuildingIds[(int) $row['houseid']] ?? null;

            if ($clientId === null || $buildingId === null) {
                $this->report->skip('sales_payments');
                $this->report->warn("Skipped sales payment {$legacyId}: unresolved references.");

                continue;
            }

            if ($dryRun) {
                $this->report->increment('sales_payments');
                continue;
            }

            DB::table('sales_payments')->insert([
                'legacy_id' => $legacyId,
                'client_id' => $clientId,
                'sale_building_id' => $buildingId,
                'amount' => $this->money($row['amount'] ?? 0),
                'discount' => $this->money($row['discount'] ?? 0),
                'invoice_reference' => $this->nullableString($row['invoice'] ?? null),
                'bank' => $this->nullableString($row['bank'] ?? null),
                'remark' => $this->nullableString($row['remark'] ?? null),
                'paid_at' => (string) $row['date_created'],
                'status' => $status,
                'cancelled_at' => $status === 'cancelled' ? (string) $row['date_created'] : null,
                'cancelled_by' => $status === 'cancelled' ? $this->resolveUserId($row['username'] ?? null) : null,
                'created_by' => $this->resolveUserId($row['username'] ?? null),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->report->increment('sales_payments');
        }
    }

    private function importSalesExpenses(bool $dryRun): void
    {
        foreach ($this->data['cexpenses'] ?? [] as $row) {
            $legacyId = (int) $row['expid'];
            $buildingId = $this->saleBuildingIds[(int) $row['house_id']] ?? null;

            if ($buildingId === null) {
                $this->report->skip('sales_expenses');
                $this->report->warn("Skipped sales expense {$legacyId}: building not found.");

                continue;
            }

            if ($dryRun) {
                $this->report->increment('sales_expenses');
                continue;
            }

            DB::table('sales_expenses')->insert([
                'legacy_id' => $legacyId,
                'sale_building_id' => $buildingId,
                'name' => (string) $row['expensename'],
                'amount' => $this->money($row['amountpaid'] ?? 0),
                'description' => $this->nullableString($row['description'] ?? null),
                'expense_date' => (string) ($row['expensedate'] ?? now()),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->report->increment('sales_expenses');
        }
    }

    private function resolveUserId(mixed $reference): ?int
    {
        if ($reference === null || $reference === '') {
            return $this->fallbackUserId;
        }

        if (is_int($reference) || (is_string($reference) && ctype_digit($reference))) {
            $legacyId = (int) $reference;

            if (isset($this->userIds[$legacyId])) {
                return $this->userIds[$legacyId];
            }
        }

        $key = strtolower(trim((string) $reference));

        return $this->userIdsByUsername[$key]
            ?? $this->userIdsByName[$key]
            ?? $this->fallbackUserId;
    }

    private function money(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        if ($string === '' || $string === '0' || strcasecmp($string, 'N/A') === 0) {
            return null;
        }

        return $string;
    }

    private function nullableDate(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === '0000-00-00') {
            return null;
        }

        return (string) $value;
    }
}
