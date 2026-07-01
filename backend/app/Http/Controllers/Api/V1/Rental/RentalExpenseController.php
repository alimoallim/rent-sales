<?php

namespace App\Http\Controllers\Api\V1\Rental;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rental\StoreRentalExpenseRequest;
use App\Http\Requests\Rental\UpdateRentalExpenseRequest;
use App\Http\Resources\RentalExpenseResource;
use App\Models\RentalExpense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RentalExpenseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', RentalExpense::class);

        $expenses = RentalExpense::query()
            ->with('building')
            ->when($request->integer('building_id'), fn ($q, $id) => $q->where('rental_building_id', $id))
            ->when($request->input('from'), fn ($q, $from) => $q->whereDate('expense_date', '>=', $from))
            ->when($request->input('to'), fn ($q, $to) => $q->whereDate('expense_date', '<=', $to))
            ->orderByDesc('expense_date')
            ->paginate(50);

        return RentalExpenseResource::collection($expenses);
    }

    public function store(StoreRentalExpenseRequest $request): RentalExpenseResource
    {
        $this->authorize('create', RentalExpense::class);

        $expense = RentalExpense::query()->create($request->validated());
        $expense->load('building');

        return new RentalExpenseResource($expense);
    }

    public function update(UpdateRentalExpenseRequest $request, RentalExpense $expense): RentalExpenseResource
    {
        $this->authorize('update', $expense);

        $expense->update($request->validated());
        $expense->load('building');

        return new RentalExpenseResource($expense);
    }

    public function destroy(RentalExpense $expense): JsonResponse
    {
        $this->authorize('delete', $expense);

        $expense->delete();

        return response()->json(['message' => 'Expense deleted.']);
    }
}
