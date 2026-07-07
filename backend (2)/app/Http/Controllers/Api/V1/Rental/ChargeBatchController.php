<?php

namespace App\Http\Controllers\Api\V1\Rental;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rental\ExcludeChargeBatchTenantRequest;
use App\Http\Requests\Rental\GenerateChargeBatchRequest;
use App\Http\Requests\Rental\UpdateChargeBatchItemRequest;
use App\Http\Resources\ChargeBatchItemResource;
use App\Http\Resources\ChargeBatchResource;
use App\Models\ChargeBatch;
use App\Models\ChargeBatchItem;
use App\Services\Rental\ChargeBatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChargeBatchController extends Controller
{
    public function __construct(private readonly ChargeBatchService $chargeBatchService) {}

    public function pendingCount(): JsonResponse
    {
        $this->authorize('viewAny', ChargeBatch::class);

        return response()->json([
            'count' => $this->chargeBatchService->pendingBatchCount(),
        ]);
    }

    public function show(Request $request): ChargeBatchResource|JsonResponse
    {
        $this->authorize('viewAny', ChargeBatch::class);

        $buildingId = $request->integer('building_id');
        $month = $request->integer('billing_month');
        $year = $request->integer('billing_year');

        if (! $buildingId || ! $month || ! $year) {
            return response()->json(['message' => 'building_id, billing_month, and billing_year are required.'], 422);
        }

        $batch = $this->chargeBatchService->findForPeriod($buildingId, $month, $year);

        if ($batch === null) {
            return response()->json(['data' => null]);
        }

        return $this->batchResponse($batch);
    }

    public function generate(GenerateChargeBatchRequest $request): ChargeBatchResource
    {
        $this->authorize('generate', ChargeBatch::class);

        $batch = $this->chargeBatchService->generateDraft(
            $request->integer('building_id'),
            $request->integer('billing_month'),
            $request->integer('billing_year'),
            $request->user(),
        );

        return $this->batchResponse($batch);
    }

    public function refreshPending(ChargeBatch $chargeBatch): ChargeBatchResource
    {
        $this->authorize('update', $chargeBatch);

        $batch = $this->chargeBatchService->refreshPendingItems($chargeBatch);

        return $this->batchResponse($batch);
    }

    public function updateItem(
        UpdateChargeBatchItemRequest $request,
        ChargeBatch $chargeBatch,
        ChargeBatchItem $chargeBatchItem,
    ): ChargeBatchResource {
        $this->authorize('update', $chargeBatch);

        $this->chargeBatchService->updateItemAmount(
            $chargeBatch,
            $chargeBatchItem,
            $request->string('amount')->toString(),
            $request->string('adjustment_note')->toString() ?: null,
            $request->user(),
        );

        return $this->batchResponse($chargeBatch->fresh(['building', 'items.tenant.unit', 'items.approvedByUser', 'items.adjustedByUser']));
    }

    public function excludeTenant(
        ExcludeChargeBatchTenantRequest $request,
        ChargeBatch $chargeBatch,
        int $tenantId,
    ): ChargeBatchResource {
        $this->authorize('update', $chargeBatch);

        $batch = $this->chargeBatchService->excludeTenant(
            $chargeBatch,
            $tenantId,
            $request->string('reason')->toString(),
            $request->user(),
        );

        return $this->batchResponse($batch);
    }

    public function approveTenant(ChargeBatch $chargeBatch, int $tenantId): ChargeBatchResource
    {
        $this->authorize('approve', $chargeBatch);

        $batch = $this->chargeBatchService->approveTenant($chargeBatch, $tenantId, request()->user());

        return $this->batchResponse($batch);
    }

    public function reopenTenant(ChargeBatch $chargeBatch, int $tenantId): ChargeBatchResource
    {
        $this->authorize('update', $chargeBatch);

        $batch = $this->chargeBatchService->reopenTenant($chargeBatch, $tenantId, request()->user());

        return $this->batchResponse($batch);
    }

    public function approveAll(ChargeBatch $chargeBatch): JsonResponse
    {
        $this->authorize('approve', $chargeBatch);

        $result = $this->chargeBatchService->approveAll($chargeBatch, request()->user());
        $batch = $chargeBatch->fresh(['building', 'items.tenant.unit', 'items.approvedByUser', 'items.adjustedByUser']);

        return response()->json([
            'message' => "Approved charges for {$result['approved_tenants']} tenant(s).",
            'approved_tenants' => $result['approved_tenants'],
            'posted_total' => $result['posted_total'],
            'data' => $this->batchResponse($batch)->resolve(),
        ]);
    }

    private function batchResponse(ChargeBatch $batch): ChargeBatchResource
    {
        $batch->loadMissing(['building', 'generatedByUser', 'lockedByUser', 'items.tenant.unit', 'items.approvedByUser', 'items.adjustedByUser']);
        $groups = $this->chargeBatchService->tenantGroups($batch)->map(function (array $group): array {
            $group['items'] = collect($group['items'])
                ->map(fn (ChargeBatchItem $item) => (new ChargeBatchItemResource($item))->resolve())
                ->values()
                ->all();

            return $group;
        });

        $batch->tenant_groups = $groups;

        return new ChargeBatchResource($batch);
    }
}
