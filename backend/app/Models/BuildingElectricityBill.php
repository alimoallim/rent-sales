<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuildingElectricityBill extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'legacy_id',
        'rental_building_id',
        'billing_month',
        'billing_year',
        'amount',
        'remark',
        'billed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'billed_at' => 'date',
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(RentalBuilding::class, 'rental_building_id');
    }
}
