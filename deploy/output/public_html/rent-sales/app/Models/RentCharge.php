<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentCharge extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'legacy_id',
        'tenant_id',
        'rental_unit_id',
        'rental_building_id',
        'billing_month',
        'billing_year',
        'rent_amount',
        'service_amount',
        'total_amount',
        'purpose',
        'tenant_water_bill_id',
        'tenant_electricity_bill_id',
        'charge_batch_item_id',
        'charged_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rent_amount' => 'decimal:2',
            'service_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'charged_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(RentalUnit::class, 'rental_unit_id');
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(RentalBuilding::class, 'rental_building_id');
    }

    public function waterBill(): BelongsTo
    {
        return $this->belongsTo(TenantWaterBill::class, 'tenant_water_bill_id');
    }

    public function electricityBill(): BelongsTo
    {
        return $this->belongsTo(TenantElectricityBill::class, 'tenant_electricity_bill_id');
    }

    public function batchItem(): BelongsTo
    {
        return $this->belongsTo(ChargeBatchItem::class, 'charge_batch_item_id');
    }

    public function recalculateTotal(): void
    {
        $this->total_amount = bcadd((string) $this->rent_amount, (string) $this->service_amount, 2);
    }
}
