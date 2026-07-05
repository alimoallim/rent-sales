<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShareholderBill extends Model
{
    use LogsActivity;
    use SoftDeletes;

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

    public function activityLabel(): ?string
    {
        return 'Bill '.$this->amount.' — '.$this->bill_date?->format('Y-m-d');
    }
}
