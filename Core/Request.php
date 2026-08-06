<?php

declare(strict_types=1);

namespace OEMS\Core;

final class Request
{
    private array $routeParameters = [];

    private function __construct(
        private readonly string $method,
        private readonly string $uri,
        private readonly array $query,
        private readonly array $input,
        private readonly array $headers,
        private readonly array $cookies,
        private readonly array $files,
        private readonly array $server,
    ) {
    }

    public static function create(
        string $method,
        string $uri,
        array $query = [],
        array $input = [],
        array $headers = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
    ): self {
        return new self(
            strtoupper($method),
            $uri,
            $query,
            $input,
            array_change_key_case($headers, CASE_LOWER),
            $cookies,
            $files,
            $server,
        );
    }

    public static function fromGlobals(): self
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        return self::create(
            $method,
            $uri,
            $_GET,
            $_POST,
            is_array($headers) ? $headers : [],
            $_COOKIE,
            $_FILES,
            $_SERVER,
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function path(): string
    {
        $path = parse_url($this->uri, PHP_URL_PATH);
        $normalized = '/' . trim(is_string($path) ? $path : '/', '/');

        return $normalized === '//' ? '/' : $normalized;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->input[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->input);
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    public function header(string $key, mixed $default = null): mixed
    {
        return $this->headers[strtolower($key)] ?? $default;
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        return is_array($file) ? $file : null;
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '127.0.0.1');
    }

    public function route(string $key, mixed $default = null): mixed
    {
        return $this->routeParameters[$key] ?? $default;
    }

    public function withRouteParameters(array $parameters): self
    {
        $clone = clone $this;
        $clone->routeParameters = $parameters;

        return $clone;
    }
}

