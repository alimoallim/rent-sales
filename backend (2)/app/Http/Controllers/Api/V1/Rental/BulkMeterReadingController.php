<?php

namespace App\Http\Controllers\Api\V1\Rental;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rental\BulkMeterReadingGridRequest;
use App\Http\Requests\Rental\StoreBulkMeterReadingsRequest;
use App\Models\TenantElectricityBill;
use App\Models\TenantWaterBill;
use App\Services\Rental\BulkMeterReadingService;
use Illuminate\Http\JsonResponse;

class BulkMeterReadingController extends Controller
{
    public function __construct(private readonly BulkMeterReadingService $bulkMeterReadingService) {}

    public function index(BulkMeterReadingGridRequest $request): JsonResponse
    {
        $utility = $request->string('utility')->toString();
        $this->authorizeBulk($utility);

        $grid = $this->bulkMeterReadingService->grid(
            $utility,
            $request->integer('building_id'),
            $request->integer('billing_month'),
            $request->integer('billing_year'),
        );

        return response()->json(['data' => $grid]);
    }

    public function store(StoreBulkMeterReadingsRequest $request): JsonResponse
    {
        $utility = $request->string('utility')->toString();
        $this->authorizeBulk($utility);

        $result = $this->bulkMeterReadingService->store(
            $utility,
            $request->integer('rental_building_id'),
            $request->integer('billing_month'),
            $request->integer('billing_year'),
            $request->validated('readings'),
            $request->user()->id,
        );

        return response()->json(['data' => $result]);
    }

    private function authorizeBulk(string $utility): void
    {
        $model = $utility === 'water' ? TenantWaterBill::class : TenantElectricityBill::class;
        $this->authorize('create', $model);
    }
}
