<?php

namespace Tests\Feature;

use App\Models\RentalBuilding;
use App\Models\RentalUnit;
use App\Models\SaleBuilding;
use App\Models\SaleUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuplicatePreventionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_rental_building_name_must_be_unique(): void
    {
        RentalBuilding::query()->create(['name' => 'Baraka Towers']);

        $this->actingAs($this->admin())
            ->postJson('/api/v1/rental/buildings', ['name' => 'Baraka Towers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_building_name_uniqueness_is_case_insensitive(): void
    {
        RentalBuilding::query()->create(['name' => 'Baraka Towers']);

        $this->actingAs($this->admin())
            ->postJson('/api/v1/rental/buildings', ['name' => '  baraka towers '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_building_name_must_be_unique_across_rental_and_sales(): void
    {
        SaleBuilding::query()->create(['name' => 'Crossmodule Plaza']);

        $this->actingAs($this->admin())
            ->postJson('/api/v1/rental/buildings', ['name' => 'Crossmodule Plaza'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        RentalBuilding::query()->create(['name' => 'Rental Heights']);

        $this->actingAs($this->admin())
            ->postJson('/api/v1/sales/buildings', ['name' => 'rental heights'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_building_can_keep_its_own_name_on_update(): void
    {
        $building = RentalBuilding::query()->create(['name' => 'Baraka Towers']);

        $this->actingAs($this->admin())
            ->putJson("/api/v1/rental/buildings/{$building->id}", ['name' => 'Baraka Towers'])
            ->assertOk();
    }

    public function test_building_cannot_be_renamed_to_existing_name(): void
    {
        RentalBuilding::query()->create(['name' => 'Tower One']);
        $building = RentalBuilding::query()->create(['name' => 'Tower Two']);

        $this->actingAs($this->admin())
            ->putJson("/api/v1/rental/buildings/{$building->id}", ['name' => 'Tower One'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_rental_unit_number_must_be_unique_within_building(): void
    {
        $building = RentalBuilding::query()->create(['name' => 'Baraka Towers']);
        RentalUnit::query()->create([
            'rental_building_id' => $building->id,
            'house_number' => 'A1',
            'floor' => '1',
            'description' => 'Unit',
            'monthly_rent' => 100,
            'status' => 'vacant',
        ]);

        $this->actingAs($this->admin())
            ->postJson('/api/v1/rental/units', [
                'rental_building_id' => $building->id,
                'house_number' => 'a1',
                'floor' => '1',
                'description' => 'Duplicate unit',
                'monthly_rent' => 120,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['house_number']);
    }

    public function test_same_unit_number_is_allowed_in_different_buildings(): void
    {
        $buildingA = RentalBuilding::query()->create(['name' => 'Tower A']);
        $buildingB = RentalBuilding::query()->create(['name' => 'Tower B']);
        RentalUnit::query()->create([
            'rental_building_id' => $buildingA->id,
            'house_number' => 'A1',
            'floor' => '1',
            'description' => 'Unit',
            'monthly_rent' => 100,
            'status' => 'vacant',
        ]);

        $this->actingAs($this->admin())
            ->postJson('/api/v1/rental/units', [
                'rental_building_id' => $buildingB->id,
                'house_number' => 'A1',
                'floor' => '1',
                'description' => 'Same number, other building',
                'monthly_rent' => 120,
            ])
            ->assertCreated();
    }

    public function test_rental_unit_can_keep_its_own_number_on_update(): void
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

        $this->actingAs($this->admin())
            ->putJson("/api/v1/rental/units/{$unit->id}", [
                'house_number' => 'A1',
                'floor' => '2',
                'description' => 'Updated',
                'monthly_rent' => 150,
            ])
            ->assertOk();
    }

    public function test_rental_unit_cannot_take_number_of_sibling_unit(): void
    {
        $building = RentalBuilding::query()->create(['name' => 'Baraka Towers']);
        RentalUnit::query()->create([
            'rental_building_id' => $building->id,
            'house_number' => 'A1',
            'floor' => '1',
            'description' => 'Unit',
            'monthly_rent' => 100,
            'status' => 'vacant',
        ]);
        $unit = RentalUnit::query()->create([
            'rental_building_id' => $building->id,
            'house_number' => 'B2',
            'floor' => '1',
            'description' => 'Unit',
            'monthly_rent' => 100,
            'status' => 'vacant',
        ]);

        $this->actingAs($this->admin())
            ->putJson("/api/v1/rental/units/{$unit->id}", [
                'house_number' => 'A1',
                'floor' => '1',
                'description' => 'Unit',
                'monthly_rent' => 100,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['house_number']);
    }

    public function test_sale_unit_number_must_be_unique_within_building(): void
    {
        $building = SaleBuilding::query()->create(['name' => 'Sales Plaza']);
        SaleUnit::query()->create([
            'sale_building_id' => $building->id,
            'house_number' => 'S1',
            'floor' => '1',
            'description' => 'Unit',
            'list_price' => 50000,
            'status' => 'available',
        ]);

        $this->actingAs($this->admin())
            ->postJson('/api/v1/sales/units', [
                'sale_building_id' => $building->id,
                'house_number' => 's1',
                'floor' => '1',
                'description' => 'Duplicate unit',
                'list_price' => 60000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['house_number']);
    }
}
