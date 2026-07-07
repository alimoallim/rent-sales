<?php

namespace App\Models;

use App\Enums\SalesPaymentStatus;
use App\Models\Concerns\HasSalesCurrency;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesPayment extends Model
{
    use HasSalesCurrency;
    use LogsActivity;

    public function activityLabel(): ?string
    {
        return 'Payment of '.$this->amount;
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'legacy_id',
        'currency_code',
        'client_id',
        'sale_building_id',
        'amount',
        'discount',
        'invoice_reference',
        'bank',
        'remark',
        'paid_at',
    ];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function createActive(array $attributes, int $createdBy): self
    {
        $payment = new static($attributes);
        $payment->forceFill([
            'status' => SalesPaymentStatus::Active,
            'created_by' => $createdBy,
        ]);
        $payment->save();

        return $payment;
    }

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

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
