<?php

namespace App\Http\Controllers\Api\V1\Rental;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rental\UpdateRentChargeRequest;
use App\Http\Resources\RentChargeResource;
use App\Models\RentCharge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RentChargeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', RentCharge::class);

        $perPage = min(max($request->integer('per_page', 50), 1), 100);

        $charges = RentCharge::query()
            ->with(['tenant', 'building', 'unit'])
            ->when($request->integer('building_id'), fn ($q, $id) => $q->where('rental_building_id', $id))
            ->when($request->integer('tenant_id'), fn ($q, $id) => $q->where('tenant_id', $id))
            ->when($request->integer('billing_month'), fn ($q, $m) => $q->where('billing_month', $m))
            ->when($request->integer('billing_year'), fn ($q, $y) => $q->where('billing_year', $y))
            ->orderByDesc('billing_year')
            ->orderByDesc('billing_month')
            ->orderByDesc('charged_at')
            ->paginate($perPage);

        return RentChargeResource::collection($charges);
    }

    public function update(UpdateRentChargeRequest $request, RentCharge $rentCharge): RentChargeResource
    {
        $this->authorize('update', $rentCharge);

        if ($rentCharge->charge_batch_item_id !== null) {
            abort(422, 'Charges posted from a locked batch cannot be edited. Post a credit adjustment instead.');
        }

        if ($rentCharge->purpose !== 'Rent + service') {
            abort(422, 'Only monthly rent and service charges can be edited here. Edit water or electricity bills from their respective screens.');
        }

        $rentCharge->fill($request->validated());
        $rentCharge->recalculateTotal();
        $rentCharge->save();
        $rentCharge->load(['tenant', 'building', 'unit']);

        return new RentChargeResource($rentCharge);
    }
}
