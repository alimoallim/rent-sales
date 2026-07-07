<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollEntry extends Model
{
    use LogsActivity;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'legacy_id',
        'employee_id',
        'rental_building_id',
        'billing_month',
        'billing_year',
        'salary_amount',
        'paid_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'salary_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(RentalBuilding::class, 'rental_building_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activityLabel(): ?string
    {
        $employee = $this->relationLoaded('employee') ? $this->employee : $this->employee()->first();

        if ($employee?->name) {
            return "{$employee->name} — {$this->billing_month}/{$this->billing_year}";
        }

        return "Payroll {$this->billing_month}/{$this->billing_year}";
    }
}
