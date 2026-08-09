<?php

declare(strict_types=1);

if (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) === '/redirect') {
    header('Location: /final', true, 302);
    echo 'redirect response';

    return;
}

http_response_code(200);
echo 'final response';
