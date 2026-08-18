<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mercure Hub URL
    |--------------------------------------------------------------------------
    |
    | The URL used internally by the backend (PHP/Laravel) to publish updates
    | to the Mercure hub running inside FrankenPHP.
    |
    */

    'url' => env('MERCURE_URL', 'https://localhost/.well-known/mercure'),

    /*
    |--------------------------------------------------------------------------
    | Public Mercure Hub URL
    |--------------------------------------------------------------------------
    |
    | The public URL used by frontend clients (browsers / mobile apps)
    | to establish Server-Sent Events (SSE) connections.
    |
    */

    'public_url' => env('MERCURE_PUBLIC_URL', 'https://localhost/.well-known/mercure'),

    /*
    |--------------------------------------------------------------------------
    | Mercure JWT Keys
    |--------------------------------------------------------------------------
    |
    | Secret keys used to sign and verify dynamic JWTs for publishers and
    | subscribers. In development, a shared secret is often used.
    |
    */

    'jwt_secret' => env('MERCURE_JWT_SECRET', env('APP_KEY')),

    'publisher_key' => env('MERCURE_PUBLISHER_JWT_KEY', env('MERCURE_JWT_SECRET', env('APP_KEY'))),

    'subscriber_key' => env('MERCURE_SUBSCRIBER_JWT_KEY', env('MERCURE_JWT_SECRET', env('APP_KEY'))),

    /*
    |--------------------------------------------------------------------------
    | Token Expiration Times (in seconds)
    |--------------------------------------------------------------------------
    |
    | Dynamic JWT expiration settings. Publishers need short-lived tokens (e.g. 5 min)
    | while subscriber tokens typically live for 1 hour or matching session duration.
    |
    */

    'publisher_token_lifetime' => (int) env('MERCURE_PUBLISHER_TOKEN_LIFETIME', 300),

    'subscriber_token_lifetime' => (int) env('MERCURE_SUBSCRIBER_TOKEN_LIFETIME', 3600),

];
