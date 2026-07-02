<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Module currencies
    |--------------------------------------------------------------------------
    |
    | Rental and sales operate as independent ledgers with fixed ISO 4217
    | currencies. Amounts are never converted across modules in core flows.
    |
    */

    'rental' => [
        'currency' => env('RENTAL_CURRENCY', 'KES'),
        'locale' => env('RENTAL_MONEY_LOCALE', 'en-KE'),
    ],

    'sales' => [
        'currency' => env('SALES_CURRENCY', 'USD'),
        'locale' => env('SALES_MONEY_LOCALE', 'en-US'),
    ],

];
