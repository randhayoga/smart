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

    'mercure' => env('MERCURE_JWT_SECRET') ? (static function (): array {
        $issuer = rtrim((string) env('MERCURE_ISSUER', env('APP_URL', 'http://localhost')), '/');
        $publicUrl = (string) env('MERCURE_PUBLIC_URL', 'http://localhost/.well-known/mercure');
        $resourceIdentifier = str_starts_with($publicUrl, 'http://') || str_starts_with($publicUrl, 'https://')
            ? $publicUrl
            : $issuer . '/' . ltrim($publicUrl, '/');
        $publisherKey = (string) env('MERCURE_PUBLISHER_JWT_KEY', env('MERCURE_JWT_SECRET'));
        $subscriberKey = (string) env('MERCURE_SUBSCRIBER_JWT_KEY', env('MERCURE_JWT_SECRET'));

        return [
            'resource_identifier' => $resourceIdentifier,
            'cookie_name' => (string) env('MERCURE_COOKIE_NAME', 'mercure_access_token'),
            'issuer' => sprintf(
                "%s {\n\t\t\t\tpublisher {\n\t\t\t\t\tjwt %s\n\t\t\t\t}\n\t\t\t\tsubscriber {\n\t\t\t\t\tjwt %s\n\t\t\t\t}\n\t\t\t}",
                $issuer,
                $publisherKey,
                $subscriberKey,
            ),
            'subscriptions' => true,
        ];
    })() : false,

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