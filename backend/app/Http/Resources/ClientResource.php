<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Client */
class ClientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_building_id' => $this->sale_building_id,
            'sale_unit_id' => $this->sale_unit_id,
            'building_name' => $this->whenLoaded('building', fn () => $this->building->name),
            'unit_label' => $this->whenLoaded('unit', fn () => $this->unit->house_number),
            'name' => $this->name,
            'phone' => $this->phone,
            'gender' => $this->gender,
            'email' => $this->email,
            'passport_or_id' => $this->passport_or_id,
            'agreed_sale_price' => $this->agreed_sale_price,
            'voucher_number' => $this->voucher_number,
            'deposit' => $this->deposit,
            'next_of_kin_name' => $this->next_of_kin_name,
            'next_of_kin_address' => $this->next_of_kin_address,
            'next_of_kin_id' => $this->next_of_kin_id,
            'next_of_kin_phone' => $this->next_of_kin_phone,
            'registration_date' => $this->registration_date?->toDateString(),
            'status' => $this->status->value,
            'balance' => $this->when(isset($this->balance), $this->balance),
            'payments_count' => $this->whenCounted('payments'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
