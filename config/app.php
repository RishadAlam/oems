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
];

