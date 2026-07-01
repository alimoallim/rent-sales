<?php

namespace App\Http\Controllers\Api\V1\Rental;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rental\StoreShareholderBillRequest;
use App\Http\Requests\Rental\UpdateShareholderBillRequest;
use App\Http\Resources\ShareholderBillResource;
use App\Models\ShareholderBill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ShareholderBillController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ShareholderBill::class);

        $bills = ShareholderBill::query()
            ->with(['shareholder', 'building'])
            ->when($request->integer('building_id'), fn ($q, $id) => $q->where('rental_building_id', $id))
            ->when($request->integer('shareholder_id'), fn ($q, $id) => $q->where('shareholder_id', $id))
            ->when($request->input('from'), fn ($q, $from) => $q->whereDate('bill_date', '>=', $from))
            ->when($request->input('to'), fn ($q, $to) => $q->whereDate('bill_date', '<=', $to))
            ->orderByDesc('bill_date')
            ->paginate(50);

        return ShareholderBillResource::collection($bills);
    }

    public function store(StoreShareholderBillRequest $request): ShareholderBillResource
    {
        $this->authorize('create', ShareholderBill::class);

        $bill = ShareholderBill::query()->create($request->validated());
        $bill->load(['shareholder', 'building']);

        return new ShareholderBillResource($bill);
    }

    public function update(UpdateShareholderBillRequest $request, ShareholderBill $shareholderBill): ShareholderBillResource
    {
        $this->authorize('update', $shareholderBill);

        $shareholderBill->update($request->validated());
        $shareholderBill->load(['shareholder', 'building']);

        return new ShareholderBillResource($shareholderBill);
    }

    public function destroy(ShareholderBill $shareholderBill): JsonResponse
    {
        $this->authorize('delete', $shareholderBill);

        $shareholderBill->delete();

        return response()->json(['message' => 'Shareholder bill deleted.']);
    }
}
