<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS)
|--------------------------------------------------------------------------
|
| The React SPA runs on a different origin (port) from the API, so the API
| must explicitly allow it. `supports_credentials` is REQUIRED: without it the
| browser refuses to send the Sanctum session cookie, and every authenticated
| request would 401.
|
| Origins are an explicit allow-list — never '*', which is incompatible with
| credentialed requests anyway.
|
*/

$frontend = rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/');

$additional = array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
));

return [

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'login',
        'logout',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_unique(array_merge([$frontend], $additional))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // Exposed so the SPA can read pagination hints and the CSRF cookie name.
    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => true,

];
