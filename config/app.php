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
    'map' => [
        'tile_url' => env('MAP_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'tile_attribution' => env('MAP_TILE_ATTRIBUTION', '&copy; OpenStreetMap contributors'),
        'default_lat' => (float) env('MAP_DEFAULT_LAT', 23.8103),
        'default_lng' => (float) env('MAP_DEFAULT_LNG', 90.4125),
        'default_zoom' => (int) env('MAP_DEFAULT_ZOOM', 11),
        'geocoder_url' => env('MAP_GEOCODER_URL', 'https://nominatim.openstreetmap.org/search'),
        'provider_name' => env('MAP_PROVIDER_NAME', 'OpenStreetMap Nominatim'),
        'user_agent' => env('MAP_USER_AGENT', 'OEMS/1.0'),
        'contact_email' => env('MAP_CONTACT_EMAIL', ''),
        'directions_hosts' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env(
                'MAP_DIRECTIONS_HOSTS',
                'www.google.com,maps.google.com,maps.app.goo.gl,www.openstreetmap.org',
            )),
        ))),
        'location_session_ttl' => (int) env('LOCATION_SESSION_TTL', 1209600),
    ],
];
