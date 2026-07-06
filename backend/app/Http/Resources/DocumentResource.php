<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Document */
class DocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'url' => url("/api/v1/documents/{$this->id}"),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
