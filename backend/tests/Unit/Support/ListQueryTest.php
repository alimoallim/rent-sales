<?php

namespace Tests\Unit\Support;

use App\Enums\RentPaymentStatus;
use App\Enums\RentalUnitStatus;
use App\Enums\TenantStatus;
use App\Models\RentPayment;
use App\Models\RentalBuilding;
use App\Models\RentalUnit;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ListQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ListQueryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: RentalBuilding, 1: list<RentalUnit>}
     */
    private function buildingWithUnits(int $count): array
    {
        $building = RentalBuilding::query()->create(['name' => 'Test Building']);
        $units = [];

        for ($i = 1; $i <= $count; $i++) {
            $units[] = RentalUnit::query()->create([
                'rental_building_id' => $building->id,
                'house_number' => (string) (100 + $i),
                'floor' => '1',
                'description' => 'Unit',
                'monthly_rent' => 50000,
                'status' => RentalUnitStatus::Occupied,
            ]);
        }

        return [$building, $units];
    }

    #[Test]
    public function test_search_is_case_insensitive_prefix_for_tenant_names(): void
    {
        $user = User::factory()->rental()->manager()->create();
        [$building, $units] = $this->buildingWithUnits(2);

        Tenant::query()->create([
            'rental_building_id' => $building->id,
            'rental_unit_id' => $units[0]->id,
            'name' => 'Yurub Muhiyadin',
            'phone' => '0700000001',
            'deposit' => 0,
            'service_amount' => 0,
            'status' => TenantStatus::Active,
            'created_by' => $user->id,
        ]);

        Tenant::query()->create([
            'rental_building_id' => $building->id,
            'rental_unit_id' => $units[1]->id,
            'name' => 'Ali Yurub',
            'phone' => '0700000002',
            'deposit' => 0,
            'service_amount' => 0,
            'status' => TenantStatus::Active,
            'created_by' => $user->id,
        ]);

        $lower = Tenant::query();
        ListQuery::applySearch($lower, Request::create('/', 'GET', ['search' => 'yurub']), ['name']);

        $upper = Tenant::query();
        ListQuery::applySearch($upper, Request::create('/', 'GET', ['search' => 'YURUB']), ['name']);

        $this->assertSame(1, $lower->count());
        $this->assertSame(1, $upper->count());
        $this->assertSame('Yurub Muhiyadin', $lower->first()->name);
    }

    #[Test]
    public function test_search_does_not_match_names_that_only_contain_the_term(): void
    {
        $user = User::factory()->rental()->manager()->create();
        [$building, $units] = $this->buildingWithUnits(2);

        Tenant::query()->create([
            'rental_building_id' => $building->id,
            'rental_unit_id' => $units[0]->id,
            'name' => 'Mohammed Ali',
            'phone' => '0700000001',
            'deposit' => 0,
            'service_amount' => 0,
            'status' => TenantStatus::Active,
            'created_by' => $user->id,
        ]);

        Tenant::query()->create([
            'rental_building_id' => $building->id,
            'rental_unit_id' => $units[1]->id,
            'name' => 'Ali Mohammed',
            'phone' => '0700000002',
            'deposit' => 0,
            'service_amount' => 0,
            'status' => TenantStatus::Active,
            'created_by' => $user->id,
        ]);

        $query = Tenant::query();
        ListQuery::applySearch($query, Request::create('/', 'GET', ['search' => 'Moh']), ['name']);

        $this->assertSame(1, $query->count());
        $this->assertSame('Mohammed Ali', $query->first()->name);
    }

    #[Test]
    public function test_search_filters_payments_by_related_tenant_name_prefix(): void
    {
        $user = User::factory()->rental()->manager()->create();
        [$building, $units] = $this->buildingWithUnits(2);

        $mohammed = Tenant::query()->create([
            'rental_building_id' => $building->id,
            'rental_unit_id' => $units[0]->id,
            'name' => 'Mohammed Ali',
            'phone' => '0700000001',
            'deposit' => 0,
            'service_amount' => 0,
            'status' => TenantStatus::Active,
            'created_by' => $user->id,
        ]);

        $sara = Tenant::query()->create([
            'rental_building_id' => $building->id,
            'rental_unit_id' => $units[1]->id,
            'name' => 'Sara Ahmed',
            'phone' => '0700000002',
            'deposit' => 0,
            'service_amount' => 0,
            'status' => TenantStatus::Active,
            'created_by' => $user->id,
        ]);

        RentPayment::createActive([
            'tenant_id' => $mohammed->id,
            'rental_building_id' => $building->id,
            'amount' => 10000,
            'discount' => 0,
            'paid_at' => '2026-01-01',
        ], $user->id);

        RentPayment::createActive([
            'tenant_id' => $sara->id,
            'rental_building_id' => $building->id,
            'amount' => 20000,
            'discount' => 0,
            'paid_at' => '2026-01-02',
        ], $user->id);

        $query = RentPayment::query();
        ListQuery::applySearch($query, Request::create('/', 'GET', ['search' => 'Moh']), ['invoice_reference'], ['tenant' => 'name']);

        $this->assertSame(1, $query->count());
        $this->assertSame($mohammed->id, $query->first()->tenant_id);
    }
}
