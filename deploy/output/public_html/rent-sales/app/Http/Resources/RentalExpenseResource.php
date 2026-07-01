<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RentalExpense */
class RentalExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rental_building_id' => $this->rental_building_id,
            'building_name' => $this->whenLoaded('building', fn () => $this->building->name),
            'name' => $this->name,
            'amount' => $this->amount,
            'description' => $this->description,
            'expense_date' => $this->expense_date?->toISOString(),
        ];
    }
}
