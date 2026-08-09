<?php

declare(strict_types=1);

namespace OEMS\Tests\Support;

use RuntimeException;
use Throwable;

abstract class TestCase
{
    private int $assertions = 0;

    public function run(): array
    {
        $results = [];

        foreach (get_class_methods($this) as $method) {
            if (!str_starts_with($method, 'test')) {
                continue;
            }

            $before = $this->assertions;

            try {
                $this->setUp();
                $this->{$method}();
                $this->tearDown();
                $results[] = [
                    'test' => static::class . '::' . $method,
                    'passed' => true,
                    'assertions' => $this->assertions - $before,
                    'message' => '',
                ];
            } catch (Throwable $exception) {
                $results[] = [
                    'test' => static::class . '::' . $method,
                    'passed' => false,
                    'assertions' => $this->assertions - $before,
                    'message' => $exception::class . ': ' . $exception->getMessage(),
                ];
            }
        }

        return $results;
    }

    protected function setUp(): void
    {
    }

    protected function tearDown(): void
    {
    }

    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertions++;

        if ($expected !== $actual) {
            throw new RuntimeException($message !== '' ? $message : sprintf(
                'Expected %s, received %s.',
                var_export($expected, true),
                var_export($actual, true),
            ));
        }
    }

    protected function assertTrue(bool $condition, string $message = 'Expected condition to be true.'): void
    {
        $this->assertions++;

        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    protected function assertFalse(bool $condition, string $message = 'Expected condition to be false.'): void
    {
        $this->assertions++;

        if ($condition) {
            throw new RuntimeException($message);
        }
    }

    protected function assertNotSame(mixed $unexpected, mixed $actual, string $message = ''): void
    {
        $this->assertions++;

        if ($unexpected === $actual) {
            throw new RuntimeException($message !== '' ? $message : sprintf(
                'Did not expect %s.',
                var_export($actual, true),
            ));
        }
    }

    protected function assertArrayHasKey(string|int $key, array $array, string $message = ''): void
    {
        $this->assertions++;

        if (!array_key_exists($key, $array)) {
            throw new RuntimeException($message !== '' ? $message : sprintf(
                'Array does not contain key %s.',
                var_export($key, true),
            ));
        }
    }

    protected function assertNull(mixed $actual, string $message = 'Expected null.'): void
    {
        $this->assertions++;

        if ($actual !== null) {
            throw new RuntimeException($message);
        }
    }

    protected function assertNotNull(mixed $actual, string $message = 'Expected a non-null value.'): void
    {
        $this->assertions++;

        if ($actual === null) {
            throw new RuntimeException($message);
        }
    }

    protected function authenticateSession(
        \OEMS\Core\Session $session,
        \OEMS\App\Contracts\UserRepositoryInterface $users,
        int $userId,
    ): void {
        $user = $users->findById($userId);

        if ($user === null) {
            throw new RuntimeException('Cannot authenticate a missing test user.');
        }

        $session->put('auth.user_id', $userId);
        $session->put('auth.role', (string) ($user['role_slug'] ?? ''));
        $session->put('auth.password_signature', hash('sha256', (string) ($user['password'] ?? '')));
    }
}
