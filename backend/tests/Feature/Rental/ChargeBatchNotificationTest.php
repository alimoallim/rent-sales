<?php

namespace Tests\Feature\Rental;

use App\Mail\PendingChargeBatchesMail;
use App\Models\RentalBuilding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ChargeBatchNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_command_sends_notification_when_configured(): void
    {
        Mail::fake();
        config(['notifications.admin_emails' => ['ops@example.com']]);

        User::factory()->admin()->create(['is_manager' => true]);
        RentalBuilding::query()->create(['name' => 'Batch Tower']);

        $this->artisan('rental:generate-charge-batches --notify')
            ->assertSuccessful();

        Mail::assertSent(PendingChargeBatchesMail::class, function (PendingChargeBatchesMail $mail) {
            return $mail->hasTo('ops@example.com');
        });
    }
}
