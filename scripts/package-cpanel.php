<?php

declare(strict_types=1);

use OEMS\App\Services\CpanelPackageService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$basePath = dirname(__DIR__);
require $basePath . '/vendor/autoload.php';

try {
    $destination = $basePath . '/dist/oems-cpanel.zip';
    $path = (new CpanelPackageService($basePath))->package($destination);
    $size = filesize($path);
    $formattedSize = $size === false ? 'unknown size' : number_format($size) . ' bytes';
    fwrite(STDOUT, 'Created dist/oems-cpanel.zip (' . $formattedSize . ').' . PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, 'cPanel package failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
