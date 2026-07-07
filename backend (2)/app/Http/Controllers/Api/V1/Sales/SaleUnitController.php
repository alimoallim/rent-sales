<?php

namespace App\Http\Controllers\Api\V1\Sales;

use App\Enums\ClientStatus;
use App\Enums\SaleUnitStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreSaleUnitRequest;
use App\Http\Requests\Sales\UpdateSaleUnitRequest;
use App\Http\Resources\SaleUnitResource;
use App\Models\Client;
use App\Models\SaleUnit;
use App\Services\Sales\ClientBalanceCalculator;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SaleUnitController extends Controller
{
    public function __construct(private readonly ClientBalanceCalculator $balanceCalculator) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SaleUnit::class);

        $query = SaleUnit::query()
            ->with(['building', 'saleClient'])
            ->when($request->integer('building_id'), fn ($query, $buildingId) => $query->where('sale_building_id', $buildingId))
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status));

        ListQuery::applySearch($query, $request, ['house_number', 'floor', 'description'], ['building' => 'name']);

        $total = (clone $query)->count();
        $available = (clone $query)->where('status', SaleUnitStatus::Available)->count();
        $sold = (clone $query)->where('status', SaleUnitStatus::Sold)->count();
        $availableListValue = (string) (clone $query)
            ->where('status', SaleUnitStatus::Available)
            ->sum('list_price');

        $soldUnitIds = (clone $query)->where('status', SaleUnitStatus::Sold)->pluck('id');
        $outstandingBalance = '0.00';
        $collectedOnSold = '0.00';
        $agreedOnSold = '0.00';

        Client::query()
            ->whereIn('sale_unit_id', $soldUnitIds)
            ->where('status', ClientStatus::Active)
            ->each(function (Client $client) use (&$outstandingBalance, &$collectedOnSold, &$agreedOnSold): void {
                $summary = $this->balanceCalculator->summary($client);
                $agreedOnSold = bcadd($agreedOnSold, $summary['agreed_sale_price'], 2);
                $collectedOnSold = bcadd($collectedOnSold, $summary['paid_total'], 2);

                if (bccomp($summary['balance'], '0', 2) > 0) {
                    $outstandingBalance = bcadd($outstandingBalance, $summary['balance'], 2);
                }
            });

        $units = $query
            ->orderBy('house_number')
            ->paginate(ListQuery::perPage($request, 50));

        return SaleUnitResource::collection($units)->additional([
            'summary' => [
                'total' => $total,
                'available' => $available,
                'sold' => $sold,
                'sell_through_rate' => $total > 0 ? (int) round(($sold / $total) * 100) : 0,
                'available_list_value' => $availableListValue,
                'sold_agreed_value' => $agreedOnSold,
                'collected_on_sold' => $collectedOnSold,
                'outstanding_on_sold' => $outstandingBalance,
            ],
        ]);
    }

    public function store(StoreSaleUnitRequest $request): SaleUnitResource
    {
        $this->authorize('create', SaleUnit::class);

        $unit = SaleUnit::query()->create([
            ...$request->validated(),
            'status' => SaleUnitStatus::Available,
        ]);

        $unit->load(['building', 'saleClient']);

        return new SaleUnitResource($unit);
    }

    public function show(SaleUnit $unit): SaleUnitResource
    {
        $this->authorize('view', $unit);

        $unit->load(['building', 'saleClient']);

        return new SaleUnitResource($unit);
    }

    public function update(UpdateSaleUnitRequest $request, SaleUnit $unit): SaleUnitResource
    {
        $this->authorize('update', $unit);

        if ($unit->status === SaleUnitStatus::Sold) {
            abort(422, 'Cannot edit a sold unit. Disable the client first.');
        }

        $unit->update($request->validated());
        $unit->load(['building', 'saleClient']);

        return new SaleUnitResource($unit);
    }

    public function destroy(SaleUnit $unit): JsonResponse
    {
        $this->authorize('delete', $unit);

        if ($unit->status === SaleUnitStatus::Sold) {
            return response()->json([
                'message' => 'Cannot delete a sold unit.',
            ], 422);
        }

        $unit->delete();

        return response()->json(['message' => 'Unit deleted.']);
    }
}
