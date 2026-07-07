<?php

namespace Tests\Feature\Security;

use App\Enums\ClientStatus;
use App\Enums\SaleUnitStatus;
use App\Models\Client;
use App\Models\SaleBuilding;
use App\Models\SaleUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRentalDomain;
use Tests\TestCase;

class IdorProtectionTest extends TestCase
{
    use InteractsWithRentalDomain;
    use RefreshDatabase;

    public function test_rental_user_cannot_read_sales_client_by_id(): void
    {
        $rentalUser = User::factory()->rental()->create();
        $building = SaleBuilding::query()->create(['name' => 'Sales Only Plaza']);
        $unit = SaleUnit::query()->create([
            'sale_building_id' => $building->id,
            'house_number' => 'S1',
            'floor' => '1',
            'description' => 'Unit',
            'list_price' => 100000,
            'status' => SaleUnitStatus::Available,
        ]);
        $client = Client::query()->create([
            'sale_building_id' => $building->id,
            'sale_unit_id' => $unit->id,
            'name' => 'Private Buyer',
            'phone' => '0700111000',
            'agreed_sale_price' => 100000,
            'deposit' => 10000,
            'status' => ClientStatus::Active,
        ]);

        $this->actingAs($rentalUser)
            ->getJson("/api/v1/sales/clients/{$client->id}")
            ->assertForbidden();
    }

    public function test_sales_user_cannot_read_rental_tenant_by_id(): void
    {
        $salesUser = User::factory()->sales()->create();
        $rentalUser = $this->rentalUser();
        $tenant = $this->activeTenantSetup($rentalUser);

        $this->actingAs($salesUser)
            ->getJson("/api/v1/rental/tenants/{$tenant->id}")
            ->assertForbidden();
    }

    public function test_nonexistent_tenant_returns_not_found_not_success(): void
    {
        $user = $this->rentalUser();

        $this->actingAs($user)
            ->getJson('/api/v1/rental/tenants/999999')
            ->assertNotFound();
    }

    public function test_payment_with_mismatched_building_id_is_rejected(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenantSetup($user);
        $this->postRentCharge($tenant, '10000.00');
        $otherBuilding = $this->rentalBuilding(['name' => 'Wrong Building']);

        $this->actingAs($user)
            ->postJson('/api/v1/rental/payments', $this->paymentPayload($tenant, '1000.00', [
                'rental_building_id' => $otherBuilding->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tenant_id']);
    }

    public function test_tenant_registration_rejects_unit_from_different_building(): void
    {
        $user = $this->rentalUser();
        $buildingA = $this->rentalBuilding(['name' => 'Building A']);
        $buildingB = $this->rentalBuilding(['name' => 'Building B']);
        $unitInB = $this->vacantUnit($buildingB);

        $this->actingAs($user)
            ->postJson('/api/v1/rental/tenants', [
                'rental_building_id' => $buildingA->id,
                'rental_unit_id' => $unitInB->id,
                'name' => 'IDOR Tenant',
                'phone' => '0700555666',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rental_unit_id']);
    }

    public function test_soft_deleted_building_is_not_accessible_by_id(): void
    {
        $user = User::factory()->admin()->create();
        $building = $this->rentalBuilding();
        $building->delete();

        $this->actingAs($user)
            ->getJson("/api/v1/rental/buildings/{$building->id}")
            ->assertNotFound();
    }

    public function test_rental_user_cannot_void_payment_they_did_not_create_via_cross_module(): void
    {
        $rentalUser = $this->rentalUser();
        $tenant = $this->activeTenantSetup($rentalUser);
        $payment = $this->createPaymentRecord($tenant, $rentalUser, '5000.00');
        $salesUser = User::factory()->sales()->create();

        $this->actingAs($salesUser)
            ->postJson("/api/v1/rental/payments/{$payment->id}/void")
            ->assertForbidden();
    }
}
