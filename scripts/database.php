#!/usr/bin/env php
<?php

declare(strict_types=1);

use OEMS\App\Services\DatabaseLifecycleException;
use OEMS\App\Services\DatabaseLifecycleService;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Database commands are available only from the command line.\n");
    exit(2);
}

$arguments = array_slice($argv, 1);
$command = array_shift($arguments);
$force = false;
foreach ($arguments as $argument) {
    if ($argument !== '--force' || $force) {
        fwrite(STDERR, "Usage: php scripts/database.php <migrate|rollback|refresh|seed> [--force]\n");
        exit(2);
    }
    $force = true;
}
if (!in_array($command, ['migrate', 'rollback', 'refresh', 'seed'], true)) {
    fwrite(STDERR, "Usage: php scripts/database.php <migrate|rollback|refresh|seed> [--force]\n");
    exit(2);
}
if ($force && !in_array($command, ['rollback', 'refresh'], true)) {
    fwrite(STDERR, "The --force option is supported only by rollback and refresh.\n");
    exit(2);
}

$basePath = dirname(__DIR__);
require $basePath . '/bootstrap/app.php';
$database = require $basePath . '/config/database.php';
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];
if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
    $options[PDO::MYSQL_ATTR_MULTI_STATEMENTS] = true;
}
$dsn = sprintf(
    '%s:host=%s;port=%d;dbname=%s;charset=%s',
    $database['driver'],
    $database['host'],
    $database['port'],
    $database['database'],
    $database['charset'],
);

try {
    $pdo = new PDO($dsn, (string) $database['username'], (string) $database['password'], $options);
    $service = new DatabaseLifecycleService(
        $pdo,
        (string) $database['driver'],
        $basePath . '/database/schema.sql',
        $basePath . '/database/seed.sql',
        $basePath . '/database/demo_seed.sql',
        require $basePath . '/database/migrations/manifest.php',
        (string) env('APP_ENV', 'production'),
    );
    $result = match ($command) {
        'migrate' => $service->migrate(),
        'rollback' => $service->rollback($force),
        'refresh' => $service->refresh($force),
        'seed' => $service->seedDemo(),
    };
    fwrite(STDOUT, $result['message'] . PHP_EOL);
} catch (DatabaseLifecycleException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
} catch (Throwable) {
    fwrite(STDERR, "Database command failed. Check the configured connection and database permissions.\n");
    exit(1);
}
