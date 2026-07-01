<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SalesPayment */
class SalesPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'client_name' => $this->whenLoaded('client', fn () => $this->client->name),
            'unit_label' => $this->when(
                $this->relationLoaded('client') && $this->client?->relationLoaded('unit'),
                fn () => $this->client->unit?->house_number,
            ),
            'sale_building_id' => $this->sale_building_id,
            'building_name' => $this->whenLoaded('building', fn () => $this->building->name),
            'amount' => $this->amount,
            'discount' => $this->discount,
            'invoice_reference' => $this->invoice_reference,
            'bank' => $this->bank,
            'remark' => $this->remark,
            'paid_at' => $this->paid_at?->toISOString(),
            'status' => $this->status->value,
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
