<?php

namespace App\Http\Controllers\Api\V1\Rental;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rental\StoreRentalBuildingRequest;
use App\Http\Requests\Rental\UpdateRentalBuildingRequest;
use App\Http\Resources\RentalBuildingResource;
use App\Models\RentalBuilding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RentalBuildingController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', RentalBuilding::class);

        $buildings = RentalBuilding::query()
            ->withCount('units')
            ->orderBy('name')
            ->paginate(25);

        return RentalBuildingResource::collection($buildings);
    }

    public function store(StoreRentalBuildingRequest $request): RentalBuildingResource
    {
        $this->authorize('create', RentalBuilding::class);

        $building = RentalBuilding::query()->create($request->validated());

        return new RentalBuildingResource($building);
    }

    public function show(RentalBuilding $building): RentalBuildingResource
    {
        $this->authorize('view', $building);

        $building->loadCount(['units', 'tenants']);

        return new RentalBuildingResource($building);
    }

    public function update(UpdateRentalBuildingRequest $request, RentalBuilding $building): RentalBuildingResource
    {
        $this->authorize('update', $building);

        $building->update($request->validated());

        return new RentalBuildingResource($building);
    }

    public function destroy(RentalBuilding $building): JsonResponse
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
