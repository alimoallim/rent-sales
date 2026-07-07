<?php

namespace App\Http\Resources;

use App\Support\MoneyConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\BuildingElectricityBill */
class BuildingElectricityBillResource extends JsonResource
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
            'building_name' => $this->whenLoaded('building', fn () => $this->building->name),
            'billing_month' => $this->billing_month,
            'billing_year' => $this->billing_year,
            'amount' => $this->amount,
            'remark' => $this->remark,
            'billed_at' => $this->billed_at?->toDateString(),
        ];
    }
}
