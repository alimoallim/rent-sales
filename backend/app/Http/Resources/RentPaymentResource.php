<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RentPayment */
class RentPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'tenant_name' => $this->whenLoaded('tenant', fn () => $this->tenant->name),
            'rental_building_id' => $this->rental_building_id,
            'building_name' => $this->whenLoaded('building', fn () => $this->building->name),
            'amount' => $this->amount,
            'discount' => $this->discount,
            'invoice_reference' => $this->invoice_reference,
            'paid_at' => $this->paid_at?->toISOString(),
            'status' => $this->status->value,
            'voided_at' => $this->voided_at?->toISOString(),
        ];
    }
}
