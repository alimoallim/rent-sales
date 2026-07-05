<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin notification recipients
    |--------------------------------------------------------------------------
    |
    | Comma-separated list in ADMIN_NOTIFICATION_EMAILS (.env). These addresses
    | receive operational alerts such as new draft charge batches.
    |
    */

    'admin_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => trim($email),
        explode(',', (string) env('ADMIN_NOTIFICATION_EMAILS', '')),
    ))),

];
