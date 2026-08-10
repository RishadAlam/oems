<?php

declare(strict_types=1);

use OEMS\App\Services\AuthService;
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

    if (!$healthRequest) {
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
        ->handle($request, static fn (Request $r): Response => $router->dispatch($r))
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

    $body = (bool) $app['config']['debug']
        ? '<h1>Application error</h1><pre>' . e($exception->getMessage()) . '</pre>'
        : '<h1>Something went wrong</h1><p>Please try again shortly.</p>';
    Response::html($body, 500)
        ->withSecurityHeaders()
        ->send();
}
