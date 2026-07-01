<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ChargeBatchItem */
class ChargeBatchItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'charge_type' => $this->charge_type->value,
            'amount' => $this->amount !== null ? number_format((float) $this->amount, 2, '.', '') : null,
            'source_amount' => $this->source_amount !== null ? number_format((float) $this->source_amount, 2, '.', '') : null,
            'item_status' => $this->item_status->value,
            'pending_reason' => $this->pending_reason,
            'exclusion_reason' => $this->exclusion_reason,
            'manually_adjusted' => $this->manually_adjusted,
            'adjustment_note' => $this->adjustment_note,
            'adjusted_by_name' => $this->adjustedByUser?->name,
            'adjusted_at' => $this->adjusted_at?->toISOString(),
            'approved_by_name' => $this->approvedByUser?->name,
            'approved_at' => $this->approved_at?->toISOString(),
            'tenant_water_bill_id' => $this->tenant_water_bill_id,
            'tenant_electricity_bill_id' => $this->tenant_electricity_bill_id,
        ];
    }
}
