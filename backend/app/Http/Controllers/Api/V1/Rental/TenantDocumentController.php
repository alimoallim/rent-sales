<?php

namespace App\Http\Controllers\Api\V1\Rental;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Tenant;
use App\Services\DocumentService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TenantDocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documents) {}

    public function index(Tenant $tenant): AnonymousResourceCollection
    {
        $this->authorize('view', $tenant);

        $tenant->load('documents');

        return DocumentResource::collection($tenant->documents);
    }

    public function store(StoreDocumentRequest $request, Tenant $tenant): DocumentResource
    {
        $this->authorize('update', $tenant);

        $document = $this->documents->store(
            $tenant,
            $request->enum('kind', \App\Enums\DocumentKind::class),
            $request->file('file'),
            $request->user(),
        );

        return new DocumentResource($document);
    }
}
