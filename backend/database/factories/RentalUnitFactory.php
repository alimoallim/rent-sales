<?php

namespace Database\Factories;

use App\Enums\RentalUnitStatus;
use App\Models\RentalBuilding;
use App\Models\RentalUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RentalUnit>
 */
class RentalUnitFactory extends Factory
{
    protected $model = RentalUnit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rental_building_id' => RentalBuildingFactory::new(),
            'house_number' => fake()->unique()->regexify('[A-Z][0-9]{1,2}'),
            'floor' => (string) fake()->numberBetween(1, 12),
            'description' => fake()->randomElement(['Studio', '1 bed', '2 bed', '3 bed']),
            'monthly_rent' => fake()->numberBetween(30000, 120000),
            'status' => RentalUnitStatus::Vacant,
        ];
    }

    public function occupied(): static
    {
        return $this->state(fn () => ['status' => RentalUnitStatus::Occupied]);
    }

    public function forBuilding(RentalBuilding $building): static
    {
        return $this->state(fn () => ['rental_building_id' => $building->id]);
    }
}
