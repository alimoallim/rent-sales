<?php

namespace App\Http\Controllers\Api\V1\Sales;

use App\Enums\ClientStatus;
use App\Enums\SaleUnitStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\SaleBuilding;
use App\Models\SaleUnit;
use App\Services\Sales\ClientBalanceCalculator;
use Illuminate\Http\JsonResponse;

class SalesDashboardController extends Controller
{
    public function __invoke(ClientBalanceCalculator $balanceCalculator): JsonResponse
    {
        $this->authorize('viewAny', SaleBuilding::class);

        $clientsWithBalance = 0;

        Client::query()
            ->where('status', ClientStatus::Active)
            ->each(function (Client $client) use ($balanceCalculator, &$clientsWithBalance): void {
                if (bccomp($balanceCalculator->calculate($client), '0', 2) > 0) {
                    $clientsWithBalance++;
                }
            });

        return response()->json([
            'buildings' => SaleBuilding::query()->count(),
            'active_clients' => Client::query()->where('status', ClientStatus::Active)->count(),
            'disabled_clients' => Client::query()->where('status', ClientStatus::Disabled)->count(),
            'available_units' => SaleUnit::query()->where('status', SaleUnitStatus::Available)->count(),
            'sold_units' => SaleUnit::query()->where('status', SaleUnitStatus::Sold)->count(),
            'clients_with_balance' => $clientsWithBalance,
        ]);
    }
}
