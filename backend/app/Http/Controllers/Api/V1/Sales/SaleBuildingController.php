<?php

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreSaleBuildingRequest;
use App\Http\Requests\Sales\UpdateSaleBuildingRequest;
use App\Http\Resources\SaleBuildingResource;
use App\Models\SaleBuilding;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SaleBuildingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SaleBuilding::class);

        $query = SaleBuilding::query()->withCount('units');

        ListQuery::applySearch($query, $request, ['name']);

        $buildings = $query
            ->orderBy('name')
            ->paginate(ListQuery::perPage($request));

        return SaleBuildingResource::collection($buildings);
    }

    public function store(StoreSaleBuildingRequest $request): SaleBuildingResource
    {
        $this->authorize('create', SaleBuilding::class);

        $building = SaleBuilding::query()->create($request->validated());

        return new SaleBuildingResource($building);
    }

    public function show(SaleBuilding $building): SaleBuildingResource
    {
        $this->authorize('view', $building);

        $building->loadCount(['units', 'clients']);

        return new SaleBuildingResource($building);
    }

    public function update(UpdateSaleBuildingRequest $request, SaleBuilding $building): SaleBuildingResource
    {
        $this->authorize('update', $building);

        $building->update($request->validated());

        return new SaleBuildingResource($building);
    }

    public function destroy(SaleBuilding $building): JsonResponse
    {
        $this->authorize('delete', $building);

        if ($building->units()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a building that still has units.',
            ], 422);
        }

        $building->delete();

        return response()->json(['message' => 'Building deleted.']);
    }
}
