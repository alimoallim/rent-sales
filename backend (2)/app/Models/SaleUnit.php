<?php

namespace App\Models;

use App\Enums\ClientStatus;
use App\Enums\SaleUnitStatus;
use App\Models\Concerns\HasSalesCurrency;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SaleUnit extends Model
{
    use HasSalesCurrency;
    use LogsActivity;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'legacy_id',
        'currency_code',
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

    public function saleClient(): HasOne
    {
        return $this->hasOne(Client::class, 'sale_unit_id')
            ->where('status', ClientStatus::Active)
            ->latestOfMany();
    }
}
