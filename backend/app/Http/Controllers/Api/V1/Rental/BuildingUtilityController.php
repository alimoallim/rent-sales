<?php

namespace App\Http\Controllers\Api\V1\Rental;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rental\StoreBuildingUtilityBillRequest;
use App\Http\Resources\BuildingElectricityBillResource;
use App\Http\Resources\BuildingWaterUtilityBillResource;
use App\Models\BuildingElectricityBill;
use App\Models\BuildingWaterUtilityBill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class BuildingUtilityController extends Controller
{
    public function nairobiWaterIndex(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', BuildingWaterUtilityBill::class);

        $bills = BuildingWaterUtilityBill::query()
            ->with('building')
            ->when($request->integer('building_id'), fn ($q, $id) => $q->where('rental_building_id', $id))
            ->orderByDesc('billing_year')
            ->orderByDesc('billing_month')
            ->paginate(50);

        return BuildingWaterUtilityBillResource::collection($bills);
    }

    public function nairobiWaterStore(StoreBuildingUtilityBillRequest $request): BuildingWaterUtilityBillResource
    {
        $this->authorize('create', BuildingWaterUtilityBill::class);

        $exists = BuildingWaterUtilityBill::query()
            ->where('rental_building_id', $request->integer('rental_building_id'))
            ->where('billing_month', $request->integer('billing_month'))
            ->where('billing_year', $request->integer('billing_year'))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'billing_month' => ['A Nairobi Water bill already exists for this building and period.'],
            ]);
        }

        $bill = BuildingWaterUtilityBill::query()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        $bill->load('building');

        return new BuildingWaterUtilityBillResource($bill);
    }

    public function electricityIndex(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', BuildingElectricityBill::class);

        $bills = BuildingElectricityBill::query()
            ->with('building')
            ->when($request->integer('building_id'), fn ($q, $id) => $q->where('rental_building_id', $id))
            ->orderByDesc('billing_year')
            ->orderByDesc('billing_month')
            ->paginate(50);

        return BuildingElectricityBillResource::collection($bills);
    }

    public function electricityStore(StoreBuildingUtilityBillRequest $request): BuildingElectricityBillResource
    {
        $this->authorize('create', BuildingElectricityBill::class);

        $bill = BuildingElectricityBill::query()->create($request->validated());
        $bill->load('building');

        return new BuildingElectricityBillResource($bill);
    }
}
