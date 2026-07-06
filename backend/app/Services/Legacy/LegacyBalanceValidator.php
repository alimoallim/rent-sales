<?php

namespace App\Services\Legacy;

class LegacyBalanceValidator
{
    public function __construct(
        private readonly LegacySqlParser $parser,
        private readonly LegacyMonthMapper $monthMapper = new LegacyMonthMapper,
    ) {}

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function parseDump(string $path): array
    {
        return $this->parser->parseFile($path);
    }

    /**
     * @return array{charged: string, paid: string, discount: string, balance: string}|null
     */
    public function legacyTenantTotals(array $data, int $legacyTenantId): ?array
    {
        if ($this->findTenantRow($data, $legacyTenantId) === null) {
            return null;
        }

        $charged = '0.00';
        foreach ($data['charge'] ?? [] as $row) {
            if ((int) ($row['clientid'] ?? 0) !== $legacyTenantId) {
                continue;
            }
            $charged = bcadd($charged, $this->money($row['total'] ?? 0), 2);
        }

        $waterBilled = '0.00';
        foreach ($this->uniqueWaterBillRows($data, $legacyTenantId) as $row) {
            $waterBilled = bcadd($waterBilled, $this->money($row['amount'] ?? 0), 2);
        }

        $paid = '0.00';
        $discount = '0.00';
        foreach ($data['payments'] ?? [] as $row) {
            if ((int) ($row['tenant_id'] ?? 0) !== $legacyTenantId) {
                continue;
            }
            $paid = bcadd($paid, $this->money($row['amount'] ?? 0), 2);
            $discount = bcadd($discount, $this->money($row['discount'] ?? 0), 2);
        }

        return [
            'charged' => $charged,
            'water_billed' => $waterBilled,
            'expected_imported_charged' => bcadd($charged, $waterBilled, 2),
            'paid' => $paid,
            'discount' => $discount,
            'balance' => bcsub($charged, bcadd($paid, $discount, 2), 2),
        ];
    }

    public function legacyTenantBalance(array $data, int $legacyTenantId): ?string
    {
        return $this->legacyTenantTotals($data, $legacyTenantId)['balance'] ?? null;
    }

    /**
     * @return array{agreed: string, paid: string, deposit: string, balance: string}|null
     */
    public function legacyClientTotals(array $data, int $legacyClientId): ?array
    {
        $client = $this->findClientRow($data, $legacyClientId);
        if ($client === null) {
            return null;
        }

        $agreed = $this->money($client['PassImage'] ?? 0);
        $deposit = $this->money($client['Deposit'] ?? 0);

        $paid = '0.00';
        foreach ($data['cpayments'] ?? [] as $row) {
            if ((int) ($row['tenant_id'] ?? 0) !== $legacyClientId) {
                continue;
            }
            $paid = bcadd($paid, $this->money($row['amount'] ?? 0), 2);
        }

        return [
            'agreed' => $agreed,
            'paid' => $paid,
            'deposit' => $deposit,
            'balance' => bcsub($agreed, bcadd($paid, $deposit, 2), 2),
        ];
    }

    public function legacyClientBalance(array $data, int $legacyClientId): ?string
    {
        return $this->legacyClientTotals($data, $legacyClientId)['balance'] ?? null;
    }

    /**
     * @return list<int>
     */
    public function sampleLegacyTenantIds(array $data, int $limit = 5): array
    {
        $candidates = [];

        foreach ($data['tenants'] ?? [] as $row) {
            $legacyId = (int) ($row['ClientID'] ?? 0);
            if ($legacyId <= 0) {
                continue;
            }

            $totals = $this->legacyTenantTotals($data, $legacyId);
            if ($totals === null) {
                continue;
            }

            $candidates[] = [
                'id' => $legacyId,
                'weight' => abs((float) $totals['balance']) + (float) $totals['charged'] + (float) $totals['paid'],
            ];
        }

        usort($candidates, fn (array $a, array $b): int => $b['weight'] <=> $a['weight']);

        if ($limit <= 0) {
            return array_map(static fn (array $row): int => $row['id'], $candidates);
        }

        return array_map(
            static fn (array $row): int => $row['id'],
            array_slice($candidates, 0, $limit),
        );
    }

    /**
     * @return list<int>
     */
    public function sampleLegacyClientIds(array $data, int $limit = 5): array
    {
        $candidates = [];

        foreach ($data['clients'] ?? [] as $row) {
            $legacyId = (int) ($row['ClientID'] ?? 0);
            if ($legacyId <= 0) {
                continue;
            }

            $totals = $this->legacyClientTotals($data, $legacyId);
            if ($totals === null) {
                continue;
            }

            $candidates[] = [
                'id' => $legacyId,
                'weight' => abs((float) $totals['balance']) + (float) $totals['paid'],
            ];
        }

        usort($candidates, fn (array $a, array $b): int => $b['weight'] <=> $a['weight']);

        if ($limit <= 0) {
            return array_map(static fn (array $row): int => $row['id'], $candidates);
        }

        return array_map(
            static fn (array $row): int => $row['id'],
            array_slice($candidates, 0, $limit),
        );
    }

    /**
     * @return array<string, int>
     */
    public function expectedImportCounts(array $data): array
    {
        return [
            'rental_buildings' => count($data['categories'] ?? []),
            'rental_units' => count($data['houses'] ?? []),
            'tenants' => count($data['tenants'] ?? []),
            'rent_charges' => count($data['charge'] ?? []),
            'rent_payments' => count($data['payments'] ?? []),
            'tenant_water_bills' => count($this->uniqueWaterBillRows($data)),
            'sale_buildings' => count($data['buildings'] ?? []),
            'sale_units' => count($data['forsale_apt'] ?? []),
            'clients' => count($data['clients'] ?? []),
            'sales_payments' => count($data['cpayments'] ?? []) + count($data['cpayments_del'] ?? []),
        ];
    }

    private function findTenantRow(array $data, int $legacyTenantId): ?array
    {
        foreach ($data['tenants'] ?? [] as $row) {
            if ((int) ($row['ClientID'] ?? 0) === $legacyTenantId) {
                return $row;
            }
        }

        return null;
    }

    private function findClientRow(array $data, int $legacyClientId): ?array
    {
        foreach ($data['clients'] ?? [] as $row) {
            if ((int) ($row['ClientID'] ?? 0) === $legacyClientId) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Legacy dumps may contain duplicate water bills for the same tenant and billing period.
     *
     * @return list<array<string, mixed>>
     */
    private function uniqueWaterBillRows(array $data, ?int $legacyTenantId = null): array
    {
        $rows = [];
        $seen = [];

        foreach ($data['water_bill'] ?? [] as $row) {
            if ($legacyTenantId !== null && (int) ($row['tenant_id'] ?? 0) !== $legacyTenantId) {
                continue;
            }

            $period = $this->monthMapper->fromMonthYear(
                (string) ($row['month_id'] ?? ''),
                $row['year_id'] ?? null,
            );

            if ($period === null) {
                continue;
            }

            $key = ($row['tenant_id'] ?? 0).':'.$period['month'].':'.$period['year'];
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $rows[] = $row;
        }

        return $rows;
    }

    private function money(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        return bcadd((string) $value, '0', 2);
    }
}
