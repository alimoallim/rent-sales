<?php

namespace Tests\Feature\Admin;

use App\Models\RentalBuilding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecycleBinTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_can_list_recycle_bin_types(): void
    {
        $this->actingAs($this->admin())
            ->getJson('/api/v1/admin/recycle-bin/types')
            ->assertOk()
            ->assertJsonFragment(['key' => 'rental_buildings', 'label' => 'Rental building']);
    }

    public function test_admin_can_list_deleted_buildings(): void
    {
        $building = RentalBuilding::query()->create(['name' => 'Deleted Tower']);
        $building->delete();

        $this->actingAs($this->admin())
            ->getJson('/api/v1/admin/recycle-bin?type=rental_buildings')
            ->assertOk()
            ->assertJsonFragment(['label' => 'Deleted Tower']);
    }

    public function test_admin_can_restore_deleted_building(): void
    {
        $building = RentalBuilding::query()->create(['name' => 'Deleted Tower']);
        $building->delete();

        $this->actingAs($this->admin())
            ->postJson("/api/v1/admin/recycle-bin/rental_buildings/{$building->id}/restore")
            ->assertOk();

        $this->assertDatabaseHas('rental_buildings', [
            'id' => $building->id,
            'name' => 'Deleted Tower',
            'deleted_at' => null,
        ]);
    }

    public function test_non_admin_cannot_access_recycle_bin(): void
    {
        $user = User::factory()->rental()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/admin/recycle-bin/types')
            ->assertForbidden();
    }
}
