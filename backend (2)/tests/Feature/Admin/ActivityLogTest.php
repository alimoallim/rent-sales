<?php

namespace Tests\Feature\Admin;

use App\Models\RentalBuilding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_activity_log(): void
    {
        $admin = User::factory()->admin()->create();
        $building = RentalBuilding::query()->create(['name' => 'Audit Tower']);

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/activity-log')
            ->assertOk()
            ->assertJsonFragment(['action' => 'created', 'subject_label' => 'Audit Tower']);
    }

    public function test_non_admin_cannot_view_activity_log(): void
    {
        $user = User::factory()->rental()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/admin/activity-log')
            ->assertForbidden();
    }

    public function test_delete_action_is_logged(): void
    {
        $admin = User::factory()->admin()->create();
        $building = RentalBuilding::query()->create(['name' => 'Gone Tower']);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/rental/buildings/{$building->id}")
            ->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'deleted',
            'subject_label' => 'Gone Tower',
            'user_id' => $admin->id,
        ]);
    }

    public function test_restore_action_is_logged(): void
    {
        $admin = User::factory()->admin()->create();
        $building = RentalBuilding::query()->create(['name' => 'Restore Tower']);
        $building->delete();

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/recycle-bin/rental_buildings/{$building->id}/restore")
            ->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'restored',
            'subject_label' => 'Restore Tower',
        ]);
    }
}
