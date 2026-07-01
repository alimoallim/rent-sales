<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShareholderBill extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'legacy_id',
        'shareholder_id',
        'rental_building_id',
        'amount',
        'remark',
        'bill_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'bill_date' => 'date',
        ];
    }

    public function shareholder(): BelongsTo
    {
        return $this->belongsTo(Shareholder::class);
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(RentalBuilding::class, 'rental_building_id');
    }
}
