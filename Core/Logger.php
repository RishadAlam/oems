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
        $sensitiveKeys = [
            'api_key',
            'authorization',
            'cookie',
            'password',
            'password_confirmation',
            'remember_token',
            'reset_token',
            'secret',
            'set-cookie',
            'token',
            'validator',
        ];
        $safe = [];

        foreach ($context as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $sensitiveKeys, true)) {
                $safe[$key] = '[redacted]';
                continue;
            }

            $safe[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $safe;
    }
}
