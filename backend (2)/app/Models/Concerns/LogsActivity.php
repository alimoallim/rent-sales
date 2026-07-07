<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Records created/updated/deleted/restored events into the activity_logs
 * table with the acting user and a before/after diff for updates.
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(fn ($model) => $model->recordActivity('created'));
        static::updated(fn ($model) => $model->recordActivity('updated'));
        static::deleted(fn ($model) => $model->recordActivity('deleted'));

        if (method_exists(static::class, 'restored')) {
            static::restored(fn ($model) => $model->recordActivity('restored'));
        }
    }

    protected function recordActivity(string $action): void
    {
        try {
            $changes = null;

            if ($action === 'updated') {
                $changes = $this->activityChanges();

                // Skip no-op updates and the internal save performed by restore().
                if ($changes === null) {
                    return;
                }
            }

            ActivityLog::query()->create([
                'user_id' => Auth::id(),
                'action' => $action,
                'subject_type' => $this->getMorphClass(),
                'subject_id' => $this->getKey(),
                'subject_label' => $this->activityLabel(),
                'changes' => $changes,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Activity logging must never break the underlying operation.
        }
    }

    /**
     * @return array{before: array<string, mixed>, after: array<string, mixed>}|null
     */
    protected function activityChanges(): ?array
    {
        $ignored = array_merge(
            ['created_at', 'updated_at', 'deleted_at', 'remember_token', 'password'],
            $this->activityIgnoredAttributes(),
        );

        $after = collect($this->getChanges())->except($ignored);

        if ($after->isEmpty()) {
            return null;
        }

        $before = $after->mapWithKeys(fn ($value, $key) => [$key => $this->getOriginal($key)]);

        return [
            'before' => $before->all(),
            'after' => $after->all(),
        ];
    }

    /**
     * @return list<string>
     */
    protected function activityIgnoredAttributes(): array
    {
        return [];
    }

    public function activityLabel(): ?string
    {
        foreach (['name', 'house_number', 'username'] as $attribute) {
            $value = $this->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
