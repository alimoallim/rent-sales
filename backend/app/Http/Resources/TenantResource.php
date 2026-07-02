<?php

namespace App\Http\Resources;

use App\Support\MoneyConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Tenant */
class TenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'currency_code' => MoneyConfig::rentalCurrency(),
            'rental_building_id' => $this->rental_building_id,
            'rental_unit_id' => $this->rental_unit_id,
            'building_name' => $this->whenLoaded('building', fn () => $this->building->name),
            'unit_label' => $this->whenLoaded('unit', fn () => $this->unit->house_number),
            'name' => $this->name,
            'phone' => $this->phone,
            'gender' => $this->gender,
            'email' => $this->email,
            'passport_or_id' => $this->passport_or_id,
            'deposit' => $this->deposit,
            'service_amount' => $this->service_amount,
            'requires_water_metering' => (bool) $this->requires_water_metering,
            'requires_electricity_metering' => (bool) $this->requires_electricity_metering,
            'contract' => $this->contractDetails(),
            'next_of_kin_name' => $this->next_of_kin_name,
            'next_of_kin_address' => $this->next_of_kin_address,
            'next_of_kin_id' => $this->next_of_kin_id,
            'next_of_kin_phone' => $this->next_of_kin_phone,
            'start_date' => $this->start_date?->toDateString(),
            'status' => $this->status->value,
            'balance' => $this->when(isset($this->balance), $this->balance),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
