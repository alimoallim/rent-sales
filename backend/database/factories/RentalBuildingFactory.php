<?php

namespace Database\Factories;

use App\Models\RentalBuilding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RentalBuilding>
 */
class RentalBuildingFactory extends Factory
{
    protected $model = RentalBuilding::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Towers',
        ];
    }
}
