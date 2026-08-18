<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Force HTTPS
    |--------------------------------------------------------------------------
    |
    | When this configuration value is set to "true", Octane will inform the
    | framework that all absolute links must be generated using the HTTPS
    | protocol. Otherwise your links may be generated using plain HTTP.
    |
    */

    'https' => env('OCTANE_HTTPS', true),

    /*
    |--------------------------------------------------------------------------
    | Mercure Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for the built-in Mercure hub running in FrankenPHP.
    |
    */

    'mercure' => [
        'publisher_jwt' => env('MERCURE_JWT_SECRET'),
        'subscriber_jwt' => env('MERCURE_JWT_SECRET'),
        'subscriptions' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Caddy Environment Variables
    |--------------------------------------------------------------------------
    |
    | Environment variables passed directly to the FrankenPHP/Caddy process.
    |
    */

    'caddy' => [
        'env' => [
            'MERCURE_JWT_SECRET' => env('MERCURE_JWT_SECRET'),
        ],
    ],
];