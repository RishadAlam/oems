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
        private Auth $auth,
        private Security $security,
        private Config $config,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!$this->shouldRenderHtml($request, $response)) {
            return $response;
        }

        $currentUser = $this->auth->user();
        $layout = $currentUser !== null && $this->isWorkspacePath($request->path())
            ? 'dashboard'
            : 'public';
        $html = $this->view->render('errors/404', [
            'app' => $this->config->all(),
            'currentUser' => $currentUser,
            'csrfToken' => $this->security->csrfToken(),
            'errors' => [],
            'old' => [],
            'flash' => [],
            'pageTitle' => 'Page not found',
            'robots' => 'noindex, nofollow',
        ], $layout);

        return Response::html($html, 404, ['Cache-Control' => 'no-store']);
    }

    private function shouldRenderHtml(Request $request, Response $response): bool
    {
        if ($response->status() !== 404) {
            return false;
        }

        $contentType = strtolower((string) $response->header('Content-Type'));
        if (!str_starts_with($contentType, 'text/plain')) {
            return false;
        }

        $accept = strtolower((string) $request->header('Accept', ''));

        return str_contains($accept, 'text/html') || str_contains($accept, 'application/xhtml+xml');
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
}
