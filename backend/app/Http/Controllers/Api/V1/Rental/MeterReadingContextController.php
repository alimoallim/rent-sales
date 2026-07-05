<?php

namespace App\Http\Controllers\Api\V1\Rental;

use App\Http\Controllers\Controller;
use App\Models\TenantElectricityBill;
use App\Models\TenantWaterBill;
use App\Services\Rental\MeterReadingResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MeterReadingContextController extends Controller
{
    public function __construct(private readonly MeterReadingResolver $meterReadingResolver) {}

    public function show(Request $request): JsonResponse
    {
        $utility = $request->string('utility')->toString();
        $tenantId = $request->integer('tenant_id');
        $month = $request->integer('billing_month');
        $year = $request->integer('billing_year');

        if (! in_array($utility, ['water', 'electricity'], true)) {
            throw ValidationException::withMessages([
                'utility' => ['Utility must be water or electricity.'],
            ]);
        }

        if (! $tenantId || ! $month || ! $year) {
            throw ValidationException::withMessages([
                'tenant_id' => ['Tenant, billing month, and billing year are required.'],
            ]);
        }

        $billModel = $utility === 'water' ? TenantWaterBill::class : TenantElectricityBill::class;
        $this->authorize('create', $billModel);

        return response()->json([
            'data' => $this->meterReadingResolver->context($billModel, $tenantId, $month, $year),
        ]);
    }
}
