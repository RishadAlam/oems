<?php

declare(strict_types=1);

use OEMS\App\Services\AuthService;
use OEMS\App\Middleware\HtmlErrorPageMiddleware;
use OEMS\App\Middleware\MaintenanceMiddleware;
use OEMS\App\Support\RememberCookie;
use OEMS\Core\Auth;
use OEMS\Core\Logger;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\SensitiveDataRedactor;

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$router = $app['router'];
$registerRoutes = require dirname(__DIR__) . '/routes/web.php';
$registerRoutes($router);
$request = Request::fromGlobals((array) ($app['config']['trusted_proxies'] ?? []));
header_remove('X-Powered-By');

try {
    $rememberResult = null;
    $rememberCookieName = (string) $app['config']['remember_cookie'];
    $healthRequest = in_array($request->path(), ['/health/live', '/health/ready'], true);
    $statelessApiRequest = str_starts_with($request->path(), '/api/v1/');

    if (!$healthRequest && !$statelessApiRequest) {
        $auth = $app['container']->get(Auth::class);
        $rememberCookie = $request->cookie($rememberCookieName);

        if ($auth->guest() && is_string($rememberCookie) && $rememberCookie !== '') {
            $rememberResult = $app['container']->get(AuthService::class)->consumeRememberCookie(
                $rememberCookie,
                $request->ip(),
                (string) $request->header('User-Agent', ''),
            );
        }
    }

    $response = $app['container']->get(MaintenanceMiddleware::class)
        ->handle(
            $request,
            static fn (Request $r): Response => $app['container']->get(HtmlErrorPageMiddleware::class)
                ->handle($r, static fn (Request $handledRequest): Response => $router->dispatch($handledRequest)),
        )
        ->withSecurityHeaders();

    if (is_array($rememberResult)) {
        $rememberHeader = (new RememberCookie(
            $rememberCookieName,
            (bool) $app['config']['secure_cookies'],
        ))->forConsumptionResult($rememberResult);

        if ($rememberHeader !== null) {
            $response = $response->withHeader('Set-Cookie', $rememberHeader);
        }
    }

    $response->send();
} catch (Throwable $exception) {
    $app['container']->get(Logger::class)->error('Unhandled application exception.', [
        'exception' => $exception::class,
        'message' => $exception->getMessage(),
        'path' => SensitiveDataRedactor::requestPath($request->path()),
    ]);

    try {
        $errorResponse = $app['container']->get(HtmlErrorPageMiddleware::class)->serverError($request);
    } catch (Throwable) {
        $errorResponse = Response::html(
            '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Something went wrong | OEMS</title></head><body><main><h1>Something went wrong.</h1><p>Please try again shortly.</p><p><a href="/">Return home</a></p></main></body></html>',
            500,
            ['Cache-Control' => 'no-store'],
        );
    }

    $errorResponse->withSecurityHeaders()->send();
}
