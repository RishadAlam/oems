<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($path === '/redirect') {
    header('Location: /final', true, 302);
    echo 'redirect response';

    return;
}

if ($path === '/oversized') {
    echo str_repeat('x', 128);

    return;
}

if ($path === '/slow-trickle') {
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    ob_implicit_flush(true);

    for ($index = 0; $index < 5; $index++) {
        echo 'x';
        flush();
        usleep(300_000);
    }

    return;
}

http_response_code(200);
echo 'final response';
