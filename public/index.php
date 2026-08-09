<?php

declare(strict_types=1);

use OEMS\App\Services\AuthService;
use OEMS\Core\Auth;
use OEMS\Core\Logger;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\SensitiveDataRedactor;

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$router = $app['router'];
$registerRoutes = require dirname(__DIR__) . '/routes/web.php';
$registerRoutes($router);
$request = Request::fromGlobals();

try {
    $auth = $app['container']->get(Auth::class);
    $rememberCookieName = (string) $app['config']['remember_cookie'];
    $rememberCookie = $request->cookie($rememberCookieName);

    if ($auth->guest() && is_string($rememberCookie) && $rememberCookie !== '') {
        $app['container']->get(AuthService::class)->consumeRememberCookie(
            $rememberCookie,
            $request->ip(),
            (string) $request->header('User-Agent', ''),
        );
    }

    $router->dispatch($request)
        ->withHeader('Permissions-Policy', 'geolocation=(self)')
        ->send();
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
        ->withHeader('Permissions-Policy', 'geolocation=(self)')
        ->send();
}
