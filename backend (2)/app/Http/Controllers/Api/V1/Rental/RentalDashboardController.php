<?php

namespace App\Http\Controllers\Api\V1\Rental;

use App\Http\Controllers\Controller;
use App\Models\RentalBuilding;
use App\Services\Rental\RentalDashboardService;
use Illuminate\Http\JsonResponse;

class RentalDashboardController extends Controller
{
    public function __invoke(RentalDashboardService $dashboard): JsonResponse
    {
        $this->authorize('viewAny', RentalBuilding::class);

        return response()->json($dashboard->build());
    }
}
