<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'OEMS'),
    'environment' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => rtrim((string) env('APP_URL', 'http://localhost:8000'), '/'),
    'timezone' => env('APP_TIMEZONE', 'Asia/Dhaka'),
    'session_name' => env('SESSION_NAME', 'OEMS_SESSION'),
    'remember_cookie' => env('REMEMBER_COOKIE', 'OEMS_REMEMBER'),
    'mail' => [
        'host' => env('MAIL_HOST', 'localhost'),
        'port' => (int) env('MAIL_PORT', 2525),
        'username' => env('MAIL_USERNAME', ''),
        'password' => env('MAIL_PASSWORD', ''),
        'encryption' => env('MAIL_ENCRYPTION', 'tls'),
        'from_address' => env('MAIL_FROM_ADDRESS', 'no-reply@oems.local'),
        'from_name' => env('MAIL_FROM_NAME', 'OEMS'),
        'privacy_sink_address' => env(
            'MAIL_PRIVACY_SINK_ADDRESS',
            env('MAIL_FROM_ADDRESS', 'no-reply@oems.local'),
        ),
    ],
];
