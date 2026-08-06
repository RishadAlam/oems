<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$filter = $argv[1] ?? '';
$files = glob(__DIR__ . '/Unit/*Test.php') ?: [];

foreach ($files as $file) {
    if ($filter !== '' && !str_contains($file, $filter)) {
        continue;
    }

    require_once $file;
}

$testClasses = array_filter(
    get_declared_classes(),
    static fn (string $class): bool => str_starts_with($class, 'OEMS\\Tests\\Unit\\')
        && is_subclass_of($class, OEMS\Tests\Support\TestCase::class),
);

$failures = 0;
$assertions = 0;
$tests = 0;

foreach ($testClasses as $class) {
    $instance = new $class();

    foreach ($instance->run() as $result) {
        $tests++;
        $assertions += $result['assertions'];

        if ($result['passed']) {
            echo "PASS {$result['test']}\n";
            continue;
        }

        $failures++;
        echo "FAIL {$result['test']}\n  {$result['message']}\n";
    }
}

echo "\nTests: {$tests}, Assertions: {$assertions}, Failures: {$failures}\n";

exit($failures === 0 ? 0 : 1);

