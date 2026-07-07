<?php

namespace App\Http\Requests\Sales;

use App\Rules\UniqueBuildingName;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSaleBuildingRequest extends FormRequest
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
                new UniqueBuildingName(ignoreSaleBuildingId: $this->route('building')?->id),
            ],
        ];
    }
}
