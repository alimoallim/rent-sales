<?php

namespace App\Http\Controllers\Api\V1\Rental;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rental\StoreTenantElectricityBillRequest;
use App\Http\Resources\TenantElectricityBillResource;
use App\Models\TenantElectricityBill;
use App\Services\Rental\ElectricityBillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TenantElectricityBillController extends Controller
{
    public function __construct(private readonly ElectricityBillService $electricityBillService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', TenantElectricityBill::class);

        $bills = TenantElectricityBill::query()
            ->with(['tenant', 'building', 'rentCharge'])
            ->when($request->integer('building_id'), fn ($q, $id) => $q->where('rental_building_id', $id))
            ->when($request->integer('tenant_id'), fn ($q, $id) => $q->where('tenant_id', $id))
            ->when($request->integer('billing_month'), fn ($q, $m) => $q->where('billing_month', $m))
            ->when($request->integer('billing_year'), fn ($q, $y) => $q->where('billing_year', $y))
            ->orderByDesc('billing_year')
            ->orderByDesc('billing_month')
            ->paginate(50);

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

    public function markPaid(Request $request, TenantElectricityBill $tenantElectricityBill): TenantElectricityBillResource
    {
        $this->authorize('update', $tenantElectricityBill);

        $bill = $this->electricityBillService->markPaid(
            $tenantElectricityBill,
            $request->input('amount_paid'),
        );

        return new TenantElectricityBillResource($bill);
    }
}
