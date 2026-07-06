<?php

namespace App\Services;

use App\Enums\DocumentKind;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DocumentService
{
    public function store(Model $documentable, DocumentKind $kind, UploadedFile $file, User $user): Document
    {
        $existing = $documentable->documents()->where('kind', $kind)->first();

        if ($existing instanceof Document) {
            $this->delete($existing);
        }

        $disk = (string) config('filesystems.default', 'local');
        $folder = sprintf(
            'documents/%s/%s',
            str_replace('\\', '/', $documentable->getMorphClass()),
            $documentable->getKey(),
        );

        $path = $file->store($folder, $disk);

        return $documentable->documents()->create([
            'kind' => $kind,
            'disk' => $disk,
            'path' => $path,
            'mime_type' => (string) $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by' => $user->id,
        ]);
    }

    public function delete(Document $document): void
    {
        if (Storage::disk($document->disk)->exists($document->path)) {
            Storage::disk($document->disk)->delete($document->path);
        }

        $document->delete();
    }
}
