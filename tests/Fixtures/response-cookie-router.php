<?php

declare(strict_types=1);

use OEMS\Core\Response;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

session_name('OEMS_RESPONSE_COOKIE_TEST');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

Response::text('ok')
    ->withHeader('Set-Cookie', 'OEMS_REMEMBER=rotated; Path=/; HttpOnly; SameSite=Lax')
    ->send();
