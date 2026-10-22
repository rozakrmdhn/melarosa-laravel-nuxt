<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_unique(array_filter(
        env('APP_ENV') === 'production'
            ? [
                env('FRONTEND_URL'),
                'https://dev-melarosa.saggaserv.my.id',
            ]
            : [
                env('FRONTEND_URL', 'http://localhost:4000'),
                'https://dev-melarosa.saggaserv.my.id',
                'http://localhost:3000',
                'http://localhost:3001',
                'http://localhost:3002',
                'http://localhost:4000',
                'http://localhost:5000',
                'http://127.0.0.1:3000',
                'http://127.0.0.1:3001',
                'http://127.0.0.1:3002',
                'http://127.0.0.1:4000',
                'http://127.0.0.1:5000',
            ]
    ))),

    'allowed_origins_patterns' => array_values(array_filter([
        '#^https?://.*\.saggaserv\.my\.id$#',
        env('APP_ENV') !== 'production' ? '#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#' : null,
    ])),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 1440,

    'supports_credentials' => true,

];
