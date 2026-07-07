<?php

namespace App\Http\Controllers\Api\V1\Rental;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rental\MoveOutTenantRequest;
use App\Http\Requests\Rental\StoreTenantRequest;
use App\Http\Requests\Rental\UpdateTenantRequest;
use App\Http\Resources\TenantMoveOutResource;
use App\Http\Resources\TenantPaymentSummaryResource;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Models\TenantMoveOut;
use App\Services\Rental\TenantBalanceBatchService;
use App\Services\Rental\TenantBalanceBreakdownService;
use App\Services\Rental\TenantMeterReadingReminderService;
use App\Services\Rental\TenantService;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TenantController extends Controller
{
    public function __construct(
        private readonly TenantService $tenantService,
        private readonly TenantBalanceBatchService $balanceBatchService,
        private readonly TenantBalanceBreakdownService $balanceBreakdownService,
        private readonly TenantMeterReadingReminderService $meterReadingReminderService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Tenant::class);

        $status = $request->string('status', TenantStatus::Active->value)->toString();

        $query = Tenant::query()
            ->with(['building', 'unit'])
            ->when($request->integer('building_id'), fn ($query, $buildingId) => $query->where('rental_building_id', $buildingId))
            ->where('status', $status);

        ListQuery::applySearch($query, $request, ['name', 'phone', 'email', 'passport_or_id'], [
            'building' => 'name',
            'unit' => 'house_number',
        ]);

        $tenantIds = (clone $query)->pluck('id')->all();
        $balances = $this->balanceBatchService->totalDueForTenants($tenantIds);

        if ($request->boolean('with_balance')) {
            $owingIds = array_values(array_filter(
                $tenantIds,
                fn (int $id): bool => bccomp($balances[$id] ?? '0', '0', 2) > 0,
            ));
            $query->whereIn('id', $owingIds !== [] ? $owingIds : [0]);
            $balances = array_intersect_key($balances, array_flip($owingIds));
        }

        $summary = $this->tenantIndexSummary($query, $status, $balances);

        $tenants = $query
            ->orderBy('name')
            ->paginate(ListQuery::perPage($request, 50));

        $tenants->getCollection()->transform(function (Tenant $tenant) use ($balances): Tenant {
            $tenant->balance = $balances[$tenant->id] ?? '0.00';

            return $tenant;
        });

        return TenantResource::collection($tenants)->additional([
            'summary' => $summary,
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Tenant>  $query
     * @param  array<int, string>  $balances
     * @return array<string, int|string>
     */
    private function tenantIndexSummary($query, string $status, array $balances): array
    {
        $total = (clone $query)->count();
        $summary = $this->balanceBatchService->summarizePositiveBalances($balances);

        if ($status !== TenantStatus::Active->value) {
            return [
                'total' => $total,
                'with_balance' => $summary['with_balance'],
                'total_outstanding' => $summary['total_outstanding'],
            ];
        }

        $metered = (clone $query)
            ->where(function ($builder): void {
                $builder->where('requires_water_metering', true)
                    ->orWhere('requires_electricity_metering', true);
            })
            ->count();

        return [
            'total' => $total,
            'with_balance' => $summary['with_balance'],
            'total_outstanding' => $summary['total_outstanding'],
            'metered' => $metered,
        ];
    }

    public function store(StoreTenantRequest $request): TenantResource
    {
        $this->authorize('create', Tenant::class);

        $tenant = $this->tenantService->register($request->validated(), $request->user());

        return new TenantResource($tenant);
    }

    public function show(Tenant $tenant): TenantResource
    {
        $this->authorize('view', $tenant);

        $tenant->load(['building', 'unit', 'documents']);

        return new TenantResource($tenant);
    }

    public function paymentSummary(Request $request, Tenant $tenant): TenantPaymentSummaryResource
    {
        $this->authorize('view', $tenant);

        $excludePaymentId = $request->integer('exclude_payment_id') ?: null;
        $billingMonth = $request->integer('billing_month') ?: (int) now()->month;
        $billingYear = $request->integer('billing_year') ?: (int) now()->year;

        $summary = $this->balanceBreakdownService->breakdown($tenant, $excludePaymentId);
        $reminders = $this->meterReadingReminderService->remindersForTenant($tenant, $billingMonth, $billingYear);
        $summary['meter_reading_reminders'] = $reminders;
        $summary['payment_blocked'] = $reminders !== [];
        $summary['contract'] = $tenant->contractDetails();

        return new TenantPaymentSummaryResource($summary);
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): TenantResource
    {
        $this->authorize('update', $tenant);

        $tenant = $this->tenantService->update($tenant, $request->validated());

        return new TenantResource($tenant);
    }

    public function moveOut(MoveOutTenantRequest $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('moveOut', $tenant);

        $moveOut = $this->tenantService->moveOut($tenant, $request->validated(), $request->user());

        return (new TenantMoveOutResource($moveOut))
            ->response()
            ->setStatusCode(201);
    }

    public function moveOuts(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Tenant::class);

        $records = TenantMoveOut::query()
            ->with(['tenant', 'building', 'unit'])
            ->when($request->integer('building_id'), fn ($query, $buildingId) => $query->where('rental_building_id', $buildingId))
            ->orderByDesc('moved_out_at')
            ->paginate(50);

        return TenantMoveOutResource::collection($records);
    }
}
