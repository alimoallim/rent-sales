<?php

namespace App\Http\Controllers\Api\V1\Rental;

use App\Enums\RentalUnitStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rental\StoreRentalUnitRequest;
use App\Http\Requests\Rental\UpdateRentalUnitRequest;
use App\Http\Resources\RentalUnitResource;
use App\Models\RentalUnit;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RentalUnitController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', RentalUnit::class);

        $query = RentalUnit::query()
            ->with(['building', 'activeTenant'])
            ->when($request->integer('building_id'), fn ($query, $buildingId) => $query->where('rental_building_id', $buildingId))
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status));

        ListQuery::applySearch($query, $request, ['house_number', 'floor', 'description'], ['building' => 'name']);

        $total = (clone $query)->count();
        $vacant = (clone $query)->where('status', RentalUnitStatus::Vacant)->count();
        $occupied = (clone $query)->where('status', RentalUnitStatus::Occupied)->count();

        $units = $query
            ->orderBy('house_number')
            ->paginate(ListQuery::perPage($request, 50));

        return RentalUnitResource::collection($units)->additional([
            'summary' => [
                'total' => $total,
                'vacant' => $vacant,
                'occupied' => $occupied,
                'occupancy_rate' => $total > 0 ? (int) round(($occupied / $total) * 100) : 0,
            ],
        ]);
    }

    public function store(StoreRentalUnitRequest $request): RentalUnitResource
    {
        $this->authorize('create', RentalUnit::class);

        $unit = RentalUnit::query()->create([
            ...$request->validated(),
            'status' => RentalUnitStatus::Vacant,
        ]);

        $unit->load(['building', 'activeTenant']);

        return new RentalUnitResource($unit);
    }

    public function show(RentalUnit $unit): RentalUnitResource
    {
        $this->authorize('view', $unit);

        $unit->load(['building', 'activeTenant']);

        return new RentalUnitResource($unit);
    }

    public function update(UpdateRentalUnitRequest $request, RentalUnit $unit): RentalUnitResource
    {
        $this->authorize('update', $unit);

        $unit->update($request->validated());
        $unit->load(['building', 'activeTenant']);

        return new RentalUnitResource($unit);
    }

    public function destroy(RentalUnit $unit): JsonResponse
    {
        $this->authorize('delete', $unit);

        if ($unit->status === RentalUnitStatus::Occupied) {
            return response()->json([
                'message' => 'Cannot delete an occupied unit.',
            ], 422);
        }

        $unit->delete();

        return response()->json(['message' => 'Unit deleted.']);
    }
}
