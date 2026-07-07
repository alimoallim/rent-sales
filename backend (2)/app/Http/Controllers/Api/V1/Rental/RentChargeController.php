<?php

namespace App\Http\Controllers\Api\V1\Rental;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rental\UpdateRentChargeRequest;
use App\Http\Resources\RentChargeResource;
use App\Models\RentCharge;
use App\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RentChargeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', RentCharge::class);

        $query = RentCharge::query()
            ->with(['tenant', 'building', 'unit'])
            ->when($request->integer('building_id'), fn ($q, $id) => $q->where('rental_building_id', $id))
            ->when($request->integer('tenant_id'), fn ($q, $id) => $q->where('tenant_id', $id))
            ->when($request->integer('billing_month'), fn ($q, $m) => $q->where('billing_month', $m))
            ->when($request->integer('billing_year'), fn ($q, $y) => $q->where('billing_year', $y));

        ListQuery::applySearch($query, $request, ['purpose'], [
            'tenant' => 'name',
            'unit' => 'house_number',
        ]);

        $charges = $query
            ->orderByDesc('billing_year')
            ->orderByDesc('billing_month')
            ->orderByDesc('charged_at')
            ->paginate(ListQuery::perPage($request, 50));

        return RentChargeResource::collection($charges);
    }

    public function update(UpdateRentChargeRequest $request, RentCharge $rentCharge): RentChargeResource
    {
        $this->authorize('update', $rentCharge);

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
