<?php

namespace App\Models;

use App\Enums\ChargeAdjustmentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChargeAdjustment extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'rental_building_id',
        'billing_month',
        'billing_year',
        'charge_type',
        'amount',
        'reason',
        'rent_charge_id',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'charge_type' => ChargeAdjustmentType::class,
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
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

    public function rentCharge(): BelongsTo
    {
        return $this->belongsTo(RentCharge::class);
    }
}
