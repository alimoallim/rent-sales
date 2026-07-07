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
use App\Support\MoneyConfig;
use Illuminate\Support\Carbon;

class SalesDashboardService
{
    public function __construct(private readonly ClientBalanceCalculator $balanceCalculator) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $now = now();
        $currentMonthStart = $now->copy()->startOfMonth();
        $currentMonthEnd = $now->copy()->endOfMonth();
        $previousMonthStart = $now->copy()->subMonth()->startOfMonth();
        $previousMonthEnd = $now->copy()->subMonth()->endOfMonth();

        $totalUnits = SaleUnit::query()->count();
        $availableUnits = SaleUnit::query()->where('status', SaleUnitStatus::Available)->count();
        $soldUnits = SaleUnit::query()->where('status', SaleUnitStatus::Sold)->count();

        $portfolio = $this->aggregatePortfolio();
        $collections = $this->aggregateCollections($currentMonthStart, $currentMonthEnd, $previousMonthStart, $previousMonthEnd);

        return [
            'generated_at' => $now->toISOString(),
            'currency_code' => MoneyConfig::salesCurrency(),
            'period' => [
                'month' => (int) $now->month,
                'year' => (int) $now->year,
                'label' => $now->format('F Y'),
            ],
            'inventory' => [
                'buildings' => SaleBuilding::query()->count(),
                'total_units' => $totalUnits,
                'available_units' => $availableUnits,
                'sold_units' => $soldUnits,
                'sell_through_rate' => $this->sellThroughRate($soldUnits, $totalUnits),
                'available_list_value' => (string) SaleUnit::query()
                    ->where('status', SaleUnitStatus::Available)
                    ->sum('list_price'),
            ],
            'portfolio' => $portfolio,
            'collections' => $collections,
            'operations' => [
                'new_clients_this_month' => Client::query()
                    ->where('status', ClientStatus::Active)
                    ->whereBetween('registration_date', [$currentMonthStart->toDateString(), $currentMonthEnd->toDateString()])
                    ->count(),
                'new_clients_last_month' => Client::query()
                    ->where('status', ClientStatus::Active)
                    ->whereBetween('registration_date', [$previousMonthStart->toDateString(), $previousMonthEnd->toDateString()])
                    ->count(),
                'expenses_current_month' => (string) SalesExpense::query()
                    ->whereBetween('expense_date', [$currentMonthStart, $currentMonthEnd])
                    ->sum('amount'),
            ],
            'pipeline' => [
                'available_list_value' => (string) SaleUnit::query()
                    ->where('status', SaleUnitStatus::Available)
                    ->sum('list_price'),
                'agreed_sale_value' => $portfolio['agreed_sale_value'],
                'collected_total' => $portfolio['collected_total'],
            ],
            'top_outstanding' => $this->topOutstanding(),
            'recent_payments' => $this->recentPayments(),
            'recent_registrations' => $this->recentRegistrations(),
            'available_inventory' => $this->availableInventory(),
            'building_summary' => $this->buildingSummary(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function aggregatePortfolio(): array
    {
        $agreedSaleValue = '0.00';
        $collectedTotal = '0.00';
        $outstandingTotal = '0.00';
        $clientsWithBalance = 0;
        $clientsPaidUp = 0;
        $activeClients = 0;

        Client::query()
            ->where('status', ClientStatus::Active)
            ->each(function (Client $client) use (
                &$agreedSaleValue,
                &$collectedTotal,
                &$outstandingTotal,
                &$clientsWithBalance,
                &$clientsPaidUp,
                &$activeClients,
            ): void {
                $activeClients++;
                $summary = $this->balanceCalculator->summary($client);

                $agreedSaleValue = bcadd($agreedSaleValue, $summary['agreed_sale_price'], 2);
                $collectedTotal = bcadd($collectedTotal, $summary['paid_total'], 2);

                if (bccomp($summary['balance'], '0', 2) > 0) {
                    $clientsWithBalance++;
                    $outstandingTotal = bcadd($outstandingTotal, $summary['balance'], 2);
                } else {
                    $clientsPaidUp++;
                }
            });

        $collectionRate = 0.0;
        if (bccomp($agreedSaleValue, '0', 2) > 0) {
            $collectionRate = round((float) bcmul(bcdiv($collectedTotal, $agreedSaleValue, 4), '100', 2), 1);
        }

        return [
            'active_clients' => $activeClients,
            'disabled_clients' => Client::query()->where('status', ClientStatus::Disabled)->count(),
            'clients_with_balance' => $clientsWithBalance,
            'clients_paid_up' => $clientsPaidUp,
            'agreed_sale_value' => $agreedSaleValue,
            'collected_total' => $collectedTotal,
            'outstanding_total' => $outstandingTotal,
            'collection_rate' => $collectionRate,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function aggregateCollections(
        Carbon $currentStart,
        Carbon $currentEnd,
        Carbon $previousStart,
        Carbon $previousEnd,
    ): array {
        $currentAmount = (string) SalesPayment::query()
            ->where('status', SalesPaymentStatus::Active)
            ->whereBetween('paid_at', [$currentStart, $currentEnd])
            ->sum('amount');

        $currentDiscount = (string) SalesPayment::query()
            ->where('status', SalesPaymentStatus::Active)
            ->whereBetween('paid_at', [$currentStart, $currentEnd])
            ->sum('discount');

        $currentTotal = bcadd($currentAmount, $currentDiscount, 2);

        $previousAmount = (string) SalesPayment::query()
            ->where('status', SalesPaymentStatus::Active)
            ->whereBetween('paid_at', [$previousStart, $previousEnd])
            ->sum('amount');

        $previousDiscount = (string) SalesPayment::query()
            ->where('status', SalesPaymentStatus::Active)
            ->whereBetween('paid_at', [$previousStart, $previousEnd])
            ->sum('discount');

        $previousTotal = bcadd($previousAmount, $previousDiscount, 2);

        $currentCount = SalesPayment::query()
            ->where('status', SalesPaymentStatus::Active)
            ->whereBetween('paid_at', [$currentStart, $currentEnd])
            ->count();

        $changePercent = null;
        if (bccomp($previousTotal, '0', 2) > 0) {
            $delta = bcsub($currentTotal, $previousTotal, 2);
            $changePercent = round((float) bcmul(bcdiv($delta, $previousTotal, 4), '100', 2), 1);
        }

        return [
            'current_month' => $currentTotal,
            'previous_month' => $previousTotal,
            'change_percent' => $changePercent,
            'payment_count_current_month' => $currentCount,
            'current_month_label' => $currentStart->format('F Y'),
            'previous_month_label' => $previousStart->format('F Y'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function topOutstanding(int $limit = 8): array
    {
        $clients = [];

        Client::query()
            ->with(['building', 'unit'])
            ->where('status', ClientStatus::Active)
            ->each(function (Client $client) use (&$clients): void {
                $summary = $this->balanceCalculator->summary($client);

                if (bccomp($summary['balance'], '0', 2) <= 0) {
                    return;
                }

                $paidPercent = 0;
                if (bccomp($summary['agreed_sale_price'], '0', 2) > 0) {
                    $paidPercent = (int) round(
                        (float) bcmul(
                            bcdiv($summary['paid_total'], $summary['agreed_sale_price'], 4),
                            '100',
                            2,
                        ),
                    );
                }

                $clients[] = [
                    'client_id' => $client->id,
                    'client_name' => $client->name,
                    'building_name' => $client->building?->name,
                    'unit_label' => $client->unit?->house_number,
                    'agreed_sale_price' => $summary['agreed_sale_price'],
                    'paid_total' => $summary['paid_total'],
                    'balance' => $summary['balance'],
                    'paid_percent' => min(100, $paidPercent),
                ];
            });

        usort($clients, fn (array $a, array $b) => bccomp($b['balance'], $a['balance'], 2));

        return array_slice($clients, 0, $limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentPayments(int $limit = 8): array
    {
        return SalesPayment::query()
            ->with(['client', 'building'])
            ->where('status', SalesPaymentStatus::Active)
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (SalesPayment $payment) => [
                'payment_id' => $payment->id,
                'paid_at' => $payment->paid_at?->toDateString(),
                'client_id' => $payment->client_id,
                'client_name' => $payment->client?->name,
                'building_name' => $payment->building?->name,
                'amount' => $payment->amount,
                'discount' => $payment->discount,
                'invoice_reference' => $payment->invoice_reference,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentRegistrations(int $limit = 6): array
    {
        return Client::query()
            ->with(['building', 'unit'])
            ->where('status', ClientStatus::Active)
            ->orderByDesc('registration_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (Client $client) {
                $summary = $this->balanceCalculator->summary($client);

                return [
                    'client_id' => $client->id,
                    'client_name' => $client->name,
                    'building_name' => $client->building?->name,
                    'unit_label' => $client->unit?->house_number,
                    'registration_date' => $client->registration_date?->toDateString(),
                    'agreed_sale_price' => $summary['agreed_sale_price'],
                    'balance' => $summary['balance'],
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function availableInventory(int $limit = 6): array
    {
        return SaleUnit::query()
            ->with('building')
            ->where('status', SaleUnitStatus::Available)
            ->orderByDesc('list_price')
            ->limit($limit)
            ->get()
            ->map(fn (SaleUnit $unit) => [
                'unit_id' => $unit->id,
                'house_number' => $unit->house_number,
                'building_name' => $unit->building?->name,
                'floor' => $unit->floor,
                'description' => $unit->description,
                'list_price' => $unit->list_price,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildingSummary(): array
    {
        $buildings = SaleBuilding::query()->orderBy('name')->get();
        $summary = [];

        foreach ($buildings as $building) {
            $totalUnits = SaleUnit::query()->where('sale_building_id', $building->id)->count();
            $availableUnits = SaleUnit::query()
                ->where('sale_building_id', $building->id)
                ->where('status', SaleUnitStatus::Available)
                ->count();
            $soldUnits = SaleUnit::query()
                ->where('sale_building_id', $building->id)
                ->where('status', SaleUnitStatus::Sold)
                ->count();
            $availableValue = (string) SaleUnit::query()
                ->where('sale_building_id', $building->id)
                ->where('status', SaleUnitStatus::Available)
                ->sum('list_price');

            $agreedValue = '0.00';
            $collected = '0.00';
            $outstanding = '0.00';
            $activeClients = 0;

            Client::query()
                ->where('sale_building_id', $building->id)
                ->where('status', ClientStatus::Active)
                ->each(function (Client $client) use (&$agreedValue, &$collected, &$outstanding, &$activeClients): void {
                    $activeClients++;
                    $clientSummary = $this->balanceCalculator->summary($client);
                    $agreedValue = bcadd($agreedValue, $clientSummary['agreed_sale_price'], 2);
                    $collected = bcadd($collected, $clientSummary['paid_total'], 2);

                    if (bccomp($clientSummary['balance'], '0', 2) > 0) {
                        $outstanding = bcadd($outstanding, $clientSummary['balance'], 2);
                    }
                });

            $summary[] = [
                'building_id' => $building->id,
                'building_name' => $building->name,
                'active_clients' => $activeClients,
                'total_units' => $totalUnits,
                'available_units' => $availableUnits,
                'sold_units' => $soldUnits,
                'sell_through_rate' => $this->sellThroughRate($soldUnits, $totalUnits),
                'available_list_value' => $availableValue,
                'agreed_sale_value' => $agreedValue,
                'collected_total' => $collected,
                'outstanding_balance' => $outstanding,
            ];
        }

        usort($summary, fn (array $a, array $b) => bccomp($b['outstanding_balance'], $a['outstanding_balance'], 2));

        return $summary;
    }

    private function sellThroughRate(int $sold, int $total): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return round(($sold / $total) * 100, 1);
    }
}
