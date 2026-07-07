<?php

namespace App\Http\Requests\Rental;

use App\Rules\UniqueBuildingName;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRentalBuildingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:200',
                new UniqueBuildingName(ignoreRentalBuildingId: $this->route('building')?->id),
            ],
        ];
    }
}
