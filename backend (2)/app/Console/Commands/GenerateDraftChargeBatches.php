<?php

namespace App\Console\Commands;

use App\Mail\PendingChargeBatchesMail;
use App\Models\RentalBuilding;
use App\Models\User;
use App\Services\Rental\ChargeBatchService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class GenerateDraftChargeBatches extends Command
{
    protected $signature = 'rental:generate-charge-batches {--month=} {--year=} {--notify}';

    protected $description = 'Generate draft monthly charge batches for all buildings when none exist for the period';

    public function handle(ChargeBatchService $chargeBatchService): int
    {
        $now = Carbon::now();
        $month = (int) ($this->option('month') ?: $now->month);
        $year = (int) ($this->option('year') ?: $now->year);

        $systemUser = User::query()->where('is_manager', true)->orderBy('id')->first()
            ?? User::query()->orderBy('id')->first();

        if ($systemUser === null) {
            $this->error('No users found to attribute batch generation.');

            return self::FAILURE;
        }

        $created = 0;
        $skipped = 0;

        RentalBuilding::query()->orderBy('id')->each(function (RentalBuilding $building) use ($chargeBatchService, $month, $year, $systemUser, &$created, &$skipped): void {
            try {
                $chargeBatchService->generateDraft($building->id, $month, $year, $systemUser);
                $created++;
                $this->line("Created draft batch for {$building->name} ({$month}/{$year}).");
            } catch (ValidationException) {
                $skipped++;
            }
        });

        $pendingCount = $chargeBatchService->pendingBatchCount();

        $this->info("Done. Created {$created}, skipped {$skipped} existing batch(es). Pending total: {$pendingCount}.");

        $shouldNotify = $this->option('notify') || $this->shouldNotifyByDefault();
        $recipients = config('notifications.admin_emails', []);

        if ($shouldNotify && $recipients !== [] && ($created > 0 || $pendingCount > 0)) {
            Mail::to($recipients)->send(new PendingChargeBatchesMail($created, $pendingCount, $month, $year));
            $this->info('Notification email sent to: '.implode(', ', $recipients));
        }

        return self::SUCCESS;
    }

    private function shouldNotifyByDefault(): bool
    {
        return config('notifications.admin_emails') !== [];
    }
}
