<?php

namespace App\Http\Resources;

use App\Support\MoneyConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PayrollEntry */
class PayrollEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'currency_code' => MoneyConfig::rentalCurrency(),
            'employee_id' => $this->employee_id,
            'employee_name' => $this->whenLoaded('employee', fn () => $this->employee->name),
            'rental_building_id' => $this->rental_building_id,
            'building_name' => $this->whenLoaded('building', fn () => $this->building->name),
            'billing_month' => $this->billing_month,
            'billing_year' => $this->billing_year,
            'salary_amount' => $this->salary_amount,
            'paid_at' => $this->paid_at?->toISOString(),
        ];
    }
}
