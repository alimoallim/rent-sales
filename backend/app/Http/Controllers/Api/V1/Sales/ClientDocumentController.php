<?php

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Client;
use App\Services\DocumentService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientDocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documents) {}

    public function index(Client $client): AnonymousResourceCollection
    {
        $this->authorize('view', $client);

        $client->load('documents');

        return DocumentResource::collection($client->documents);
    }

    public function store(StoreDocumentRequest $request, Client $client): DocumentResource
    {
        $this->authorize('update', $client);

        $document = $this->documents->store(
            $client,
            $request->enum('kind', \App\Enums\DocumentKind::class),
            $request->file('file'),
            $request->user(),
        );

        return new DocumentResource($document);
    }
}
