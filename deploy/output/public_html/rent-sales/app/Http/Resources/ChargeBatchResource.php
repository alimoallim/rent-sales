<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ChargeBatch */
class ChargeBatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rental_building_id' => $this->rental_building_id,
            'building_name' => $this->building?->name,
            'billing_month' => $this->billing_month,
            'billing_year' => $this->billing_year,
            'period_label' => sprintf('%s %d', date('F', mktime(0, 0, 0, $this->billing_month, 1)), $this->billing_year),
            'status' => $this->status->value,
            'is_locked' => ! $this->isEditable(),
            'generated_by_name' => $this->generatedByUser?->name,
            'generated_at' => $this->generated_at?->toISOString(),
            'locked_by_name' => $this->lockedByUser?->name,
            'locked_at' => $this->locked_at?->toISOString(),
            'tenant_groups' => $this->when(isset($this->tenant_groups), $this->tenant_groups),
            'items' => ChargeBatchItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
