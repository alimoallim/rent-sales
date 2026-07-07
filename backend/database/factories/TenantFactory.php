<?php

namespace Database\Factories;

use App\Enums\RentalUnitStatus;
use App\Enums\TenantStatus;
use App\Models\RentalUnit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $building = RentalBuildingFactory::new()->create();
        $unit = RentalUnitFactory::new()->forBuilding($building)->occupied()->create();

        return [
            'rental_building_id' => $building->id,
            'rental_unit_id' => $unit->id,
            'name' => fake()->name(),
            'phone' => fake()->numerify('07########'),
            'deposit' => 0,
            'service_amount' => 5000,
            'status' => TenantStatus::Active,
            'created_by' => User::factory()->rental(),
        ];
    }

    public function forUnit(RentalUnit $unit): static
    {
        return $this->state(fn () => [
            'rental_building_id' => $unit->rental_building_id,
            'rental_unit_id' => $unit->id,
        ]);
    }
}
