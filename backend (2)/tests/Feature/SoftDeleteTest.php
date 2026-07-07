<?php

namespace Tests\Feature;

use App\Models\RentalBuilding;
use App\Models\RentalUnit;
use App\Models\SaleBuilding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_deleting_building_is_soft_and_hides_it_from_listing(): void
    {
        $building = RentalBuilding::query()->create(['name' => 'Baraka Towers']);

        $this->actingAs($this->admin())
            ->deleteJson("/api/v1/rental/buildings/{$building->id}")
            ->assertOk();

        $this->assertSoftDeleted('rental_buildings', ['id' => $building->id]);

        $this->actingAs($this->admin())
            ->getJson('/api/v1/rental/buildings')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Baraka Towers']);
    }

    public function test_building_name_can_be_reused_after_soft_delete(): void
    {
        $building = RentalBuilding::query()->create(['name' => 'Baraka Towers']);
        $building->delete();

        $this->actingAs($this->admin())
            ->postJson('/api/v1/rental/buildings', ['name' => 'Baraka Towers'])
            ->assertCreated();
    }

    public function test_unit_number_can_be_reused_after_soft_delete(): void
    {
        $building = RentalBuilding::query()->create(['name' => 'Baraka Towers']);
        $unit = RentalUnit::query()->create([
            'rental_building_id' => $building->id,
            'house_number' => 'A1',
            'floor' => '1',
            'description' => 'Unit',
            'monthly_rent' => 100,
            'status' => 'vacant',
        ]);
        $unit->delete();

        $this->actingAs($this->admin())
            ->postJson('/api/v1/rental/units', [
                'rental_building_id' => $building->id,
                'house_number' => 'A1',
                'floor' => '1',
                'description' => 'Replacement unit',
                'monthly_rent' => 120,
            ])
            ->assertCreated();
    }

    public function test_soft_deleted_building_cannot_be_used_for_new_units(): void
    {
        $building = RentalBuilding::query()->create(['name' => 'Gone Towers']);
        $building->delete();

        $this->actingAs($this->admin())
            ->postJson('/api/v1/rental/units', [
                'rental_building_id' => $building->id,
                'house_number' => 'A1',
                'floor' => '1',
                'description' => 'Unit',
                'monthly_rent' => 100,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rental_building_id']);
    }

    public function test_deleting_user_is_soft_and_blocks_login(): void
    {
        $admin = $this->admin();
        $user = User::factory()->rental()->create(['username' => 'ghost']);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/users/{$user->id}")
            ->assertOk();

        $this->assertSoftDeleted('users', ['id' => $user->id]);

        $this->postJson('/api/v1/auth/login', [
            'username' => 'ghost',
            'password' => 'password',
        ])->assertUnprocessable();
    }

    public function test_deleting_sale_building_is_soft(): void
    {
        $building = SaleBuilding::query()->create(['name' => 'Sales Plaza']);

        $this->actingAs($this->admin())
            ->deleteJson("/api/v1/sales/buildings/{$building->id}")
            ->assertOk();

        $this->assertSoftDeleted('sale_buildings', ['id' => $building->id]);
    }
}
