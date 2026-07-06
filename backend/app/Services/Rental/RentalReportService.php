<?php

namespace App\Services\Rental;

use App\Enums\RentPaymentStatus;
use App\Enums\TenantStatus;
use App\Models\BuildingElectricityBill;
use App\Models\BuildingWaterUtilityBill;
use App\Models\PayrollEntry;
use App\Models\RentCharge;
use App\Models\RentPayment;
use App\Models\RentalExpense;
use App\Models\ShareholderBill;
use App\Models\Tenant;
use App\Models\TenantWaterBill;
use App\Support\MoneyConfig;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RentalReportService
{
    public function __construct(
        private readonly TenantBalanceCalculator $balanceCalculator,
        private readonly ArrearsAgingService $arrearsAging,
    ) {}

    /**
     * @return array{generated_at: string, filters: array<string, mixed>, rows: list<array<string, mixed>>, totals: array<string, string>}
     */
    public function tenantBalances(?int $buildingId = null, bool $outstandingOnly = false): array
    {
        $tenants = Tenant::query()
            ->with(['building', 'unit'])
            ->where('status', TenantStatus::Active)
            ->when($buildingId, fn ($q) => $q->where('rental_building_id', $buildingId))
            ->orderBy('name')
            ->get();

        $rows = [];
        $totalBalance = '0.00';
        $totalCharged = '0.00';
        $totalPaid = '0.00';

        foreach ($tenants as $tenant) {
            $balance = $this->balanceCalculator->calculate($tenant);

            if ($outstandingOnly && bccomp($balance, '0', 2) <= 0) {
                continue;
            }

            $charged = (string) RentCharge::query()->where('tenant_id', $tenant->id)->sum('total_amount');
            $paid = (string) RentPayment::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', RentPaymentStatus::Active)
                ->sum('amount');
            $discount = (string) RentPayment::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', RentPaymentStatus::Active)
                ->sum('discount');
            $paidTotal = bcadd($paid, $discount, 2);
            $chargeCount = RentCharge::query()->where('tenant_id', $tenant->id)->count();

            $rows[] = [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'building_id' => $tenant->rental_building_id,
                'building_name' => $tenant->building?->name,
                'unit_label' => $tenant->unit?->house_number,
                'monthly_rent' => $tenant->unit?->monthly_rent ?? '0.00',
                'service_amount' => $tenant->service_amount,
                'charge_count' => $chargeCount,
                'charged_amount' => $charged,
                'paid_amount' => $paidTotal,
                'balance' => $balance,
            ];

            $totalBalance = bcadd($totalBalance, $balance, 2);
            $totalCharged = bcadd($totalCharged, $charged, 2);
            $totalPaid = bcadd($totalPaid, $paidTotal, 2);
        }

        return [
            'generated_at' => now()->toISOString(),
            'currency_code' => MoneyConfig::rentalCurrency(),
            'filters' => [
                'building_id' => $buildingId,
                'outstanding_only' => $outstandingOnly,
            ],
            'rows' => $rows,
            'totals' => [
                'charged_amount' => $totalCharged,
                'paid_amount' => $totalPaid,
                'balance' => $totalBalance,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function arrearsAging(?int $buildingId = null, bool $outstandingOnly = true, ?Carbon $asOf = null): array
    {
        return $this->arrearsAging->report($buildingId, $outstandingOnly, $asOf);
    }

    /**
     * @return array{generated_at: string, filters: array<string, mixed>, rows: list<array<string, mixed>>, totals: array<string, string>}
     */
    public function paymentHistory(
        ?int $buildingId = null,
        ?int $tenantId = null,
        ?string $from = null,
        ?string $to = null,
        bool $includeVoided = false,
    ): array {
        $payments = RentPayment::query()
            ->with(['tenant', 'building'])
            ->when($buildingId, fn ($q) => $q->where('rental_building_id', $buildingId))
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($from, fn ($q) => $q->whereDate('paid_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('paid_at', '<=', $to))
            ->when(! $includeVoided, fn ($q) => $q->where('status', RentPaymentStatus::Active))
            ->orderBy('paid_at')
            ->get();

        $rows = $this->mapPaymentRows($payments);

        return [
            'generated_at' => now()->toISOString(),
            'currency_code' => MoneyConfig::rentalCurrency(),
            'filters' => compact('buildingId', 'tenantId', 'from', 'to', 'includeVoided'),
            'rows' => $rows,
            'totals' => $this->summarizePayments($payments),
        ];
    }

    /**
     * @return array{generated_at: string, filters: array<string, mixed>, rows: list<array<string, mixed>>, totals: array<string, string>}
     */
    public function chargeSummary(
        ?int $buildingId = null,
        ?int $tenantId = null,
        ?int $billingMonth = null,
        ?int $billingYear = null,
    ): array {
        $charges = RentCharge::query()
            ->with(['tenant', 'building', 'unit'])
            ->when($buildingId, fn ($q) => $q->where('rental_building_id', $buildingId))
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($billingMonth, fn ($q) => $q->where('billing_month', $billingMonth))
            ->when($billingYear, fn ($q) => $q->where('billing_year', $billingYear))
            ->orderByDesc('billing_year')
            ->orderByDesc('billing_month')
            ->orderBy('tenant_id')
            ->get();

        $rows = [];
        $totalRent = '0.00';
        $totalService = '0.00';
        $totalAmount = '0.00';

        foreach ($charges as $charge) {
            $rows[] = [
                'charge_id' => $charge->id,
                'billing_month' => $charge->billing_month,
                'billing_year' => $charge->billing_year,
                'tenant_id' => $charge->tenant_id,
                'tenant_name' => $charge->tenant?->name,
                'building_name' => $charge->building?->name,
                'unit_label' => $charge->unit?->house_number,
                'rent_amount' => $charge->rent_amount,
                'service_amount' => $charge->service_amount,
                'total_amount' => $charge->total_amount,
                'purpose' => $charge->purpose,
            ];

            $totalRent = bcadd($totalRent, (string) $charge->rent_amount, 2);
            $totalService = bcadd($totalService, (string) $charge->service_amount, 2);
            $totalAmount = bcadd($totalAmount, (string) $charge->total_amount, 2);
        }

        return [
            'generated_at' => now()->toISOString(),
            'currency_code' => MoneyConfig::rentalCurrency(),
            'filters' => compact('buildingId', 'tenantId', 'billingMonth', 'billingYear'),
            'rows' => $rows,
            'totals' => [
                'rent_amount' => $totalRent,
                'service_amount' => $totalService,
                'total_amount' => $totalAmount,
            ],
        ];
    }

    /**
     * @param  'unified'|'legacy'  $mode
     * @return array<string, mixed>
     */
    public function incomeStatement(
        int $buildingId,
        int $billingMonth,
        int $billingYear,
        string $mode = 'unified',
    ): array {
        $start = Carbon::create($billingYear, $billingMonth, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $rentCollections = $this->sumRentCollections($buildingId, $start, $end);
        $shareholderDeductions = $this->sumShareholderDeductions($buildingId, $start, $end);
        $serviceIncome = $mode === 'legacy'
            ? $this->legacyServiceIncome($buildingId, $start, $end)
            : $this->unifiedServiceIncome($buildingId, $billingMonth, $billingYear);
        $waterIncome = $mode === 'legacy'
            ? $this->legacyWaterIncome($buildingId, $billingMonth, $billingYear)
            : '0.00';

        $rentNet = bcsub(bcsub($rentCollections, $serviceIncome, 2), $shareholderDeductions, 2);
        $serviceWaterSubtotal = bcadd($serviceIncome, $waterIncome, 2);

        $expenses = $this->sumExpenses($buildingId, $start, $end);
        $payroll = $mode === 'legacy'
            ? $this->legacyPayroll($buildingId, $start, $end)
            : $this->unifiedPayroll($buildingId, $billingMonth, $billingYear);
        $electricity = $this->sumBuildingElectricity($buildingId, $billingMonth, $billingYear);
        $nairobiWater = $this->sumNairobiWater($buildingId, $billingMonth, $billingYear);

        $expenseSubtotal = bcadd(bcadd(bcadd($expenses, $payroll, 2), $electricity, 2), $nairobiWater, 2);
        $serviceWaterNet = bcsub($serviceWaterSubtotal, $expenseSubtotal, 2);
        $netBalanceInHand = bcadd($rentNet, $serviceWaterNet, 2);

        return [
            'generated_at' => now()->toISOString(),
            'currency_code' => MoneyConfig::rentalCurrency(),
            'building_id' => $buildingId,
            'billing_month' => $billingMonth,
            'billing_year' => $billingYear,
            'period_label' => $start->format('F Y'),
            'calculation_mode' => $mode,
            'lines' => [
                'rent_collections' => $rentCollections,
                'service_income' => $serviceIncome,
                'shareholder_deductions' => $shareholderDeductions,
                'rent_net' => $rentNet,
                'water_income' => $waterIncome,
                'service_water_subtotal' => $serviceWaterSubtotal,
                'expenses' => $expenses,
                'payroll' => $payroll,
                'electricity' => $electricity,
                'nairobi_water' => $nairobiWater,
                'expense_subtotal' => $expenseSubtotal,
                'service_water_net' => $serviceWaterNet,
                'net_balance_in_hand' => $netBalanceInHand,
            ],
        ];
    }

    private function sumRentCollections(int $buildingId, Carbon $start, Carbon $end): string
    {
        return (string) RentPayment::query()
            ->where('rental_building_id', $buildingId)
            ->where('status', RentPaymentStatus::Active)
            ->whereBetween('paid_at', [$start, $end])
            ->sum('amount');
    }

    private function sumShareholderDeductions(int $buildingId, Carbon $start, Carbon $end): string
    {
        return (string) ShareholderBill::query()
            ->where('rental_building_id', $buildingId)
            ->whereBetween('bill_date', [$start->toDateString(), $end->toDateString()])
            ->sum('amount');
    }

    /** Service income from monthly rent charges (greenfield unified model). */
    private function unifiedServiceIncome(int $buildingId, int $billingMonth, int $billingYear): string
    {
        return (string) RentCharge::query()
            ->where('rental_building_id', $buildingId)
            ->where('billing_month', $billingMonth)
            ->where('billing_year', $billingYear)
            ->sum('service_amount');
    }

    /**
     * Legacy find_income.php: add tenant.service_amount once per payment row in the month.
     */
    private function legacyServiceIncome(int $buildingId, Carbon $start, Carbon $end): string
    {
        $total = '0.00';

        RentPayment::query()
            ->with('tenant')
            ->where('rental_building_id', $buildingId)
            ->where('status', RentPaymentStatus::Active)
            ->whereBetween('paid_at', [$start, $end])
            ->orderBy('id')
            ->get()
            ->each(function (RentPayment $payment) use (&$total): void {
                $total = bcadd($total, (string) ($payment->tenant?->service_amount ?? 0), 2);
            });

        return $total;
    }

    /** Legacy find_income.php: sum tenant water bills for the billing period. */
    private function legacyWaterIncome(int $buildingId, int $billingMonth, int $billingYear): string
    {
        return (string) TenantWaterBill::query()
            ->where('rental_building_id', $buildingId)
            ->where('billing_month', $billingMonth)
            ->where('billing_year', $billingYear)
            ->sum('amount');
    }

    private function sumExpenses(int $buildingId, Carbon $start, Carbon $end): string
    {
        return (string) RentalExpense::query()
            ->where('rental_building_id', $buildingId)
            ->whereBetween('expense_date', [$start, $end])
            ->sum('amount');
    }

    /** Payroll matched by billing period fields. */
    private function unifiedPayroll(int $buildingId, int $billingMonth, int $billingYear): string
    {
        return (string) PayrollEntry::query()
            ->where('rental_building_id', $buildingId)
            ->where('billing_month', $billingMonth)
            ->where('billing_year', $billingYear)
            ->sum('salary_amount');
    }

    /** Legacy find_income.php: payroll rows where paid date falls in the calendar month. */
    private function legacyPayroll(int $buildingId, Carbon $start, Carbon $end): string
    {
        return (string) PayrollEntry::query()
            ->where('rental_building_id', $buildingId)
            ->whereBetween('paid_at', [$start, $end])
            ->sum('salary_amount');
    }

    private function sumBuildingElectricity(int $buildingId, int $billingMonth, int $billingYear): string
    {
        return (string) BuildingElectricityBill::query()
            ->where('rental_building_id', $buildingId)
            ->where('billing_month', $billingMonth)
            ->where('billing_year', $billingYear)
            ->sum('amount');
    }

    private function sumNairobiWater(int $buildingId, int $billingMonth, int $billingYear): string
    {
        return (string) BuildingWaterUtilityBill::query()
            ->where('rental_building_id', $buildingId)
            ->where('billing_month', $billingMonth)
            ->where('billing_year', $billingYear)
            ->sum('amount');
    }

    /**
     * @param  Collection<int, RentPayment>  $payments
     * @return list<array<string, mixed>>
     */
    private function mapPaymentRows(Collection $payments): array
    {
        return $payments->map(fn (RentPayment $payment) => [
            'payment_id' => $payment->id,
            'paid_at' => $payment->paid_at?->toDateString(),
            'tenant_id' => $payment->tenant_id,
            'tenant_name' => $payment->tenant?->name,
            'building_name' => $payment->building?->name,
            'invoice_reference' => $payment->invoice_reference,
            'amount' => $payment->amount,
            'discount' => $payment->discount,
            'status' => $payment->status->value,
        ])->all();
    }

    /**
     * @param  Collection<int, RentPayment>  $payments
     * @return array<string, string>
     */
    private function summarizePayments(Collection $payments): array
    {
        $active = $payments->where('status', RentPaymentStatus::Active);

        return [
            'amount' => bcadd('0', (string) $active->sum('amount'), 2),
            'discount' => bcadd('0', (string) $active->sum('discount'), 2),
            'net_collected' => bcadd(
                bcadd('0', (string) $active->sum('amount'), 2),
                bcadd('0', (string) $active->sum('discount'), 2),
                2,
            ),
        ];
    }
}
