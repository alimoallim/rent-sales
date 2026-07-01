<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SalesExpense */
class SalesExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_building_id' => $this->sale_building_id,
            'building_name' => $this->whenLoaded('building', fn () => $this->building->name),
            'name' => $this->name,
            'amount' => $this->amount,
            'description' => $this->description,
            'expense_date' => $this->expense_date?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
