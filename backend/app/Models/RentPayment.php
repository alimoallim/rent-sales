<?php

namespace App\Models;

use App\Enums\RentPaymentStatus;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentPayment extends Model
{
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
        'tenant_id',
        'rental_building_id',
        'amount',
        'discount',
        'invoice_reference',
        'paid_at',
    ];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function createActive(array $attributes, int $createdBy): self
    {
        $payment = new static($attributes);
        $payment->forceFill([
            'status' => RentPaymentStatus::Active,
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
            'status' => RentPaymentStatus::class,
            'voided_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(RentalBuilding::class, 'rental_building_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
