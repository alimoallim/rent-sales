<?php

namespace App\Services\Sales;

use App\Enums\ClientStatus;
use App\Enums\SalesPaymentStatus;
use App\Models\Client;
use App\Models\SalesExpense;
use App\Models\SalesPayment;
use App\Models\SaleBuilding;
use App\Support\MoneyConfig;
use Illuminate\Support\Carbon;

class SalesReportService
{
    public function __construct(private readonly ClientBalanceCalculator $balanceCalculator) {}

    /**
     * @return array{generated_at: string, filters: array<string, mixed>, rows: list<array<string, mixed>>, totals: array<string, string>}
     */
    public function balanceReport(?int $buildingId = null, bool $outstandingOnly = false): array
    {
        $clients = Client::query()
            ->with(['building', 'unit'])
            ->where('status', ClientStatus::Active)
            ->when($buildingId, fn ($q) => $q->where('sale_building_id', $buildingId))
            ->orderBy('name')
            ->get();

        $rows = [];
        $totalBalance = '0.00';
        $totalPaid = '0.00';
        $totalSalePrice = '0.00';

        foreach ($clients as $client) {
            $summary = $this->balanceCalculator->summary($client);
            $balance = $summary['balance'];

            if ($outstandingOnly && bccomp($balance, '0', 2) <= 0) {
                continue;
            }

            $paymentCount = $client->payments()->where('status', SalesPaymentStatus::Active)->count();

            $rows[] = [
                'client_id' => $client->id,
                'client_name' => $client->name,
                'building_id' => $client->sale_building_id,
                'building_name' => $client->building?->name,
                'unit_label' => $client->unit?->house_number,
                'agreed_sale_price' => $summary['agreed_sale_price'],
                'currency_code' => $summary['currency_code'],
                'deposit' => $summary['deposit'],
                'payment_count' => $paymentCount,
                'paid_total' => $summary['paid_total'],
                'balance' => $balance,
            ];

            $totalBalance = bcadd($totalBalance, $balance, 2);
            $totalPaid = bcadd($totalPaid, $summary['paid_total'], 2);
            $totalSalePrice = bcadd($totalSalePrice, $summary['agreed_sale_price'], 2);
        }

        return [
            'generated_at' => now()->toISOString(),
            'currency_code' => MoneyConfig::salesCurrency(),
            'filters' => [
                'building_id' => $buildingId,
                'outstanding_only' => $outstandingOnly,
            ],
            'rows' => $rows,
            'totals' => [
                'agreed_sale_price' => $totalSalePrice,
                'paid_total' => $totalPaid,
                'balance' => $totalBalance,
            ],
        ];
    }

