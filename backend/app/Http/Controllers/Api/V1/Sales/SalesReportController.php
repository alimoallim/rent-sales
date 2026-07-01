<?php

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\SaleBuilding;
use App\Services\Sales\SalesReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesReportController extends Controller
{
    public function __construct(private readonly SalesReportService $reportService) {}

    public function balance(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SaleBuilding::class);

        return response()->json(
            $this->reportService->balanceReport(
                $request->integer('building_id') ?: null,
                $request->boolean('outstanding_only'),
            ),
        );
    }

    public function incomeStatement(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SaleBuilding::class);

        return response()->json(
            $this->reportService->incomeStatement(
                $request->integer('building_id') ?: null,
                $request->input('from'),
                $request->input('to'),
            ),
        );
    }
}
