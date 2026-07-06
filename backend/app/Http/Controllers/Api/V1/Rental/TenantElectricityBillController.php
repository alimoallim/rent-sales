<?php

namespace App\Http\Controllers\Api\V1\Rental;

use App\Enums\ElectricityBillStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rental\StoreTenantElectricityBillRequest;
use App\Http\Requests\Rental\UpdateTenantElectricityBillRequest;
use App\Http\Resources\TenantElectricityBillResource;
use App\Models\TenantElectricityBill;
use App\Services\Rental\ElectricityBillService;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TenantElectricityBillController extends Controller
{
    public function __construct(private readonly ElectricityBillService $electricityBillService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', TenantElectricityBill::class);

        $query = TenantElectricityBill::query()
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

        return TenantElectricityBillResource::collection($bills);
    }

    public function store(StoreTenantElectricityBillRequest $request): JsonResponse
    {
        $this->authorize('create', TenantElectricityBill::class);

        $bill = $this->electricityBillService->create([
            ...$request->validated(),
            'fixed_fee' => $request->input('fixed_fee', 0),
        ], $request->user()->id);

        $bill->load(['tenant', 'building', 'rentCharge']);

        return (new TenantElectricityBillResource($bill))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateTenantElectricityBillRequest $request, TenantElectricityBill $electricityBill): TenantElectricityBillResource
    {
        $this->authorize('update', $electricityBill);

        if ($electricityBill->status === ElectricityBillStatus::Paid) {
            abort(422, 'Paid electricity bills cannot be edited.');
        }

        $bill = $this->electricityBillService->update($electricityBill, [
            ...$request->validated(),
            'tenant_id' => $electricityBill->tenant_id,
            'billing_month' => $electricityBill->billing_month,
            'billing_year' => $electricityBill->billing_year,
            'fixed_fee' => $request->input('fixed_fee', 0),
        ]);

        $bill->load(['tenant', 'building', 'rentCharge']);

        return new TenantElectricityBillResource($bill);
    }
}
