<?php

declare(strict_types=1);

namespace OEMS\App\Middleware;

use Closure;
use OEMS\Core\Middleware;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;

final class CsrfMiddleware implements Middleware
{
    public function __construct(private readonly Security|Closure $security)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $security = $this->security instanceof Closure ? ($this->security)() : $this->security;
        if (!$security->verifyCsrf((string) $request->input('_token', ''))) {
            return Response::text('Page expired. Refresh the page and try again.', 419);
        }

        return $next($request);
    }
}
