<?php

namespace App\Http\Requests;

use App\Enums\DocumentKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
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
            'kind' => ['required', Rule::enum(DocumentKind::class)],
            'file' => [
                'required',
                'file',
                'max:5120',
                'mimes:jpeg,jpg,png,webp,pdf',
            ],
        ];
    }
}
