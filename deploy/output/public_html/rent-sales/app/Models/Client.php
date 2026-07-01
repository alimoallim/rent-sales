<?php

namespace App\Models;

use App\Enums\ClientStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'legacy_id',
        'sale_building_id',
        'sale_unit_id',
        'name',
        'phone',
        'gender',
        'email',
        'passport_or_id',
        'agreed_sale_price',
        'voucher_number',
        'deposit',
        'next_of_kin_name',
        'next_of_kin_address',
        'next_of_kin_id',
        'next_of_kin_phone',
        'registration_date',
        'status',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'agreed_sale_price' => 'decimal:2',
            'deposit' => 'decimal:2',
            'registration_date' => 'date',
            'status' => ClientStatus::class,
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(SaleBuilding::class, 'sale_building_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(SaleUnit::class, 'sale_unit_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalesPayment::class);
    }
}
