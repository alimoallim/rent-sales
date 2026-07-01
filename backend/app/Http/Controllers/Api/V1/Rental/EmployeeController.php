<?php

namespace App\Http\Controllers\Api\V1\Rental;

use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rental\StoreEmployeeRequest;
use App\Http\Requests\Rental\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmployeeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Employee::class);

        $employees = Employee::query()
            ->with('building')
            ->when($request->integer('building_id'), fn ($q, $id) => $q->where('rental_building_id', $id))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->orderBy('name')
            ->paginate(50);

        return EmployeeResource::collection($employees);
    }

    public function store(StoreEmployeeRequest $request): EmployeeResource
    {
        $this->authorize('create', Employee::class);

        $employee = Employee::query()->create([
            ...$request->validated(),
            'status' => $request->enum('status', EmployeeStatus::class) ?? EmployeeStatus::Current,
        ]);
        $employee->load('building');

        return new EmployeeResource($employee);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): EmployeeResource
    {
        $this->authorize('update', $employee);

        $employee->update($request->validated());
        $employee->load('building');

        return new EmployeeResource($employee);
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $this->authorize('delete', $employee);

        if ($employee->payrollEntries()->exists()) {
            return response()->json([
                'message' => 'Cannot delete an employee with payroll history. Mark them as former instead.',
            ], 422);
        }

        $employee->delete();

        return response()->json(['message' => 'Employee deleted.']);
    }
}
