<?php

declare(strict_types=1);

namespace OEMS\Core;

use JsonException;
use InvalidArgumentException;
use RuntimeException;

final class Response
{
    private const FILE_CHUNK_BYTES = 65536;

    private readonly array $headers;

    public function __construct(
        private readonly string $body = '',
        private readonly int $status = 200,
        array $headers = [],
        private readonly ?string $filePath = null,
        private readonly ?\Closure $streamCallback = null,
    ) {
        $this->headers = self::validatedHeaders($headers);
    }

    public static function html(string $body, int $status = 200, array $headers = []): self
    {
        return new self(
            $body,
            $status,
            self::validatedHeaders(array_merge(['Content-Type' => 'text/html; charset=UTF-8'], $headers)),
        );
    }

    public static function text(string $body, int $status = 200, array $headers = []): self
    {
        return new self(
            $body,
            $status,
            self::validatedHeaders(array_merge(['Content-Type' => 'text/plain; charset=UTF-8'], $headers)),
        );
    }

    /** @throws JsonException */
    public static function json(array $data, int $status = 200, array $headers = []): self
    {
        return new self(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            $status,
            self::validatedHeaders(array_merge(['Content-Type' => 'application/json; charset=UTF-8'], $headers)),
        );
    }

    public static function redirect(string $location, int $status = 302, array $headers = []): self
    {
        return new self('', $status, self::validatedHeaders(array_merge(['Location' => $location], $headers)));
    }

    public static function binary(string $body, int $status = 200, array $headers = []): self
    {
        return new self($body, $status, self::validatedHeaders($headers));
    }

    public static function file(string $path, int $status = 200, array $headers = []): self
    {
        if ($path === '' || str_contains($path, "\0") || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException('The response file is unavailable.');
        }

        $size = filesize($path);
        if (is_int($size) && $size >= 0 && !self::hasHeader($headers, 'Content-Length')) {
            $headers['Content-Length'] = (string) $size;
        }

        return new self('', $status, self::validatedHeaders($headers), $path);
    }

    public static function stream(callable $callback, int $status = 200, array $headers = []): self
    {
        return new self(
            '',
            $status,
            self::validatedHeaders($headers),
            null,
            \Closure::fromCallable($callback),
        );
    }

    public function body(): string
    {
        return $this->body;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return (string) $value;
            }
        }

        return null;
    }

    public function withHeader(string $name, string $value): self
    {
        [$name, $value] = self::validatedHeader($name, $value);

        $headers = [];
        foreach ($this->headers as $key => $existingValue) {
            if (!is_string($key) || strcasecmp($key, $name) !== 0) {
                $headers[$key] = $existingValue;
            }
        }
        $headers[$name] = $value;

        return new self(
            $this->body,
            $this->status,
            $headers,
            $this->filePath,
            $this->streamCallback,
        );
    }

    public function withSecurityHeaders(): self
    {
        $headers = [
            'Content-Security-Policy' => "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self'; connect-src 'self'",
            'Permissions-Policy' => 'geolocation=(self)',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
        ];
        $response = $this;

        foreach ($headers as $name => $value) {
            if ($response->header($name) === null) {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);

            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }

        if ($this->streamCallback !== null) {
            ($this->streamCallback)(static function (string $chunk): void {
                echo $chunk;
            });

            return;
        }

        if ($this->filePath === null) {
            echo $this->body;

            return;
        }

        $stream = @fopen($this->filePath, 'rb');
        if ($stream === false) {
            throw new RuntimeException('The response file could not be opened.');
        }

        try {
            while (!feof($stream)) {
                $chunk = fread($stream, self::FILE_CHUNK_BYTES);

                if ($chunk === false) {
                    throw new RuntimeException('The response file could not be read.');
                }

                if ($chunk === '') {
                    break;
                }

                echo $chunk;
            }
        } finally {
            fclose($stream);
        }
    }

    private static function hasHeader(array $headers, string $name): bool
    {
        foreach (array_keys($headers) as $header) {
            if (is_string($header) && strcasecmp($header, $name) === 0) {
                return true;
            }
        }

        return false;
    }

    private static function validatedHeaders(array $headers): array
    {
        $validated = [];

        foreach ($headers as $name => $value) {
            if (!is_string($name) || !is_scalar($value)) {
                throw new InvalidArgumentException('Invalid response header.');
            }

            [$safeName, $safeValue] = self::validatedHeader($name, (string) $value);
            $validated[$safeName] = $safeValue;
        }

        return $validated;
    }

    private static function validatedHeader(string $name, string $value): array
    {
        $name = trim($name);
        $value = trim($value);

        if ($name === ''
            || preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/', $name) !== 1
            || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new InvalidArgumentException('Invalid response header.');
        }

        return [$name, $value];
    }
}
