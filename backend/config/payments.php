<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment duplicate window
    |--------------------------------------------------------------------------
    |
    | Identical active payments submitted within this window are treated as
    | double-submits and return the original record instead of creating again.
    |
    */

    'dedup_window_seconds' => (int) env('PAYMENT_DEDUP_WINDOW_SECONDS', 60),

    'rental_receipt_prefix' => env('RENTAL_RECEIPT_PREFIX', 'RCP'),

    'sales_receipt_prefix' => env('SALES_RECEIPT_PREFIX', 'SRCP'),

];
