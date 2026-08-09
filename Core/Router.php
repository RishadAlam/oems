<?php

declare(strict_types=1);

namespace OEMS\Core;

use Closure;
use ReflectionFunction;
use RuntimeException;

final class Router
{
    private array $routes = [];

    private array $namedRoutes = [];

    private array $middlewareAliases = [];

    public function __construct(private readonly ?Container $container = null)
    {
    }

    public function get(
        string $path,
        callable|array $handler,
        array $middleware = [],
        ?string $name = null,
    ): self {
        return $this->add('GET', $path, $handler, $middleware, $name);
    }

    public function post(
        string $path,
        callable|array $handler,
        array $middleware = [],
        ?string $name = null,
    ): self {
        return $this->add('POST', $path, $handler, $middleware, $name);
    }

    public function add(
        string $method,
        string $path,
        callable|array $handler,
        array $middleware = [],
        ?string $name = null,
    ): self {
        $normalizedPath = $this->normalizePath($path);
        $route = [
            'method' => strtoupper($method),
            'path' => $normalizedPath,
            'pattern' => $this->compilePattern($normalizedPath),
            'handler' => $handler,
            'middleware' => $middleware,
            'name' => $name,
        ];

        $this->routes[] = $route;

        if ($name !== null) {
            $this->namedRoutes[$name] = $normalizedPath;
        }

        return $this;
    }

    public function aliasMiddleware(string $name, callable|object $middleware): void
    {
        $this->middlewareAliases[$name] = $middleware;
    }

    public function dispatch(Request $request): Response
    {
        $pathMatched = false;
        $allowedMethods = [];
        $matches = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['pattern'], $request->path(), $routeMatches) !== 1) {
                continue;
            }

            $matches[] = ['route' => $route, 'parameters' => $routeMatches];
        }

        if ($matches === []) {
            return Response::text('Not Found', 404);
        }

        $specificity = min(array_map(
            static fn (array $match): int => substr_count($match['route']['path'], '{'),
            $matches,
        ));

        foreach ($matches as $match) {
            $route = $match['route'];

            if (substr_count($route['path'], '{') !== $specificity) {
                continue;
            }

            $pathMatched = true;
            $allowedMethods[] = $route['method'];

            if ($route['method'] !== $request->method()) {
                continue;
            }

            $parameters = array_filter(
                $match['parameters'],
                static fn (string|int $key): bool => is_string($key),
                ARRAY_FILTER_USE_KEY,
            );
            $routedRequest = $request->withRouteParameters(array_map('urldecode', $parameters));
            $core = fn (Request $nextRequest): Response => $this->invoke($route['handler'], $nextRequest);
            $pipeline = array_reduce(
                array_reverse($route['middleware']),
                fn (Closure $next, callable|object|string $middleware): Closure => function (Request $nextRequest) use ($middleware, $next): Response {
                    $resolved = $this->resolveMiddleware($middleware);

                    if (is_callable($resolved)) {
                        return $resolved($nextRequest, $next);
                    }

                    if (method_exists($resolved, 'handle')) {
                        return $resolved->handle($nextRequest, $next);
                    }

                    throw new RuntimeException('Route middleware must be callable or expose handle().');
                },
                $core,
            );

            return $pipeline($routedRequest);
        }

        if ($pathMatched) {
            $allowedMethods = array_values(array_unique($allowedMethods));

            return Response::text('Method Not Allowed', 405, ['Allow' => implode(', ', $allowedMethods)]);
        }

        return Response::text('Not Found', 404);
    }

    public function url(string $name, array $parameters = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new RuntimeException("Named route {$name} is not registered.");
        }

        $path = $this->namedRoutes[$name];

        foreach ($parameters as $key => $value) {
            $path = str_replace('{' . $key . '}', rawurlencode((string) $value), $path);
        }

        if (preg_match('/\{[^}]+\}/', $path) === 1) {
            throw new RuntimeException("Missing parameters for route {$name}.");
        }

        return $path;
    }

    private function invoke(callable|array $handler, Request $request): Response
    {
        if (is_array($handler)) {
            [$controller, $method] = $handler;
            $instance = is_object($controller)
                ? $controller
                : ($this->container ?? new Container())->get($controller);
            $result = $instance->{$method}($request);
        } else {
            $reflection = new ReflectionFunction(Closure::fromCallable($handler));
            $result = $reflection->getNumberOfParameters() > 0 ? $handler($request) : $handler();
        }

        if ($result instanceof Response) {
            return $result;
        }

        if (is_string($result)) {
            return Response::html($result);
        }

        throw new RuntimeException('Route handlers must return a Response or string.');
    }

    private function resolveMiddleware(callable|object|string $middleware): callable|object
    {
        if (!is_string($middleware)) {
            return $middleware;
        }

        [$alias, $argument] = array_pad(explode(':', $middleware, 2), 2, null);
        $resolved = $this->middlewareAliases[$alias] ?? null;

        if ($resolved === null) {
            throw new RuntimeException("Middleware alias {$alias} is not registered.");
        }

        if ($argument !== null && is_object($resolved) && method_exists($resolved, 'withArgument')) {
            return $resolved->withArgument($argument);
        }

        return $resolved;
    }

    private function normalizePath(string $path): string
    {
        $normalized = '/' . trim($path, '/');

        return $normalized === '//' ? '/' : $normalized;
    }

    private function compilePattern(string $path): string
    {
        if ($path === '/') {
            return '#^/$#';
        }

        $segments = array_map(static function (string $segment): string {
            if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)\}$/', $segment, $match) === 1) {
                return '(?P<' . $match[1] . '>[^/]+)';
            }

            return preg_quote($segment, '#');
        }, explode('/', trim($path, '/')));

        return '#^/' . implode('/', $segments) . '/?$#';
    }
}
