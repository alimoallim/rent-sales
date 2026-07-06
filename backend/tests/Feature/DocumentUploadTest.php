<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\RentalBuilding;
use App\Models\RentalUnit;
use App\Models\SaleBuilding;
use App\Models\SaleUnit;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function test_rental_user_can_upload_and_download_tenant_document(): void
    {
        $user = User::query()->where('role', UserRole::Rental)->firstOrFail();
        $tenant = $this->createTenant();

        $file = UploadedFile::fake()->create('tenant-photo.jpg', 100, 'image/jpeg');

        $upload = $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post("/api/v1/rental/tenants/{$tenant->id}/documents", [
            'kind' => 'photo',
            'file' => $file,
        ]);

        $upload->assertCreated()
            ->assertJsonPath('data.kind', 'photo');

        $documentId = $upload->json('data.id');

        $this->actingAs($user)->get("/api/v1/documents/{$documentId}")
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');

        $this->assertDatabaseCount('documents', 1);
    }

    #[Test]
    public function test_uploading_same_kind_replaces_existing_document(): void
    {
        $user = User::query()->where('role', UserRole::Rental)->firstOrFail();
        $tenant = $this->createTenant();

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post("/api/v1/rental/tenants/{$tenant->id}/documents", [
            'kind' => 'photo',
            'file' => UploadedFile::fake()->create('first.jpg', 100, 'image/jpeg'),
        ])->assertCreated();

        $second = $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post("/api/v1/rental/tenants/{$tenant->id}/documents", [
            'kind' => 'photo',
            'file' => UploadedFile::fake()->create('second.jpg', 100, 'image/jpeg'),
        ]);

        $second->assertCreated();
        $this->assertDatabaseCount('documents', 1);
    }

    #[Test]
    public function test_sales_user_can_upload_client_signature(): void
    {
        $user = User::query()->where('role', UserRole::Sales)->firstOrFail();
        $client = $this->createClient();

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post("/api/v1/sales/clients/{$client->id}/documents", [
            'kind' => 'signature',
            'file' => UploadedFile::fake()->create('signature.png', 100, 'image/png'),
        ])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'signature');
    }

    #[Test]
    public function test_sales_user_cannot_upload_tenant_document(): void
    {
        $user = User::query()->where('role', UserRole::Sales)->firstOrFail();
        $tenant = $this->createTenant();

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post("/api/v1/rental/tenants/{$tenant->id}/documents", [
            'kind' => 'photo',
            'file' => UploadedFile::fake()->create('blocked.jpg', 100, 'image/jpeg'),
        ])->assertForbidden();
    }

    #[Test]
    public function test_user_can_delete_document(): void
    {
        $user = User::query()->where('role', UserRole::Rental)->firstOrFail();
        $tenant = $this->createTenant();

        $upload = $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post("/api/v1/rental/tenants/{$tenant->id}/documents", [
            'kind' => 'id_document',
            'file' => UploadedFile::fake()->create('id.pdf', 100, 'application/pdf'),
        ]);

        $documentId = $upload->json('data.id');

        $this->actingAs($user)->delete("/api/v1/documents/{$documentId}")
            ->assertOk();

        $this->assertDatabaseMissing('documents', ['id' => $documentId]);
    }

    #[Test]
    public function test_invalid_file_type_is_rejected(): void
    {
        $user = User::query()->where('role', UserRole::Rental)->firstOrFail();
        $tenant = $this->createTenant();

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post("/api/v1/rental/tenants/{$tenant->id}/documents", [
            'kind' => 'photo',
            'file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ])->assertUnprocessable();
    }

    private function createTenant(): Tenant
    {
        $building = RentalBuilding::query()->create(['name' => 'Doc Test Building']);
        $unit = RentalUnit::query()->create([
            'rental_building_id' => $building->id,
            'house_number' => 'D1',
            'floor' => '1',
            'description' => 'Test unit',
            'monthly_rent' => '10000.00',
        ]);

        return Tenant::query()->create([
            'rental_building_id' => $building->id,
            'rental_unit_id' => $unit->id,
            'name' => 'Doc Tenant',
            'phone' => '0700000001',
            'deposit' => '0.00',
            'service_amount' => '0.00',
            'start_date' => '2025-01-01',
        ]);
    }

    private function createClient(): Client
    {
        $building = SaleBuilding::query()->create(['name' => 'Doc Sale Building']);
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
            'name' => 'Doc Client',
            'phone' => '0700000002',
            'agreed_sale_price' => '90000.00',
            'deposit' => '0.00',
            'registration_date' => '2025-01-01',
        ]);
    }
}
