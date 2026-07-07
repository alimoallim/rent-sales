<?php

use App\Support\Spa;
use Illuminate\Support\Facades\Route;

Route::get('/{any?}', function () {
    $spa = Spa::indexPath();

    if ($spa === null) {
        abort(503, 'Frontend build missing. Copy index.html to public_html/app/ or set SPA_INDEX_PATH in .env.');
    }

    return response()->file($spa);
})->where('any', '^(?!api|sanctum|up|storage).*$');
