<?php

namespace Tests\Feature\Rental;

use App\Enums\EmployeeStatus;
use App\Enums\RentPaymentStatus;
use App\Enums\RentalUnitStatus;
use App\Enums\TenantStatus;
use App\Models\Employee;
use App\Models\PayrollEntry;
use App\Models\RentalBuilding;
use App\Models\RentalExpense;
use App\Models\RentalUnit;
use App\Models\Shareholder;
use App\Models\ShareholderBill;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalOperationsTest extends TestCase
{
    use RefreshDatabase;

    private function rentalUser(): User
    {
        return User::factory()->rental()->create();
    }

    public function test_expense_crud(): void
    {
        $user = $this->rentalUser();
        $building = RentalBuilding::query()->create(['name' => 'Baraka Towers']);

        $this->actingAs($user)->postJson('/api/v1/rental/expenses', [
            'rental_building_id' => $building->id,
            'name' => 'Cleaning supplies',
            'amount' => 2500,
            'expense_date' => '2026-06-10',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Cleaning supplies');

        $expense = RentalExpense::query()->first();

        $this->actingAs($user)->putJson("/api/v1/rental/expenses/{$expense->id}", [
            'amount' => 3000,
        ])->assertOk()
            ->assertJsonPath('data.amount', '3000.00');

        $this->actingAs($user)->deleteJson("/api/v1/rental/expenses/{$expense->id}")
            ->assertOk();

        $this->assertSoftDeleted('rental_expenses', ['id' => $expense->id]);
    }

    public function test_employee_and_payroll_flow(): void
    {
        $user = $this->rentalUser();
        $building = RentalBuilding::query()->create(['name' => 'Baraka Towers']);

        $employeeResponse = $this->actingAs($user)->postJson('/api/v1/rental/employees', [
            'rental_building_id' => $building->id,
            'name' => 'John Guard',
            'position' => 'Caretaker',
            'salary' => 18000,
        ])->assertCreated();

        $employeeId = $employeeResponse->json('data.id');

        $this->actingAs($user)->postJson('/api/v1/rental/payroll', [
            'employee_id' => $employeeId,
            'rental_building_id' => $building->id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'paid_at' => '2026-06-28',
        ])->assertCreated()
            ->assertJsonPath('data.salary_amount', '18000.00');

        $this->actingAs($user)->deleteJson("/api/v1/rental/employees/{$employeeId}")
            ->assertUnprocessable();
    }

    public function test_shareholder_bill_crud(): void
    {
        $user = $this->rentalUser();
        $building = RentalBuilding::query()->create(['name' => 'Baraka Towers']);
        $shareholder = Shareholder::query()->create([
            'name' => 'Alice Investor',
            'phone' => '0700111222',
        ]);

        $this->actingAs($user)->postJson('/api/v1/rental/shareholder-bills', [
            'shareholder_id' => $shareholder->id,
            'rental_building_id' => $building->id,
            'amount' => 15000,
            'bill_date' => '2026-06-05',
            'remark' => 'June dividend',
        ])->assertCreated()
            ->assertJsonPath('data.amount', '15000.00');

        $this->actingAs($user)->deleteJson("/api/v1/rental/shareholders/{$shareholder->id}")
            ->assertUnprocessable();
    }

    public function test_income_statement_includes_expenses_payroll_and_shareholder_bills(): void
    {
        $user = $this->rentalUser();
        $building = RentalBuilding::query()->create(['name' => 'Baraka Towers']);
        $unit = RentalUnit::query()->create([
            'rental_building_id' => $building->id,
            'house_number' => 'A1',
            'floor' => '1',
            'description' => '2 bed',
            'monthly_rent' => 65000,
            'status' => RentalUnitStatus::Occupied,
        ]);
        $tenant = Tenant::query()->create([
            'rental_building_id' => $building->id,
            'rental_unit_id' => $unit->id,
            'name' => 'Jane Doe',
            'phone' => '0700000000',
            'deposit' => 25000,
            'service_amount' => 10000,
            'status' => TenantStatus::Active,
            'created_by' => $user->id,
        ]);

        \App\Models\RentPayment::query()->create([
            'tenant_id' => $tenant->id,
            'rental_building_id' => $building->id,
            'amount' => 50000,
            'discount' => 0,
            'paid_at' => '2026-06-15',
            'status' => RentPaymentStatus::Active,
            'created_by' => $user->id,
        ]);

        \App\Models\RentCharge::query()->create([
            'tenant_id' => $tenant->id,
            'rental_unit_id' => $unit->id,
            'rental_building_id' => $building->id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'rent_amount' => 65000,
            'service_amount' => 10000,
            'total_amount' => 75000,
            'purpose' => 'Rent + service',
            'charged_at' => now(),
        ]);

        RentalExpense::query()->create([
            'rental_building_id' => $building->id,
            'name' => 'Repairs',
            'amount' => 5000,
            'expense_date' => '2026-06-12',
        ]);

        $employee = Employee::query()->create([
            'rental_building_id' => $building->id,
            'name' => 'Guard',
            'position' => 'Caretaker',
            'salary' => 8000,
            'status' => EmployeeStatus::Current,
        ]);

        PayrollEntry::query()->create([
            'employee_id' => $employee->id,
            'rental_building_id' => $building->id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'salary_amount' => 8000,
            'paid_at' => '2026-06-28',
            'created_by' => $user->id,
        ]);

        $shareholder = Shareholder::query()->create(['name' => 'Alice']);
        ShareholderBill::query()->create([
            'shareholder_id' => $shareholder->id,
            'rental_building_id' => $building->id,
            'amount' => 3000,
            'bill_date' => '2026-06-05',
        ]);

        $this->actingAs($user)->getJson('/api/v1/rental/reports/income-statement?'.http_build_query([
            'building_id' => $building->id,
            'billing_month' => 6,
            'billing_year' => 2026,
        ]))
            ->assertOk()
            ->assertJsonPath('lines.expenses', '5000.00')
            ->assertJsonPath('lines.payroll', '8000.00')
            ->assertJsonPath('lines.shareholder_deductions', '3000.00')
            ->assertJsonPath('lines.rent_net', '37000.00')
            ->assertJsonPath('deposit_total', '25000.00');
    }
}
