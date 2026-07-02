<?php

namespace App\Models;

use App\Enums\RentalUnitStatus;
use App\Enums\TenantStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RentalUnit extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'legacy_id',
        'rental_building_id',
        'house_number',
        'floor',
        'description',
        'monthly_rent',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_rent' => 'decimal:2',
            'status' => RentalUnitStatus::class,
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(RentalBuilding::class, 'rental_building_id');
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function activeTenant(): HasOne
    {
        return $this->hasOne(Tenant::class, 'rental_unit_id')
            ->where('status', TenantStatus::Active);
    }
}
