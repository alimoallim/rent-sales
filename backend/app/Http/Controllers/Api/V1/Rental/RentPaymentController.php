<?php

namespace App\Http\Controllers\Api\V1\Rental;

use App\Enums\RentPaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rental\StoreRentPaymentRequest;
use App\Http\Requests\Rental\UpdateRentPaymentRequest;
use App\Http\Resources\RentPaymentResource;
use App\Models\RentPayment;
use App\Services\Rental\RentPaymentService;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RentPaymentController extends Controller
{
    public function __construct(
        private readonly RentPaymentService $rentPaymentService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', RentPayment::class);

        $query = RentPayment::query()
            ->with(['tenant', 'building'])
            ->when($request->integer('building_id'), fn ($q, $id) => $q->where('rental_building_id', $id))
            ->when($request->integer('tenant_id'), fn ($q, $id) => $q->where('tenant_id', $id))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status));

        ListQuery::applySearch($query, $request, ['invoice_reference'], ['tenant' => 'name']);

        $payments = $query
            ->orderByDesc('paid_at')
            ->paginate(ListQuery::perPage($request, 50));

        return RentPaymentResource::collection($payments);
    }

    public function store(StoreRentPaymentRequest $request): RentPaymentResource
    {
        $this->authorize('create', RentPayment::class);

        $payment = $this->rentPaymentService->store($request->validated(), $request->user());

        return new RentPaymentResource($payment);
    }

    public function update(UpdateRentPaymentRequest $request, RentPayment $rentPayment): RentPaymentResource
    {
        $this->authorize('update', $rentPayment);

        if ($rentPayment->status !== RentPaymentStatus::Active) {
            abort(422, 'Voided payments cannot be edited.');
        }

        $rentPayment->fill([
            ...$request->validated(),
            'discount' => $request->input('discount', 0),
        ]);
        $rentPayment->forceFill(['updated_by' => $request->user()->id])->save();

        $rentPayment->load(['tenant', 'building']);

        return new RentPaymentResource($rentPayment);
    }

    public function void(Request $request, RentPayment $rentPayment): RentPaymentResource
    {
        $this->authorize('void', $rentPayment);

        if ($rentPayment->status === RentPaymentStatus::Voided) {
            abort(422, 'Payment is already voided.');
        }

        $rentPayment->forceFill([
            'status' => RentPaymentStatus::Voided,
            'voided_at' => now(),
            'voided_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ])->save();

        $rentPayment->load(['tenant', 'building']);

        return new RentPaymentResource($rentPayment);
    }
}
