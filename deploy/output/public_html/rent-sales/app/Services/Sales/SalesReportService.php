<?php

namespace App\Services\Sales;

use App\Enums\ClientStatus;
use App\Enums\SalesPaymentStatus;
use App\Enums\SaleUnitStatus;
use App\Models\Client;
use App\Models\SalesExpense;
use App\Models\SalesPayment;
use App\Models\SaleBuilding;
use App\Models\SaleUnit;
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
                'client_name' => $payment->client?->name,
                'building_name' => $payment->building?->name,
                'amount' => $payment->amount,
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
                'expense_date' => $expense->expense_date?->toISOString(),
            ];
        }

        return [
            'generated_at' => now()->toISOString(),
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
}
