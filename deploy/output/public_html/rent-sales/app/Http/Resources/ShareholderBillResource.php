<?php

namespace App\Http\Resources;

use App\Support\MoneyConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ShareholderBill */
class ShareholderBillResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'currency_code' => MoneyConfig::rentalCurrency(),
            'shareholder_id' => $this->shareholder_id,
            'shareholder_name' => $this->whenLoaded('shareholder', fn () => $this->shareholder->name),
            'rental_building_id' => $this->rental_building_id,
            'building_name' => $this->whenLoaded('building', fn () => $this->building->name),
            'amount' => $this->amount,
            'remark' => $this->remark,
            'bill_date' => $this->bill_date?->toDateString(),
        ];
    }
}
