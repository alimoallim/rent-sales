<?php

namespace App\Http\Resources;

use App\Services\Rental\TenantBalanceCalculator;
use App\Support\MoneyConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RentCharge */
class RentChargeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'currency_code' => MoneyConfig::rentalCurrency(),
            'tenant_id' => $this->tenant_id,
            'tenant_name' => $this->whenLoaded('tenant', fn () => $this->tenant->name),
            'rental_building_id' => $this->rental_building_id,
            'building_name' => $this->whenLoaded('building', fn () => $this->building->name),
            'unit_label' => $this->whenLoaded('unit', fn () => $this->unit->house_number),
            'billing_month' => $this->billing_month,
            'billing_year' => $this->billing_year,
            'rent_amount' => $this->rent_amount,
            'service_amount' => $this->service_amount,
            'total_amount' => $this->total_amount,
            'purpose' => $this->purpose,
            'charge_type' => $this->purpose,
            'is_editable' => $this->purpose === 'Rent + service',
            'tenant_water_bill_id' => $this->tenant_water_bill_id,
            'tenant_electricity_bill_id' => $this->tenant_electricity_bill_id,
            'charged_at' => $this->charged_at?->toISOString(),
        ];
    }
}
