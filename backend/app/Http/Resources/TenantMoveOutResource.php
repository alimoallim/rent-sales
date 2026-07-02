<?php

namespace App\Http\Resources;

use App\Support\MoneyConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TenantMoveOut */
class TenantMoveOutResource extends JsonResource
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
            'refund_amount' => $this->refund_amount,
            'reason' => $this->reason,
            'moved_out_at' => $this->moved_out_at?->toDateString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
