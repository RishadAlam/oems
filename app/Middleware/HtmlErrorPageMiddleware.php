<?php

declare(strict_types=1);

namespace OEMS\App\Middleware;

use Closure;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Middleware;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\View;

final readonly class HtmlErrorPageMiddleware implements Middleware
{
    public function __construct(
        private View $view,
        private Auth|Closure $auth,
        private Security|Closure $security,
        private Config $config,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldRenderApiError($request, $response)) {
            return $this->renderApiError($response);
        }

        if (!$this->shouldRenderHtml($request, $response)) {
            return $response;
        }

        return $this->renderError($request, $response->status(), $response);
    }

    public function serverError(Request $request): Response
    {
        $accept = strtolower((string) $request->header('Accept', ''));
        if (str_starts_with($request->path(), '/api/') || str_contains($accept, 'application/json')) {
            return Response::json([
                'error' => 'server_error',
                'message' => 'The request could not be completed.',
            ], 500, ['Cache-Control' => 'no-store']);
        }

        return $this->renderError($request, 500);
    }

    private function renderError(Request $request, int $status, ?Response $original = null): Response
    {
        $auth = $this->auth instanceof Closure ? ($this->auth)() : $this->auth;
        $security = $this->security instanceof Closure ? ($this->security)() : $this->security;
        $currentUser = $auth->user();
        $layout = $currentUser !== null && $this->isWorkspacePath($request->path())
            ? 'dashboard'
            : 'public';
        $template = match ($status) {
            403 => 'errors/403',
            405 => 'errors/405',
            419 => 'errors/419',
            500 => 'errors/500',
            default => 'errors/404',
        };
        $pageTitle = match ($status) {
            403 => 'Access denied',
            405 => 'Action unavailable',
            419 => 'Session expired',
            500 => 'Something went wrong',
            default => 'Page not found',
        };
        $html = $this->view->render($template, [
            'app' => $this->config->all(),
            'currentUser' => $currentUser,
            'csrfToken' => $security->csrfToken(),
            'errors' => [],
            'old' => [],
            'flash' => [],
            'pageTitle' => $pageTitle,
            'robots' => 'noindex, nofollow',
            'recoveryUrl' => $this->recoveryUrl($request, $currentUser),
        ], $layout);

        $headers = ['Cache-Control' => 'no-store'];
        if ($status === 405 && $original?->header('Allow') !== null) {
            $headers['Allow'] = (string) $original->header('Allow');
        }

        return Response::html($html, $status, $headers);
    }

    private function shouldRenderHtml(Request $request, Response $response): bool
    {
        if (!in_array($response->status(), [403, 404, 405, 419], true)) {
            return false;
        }

        $contentType = strtolower((string) $response->header('Content-Type'));
        if (!str_starts_with($contentType, 'text/plain')) {
            return false;
        }

        $accept = strtolower((string) $request->header('Accept', ''));

        return str_contains($accept, 'text/html') || str_contains($accept, 'application/xhtml+xml');
    }

    private function shouldRenderApiError(Request $request, Response $response): bool
    {
        return str_starts_with($request->path(), '/api/')
            && in_array($response->status(), [403, 404, 405, 419], true)
            && str_starts_with(strtolower((string) $response->header('Content-Type')), 'text/plain');
    }

    private function renderApiError(Response $response): Response
    {
        [$error, $message] = match ($response->status()) {
            403 => ['forbidden', 'The request is not permitted.'],
            405 => ['method_not_allowed', 'The request method is not supported.'],
            419 => ['session_expired', 'The request session has expired.'],
            default => ['not_found', 'The requested resource was not found.'],
        };
        $headers = ['Cache-Control' => 'no-store'];
        if ($response->status() === 405 && $response->header('Allow') !== null) {
            $headers['Allow'] = (string) $response->header('Allow');
        }

        return Response::json([
            'error' => $error,
            'message' => $message,
        ], $response->status(), $headers);
    }

    private function isWorkspacePath(string $path): bool
    {
        if (in_array($path, ['/dashboard', '/profile', '/settings/password'], true)) {
            return true;
        }

        foreach (['/participant/', '/organizer/', '/admin/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function recoveryUrl(Request $request, ?array $currentUser): string
    {
        $fallback = $currentUser === null ? '/' : '/dashboard';
        $referer = $request->header('Referer');

        if (!is_string($referer) || $referer === '') {
            return $fallback;
        }

        $parts = parse_url($referer);
        if (!is_array($parts)) {
            return $fallback;
        }

        $refererHost = strtolower((string) ($parts['host'] ?? ''));
        $requestHost = strtolower((string) $request->header('Host', ''));
        $requestHost = explode(':', $requestHost, 2)[0];
        if ($refererHost !== '' && ($requestHost === '' || !hash_equals($requestHost, $refererHost))) {
            return $fallback;
        }

        $path = (string) ($parts['path'] ?? '');
        if ($path === '' || !str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return $fallback;
        }

        return $path;
    }
}
