<?php

declare(strict_types=1);

namespace OEMS\Core;

use JsonException;

final class Response
{
    public function __construct(
        private readonly string $body = '',
        private readonly int $status = 200,
        private readonly array $headers = [],
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
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->body;
    }
}
