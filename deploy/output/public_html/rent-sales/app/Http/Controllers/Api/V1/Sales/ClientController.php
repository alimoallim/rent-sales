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

        $clients = Client::query()
            ->with(['building', 'unit'])
            ->withCount('payments')
            ->when($request->integer('building_id'), fn ($query, $buildingId) => $query->where('sale_building_id', $buildingId))
            ->when($request->boolean('with_balance'), fn ($query) => $query->where('status', ClientStatus::Active))
            ->where('status', $status)
            ->orderBy('name')
            ->paginate(50);

        if ($status === ClientStatus::Active->value) {
            $clients->getCollection()->transform(function (Client $client): Client {
                $client->balance = $this->balanceCalculator->calculate($client);

                return $client;
            });

            if ($request->boolean('with_balance')) {
                $clients->setCollection(
                    $clients->getCollection()->filter(fn (Client $client): bool => bccomp($client->balance, '0', 2) > 0)->values(),
                );
            }
        }

        return ClientResource::collection($clients);
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

        $client->load(['building', 'unit']);
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
