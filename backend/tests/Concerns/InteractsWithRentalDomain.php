<?php

namespace Tests\Concerns;

use App\Enums\RentPaymentStatus;
use App\Enums\RentalUnitStatus;
use App\Enums\TenantStatus;
use App\Models\RentCharge;
use App\Models\RentalBuilding;
use App\Models\RentalUnit;
use App\Models\RentPayment;
use App\Models\Tenant;
use App\Models\User;
use Database\Factories\RentalBuildingFactory;
use Database\Factories\RentalUnitFactory;

trait InteractsWithRentalDomain
{
    protected function rentalUser(): User
    {
        return User::factory()->rental()->create();
    }

    protected function rentalBuilding(array $attributes = []): RentalBuilding
    {
        return RentalBuildingFactory::new()->create($attributes);
    }

    protected function vacantUnit(RentalBuilding $building, array $attributes = []): RentalUnit
    {
        return RentalUnitFactory::new()
            ->forBuilding($building)
            ->create(array_merge(['status' => RentalUnitStatus::Vacant], $attributes));
    }

    protected function activeTenantSetup(User $user, array $tenantAttributes = []): Tenant
    {
        $building = $this->rentalBuilding();
        $unit = RentalUnit::query()->create([
            'rental_building_id' => $building->id,
            'house_number' => 'H1',
            'floor' => '1',
            'description' => 'Hostile test unit',
            'monthly_rent' => 50000,
            'status' => RentalUnitStatus::Occupied,
        ]);

        return Tenant::query()->create(array_merge([
            'rental_building_id' => $building->id,
            'rental_unit_id' => $unit->id,
            'name' => 'Hostile Tenant',
            'phone' => '0700999888',
            'deposit' => 0,
            'service_amount' => 5000,
            'status' => TenantStatus::Active,
            'created_by' => $user->id,
        ], $tenantAttributes));
    }

    protected function postRentCharge(Tenant $tenant, string $total, int $month = 6, int $year = 2026): RentCharge
    {
        return RentCharge::query()->create([
            'tenant_id' => $tenant->id,
            'rental_unit_id' => $tenant->rental_unit_id,
            'rental_building_id' => $tenant->rental_building_id,
            'billing_month' => $month,
            'billing_year' => $year,
            'rent_amount' => bcsub($total, (string) $tenant->service_amount, 2),
            'service_amount' => $tenant->service_amount,
            'total_amount' => $total,
            'purpose' => 'Rent + service',
            'charged_at' => now(),
        ]);
    }

    protected function paymentPayload(Tenant $tenant, string $amount, array $extra = []): array
    {
        return array_merge([
            'tenant_id' => $tenant->id,
            'rental_building_id' => $tenant->rental_building_id,
            'amount' => $amount,
            'discount' => '0.00',
            'paid_at' => now()->toDateString(),
        ], $extra);
    }

    protected function createPaymentRecord(Tenant $tenant, User $user, string $amount): RentPayment
    {
        return RentPayment::createActive([
            'tenant_id' => $tenant->id,
            'rental_building_id' => $tenant->rental_building_id,
            'amount' => $amount,
            'discount' => 0,
            'paid_at' => now(),
        ], $user->id);
    }

    protected function registerTenantViaApi(User $user, RentalBuilding $building, RentalUnit $unit, array $extra = []): Tenant
    {
        $response = $this->actingAs($user)->postJson('/api/v1/rental/tenants', array_merge([
            'rental_building_id' => $building->id,
            'rental_unit_id' => $unit->id,
            'name' => 'API Tenant',
            'phone' => '0711222333',
            'deposit' => 0,
            'service_amount' => 5000,
            'start_date' => '2026-01-01',
        ], $extra));

        $response->assertCreated();

        return Tenant::query()->findOrFail($response->json('data.id'));
    }
}
