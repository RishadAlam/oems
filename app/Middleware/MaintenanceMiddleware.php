<?php

declare(strict_types=1);

namespace OEMS\App\Middleware;

use Closure;
use OEMS\App\Services\MaintenanceService;
use OEMS\Core\Auth;
use OEMS\Core\Middleware;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\View;

final class MaintenanceMiddleware implements Middleware
{
    private const PUBLIC_PATHS = ['/health/live', '/health/ready', '/login'];

    public function __construct(
        private readonly MaintenanceService|Closure $maintenance,
        private readonly Auth|Closure $auth,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->path(), self::PUBLIC_PATHS, true)) {
            return $next($request);
        }

        $maintenance = $this->maintenance instanceof Closure ? ($this->maintenance)() : $this->maintenance;
        if (!$maintenance->isEnabled()) {
            return $next($request);
        }

        $auth = $this->auth instanceof Closure ? ($this->auth)() : $this->auth;
        if ($auth->hasRole('super-admin')) {
            return $next($request);
        }

        return Response::html(
            $this->view->render('errors/maintenance', ['pageTitle' => 'Scheduled maintenance'], 'maintenance'),
            503,
            ['Retry-After' => '300', 'Cache-Control' => 'no-store, private'],
        );
    }
}
