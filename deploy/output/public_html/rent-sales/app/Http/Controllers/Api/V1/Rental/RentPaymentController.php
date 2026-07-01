<?php

namespace App\Http\Controllers\Api\V1\Rental;

use App\Enums\RentPaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rental\StoreRentPaymentRequest;
use App\Http\Requests\Rental\UpdateRentPaymentRequest;
use App\Http\Resources\RentPaymentResource;
use App\Models\RentPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RentPaymentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', RentPayment::class);

        $payments = RentPayment::query()
            ->with(['tenant', 'building'])
            ->when($request->integer('building_id'), fn ($q, $id) => $q->where('rental_building_id', $id))
            ->when($request->integer('tenant_id'), fn ($q, $id) => $q->where('tenant_id', $id))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('paid_at')
            ->paginate(50);

        return RentPaymentResource::collection($payments);
    }

    public function store(StoreRentPaymentRequest $request): RentPaymentResource
    {
        $this->authorize('create', RentPayment::class);

        $payment = RentPayment::query()->create([
            ...$request->validated(),
            'discount' => $request->input('discount', 0),
            'status' => RentPaymentStatus::Active,
            'created_by' => $request->user()->id,
        ]);

        $payment->load(['tenant', 'building']);

        return new RentPaymentResource($payment);
    }

    public function update(UpdateRentPaymentRequest $request, RentPayment $rentPayment): RentPaymentResource
    {
        $this->authorize('update', $rentPayment);

        if ($rentPayment->status !== RentPaymentStatus::Active) {
            abort(422, 'Voided payments cannot be edited.');
        }

        $rentPayment->update([
            ...$request->validated(),
            'discount' => $request->input('discount', 0),
            'updated_by' => $request->user()->id,
        ]);

        $rentPayment->load(['tenant', 'building']);

        return new RentPaymentResource($rentPayment);
    }

    public function void(Request $request, RentPayment $rentPayment): RentPaymentResource
    {
        $this->authorize('update', $rentPayment);

        if ($rentPayment->status === RentPaymentStatus::Voided) {
            abort(422, 'Payment is already voided.');
        }

        $rentPayment->update([
            'status' => RentPaymentStatus::Voided,
            'voided_at' => now(),
            'voided_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $rentPayment->load(['tenant', 'building']);

        return new RentPaymentResource($rentPayment);
    }
}
