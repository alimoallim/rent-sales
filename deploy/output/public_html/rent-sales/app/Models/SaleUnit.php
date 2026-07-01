<?php

namespace App\Models;

use App\Enums\SaleUnitStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleUnit extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'legacy_id',
        'sale_building_id',
        'house_number',
        'floor',
        'description',
        'list_price',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'list_price' => 'decimal:2',
            'status' => SaleUnitStatus::class,
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(SaleBuilding::class, 'sale_building_id');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }
}
