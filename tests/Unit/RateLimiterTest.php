<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Core\RateLimiter;
use OEMS\Tests\Support\TestCase;

final class RateLimiterTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/oems-rate-' . bin2hex(random_bytes(5));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            if (is_dir($file)) {
                foreach (glob($file . '/*') ?: [] as $nestedFile) {
                    unlink($nestedFile);
                }
                rmdir($file);
                continue;
            }

            unlink($file);
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function testBlocksAKeyAfterTheConfiguredAttempts(): void
    {
        $now = 1_786_000_000;
        $limiter = new RateLimiter($this->directory, 3, 900, static fn (): int => $now);

        $this->assertSame(1, $limiter->hit('login:user@example.com:192.0.2.1'));
        $this->assertSame(2, $limiter->hit('login:user@example.com:192.0.2.1'));
        $this->assertFalse($limiter->tooManyAttempts('login:user@example.com:192.0.2.1'));
        $this->assertSame(3, $limiter->hit('login:user@example.com:192.0.2.1'));
        $this->assertTrue($limiter->tooManyAttempts('login:user@example.com:192.0.2.1'));
        $this->assertSame(900, $limiter->availableIn('login:user@example.com:192.0.2.1'));
    }

    public function testClearRemovesTheAttemptHistory(): void
    {
        $limiter = new RateLimiter($this->directory, 1, 900);
        $limiter->hit('login:user@example.com:192.0.2.1');

        $limiter->clear('login:user@example.com:192.0.2.1');

        $this->assertFalse($limiter->tooManyAttempts('login:user@example.com:192.0.2.1'));
        $this->assertSame(0, $limiter->availableIn('login:user@example.com:192.0.2.1'));
    }

    public function testClearResetsTheStableLockedRecordWithoutReplacingItsInode(): void
    {
        $limiter = new RateLimiter($this->directory, 1, 900);
        $key = 'login:stable@example.com';
        $limiter->hit($key);
        $path = $this->directory . '/' . hash('sha256', $key) . '.json';
        $before = fileinode($path);

        $limiter->clear($key);

        clearstatcache(true, $path);
        $this->assertTrue(is_file($path));
        $this->assertSame($before, fileinode($path));
        $this->assertTrue($limiter->consumeAttempt($key));
    }

    public function testConcurrentClearAndConsumersCannotAdmitTwoAttemptsOnSplitLockFiles(): void
    {
        $key = 'login:concurrent@example.com';
        $autoload = base_path('vendor/autoload.php');
        $worker = <<<'PHP'
require $argv[1];
$directory = $argv[2];
$key = $argv[3];
$mode = $argv[4];
$markerDirectory = $argv[5];
if ($mode === 'holder') {
    $clock = static function () use ($markerDirectory): int {
        touch($markerDirectory . '/holder-ready');
        $deadline = microtime(true) + 5;
        while (!is_file($markerDirectory . '/release') && microtime(true) < $deadline) {
            usleep(1000);
        }
        return time();
    };
    (new OEMS\Core\RateLimiter($directory, 1, 900, $clock))->hit($key);
    exit(0);
}
$limiter = new OEMS\Core\RateLimiter($directory, 1, 900);
if ($mode === 'clear') {
    touch($markerDirectory . '/clear-ready');
    $limiter->clear($key);
    touch($markerDirectory . '/clear-done');
    exit(0);
}
echo $limiter->consumeAttempt($key) ? '1' : '0';
PHP;
        $markers = $this->directory . '/markers';
        mkdir($markers, 0775, true);
        $holder = $this->spawnWorker($worker, [$autoload, $this->directory, $key, 'holder', $markers]);
        $this->waitForFile($markers . '/holder-ready');
        $clearer = $this->spawnWorker($worker, [$autoload, $this->directory, $key, 'clear', $markers]);
        $this->waitForFile($markers . '/clear-ready');
        usleep(100_000);
        $firstConsumer = $this->spawnWorker($worker, [$autoload, $this->directory, $key, 'consume', $markers]);
        usleep(100_000);
        touch($markers . '/release');
        $this->finishWorker($holder);
        $this->finishWorker($clearer);
        $this->waitForFile($markers . '/clear-done');
        $secondConsumer = $this->spawnWorker($worker, [$autoload, $this->directory, $key, 'consume', $markers]);
        $first = $this->finishWorker($firstConsumer);
        $second = $this->finishWorker($secondConsumer);

        $this->assertSame(1, (int) $first + (int) $second);
    }

    public function testConsumesAnAttemptWithTheLimitCheckUnderOneLock(): void
    {
        $limiter = new RateLimiter($this->directory, 2, 900);

        $this->assertTrue($limiter->consumeAttempt('password-reset:email:user@example.com'));
        $this->assertTrue($limiter->consumeAttempt('password-reset:email:user@example.com'));
        $this->assertFalse($limiter->consumeAttempt('password-reset:email:user@example.com'));
        $this->assertTrue($limiter->tooManyAttempts('password-reset:email:user@example.com'));
    }

    private function spawnWorker(string $code, array $arguments): array
    {
        $process = proc_open(
            array_merge([PHP_BINARY, '-r', $code, '--'], $arguments),
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
        );
        $this->assertTrue(is_resource($process));
        fclose($pipes[0]);

        return [$process, $pipes[1], $pipes[2]];
    }

    private function finishWorker(array $worker): string
    {
        [$process, $stdout, $stderr] = $worker;
        $output = trim((string) stream_get_contents($stdout));
        $error = trim((string) stream_get_contents($stderr));
        fclose($stdout);
        fclose($stderr);
        $status = proc_close($process);
        $this->assertSame(0, $status, $error);

        return $output;
    }

    private function waitForFile(string $path): void
    {
        $deadline = microtime(true) + 5;

        while (!is_file($path) && microtime(true) < $deadline) {
            usleep(1000);
        }

        $this->assertTrue(is_file($path), 'Timed out waiting for worker coordination.');
    }
}
