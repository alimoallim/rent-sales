<?php

namespace App\Models;

use App\Enums\ChargeBatchItemStatus;
use App\Enums\ChargeBatchItemType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChargeBatchItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'charge_batch_id',
        'tenant_id',
        'charge_type',
        'amount',
        'source_amount',
        'item_status',
        'pending_reason',
        'exclusion_reason',
        'manually_adjusted',
        'adjusted_by',
        'adjusted_at',
        'adjustment_note',
        'approved_by',
        'approved_at',
        'tenant_water_bill_id',
        'tenant_electricity_bill_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'charge_type' => ChargeBatchItemType::class,
            'amount' => 'decimal:2',
            'source_amount' => 'decimal:2',
            'item_status' => ChargeBatchItemStatus::class,
            'manually_adjusted' => 'boolean',
            'adjusted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ChargeBatch::class, 'charge_batch_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function adjustedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function waterBill(): BelongsTo
    {
        return $this->belongsTo(TenantWaterBill::class, 'tenant_water_bill_id');
    }

    public function electricityBill(): BelongsTo
    {
        return $this->belongsTo(TenantElectricityBill::class, 'tenant_electricity_bill_id');
    }

    public function rentCharge(): HasOne
    {
        return $this->hasOne(RentCharge::class);
    }

    public function isPostable(): bool
    {
        return $this->item_status === ChargeBatchItemStatus::Draft
            && $this->amount !== null;
    }
}
