<?php

namespace App\Http\Controllers\Api\V1\Sales;

use App\Enums\SalesPaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreSalesPaymentRequest;
use App\Http\Requests\Sales\UpdateSalesPaymentRequest;
use App\Http\Resources\SalesPaymentResource;
use App\Models\SalesPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SalesPaymentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SalesPayment::class);

        $perPage = min(max($request->integer('per_page', 50), 1), 100);

        $payments = SalesPayment::query()
            ->with(['client.unit', 'building'])
            ->when($request->integer('building_id'), fn ($q, $id) => $q->where('sale_building_id', $id))
            ->when($request->integer('client_id'), fn ($q, $id) => $q->where('client_id', $id))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('from'), fn ($q, $from) => $q->whereDate('paid_at', '>=', $from))
            ->when($request->input('to'), fn ($q, $to) => $q->whereDate('paid_at', '<=', $to))
            ->orderByDesc('paid_at')
            ->paginate($perPage);

        return SalesPaymentResource::collection($payments);
    }

    public function store(StoreSalesPaymentRequest $request): SalesPaymentResource
    {
        $this->authorize('create', SalesPayment::class);

        $payment = SalesPayment::query()->create([
            ...$request->validated(),
            'discount' => $request->input('discount', 0),
            'status' => SalesPaymentStatus::Active,
            'created_by' => $request->user()->id,
        ]);

        $payment->load(['client.unit', 'building']);

        return new SalesPaymentResource($payment);
    }

    public function update(UpdateSalesPaymentRequest $request, SalesPayment $payment): SalesPaymentResource
    {
        $this->authorize('update', $payment);

        if ($payment->status !== SalesPaymentStatus::Active) {
            abort(422, 'Cancelled payments cannot be edited.');
        }

        $payment->update([
            ...$request->validated(),
            'discount' => $request->input('discount', 0),
            'updated_by' => $request->user()->id,
        ]);

        $payment->load(['client.unit', 'building']);

        return new SalesPaymentResource($payment);
    }

    public function cancel(Request $request, SalesPayment $payment): SalesPaymentResource
    {
        $this->authorize('update', $payment);

        if ($payment->status === SalesPaymentStatus::Cancelled) {
            abort(422, 'Payment is already cancelled.');
        }

        $payment->update([
            'status' => SalesPaymentStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $payment->load(['client.unit', 'building']);

        return new SalesPaymentResource($payment);
    }
}
