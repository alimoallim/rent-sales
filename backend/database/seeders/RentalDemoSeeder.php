<?php

namespace Database\Seeders;

use App\Enums\RentalUnitStatus;
use App\Models\RentalBuilding;
use App\Models\RentalUnit;
use Illuminate\Database\Seeder;

class RentalDemoSeeder extends Seeder
{
    public function run(): void
    {
        $building = RentalBuilding::query()->firstOrCreate(
            ['name' => 'BARAKA TOWERS 1 PANGANI'],
        );

        $units = [
            ['A1', '1ST FLR', '4BEDROOM', 65000, RentalUnitStatus::Vacant],
            ['A2', '1ST FLR', '4BEDROOM', 65000, RentalUnitStatus::Vacant],
            ['B1', '2ND FLR', '4BEDROOM', 65000, RentalUnitStatus::Vacant],
        ];

        foreach ($units as [$houseNumber, $floor, $description, $rent, $status]) {
            RentalUnit::query()->firstOrCreate(
                [
                    'rental_building_id' => $building->id,
                    'house_number' => $houseNumber,
                ],
                [
                    'floor' => $floor,
                    'description' => $description,
                    'monthly_rent' => $rent,
                    'status' => $status,
                ],
            );
        }
    }
}
