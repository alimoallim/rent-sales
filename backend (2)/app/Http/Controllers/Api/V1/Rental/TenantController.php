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
use App\Services\Rental\TenantBalanceBreakdownService;
use App\Services\Rental\TenantBalanceCalculator;
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
        private readonly TenantBalanceCalculator $balanceCalculator,
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

        if ($status === TenantStatus::Active->value && $request->boolean('with_balance')) {
            $this->restrictToTenantsWithBalance($query);
        }

        $summary = $this->tenantIndexSummary($query, $status);

        $tenants = $query
            ->orderBy('name')
            ->paginate(ListQuery::perPage($request, 50));

        if ($status === TenantStatus::Active->value) {
            $tenants->getCollection()->transform(function (Tenant $tenant): Tenant {
                $tenant->balance = $this->balanceCalculator->calculate($tenant);

                return $tenant;
            });
        }

        return TenantResource::collection($tenants)->additional([
            'summary' => $summary,
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Tenant>  $query
     * @return array<string, int|string>
     */
    private function tenantIndexSummary($query, string $status): array
    {
        $total = (clone $query)->count();

        if ($status !== TenantStatus::Active->value) {
            return ['total' => $total];
        }

        $withBalance = 0;
        $totalOutstanding = '0.00';
        $metered = (clone $query)
            ->where(function ($builder): void {
                $builder->where('requires_water_metering', true)
                    ->orWhere('requires_electricity_metering', true);
            })
            ->count();

        (clone $query)->orderBy('name')->each(function (Tenant $tenant) use (&$withBalance, &$totalOutstanding): void {
            $balance = $this->balanceCalculator->calculate($tenant);

            if (bccomp($balance, '0', 2) > 0) {
                $withBalance++;
                $totalOutstanding = bcadd($totalOutstanding, $balance, 2);
            }
        });

        return [
            'total' => $total,
            'with_balance' => $withBalance,
            'total_outstanding' => $totalOutstanding,
            'metered' => $metered,
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Tenant>  $query
     */
    private function restrictToTenantsWithBalance($query): void
    {
        $owingIds = (clone $query)
            ->orderBy('name')
            ->get()
            ->filter(fn (Tenant $tenant): bool => bccomp($this->balanceCalculator->calculate($tenant), '0', 2) > 0)
            ->pluck('id')
            ->all();

        $query->whereIn('id', $owingIds !== [] ? $owingIds : [0]);
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

        $tenant->load(['building', 'unit']);

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
