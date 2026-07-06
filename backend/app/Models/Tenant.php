<?php

namespace App\Models;

use App\Enums\TenantStatus;
use App\Models\Concerns\HasDocuments;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasDocuments;
    use LogsActivity;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'legacy_id',
        'rental_building_id',
        'rental_unit_id',
        'name',
        'phone',
        'gender',
        'email',
        'passport_or_id',
        'deposit',
        'service_amount',
        'requires_water_metering',
        'requires_electricity_metering',
        'next_of_kin_name',
        'next_of_kin_address',
        'next_of_kin_id',
        'next_of_kin_phone',
        'start_date',
        'status',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deposit' => 'decimal:2',
            'service_amount' => 'decimal:2',
            'requires_water_metering' => 'boolean',
            'requires_electricity_metering' => 'boolean',
            'start_date' => 'date',
            'status' => TenantStatus::class,
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(RentalBuilding::class, 'rental_building_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(RentalUnit::class, 'rental_unit_id');
    }

    public function moveOuts(): HasMany
    {
        return $this->hasMany(TenantMoveOut::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(RentCharge::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RentPayment::class);
    }

    public function waterBills(): HasMany
    {
        return $this->hasMany(TenantWaterBill::class);
    }

    public function electricityBills(): HasMany
    {
        return $this->hasMany(TenantElectricityBill::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function contractDetails(): array
    {
        return [
            'start_date' => $this->start_date?->toDateString(),
            'deposit' => $this->deposit,
            'service_amount' => $this->service_amount,
            'requires_water_metering' => (bool) $this->requires_water_metering,
            'requires_electricity_metering' => (bool) $this->requires_electricity_metering,
        ];
    }
}
