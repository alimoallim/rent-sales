<?php

namespace App\Http\Controllers\Api\V1\Rental;

use App\Enums\WaterBillStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rental\StoreTenantWaterBillRequest;
use App\Http\Requests\Rental\UpdateTenantWaterBillRequest;
use App\Http\Resources\TenantWaterBillResource;
use App\Models\TenantWaterBill;
use App\Services\Rental\WaterBillService;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TenantWaterBillController extends Controller
{
    public function __construct(private readonly WaterBillService $waterBillService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', TenantWaterBill::class);

        $query = TenantWaterBill::query()
            ->with(['tenant', 'building', 'rentCharge'])
            ->when($request->integer('building_id'), fn ($q, $id) => $q->where('rental_building_id', $id))
            ->when($request->integer('tenant_id'), fn ($q, $id) => $q->where('tenant_id', $id))
            ->when($request->integer('billing_month'), fn ($q, $m) => $q->where('billing_month', $m))
            ->when($request->integer('billing_year'), fn ($q, $y) => $q->where('billing_year', $y));

        ListQuery::applySearch($query, $request, ['remark'], ['tenant' => 'name']);

        $bills = $query
            ->orderByDesc('billing_year')
            ->orderByDesc('billing_month')
            ->paginate(ListQuery::perPage($request, 50));

        return TenantWaterBillResource::collection($bills);
    }

    public function store(StoreTenantWaterBillRequest $request): JsonResponse
    {
        $this->authorize('create', TenantWaterBill::class);

        $bill = $this->waterBillService->create([
            ...$request->validated(),
            'fixed_fee' => $request->input('fixed_fee', 0),
        ], $request->user()->id);

        $bill->load(['tenant', 'building', 'rentCharge']);

        return (new TenantWaterBillResource($bill))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateTenantWaterBillRequest $request, TenantWaterBill $waterBill): TenantWaterBillResource
    {
        $this->authorize('update', $waterBill);

        if ($waterBill->status === WaterBillStatus::Paid) {
            abort(422, 'Paid water bills cannot be edited.');
        }

        $bill = $this->waterBillService->update($waterBill, [
            ...$request->validated(),
            'tenant_id' => $waterBill->tenant_id,
            'billing_month' => $waterBill->billing_month,
            'billing_year' => $waterBill->billing_year,
            'fixed_fee' => $request->input('fixed_fee', 0),
        ]);

        $bill->load(['tenant', 'building', 'rentCharge']);

        return new TenantWaterBillResource($bill);
    }
}
