<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Rasulmart shared hosting layout
|--------------------------------------------------------------------------
|
| public_html/app/index.php  →  this file (web root for /app)
| public_html/rent-sales/    →  Laravel application root
|
| Adjust $laravelRoot if your folder name differs.
*/
$laravelRoot = dirname(__DIR__).'/rent-sales';

if (file_exists($maintenance = $laravelRoot.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $laravelRoot.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $laravelRoot.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
