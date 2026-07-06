<?php

namespace Tests\Feature\Legacy;

use App\Models\RentalBuilding;
use App\Models\Tenant;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LegacyImportTest extends TestCase
{
    use RefreshDatabase;

    private string $dumpPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dumpPath = '/home/ali/legacy-app/rasulmar_karama.sql';
    }

    #[Test]
    public function test_fresh_import_loads_core_rental_entities(): void
    {
        if (! is_readable($this->dumpPath)) {
            $this->markTestSkipped('Legacy dump file not available.');
        }

        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            $this->markTestSkipped('Database is not available for integration import test.');
        }

        $this->seed(DatabaseSeeder::class);

        $this->artisan('legacy:import', [
            'file' => $this->dumpPath,
            '--fresh' => true,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertSame(1, RentalBuilding::query()->where('legacy_id', 3)->count());
        $this->assertSame(142, Tenant::query()->count());
        $this->assertSame(1283, \App\Models\RentCharge::query()->where('purpose', 'Rent + service')->count());
        $this->assertSame(1135, \App\Models\TenantWaterBill::query()->count());
        $this->assertSame(1135, \App\Models\RentCharge::query()->where('purpose', 'Water')->count());
        $this->assertSame(1229, \App\Models\RentPayment::query()->count());
    }

    #[Test]
    public function test_skip_users_imports_when_legacy_usernames_do_not_match_greenfield(): void
    {
        $dumpPath = storage_path('legacy/rasulmar_test.sql');

        if (! is_readable($dumpPath)) {
            $this->markTestSkipped('Legacy test dump file not available.');
        }

        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            $this->markTestSkipped('Database is not available for integration import test.');
        }

        $this->seed(DatabaseSeeder::class);

        $this->artisan('legacy:import', [
            'file' => $dumpPath,
            '--fresh' => true,
            '--force' => true,
            '--skip-users' => true,
        ])->assertSuccessful();

        $this->assertSame(150, Tenant::query()->count());
        $this->assertSame(1112, \App\Models\RentPayment::query()->count());
    }
}
