<?php

namespace App\Http\Controllers\Api\V1\Sales;

use App\Enums\ClientStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreClientRequest;
use App\Http\Requests\Sales\UpdateClientRequest;
use App\Http\Resources\ClientPaymentSummaryResource;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Services\Sales\ClientBalanceCalculator;
use App\Services\Sales\ClientService;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientController extends Controller
{
    public function __construct(
        private readonly ClientService $clientService,
        private readonly ClientBalanceCalculator $balanceCalculator,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Client::class);

        $status = $request->string('status', ClientStatus::Active->value)->toString();

        $query = Client::query()
            ->with(['building', 'unit'])
            ->withCount('payments')
            ->when($request->integer('building_id'), fn ($query, $buildingId) => $query->where('sale_building_id', $buildingId))
            ->when($request->boolean('with_balance'), fn ($query) => $query->where('status', ClientStatus::Active))
            ->where('status', $status);

        ListQuery::applySearch($query, $request, ['name', 'phone', 'email', 'passport_or_id', 'voucher_number'], [
            'building' => 'name',
            'unit' => 'house_number',
        ]);

        if ($status === ClientStatus::Active->value && $request->boolean('with_balance')) {
            $this->restrictToClientsWithBalance($query);
        }

        $summary = $this->clientIndexSummary($query, $status);

        $clients = $query
            ->orderBy('name')
            ->paginate(ListQuery::perPage($request, 50));

        if ($status === ClientStatus::Active->value) {
            $clients->getCollection()->transform(function (Client $client): Client {
                $client->balance = $this->balanceCalculator->calculate($client);

                return $client;
            });
        }

        return ClientResource::collection($clients)->additional([
            'summary' => $summary,
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Client>  $query
     * @return array<string, int|string>
     */
    private function clientIndexSummary($query, string $status): array
    {
        $total = (clone $query)->count();
        $totalAgreed = (string) (clone $query)->sum('agreed_sale_price');

        if ($status !== ClientStatus::Active->value) {
            return [
                'total' => $total,
                'total_agreed' => $totalAgreed,
            ];
        }

        $withBalance = 0;
        $totalOutstanding = '0.00';
        $totalCollected = '0.00';

        (clone $query)->orderBy('name')->each(function (Client $client) use (&$withBalance, &$totalOutstanding, &$totalCollected): void {
            $summary = $this->balanceCalculator->summary($client);
            $totalCollected = bcadd($totalCollected, $summary['paid_total'], 2);

            if (bccomp($summary['balance'], '0', 2) > 0) {
                $withBalance++;
                $totalOutstanding = bcadd($totalOutstanding, $summary['balance'], 2);
            }
        });

        return [
            'total' => $total,
            'with_balance' => $withBalance,
            'total_outstanding' => $totalOutstanding,
            'total_collected' => $totalCollected,
            'total_agreed' => $totalAgreed,
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Client>  $query
     */
    private function restrictToClientsWithBalance($query): void
    {
        $owingIds = (clone $query)
            ->orderBy('name')
            ->get()
            ->filter(fn (Client $client): bool => bccomp($this->balanceCalculator->calculate($client), '0', 2) > 0)
            ->pluck('id')
            ->all();

        $query->whereIn('id', $owingIds !== [] ? $owingIds : [0]);
    }

    public function store(StoreClientRequest $request): ClientResource
    {
        $this->authorize('create', Client::class);

        $client = $this->clientService->register($request->validated(), $request->user());

        return new ClientResource($client);
    }

    public function show(Client $client): ClientResource
    {
        $this->authorize('view', $client);

        $client->load(['building', 'unit', 'documents']);
        $client->balance = $this->balanceCalculator->calculate($client);

        return new ClientResource($client);
    }

    public function paymentSummary(Client $client): ClientPaymentSummaryResource
    {
        $this->authorize('view', $client);

        return new ClientPaymentSummaryResource($this->balanceCalculator->summary($client));
    }

    public function update(UpdateClientRequest $request, Client $client): ClientResource
    {
        $this->authorize('update', $client);

        $client = $this->clientService->update($client, $request->validated());

        return new ClientResource($client);
    }

    public function disable(Request $request, Client $client): ClientResource
    {
        $this->authorize('disable', $client);

        $client = $this->clientService->disable($client, $request->user());

        return new ClientResource($client);
    }
}
