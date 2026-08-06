<?php

declare(strict_types=1);

namespace OEMS\Core;

use Closure;
use RuntimeException;

final class RateLimiter
{
    private readonly Closure $clock;

    public function __construct(
        private readonly string $directory,
        private readonly int $maxAttempts = 5,
        private readonly int $decaySeconds = 900,
        ?Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): int => time();
    }

    public function hit(string $key): int
    {
        return $this->withExclusiveLock($key, function (array $record, int $now, mixed $handle): int {
            if (($record['expires_at'] ?? 0) <= $now) {
                $record = ['attempts' => 0, 'expires_at' => $now + $this->decaySeconds];
            }

            $record['attempts'] = (int) ($record['attempts'] ?? 0) + 1;
            $this->writeRecord($handle, $record);

            return $record['attempts'];
        });
    }

    public function consumeAttempt(string $key): bool
    {
        return $this->withExclusiveLock($key, function (array $record, int $now, mixed $handle): bool {
            if (($record['expires_at'] ?? 0) <= $now) {
                $record = ['attempts' => 0, 'expires_at' => $now + $this->decaySeconds];
            }

            if ((int) ($record['attempts'] ?? 0) >= $this->maxAttempts) {
                return false;
            }

            $record['attempts'] = (int) ($record['attempts'] ?? 0) + 1;
            $this->writeRecord($handle, $record);

            return true;
        });
    }

    public function tooManyAttempts(string $key): bool
    {
        return $this->readRecord($key)['attempts'] >= $this->maxAttempts;
    }

    public function availableIn(string $key): int
    {
        $record = $this->readRecord($key);

        if ($record['attempts'] === 0) {
            return 0;
        }

        return max(0, $record['expires_at'] - ($this->clock)());
    }

    public function clear(string $key): void
    {
        $path = $this->pathFor($key);

        if (!is_file($path)) {
            return;
        }

        $handle = fopen($path, 'c+');

        if ($handle === false) {
            throw new RuntimeException('Unable to open the rate-limit store.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Unable to lock the rate-limit store.');
            }

            ftruncate($handle, 0);
            fflush($handle);
            unlink($path);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    /** @return array{attempts: int, expires_at: int} */
    private function readRecord(string $key): array
    {
        $path = $this->pathFor($key);

        if (!is_file($path)) {
            return ['attempts' => 0, 'expires_at' => 0];
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException('Unable to read the rate-limit store.');
        }

        try {
            if (!flock($handle, LOCK_SH)) {
                throw new RuntimeException('Unable to lock the rate-limit store.');
            }

            $record = $this->decodeRecord(stream_get_contents($handle) ?: '');
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        if ($record['expires_at'] <= ($this->clock)()) {
            return ['attempts' => 0, 'expires_at' => 0];
        }

        return $record;
    }

    private function withExclusiveLock(string $key, Closure $callback): mixed
    {
        $this->ensureDirectoryExists();
        $handle = fopen($this->pathFor($key), 'c+');

        if ($handle === false) {
            throw new RuntimeException('Unable to open the rate-limit store.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Unable to lock the rate-limit store.');
            }

            rewind($handle);
            $record = $this->decodeRecord(stream_get_contents($handle) ?: '');
            $result = $callback($record, ($this->clock)(), $handle);
            flock($handle, LOCK_UN);

            return $result;
        } finally {
            fclose($handle);
        }
    }

    /** @return array{attempts: int, expires_at: int} */
    private function decodeRecord(string $contents): array
    {
        $record = json_decode($contents, true);

        if (!is_array($record)) {
            return ['attempts' => 0, 'expires_at' => 0];
        }

        return [
            'attempts' => max(0, (int) ($record['attempts'] ?? 0)),
            'expires_at' => max(0, (int) ($record['expires_at'] ?? 0)),
        ];
    }

    private function writeRecord(mixed $handle, array $record): void
    {
        $encoded = json_encode($record, JSON_THROW_ON_ERROR);
        rewind($handle);
        ftruncate($handle, 0);

        if (fwrite($handle, $encoded) === false) {
            throw new RuntimeException('Unable to write the rate-limit store.');
        }

        fflush($handle);
    }

    private function pathFor(string $key): string
    {
        return rtrim($this->directory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . hash('sha256', $key)
            . '.json';
    }

    private function ensureDirectoryExists(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Unable to create the rate-limit directory.');
        }
    }
}
