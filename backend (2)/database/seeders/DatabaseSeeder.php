<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrator',
                'email' => null,
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'status' => UserStatus::Active,
            ],
        );

        User::query()->updateOrCreate(
            ['username' => 'rental'],
            [
                'name' => 'Rental Staff',
                'email' => null,
                'password' => Hash::make('password'),
                'role' => UserRole::Rental,
                'status' => UserStatus::Active,
                'is_manager' => true,
            ],
        );

        User::query()->updateOrCreate(
            ['username' => 'sales'],
            [
                'name' => 'Sales Staff',
                'email' => null,
                'password' => Hash::make('password'),
                'role' => UserRole::Sales,
                'status' => UserStatus::Active,
            ],
        );

        $this->call(RentalDemoSeeder::class);
    }
}
