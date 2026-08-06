<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Helpers/helpers.php';

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'OEMS\\Core\\' => dirname(__DIR__) . '/Core/',
        'OEMS\\App\\' => dirname(__DIR__) . '/app/',
        'OEMS\\Tests\\' => __DIR__ . '/',
    ];

    foreach ($prefixes as $prefix => $directory) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = substr($class, strlen($prefix));
        $path = $directory . str_replace('\\', '/', $relative) . '.php';

        if (is_file($path)) {
            require_once $path;
        }
    }
});
