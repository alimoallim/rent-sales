<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

Route::get('/{any?}', function () {
    $spa = public_path('index.html');

    if (! File::exists($spa)) {
        abort(503, 'Frontend build missing. Run npm run build and copy dist/ into public/.');
    }

    return response()->file($spa);
})->where('any', '^(?!api|sanctum|up|storage).*$');
