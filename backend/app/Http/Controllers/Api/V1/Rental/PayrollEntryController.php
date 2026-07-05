<?php

namespace App\Http\Controllers\Api\V1\Rental;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rental\StorePayrollEntryRequest;
use App\Http\Requests\Rental\UpdatePayrollEntryRequest;
use App\Http\Resources\PayrollEntryResource;
use App\Models\Employee;
use App\Models\PayrollEntry;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PayrollEntryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PayrollEntry::class);

        $query = PayrollEntry::query()
            ->with(['employee', 'building'])
            ->when($request->integer('building_id'), fn ($q, $id) => $q->where('rental_building_id', $id))
            ->when($request->integer('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->integer('billing_month'), fn ($q, $m) => $q->where('billing_month', $m))
            ->when($request->integer('billing_year'), fn ($q, $y) => $q->where('billing_year', $y));

        ListQuery::applySearch($query, $request, [], ['employee' => 'name']);

        $entries = $query
            ->orderByDesc('paid_at')
            ->paginate(ListQuery::perPage($request, 50));

        return PayrollEntryResource::collection($entries);
    }

    public function store(StorePayrollEntryRequest $request): PayrollEntryResource
    {
        $this->authorize('create', PayrollEntry::class);

        $employee = Employee::query()->findOrFail($request->integer('employee_id'));
        $salaryAmount = $request->input('salary_amount', $employee->salary);

        $entry = PayrollEntry::query()->create([
            ...$request->validated(),
            'salary_amount' => $salaryAmount,
            'created_by' => $request->user()->id,
        ]);

        $entry->load(['employee', 'building']);

        return new PayrollEntryResource($entry);
    }

    public function update(UpdatePayrollEntryRequest $request, PayrollEntry $payrollEntry): PayrollEntryResource
    {
        $this->authorize('update', $payrollEntry);

        $payrollEntry->update($request->validated());
        $payrollEntry->load(['employee', 'building']);

        return new PayrollEntryResource($payrollEntry);
    }

    public function destroy(PayrollEntry $payrollEntry): JsonResponse
    {
        $this->authorize('delete', $payrollEntry);

        $payrollEntry->delete();

        return response()->json(['message' => 'Payroll entry deleted.']);
    }
}
