#!/usr/bin/env php
<?php

declare(strict_types=1);

use OEMS\App\Services\DatabaseBackupService;
use OEMS\App\Contracts\PlatformSettingsRepositoryInterface;

if (PHP_SAPI !== 'cli' || count($argv) !== 1) {
    fwrite(STDERR, "Usage: php scripts/backup-database.php\n");
    exit(2);
}

$basePath = dirname(__DIR__);
$app = require $basePath . '/bootstrap/app.php';
$database = require $basePath . '/config/database.php';
$stored = $app['container']->get(PlatformSettingsRepositoryInterface::class)->privateValuesForKeys(['backup_retention']);
$retention = filter_var($stored['backup_retention'] ?? 14, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 30]]);
$service = new DatabaseBackupService($basePath);

try {
    $path = $service->backup($database, is_int($retention) ? $retention : 14);
    fwrite(STDOUT, json_encode(['status' => 'ok', 'file' => basename($path)], JSON_THROW_ON_ERROR) . PHP_EOL);
} catch (Throwable) {
    fwrite(STDERR, "Database backup failed.\n");
    exit(1);
}