    /**
     * @return array{generated_at: string, filters: array<string, mixed>, income_total: string, expense_total: string, net_balance: string, payments: list<array<string, mixed>>, expenses: list<array<string, mixed>>}
     */
    public function incomeStatement(?int $buildingId = null, ?string $from = null, ?string $to = null): array
    {
        $salesCurrency = MoneyConfig::salesCurrency();
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : null;
        $toDate = $to ? Carbon::parse($to)->endOfDay() : null;

        $payments = SalesPayment::query()
            ->with(['client', 'building'])
            ->where('status', SalesPaymentStatus::Active)
            ->when($buildingId, fn ($q) => $q->where('sale_building_id', $buildingId))
            ->when($fromDate, fn ($q) => $q->where('paid_at', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->where('paid_at', '<=', $toDate))
            ->orderByDesc('paid_at')
            ->get();

        $expenses = SalesExpense::query()
            ->with('building')
            ->when($buildingId, fn ($q) => $q->where('sale_building_id', $buildingId))
            ->when($fromDate, fn ($q) => $q->where('expense_date', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->where('expense_date', '<=', $toDate))
            ->orderByDesc('expense_date')
            ->get();

        $incomeTotal = '0.00';
        $paymentRows = [];

        foreach ($payments as $payment) {
            $net = bcadd((string) $payment->amount, (string) $payment->discount, 2);
            $incomeTotal = bcadd($incomeTotal, $net, 2);
            $paymentRows[] = [
                'id' => $payment->id,
                'client_id' => $payment->client_id,
                'sale_building_id' => $payment->sale_building_id,
                'client_name' => $payment->client?->name,
                'building_name' => $payment->building?->name,
                'amount' => $payment->amount,
                'currency_code' => $payment->currency_code ?? $salesCurrency,
                'discount' => $payment->discount,
                'paid_at' => $payment->paid_at?->toISOString(),
            ];
        }

        $expenseTotal = '0.00';
        $expenseRows = [];

        foreach ($expenses as $expense) {
            $expenseTotal = bcadd($expenseTotal, (string) $expense->amount, 2);
            $expenseRows[] = [
                'id' => $expense->id,
                'name' => $expense->name,
                'building_name' => $expense->building?->name,
                'amount' => $expense->amount,
                'currency_code' => $expense->currency_code ?? $salesCurrency,
                'expense_date' => $expense->expense_date?->toISOString(),
            ];
        }

        return [
            'generated_at' => now()->toISOString(),
            'currency_code' => $salesCurrency,
            'filters' => [
                'building_id' => $buildingId,
                'from' => $from,
                'to' => $to,
            ],
            'income_total' => $incomeTotal,
            'expense_total' => $expenseTotal,
            'net_balance' => bcsub($incomeTotal, $expenseTotal, 2),
            'payments' => $paymentRows,
            'expenses' => $expenseRows,
        ];
    }

    /**
     * @return array{generated_at: string, currency_code: string, filters: array<string, mixed>, rows: list<array<string, mixed>>, totals: array<string, string|int>}
     */
    public function cancelledClientsReport(?int $buildingId = null, ?string $from = null, ?string $to = null): array
    {
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : null;
        $toDate = $to ? Carbon::parse($to)->endOfDay() : null;

        $clients = Client::query()
            ->with(['building', 'unit'])
            ->where('status', ClientStatus::Disabled)
            ->when($buildingId, fn ($q) => $q->where('sale_building_id', $buildingId))
            ->when($fromDate, fn ($q) => $q->where('updated_at', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->where('updated_at', '<=', $toDate))
            ->orderByDesc('updated_at')
            ->get();

        $rows = [];
        $totalSalePrice = '0.00';
        $totalHistoricalPaid = '0.00';

        foreach ($clients as $client) {
            $historicalPaid = $this->historicalPaidTotal($client);
            $cancelledPaymentCount = $client->payments()
                ->where('status', SalesPaymentStatus::Cancelled)
                ->count();

            $rows[] = [
                'client_id' => $client->id,
                'client_name' => $client->name,
                'building_id' => $client->sale_building_id,
                'building_name' => $client->building?->name,
                'unit_label' => $client->unit?->house_number,
                'agreed_sale_price' => (string) $client->agreed_sale_price,
                'deposit' => (string) $client->deposit,
                'historical_paid_total' => $historicalPaid,
                'cancelled_payment_count' => $cancelledPaymentCount,
                'registration_date' => $client->registration_date?->toDateString(),
                'disabled_at' => $client->updated_at?->toISOString(),
            ];

            $totalSalePrice = bcadd($totalSalePrice, (string) $client->agreed_sale_price, 2);
            $totalHistoricalPaid = bcadd($totalHistoricalPaid, $historicalPaid, 2);
        }

        return [
            'generated_at' => now()->toISOString(),
            'currency_code' => MoneyConfig::salesCurrency(),
            'filters' => [
                'building_id' => $buildingId,
                'from' => $from,
                'to' => $to,
            ],
            'rows' => $rows,
            'totals' => [
                'clients' => count($rows),
                'agreed_sale_price' => $totalSalePrice,
                'historical_paid_total' => $totalHistoricalPaid,
            ],
        ];
    }

    /**
     * @return array{generated_at: string, currency_code: string, filters: array<string, mixed>, rows: list<array<string, mixed>>, totals: array<string, string|int>}
     */
    public function cancelledPaymentsReport(?int $buildingId = null, ?string $from = null, ?string $to = null): array
    {
        $salesCurrency = MoneyConfig::salesCurrency();
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : null;
        $toDate = $to ? Carbon::parse($to)->endOfDay() : null;

        $payments = SalesPayment::query()
            ->with(['client.unit', 'building', 'cancelledBy'])
            ->where('status', SalesPaymentStatus::Cancelled)
            ->when($buildingId, fn ($q) => $q->where('sale_building_id', $buildingId))
            ->when($fromDate, fn ($q) => $q->where('cancelled_at', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->where('cancelled_at', '<=', $toDate))
            ->orderByDesc('cancelled_at')
            ->get();

        $rows = [];
        $totalAmount = '0.00';
        $totalDiscount = '0.00';

        foreach ($payments as $payment) {
            $rows[] = [
                'id' => $payment->id,
                'client_id' => $payment->client_id,
                'client_name' => $payment->client?->name,
                'building_id' => $payment->sale_building_id,
                'building_name' => $payment->building?->name,
                'unit_label' => $payment->client?->unit?->house_number,
                'amount' => (string) $payment->amount,
                'currency_code' => $payment->currency_code ?? $salesCurrency,
                'discount' => (string) $payment->discount,
                'invoice_reference' => $payment->invoice_reference,
                'bank' => $payment->bank,
                'remark' => $payment->remark,
                'paid_at' => $payment->paid_at?->toISOString(),
                'cancelled_at' => $payment->cancelled_at?->toISOString(),
                'cancelled_by_name' => $payment->cancelledBy?->name,
            ];

            $totalAmount = bcadd($totalAmount, (string) $payment->amount, 2);
            $totalDiscount = bcadd($totalDiscount, (string) $payment->discount, 2);
        }

        return [
            'generated_at' => now()->toISOString(),
            'currency_code' => $salesCurrency,
            'filters' => [
                'building_id' => $buildingId,
                'from' => $from,
                'to' => $to,
            ],
            'rows' => $rows,
            'totals' => [
                'payments' => count($rows),
                'amount' => $totalAmount,
                'discount' => $totalDiscount,
                'credited_total' => bcadd($totalAmount, $totalDiscount, 2),
            ],
        ];
    }

    /**
     * @return array{generated_at: string, currency_code: string, filters: array<string, mixed>, rows: list<array<string, mixed>>, totals: array<string, string|int>}
     */
    public function paymentHistory(
        ?int $buildingId = null,
        ?int $clientId = null,
        ?string $from = null,
        ?string $to = null,
        bool $includeCancelled = false,
    ): array {
        $salesCurrency = MoneyConfig::salesCurrency();

        $payments = SalesPayment::query()
            ->with(['client.unit', 'building'])
            ->when($buildingId, fn ($q) => $q->where('sale_building_id', $buildingId))
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->when($from, fn ($q) => $q->whereDate('paid_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('paid_at', '<=', $to))
            ->when(! $includeCancelled, fn ($q) => $q->where('status', SalesPaymentStatus::Active))
            ->orderBy('paid_at')
            ->get();

        $rows = [];
        $totalAmount = '0.00';
        $totalDiscount = '0.00';
        $activeCount = 0;

        foreach ($payments as $payment) {
            $rows[] = [
                'payment_id' => $payment->id,
                'paid_at' => $payment->paid_at?->toDateString(),
                'client_id' => $payment->client_id,
                'client_name' => $payment->client?->name,
                'building_id' => $payment->sale_building_id,
                'building_name' => $payment->building?->name,
                'unit_label' => $payment->client?->unit?->house_number,
                'invoice_reference' => $payment->invoice_reference,
                'bank' => $payment->bank,
                'amount' => (string) $payment->amount,
                'currency_code' => $payment->currency_code ?? $salesCurrency,
                'discount' => (string) $payment->discount,
                'status' => $payment->status->value,
            ];

            if ($payment->status === SalesPaymentStatus::Active) {
                $totalAmount = bcadd($totalAmount, (string) $payment->amount, 2);
                $totalDiscount = bcadd($totalDiscount, (string) $payment->discount, 2);
                $activeCount++;
            }
        }

        return [
            'generated_at' => now()->toISOString(),
            'currency_code' => $salesCurrency,
            'filters' => [
                'building_id' => $buildingId,
                'client_id' => $clientId,
                'from' => $from,
                'to' => $to,
                'include_cancelled' => $includeCancelled,
            ],
            'rows' => $rows,
            'totals' => [
                'payments' => count($rows),
                'active_payments' => $activeCount,
                'amount' => $totalAmount,
                'discount' => $totalDiscount,
                'credited_total' => bcadd($totalAmount, $totalDiscount, 2),
            ],
        ];
    }

    private function historicalPaidTotal(Client $client): string
    {
        $payments = (string) $client->payments()->sum('amount');
        $discounts = (string) $client->payments()->sum('discount');

        return bcadd(bcadd($payments, (string) $client->deposit, 2), $discounts, 2);
    }
}
