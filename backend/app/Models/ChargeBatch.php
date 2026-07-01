<?php

namespace App\Models;

use App\Enums\ChargeBatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChargeBatch extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'rental_building_id',
        'billing_month',
        'billing_year',
        'status',
        'generated_by',
        'generated_at',
        'locked_by',
        'locked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ChargeBatchStatus::class,
            'generated_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(RentalBuilding::class, 'rental_building_id');
    }

    public function generatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function lockedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChargeBatchItem::class);
    }

    public function isEditable(): bool
    {
        return $this->status !== ChargeBatchStatus::Locked;
    }
}
