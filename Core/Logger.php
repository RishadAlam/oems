<?php

declare(strict_types=1);

namespace OEMS\Core;

use DateTimeImmutable;
use RuntimeException;

final class Logger
{
    public function __construct(private readonly string $path)
    {
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        $directory = dirname($this->path);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create log directory {$directory}.");
        }

        $safeContext = $this->redact($context);
        $record = sprintf(
            "[%s] %s: %s %s\n",
            (new DateTimeImmutable())->format(DATE_ATOM),
            $level,
            $message,
            $safeContext === [] ? '' : json_encode($safeContext, JSON_UNESCAPED_SLASHES),
        );

        if (file_put_contents($this->path, $record, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException("Unable to write log file {$this->path}.");
        }
    }

    private function redact(array $context): array
    {
        $safe = [];

        foreach ($context as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $safe[$key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                $safe[$key] = $this->redact($value);
                continue;
            }

            $safe[$key] = is_object($value) ? $this->redact(get_object_vars($value)) : $value;
        }

        return $safe;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $key));

        foreach (['apikey', 'authorization', 'cookie', 'password', 'secret', 'token', 'validator'] as $term) {
            if (str_contains($normalized, $term)) {
                return true;
            }
        }

        return false;
    }
}
