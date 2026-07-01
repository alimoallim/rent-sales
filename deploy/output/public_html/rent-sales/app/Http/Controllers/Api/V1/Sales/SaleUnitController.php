<?php

namespace App\Http\Controllers\Api\V1\Sales;

use App\Enums\SaleUnitStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreSaleUnitRequest;
use App\Http\Requests\Sales\UpdateSaleUnitRequest;
use App\Http\Resources\SaleUnitResource;
use App\Models\SaleUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SaleUnitController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SaleUnit::class);

        $units = SaleUnit::query()
            ->with('building')
            ->when($request->integer('building_id'), fn ($query, $buildingId) => $query->where('sale_building_id', $buildingId))
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status))
            ->orderBy('house_number')
            ->paginate(50);

        return SaleUnitResource::collection($units);
    }

    public function store(StoreSaleUnitRequest $request): SaleUnitResource
    {
        $this->authorize('create', SaleUnit::class);

        $unit = SaleUnit::query()->create([
            ...$request->validated(),
            'status' => SaleUnitStatus::Available,
        ]);

        $unit->load('building');

        return new SaleUnitResource($unit);
    }

    public function show(SaleUnit $unit): SaleUnitResource
    {
        $this->authorize('view', $unit);

        $unit->load('building');

        return new SaleUnitResource($unit);
    }

    public function update(UpdateSaleUnitRequest $request, SaleUnit $unit): SaleUnitResource
    {
        $this->authorize('update', $unit);

        if ($unit->status === SaleUnitStatus::Sold) {
            abort(422, 'Cannot edit a sold unit. Disable the client first.');
        }

        $unit->update($request->validated());
        $unit->load('building');

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
