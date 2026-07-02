<?php

namespace App\Models;

use App\Models\Concerns\HasSalesCurrency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesExpense extends Model
{
    use HasSalesCurrency;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'legacy_id',
        'currency_code',
        'sale_building_id',
        'name',
        'amount',
        'description',
        'expense_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'datetime',
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(SaleBuilding::class, 'sale_building_id');
    }
}
