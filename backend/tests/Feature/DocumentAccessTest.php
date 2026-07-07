<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Document;
use App\Models\RentalBuilding;
use App\Models\RentalUnit;
use App\Models\SaleBuilding;
use App\Models\SaleUnit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DocumentAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    #[Test]
    public function test_rental_user_cannot_download_client_document(): void
    {
        $salesUser = User::factory()->sales()->create();
        $rentalUser = User::factory()->rental()->create();
        $client = $this->createClient();

        $upload = $this->actingAs($salesUser)
            ->withHeader('Accept', 'application/json')
            ->post("/api/v1/sales/clients/{$client->id}/documents", [
                'kind' => 'signature',
                'file' => UploadedFile::fake()->create('signature.png', 100, 'image/png'),
            ]);

        $upload->assertCreated();
        $documentId = $upload->json('data.id');
        $this->assertNotNull($documentId);

        $this->flushSession();
        $this->actingAs($rentalUser)
            ->get("/api/v1/documents/{$documentId}")
            ->assertForbidden();
    }

    #[Test]
    public function test_sales_user_cannot_download_tenant_document(): void
    {
        $rentalUser = User::factory()->rental()->create();
        $salesUser = User::factory()->sales()->create();
        $tenant = $this->createTenant();

        $upload = $this->actingAs($rentalUser)
            ->withHeader('Accept', 'application/json')
            ->post("/api/v1/rental/tenants/{$tenant->id}/documents", [
                'kind' => 'photo',
                'file' => UploadedFile::fake()->create('tenant-photo.jpg', 100, 'image/jpeg'),
            ]);

        $upload->assertCreated();
        $documentId = $upload->json('data.id');
        $this->assertNotNull($documentId);

        $this->flushSession();
        $this->actingAs($salesUser)
            ->get("/api/v1/documents/{$documentId}")
            ->assertForbidden();
    }

    #[Test]
    public function test_orphaned_document_cannot_be_downloaded(): void
    {
        $rentalUser = User::factory()->rental()->create();
        $tenant = $this->createTenant();

        $upload = $this->actingAs($rentalUser)
            ->withHeader('Accept', 'application/json')
            ->post("/api/v1/rental/tenants/{$tenant->id}/documents", [
                'kind' => 'photo',
                'file' => UploadedFile::fake()->create('tenant-photo.jpg', 100, 'image/jpeg'),
            ]);

        $documentId = $upload->json('data.id');

        Document::query()->whereKey($documentId)->update([
            'documentable_type' => Tenant::class,
            'documentable_id' => 999999,
        ]);

        $this->actingAs($rentalUser)->get("/api/v1/documents/{$documentId}")
            ->assertForbidden();
    }

    private function createTenant(): Tenant
    {
        $building = RentalBuilding::query()->create(['name' => 'Access Test Building']);
        $unit = RentalUnit::query()->create([
            'rental_building_id' => $building->id,
            'house_number' => 'A1',
            'floor' => '1',
            'description' => 'Test unit',
            'monthly_rent' => '10000.00',
        ]);

        return Tenant::query()->create([
            'rental_building_id' => $building->id,
            'rental_unit_id' => $unit->id,
            'name' => 'Access Tenant',
            'phone' => '0700000001',
            'deposit' => '0.00',
            'service_amount' => '0.00',
            'start_date' => '2025-01-01',
        ]);
    }

    private function createClient(): Client
    {
        $building = SaleBuilding::query()->create(['name' => 'Access Sale Building']);
        $unit = SaleUnit::query()->create([
            'sale_building_id' => $building->id,
            'house_number' => 'S1',
            'floor' => '1',
            'description' => 'Sale unit',
            'list_price' => '100000.00',
        ]);

        return Client::query()->create([
            'sale_building_id' => $building->id,
            'sale_unit_id' => $unit->id,
            'name' => 'Access Client',
            'phone' => '0700000002',
            'agreed_sale_price' => '90000.00',
            'deposit' => '0.00',
            'registration_date' => '2025-01-01',
        ]);
    }
}
