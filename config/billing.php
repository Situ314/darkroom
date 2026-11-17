<?php

return [

    /*
    | Users pay Pixelforge's cost times this multiplier. Kept as a string so
    | it goes straight into exact decimal math, never through a float.
    */
    'markup' => env('BILLING_MARKUP', '1.25'),

    /*
    | Credit (USD) granted to every new account so people can try the app
    | without going through "Buy credits" first.
    */
    'welcome_credit' => env('BILLING_WELCOME_CREDIT', '1.00'),

    /*
    | Fake "Buy credits" packs, in USD.
    */
    'packs' => ['5.00', '10.00', '25.00'],

    /*
    | Emails that can see the admin report, comma separated. Handy on hosts
    | without shell access (otherwise: php artisan app:make-admin {email}).
    */
    'admin_emails' => array_filter(array_map(
        fn ($email) => strtolower(trim($email)),
        explode(',', (string) env('ADMIN_EMAILS', '')),
    )),

];
