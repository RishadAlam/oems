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
        private readonly array $trustedProxies,
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
        array $trustedProxies = [],
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
            $trustedProxies,
        );
    }

    public static function fromGlobals(array $trustedProxies = []): self
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
            $trustedProxies,
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
        $peer = $this->validIp($this->server['REMOTE_ADDR'] ?? null) ?? '127.0.0.1';

        if (!$this->isTrustedProxy($peer)) {
            return $peer;
        }

        $forwarded = $this->header('Forwarded');
        if (is_string($forwarded) && trim($forwarded) !== '') {
            $chain = $this->parseForwarded($forwarded);

            return $chain === null ? $peer : $this->firstUntrustedHop($chain, $peer);
        }

        $forwardedFor = $this->header('X-Forwarded-For');
        if (!is_string($forwardedFor) || trim($forwardedFor) === '') {
            return $peer;
        }

        $chain = $this->parseXForwardedFor($forwardedFor);

        return $chain === null ? $peer : $this->firstUntrustedHop($chain, $peer);
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

    /**
     * @return list<string>|null
     */
    private function parseForwarded(string $header): ?array
    {
        $addresses = [];

        foreach (explode(',', $header) as $element) {
            $found = null;

            foreach (explode(';', $element) as $parameter) {
                if (!str_contains($parameter, '=')) {
                    return null;
                }

                [$name, $value] = array_map('trim', explode('=', $parameter, 2));
                if (strtolower($name) !== 'for') {
                    continue;
                }

                if ($found !== null) {
                    return null;
                }

                $found = $this->forwardedAddress($value);
            }

            if ($found === null) {
                return null;
            }

            $addresses[] = $found;
        }

        return $addresses === [] ? null : $addresses;
    }

    private function forwardedAddress(string $value): ?string
    {
        if (str_starts_with($value, '"')) {
            if (!str_ends_with($value, '"') || strlen($value) < 2) {
                return null;
            }

            $value = stripcslashes(substr($value, 1, -1));
        }

        if (preg_match('/\A\[([^\]]+)](?::[0-9]{1,5})?\z/', $value, $match) === 1) {
            return $this->validIp($match[1]);
        }

        if (substr_count($value, ':') === 1
            && preg_match('/\A([^:]+):[0-9]{1,5}\z/', $value, $match) === 1) {
            return $this->validIp($match[1]);
        }

        return $this->validIp($value);
    }

    /**
     * @return list<string>|null
     */
    private function parseXForwardedFor(string $header): ?array
    {
        $addresses = [];

        foreach (explode(',', $header) as $value) {
            $address = $this->validIp(trim($value));
            if ($address === null) {
                return null;
            }

            $addresses[] = $address;
        }

        return $addresses === [] ? null : $addresses;
    }

    /**
     * @param list<string> $forwarded
     */
    private function firstUntrustedHop(array $forwarded, string $peer): string
    {
        $chain = array_merge($forwarded, [$peer]);

        for ($index = count($chain) - 1; $index >= 0; $index--) {
            if (!$this->isTrustedProxy($chain[$index])) {
                return $chain[$index];
            }
        }

        return $forwarded[0] ?? $peer;
    }

    private function isTrustedProxy(string $address): bool
    {
        foreach ($this->trustedProxies as $range) {
            if (is_string($range) && $this->isInRange($address, trim($range))) {
                return true;
            }
        }

        return false;
    }

    private function isInRange(string $address, string $range): bool
    {
        if ($range === '') {
            return false;
        }

        [$network, $prefix] = array_pad(explode('/', $range, 2), 2, null);
        $addressBytes = inet_pton($address);
        $networkBytes = inet_pton($network);

        if ($addressBytes === false || $networkBytes === false || strlen($addressBytes) !== strlen($networkBytes)) {
            return false;
        }

        $maximum = strlen($addressBytes) * 8;
        $bits = $prefix === null ? $maximum : filter_var($prefix, FILTER_VALIDATE_INT);
        if (!is_int($bits) || $bits < 0 || $bits > $maximum) {
            return false;
        }

        $wholeBytes = intdiv($bits, 8);
        if (substr($addressBytes, 0, $wholeBytes) !== substr($networkBytes, 0, $wholeBytes)) {
            return false;
        }

        $remaining = $bits % 8;
        if ($remaining === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remaining)) & 0xFF;

        return (ord($addressBytes[$wholeBytes]) & $mask) === (ord($networkBytes[$wholeBytes]) & $mask);
    }

    private function validIp(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return filter_var($value, FILTER_VALIDATE_IP) === false ? null : $value;
    }
}
