#!/usr/bin/env php
<?php

declare(strict_types=1);

use OEMS\App\Services\RegistrationService;
use OEMS\App\Services\WaitlistService;

$limit = 100;
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/\A--limit=([0-9]{1,3})\z/D', $argument, $matches) !== 1) {
        fwrite(STDERR, "Usage: php scripts/process-waitlists.php [--limit=1..100]\n");
        exit(2);
    }
    $limit = (int) $matches[1];
}
if ($limit < 1 || $limit > 100) {
    fwrite(STDERR, "The waitlist batch limit must be between 1 and 100.\n");
    exit(2);
}

$app = require dirname(__DIR__) . '/bootstrap/app.php';
/** @var WaitlistService $waitlists */
$waitlists = $app['container']->get(WaitlistService::class);
/** @var RegistrationService $registrations */
$registrations = $app['container']->get(RegistrationService::class);
$expiry = $waitlists->releaseExpiredClaims(new DateTimeImmutable(), $limit);
$eventIds = array_values(array_unique(array_merge(
    is_array($expiry['event_ids'] ?? null) ? $expiry['event_ids'] : [],
    $waitlists->promotionEventIds($limit),
)));
$promoted = 0;
$failures = 0;

foreach ($eventIds as $eventId) {
    while ($promoted < $limit) {
        $result = $registrations->promoteWaitlist((int) $eventId);
        if (!($result['success'] ?? false)) {
            $failures++;
            break;
        }
        if (!($result['promoted'] ?? false)) {
            break;
        }
        $promoted++;
    }
    if ($promoted >= $limit) {
        break;
    }
}

fwrite(STDOUT, json_encode([
    'expired_released' => (int) ($expiry['released'] ?? 0),
    'promoted' => $promoted,
    'failures' => $failures,
], JSON_THROW_ON_ERROR) . PHP_EOL);
