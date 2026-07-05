<?php

namespace App\Http\Controllers\Api\V1\Rental;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rental\StoreShareholderRequest;
use App\Http\Requests\Rental\UpdateShareholderRequest;
use App\Http\Resources\ShareholderResource;
use App\Models\Shareholder;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ShareholderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Shareholder::class);

        $query = Shareholder::query()->withCount('bills');

        ListQuery::applySearch($query, $request, ['name', 'phone', 'address']);

        $shareholders = $query
            ->orderBy('name')
            ->paginate(ListQuery::perPage($request, 50));

        return ShareholderResource::collection($shareholders);
    }

    public function store(StoreShareholderRequest $request): ShareholderResource
    {
        $this->authorize('create', Shareholder::class);

        $shareholder = Shareholder::query()->create($request->validated());

        return new ShareholderResource($shareholder);
    }

    public function update(UpdateShareholderRequest $request, Shareholder $shareholder): ShareholderResource
    {
        $this->authorize('update', $shareholder);

        $shareholder->update($request->validated());

        return new ShareholderResource($shareholder);
    }

    public function destroy(Shareholder $shareholder): JsonResponse
    {
        $this->authorize('delete', $shareholder);

        if ($shareholder->bills()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a shareholder with billing history.',
            ], 422);
        }

        $shareholder->delete();

        return response()->json(['message' => 'Shareholder deleted.']);
    }
}
