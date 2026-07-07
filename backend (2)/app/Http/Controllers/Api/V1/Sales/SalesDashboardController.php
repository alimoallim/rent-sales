<?php

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\SaleBuilding;
use App\Services\Sales\SalesDashboardService;
use Illuminate\Http\JsonResponse;

class SalesDashboardController extends Controller
{
    public function __invoke(SalesDashboardService $dashboard): JsonResponse
    {
        $this->authorize('viewAny', SaleBuilding::class);

        return response()->json($dashboard->build());
    }
}
