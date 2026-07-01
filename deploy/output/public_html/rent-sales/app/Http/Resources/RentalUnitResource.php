<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RentalUnit */
class RentalUnitResource extends JsonResource
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
            'house_number' => $this->house_number,
            'floor' => $this->floor,
            'description' => $this->description,
            'monthly_rent' => $this->monthly_rent,
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
