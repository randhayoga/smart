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

    'https' => env('OCTANE_HTTPS', false),

    /*
    |--------------------------------------------------------------------------
    | Mercure Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for the built-in Mercure hub running in FrankenPHP.
    |
    */

    'mercure' => env('MERCURE_JWT_SECRET') ? [
        'publisher_jwt' => env('MERCURE_JWT_SECRET'),
        'subscriber_jwt' => env('MERCURE_JWT_SECRET'),
        'subscriptions' => true,
    ] : false,

    /*
    |--------------------------------------------------------------------------
    | Caddy Environment Variables
    |--------------------------------------------------------------------------
    |
    | Environment variables passed directly to the FrankenPHP/Caddy process.
    |
    */

    'caddy' => [
        'env' => array_filter([
            'MERCURE_JWT_SECRET' => env('MERCURE_JWT_SECRET'),
        ]),
    ],
];