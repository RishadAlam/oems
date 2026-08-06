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

    public function testConsumesAnAttemptWithTheLimitCheckUnderOneLock(): void
    {
        $limiter = new RateLimiter($this->directory, 2, 900);

        $this->assertTrue($limiter->consumeAttempt('password-reset:email:user@example.com'));
        $this->assertTrue($limiter->consumeAttempt('password-reset:email:user@example.com'));
        $this->assertFalse($limiter->consumeAttempt('password-reset:email:user@example.com'));
        $this->assertTrue($limiter->tooManyAttempts('password-reset:email:user@example.com'));
    }
}
