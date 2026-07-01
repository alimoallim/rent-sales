<?php

namespace App\Models;

use App\Enums\SalesPaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesPayment extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'legacy_id',
        'client_id',
        'sale_building_id',
        'amount',
        'discount',
        'invoice_reference',
        'bank',
        'remark',
        'paid_at',
        'status',
        'cancelled_at',
        'cancelled_by',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'discount' => 'decimal:2',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'status' => SalesPaymentStatus::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(SaleBuilding::class, 'sale_building_id');
    }
}
