<?php

namespace App\Models;

use App\Enums\WaterBillStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TenantWaterBill extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'legacy_id',
        'tenant_id',
        'rental_building_id',
        'billing_month',
        'billing_year',
        'previous_reading',
        'current_reading',
        'consumption',
        'rate',
        'fixed_fee',
        'amount',
        'amount_paid',
        'status',
        'remark',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'fixed_fee' => 'decimal:2',
            'amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'status' => WaterBillStatus::class,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(RentalBuilding::class, 'rental_building_id');
    }

    public function rentCharge(): HasOne
    {
        return $this->hasOne(RentCharge::class);
    }
}
