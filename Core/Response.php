<?php

declare(strict_types=1);

namespace OEMS\Core;

use JsonException;
use RuntimeException;

final class Response
{
    private const FILE_CHUNK_BYTES = 65536;

    public function __construct(
        private readonly string $body = '',
        private readonly int $status = 200,
        private readonly array $headers = [],
        private readonly ?string $filePath = null,
    ) {
    }

    public static function html(string $body, int $status = 200, array $headers = []): self
    {
        return new self($body, $status, array_merge(['Content-Type' => 'text/html; charset=UTF-8'], $headers));
    }

    public static function text(string $body, int $status = 200, array $headers = []): self
    {
        return new self($body, $status, array_merge(['Content-Type' => 'text/plain; charset=UTF-8'], $headers));
    }

    /** @throws JsonException */
    public static function json(array $data, int $status = 200, array $headers = []): self
    {
        return new self(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            $status,
            array_merge(['Content-Type' => 'application/json; charset=UTF-8'], $headers),
        );
    }

    public static function redirect(string $location, int $status = 302, array $headers = []): self
    {
        return new self('', $status, array_merge(['Location' => $location], $headers));
    }

    public static function binary(string $body, int $status = 200, array $headers = []): self
    {
        return new self($body, $status, $headers);
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

        return new self('', $status, $headers, $path);
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

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);

            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
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
}
