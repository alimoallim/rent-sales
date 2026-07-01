<?php

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreSalesExpenseRequest;
use App\Http\Requests\Sales\UpdateSalesExpenseRequest;
use App\Http\Resources\SalesExpenseResource;
use App\Models\SalesExpense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SalesExpenseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SalesExpense::class);

        $expenses = SalesExpense::query()
            ->with('building')
            ->when($request->integer('building_id'), fn ($q, $id) => $q->where('sale_building_id', $id))
            ->when($request->input('from'), fn ($q, $from) => $q->whereDate('expense_date', '>=', $from))
            ->when($request->input('to'), fn ($q, $to) => $q->whereDate('expense_date', '<=', $to))
            ->orderByDesc('expense_date')
            ->paginate(50);

        return SalesExpenseResource::collection($expenses);
    }

    public function store(StoreSalesExpenseRequest $request): SalesExpenseResource
    {
        $this->authorize('create', SalesExpense::class);

        $expense = SalesExpense::query()->create($request->validated());
        $expense->load('building');

        return new SalesExpenseResource($expense);
    }

    public function update(UpdateSalesExpenseRequest $request, SalesExpense $expense): SalesExpenseResource
    {
        $this->authorize('update', $expense);

        $expense->update($request->validated());
        $expense->load('building');

        return new SalesExpenseResource($expense);
    }

    public function destroy(SalesExpense $expense): JsonResponse
    {
        $this->authorize('delete', $expense);

        $expense->delete();

        return response()->json(['message' => 'Expense deleted.']);
    }
}
