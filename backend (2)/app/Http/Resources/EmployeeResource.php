<?php

namespace App\Http\Resources;

use App\Support\MoneyConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Employee */
class EmployeeResource extends JsonResource
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
            'building_name' => $this->whenLoaded('building', fn () => $this->building?->name),
            'name' => $this->name,
            'address' => $this->address,
            'salary' => $this->salary,
            'phone' => $this->phone,
            'position' => $this->position,
            'status' => $this->status->value,
        ];
    }
}
