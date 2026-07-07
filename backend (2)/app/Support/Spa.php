<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class Spa
{
    public static function indexPath(): ?string
    {
        $candidates = array_filter([
            env('SPA_INDEX_PATH'),
            public_path('index.html'),
            base_path('../app/index.html'),
        ]);

        foreach ($candidates as $path) {
            if (File::isFile($path)) {
                return $path;
            }
        }

        return null;
    }
}
