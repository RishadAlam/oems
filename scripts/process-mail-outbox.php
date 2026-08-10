#!/usr/bin/env php
<?php

declare(strict_types=1);

use OEMS\App\Services\MailOutboxWorker;

$limit = 50;
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/\A--limit=([0-9]{1,3})\z/D', $argument, $matches) !== 1) {
        fwrite(STDERR, "Usage: php scripts/process-mail-outbox.php [--limit=1..100]\n");
        exit(2);
    }
    $limit = (int) $matches[1];
}

if ($limit < 1 || $limit > 100) {
    fwrite(STDERR, "The mail batch limit must be between 1 and 100.\n");
    exit(2);
}

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$result = $app['container']->get(MailOutboxWorker::class)->run($limit, new DateTimeImmutable());
fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR) . PHP_EOL);
