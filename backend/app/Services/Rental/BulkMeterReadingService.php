<?php

namespace App\Services\Rental;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\TenantElectricityBill;
use App\Models\TenantWaterBill;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BulkMeterReadingService
{
    public function __construct(
        private readonly WaterBillService $waterBillService,
        private readonly ElectricityBillService $electricityBillService,
    ) {}

    /**
     * @return array{
     *     utility: string,
     *     rental_building_id: int,
     *     billing_month: int,
     *     billing_year: int,
     *     rows: list<array<string, mixed>>
     * }
     */
    public function grid(string $utility, int $buildingId, int $month, int $year): array
    {
        $this->assertUtility($utility);

        $meteringColumn = $utility === 'water' ? 'requires_water_metering' : 'requires_electricity_metering';
        $billModel = $this->billModel($utility);

        $tenants = Tenant::query()
            ->with('unit')
            ->where('rental_building_id', $buildingId)
            ->where('status', TenantStatus::Active)
            ->where($meteringColumn, true)
            ->orderBy('name')
            ->get();

        $tenantIds = $tenants->pluck('id');

        if ($tenantIds->isEmpty()) {
            return $this->gridPayload($utility, $buildingId, $month, $year, []);
        }

        /** @var Collection<int, TenantWaterBill|TenantElectricityBill> $latestBills */
        $latestBills = $billModel::query()
            ->whereIn('tenant_id', $tenantIds)
            ->orderByDesc('billing_year')
            ->orderByDesc('billing_month')
            ->get()
            ->unique('tenant_id')
            ->keyBy('tenant_id');

        /** @var Collection<int, TenantWaterBill|TenantElectricityBill> $periodBills */
        $periodBills = $billModel::query()
            ->where('rental_building_id', $buildingId)
            ->where('billing_month', $month)
            ->where('billing_year', $year)
            ->whereIn('tenant_id', $tenantIds)
            ->get()
            ->keyBy('tenant_id');

        $averageConsumptions = $this->averageConsumptions($billModel, $tenantIds);

        $rows = $tenants->map(function (Tenant $tenant) use ($latestBills, $periodBills, $averageConsumptions): array {
            $latest = $latestBills->get($tenant->id);
            $periodBill = $periodBills->get($tenant->id);
            $previousReading = $latest ? (int) $latest->current_reading : 0;

            return [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'unit_label' => $tenant->unit?->house_number,
                'unit_floor' => $tenant->unit?->floor,
                'previous_reading' => $previousReading,
                'default_rate' => $latest ? (string) $latest->rate : '50.00',
                'default_fixed_fee' => $latest ? (string) $latest->fixed_fee : '0.00',
                'average_consumption' => $averageConsumptions->get($tenant->id),
                'already_recorded' => $periodBill !== null,
                'existing_bill_id' => $periodBill?->id,
                'existing_current_reading' => $periodBill ? (int) $periodBill->current_reading : null,
                'existing_consumption' => $periodBill ? (int) $periodBill->consumption : null,
            ];
        })->values()->all();

        return $this->gridPayload($utility, $buildingId, $month, $year, $rows);
    }

    /**
     * @param  list<array{tenant_id: int, current_reading?: int|null}>  $readings
     * @return array<string, mixed>
     */
    public function store(string $utility, int $buildingId, int $month, int $year, array $readings, int $userId): array
    {
        $this->assertUtility($utility);

        $grid = $this->grid($utility, $buildingId, $month, $year);
        $rowMap = collect($grid['rows'])->keyBy('tenant_id');

        $results = [];
        $savedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        foreach ($readings as $entry) {
            $tenantId = (int) ($entry['tenant_id'] ?? 0);
            $currentReading = $entry['current_reading'] ?? null;

            if ($currentReading === null || $currentReading === '') {
                $skippedCount++;
                $results[] = [
                    'tenant_id' => $tenantId,
                    'status' => 'skipped',
                    'message' => 'No reading entered.',
                ];

                continue;
            }

            $currentReading = (int) $currentReading;
            $row = $rowMap->get($tenantId);

            if ($row === null) {
                $errorCount++;
                $results[] = [
                    'tenant_id' => $tenantId,
                    'status' => 'error',
                    'message' => 'Tenant is not eligible for this utility in the selected building.',
                ];

                continue;
            }

            if ($row['already_recorded']) {
                $errorCount++;
                $results[] = [
                    'tenant_id' => $tenantId,
                    'status' => 'error',
                    'message' => 'A reading for this period already exists.',
                ];

                continue;
            }

            $previousReading = (int) $row['previous_reading'];

            if ($currentReading < $previousReading) {
                $errorCount++;
                $results[] = [
                    'tenant_id' => $tenantId,
                    'status' => 'error',
                    'message' => "Current reading must be at least {$previousReading}.",
                ];

                continue;
            }

            $payload = [
                'tenant_id' => $tenantId,
                'rental_building_id' => $buildingId,
                'billing_month' => $month,
                'billing_year' => $year,
                'previous_reading' => $previousReading,
                'current_reading' => $currentReading,
                'rate' => $row['default_rate'],
                'fixed_fee' => $row['default_fixed_fee'],
            ];

            try {
                $bill = $utility === 'water'
                    ? $this->waterBillService->create($payload, $userId)
                    : $this->electricityBillService->create($payload, $userId);

                $savedCount++;
                $results[] = [
                    'tenant_id' => $tenantId,
                    'status' => 'saved',
                    'bill_id' => $bill->id,
                    'consumption' => (int) $bill->consumption,
                ];
            } catch (ValidationException $exception) {
                $errorCount++;
                $results[] = [
                    'tenant_id' => $tenantId,
                    'status' => 'error',
                    'message' => collect($exception->errors())->flatten()->first() ?? 'Could not save reading.',
                ];
            }
        }

        return [
            'saved_count' => $savedCount,
            'skipped_count' => $skippedCount,
            'error_count' => $errorCount,
            'results' => $results,
        ];
    }

    private function assertUtility(string $utility): void
    {
        if (! in_array($utility, ['water', 'electricity'], true)) {
            throw ValidationException::withMessages([
                'utility' => ['Utility must be water or electricity.'],
            ]);
        }
    }

    /**
     * @param  class-string<TenantWaterBill|TenantElectricityBill>  $billModel
     * @param  Collection<int, int>  $tenantIds
     * @return Collection<int, float|null>
     */
    private function averageConsumptions(string $billModel, Collection $tenantIds): Collection
    {
        $bills = $billModel::query()
            ->whereIn('tenant_id', $tenantIds)
            ->orderByDesc('billing_year')
            ->orderByDesc('billing_month')
            ->get(['tenant_id', 'consumption']);

        return $bills
            ->groupBy('tenant_id')
            ->map(function (Collection $group): ?float {
                $values = $group->take(3)->pluck('consumption')->map(fn ($value) => (int) $value);

                if ($values->isEmpty()) {
                    return null;
                }

                return round($values->avg(), 2);
            });
    }

    /**
     * @return class-string<TenantWaterBill|TenantElectricityBill>
     */
    private function billModel(string $utility): string
    {
        return $utility === 'water' ? TenantWaterBill::class : TenantElectricityBill::class;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{
     *     utility: string,
     *     rental_building_id: int,
     *     billing_month: int,
     *     billing_year: int,
     *     rows: list<array<string, mixed>>
     * }
     */
    private function gridPayload(string $utility, int $buildingId, int $month, int $year, array $rows): array
    {
        return [
            'utility' => $utility,
            'rental_building_id' => $buildingId,
            'billing_month' => $month,
            'billing_year' => $year,
            'rows' => $rows,
        ];
    }
}
