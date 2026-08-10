<?php

declare(strict_types=1);

namespace OEMS\App\Middleware;

use Closure;
use OEMS\Core\Auth;
use OEMS\Core\Middleware;
use OEMS\Core\Request;
use OEMS\Core\Response;

final class AuthMiddleware implements Middleware
{
    public function __construct(private readonly Auth|Closure $auth)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $auth = $this->auth instanceof Closure ? ($this->auth)() : $this->auth;
        if ($auth->guest()) {
            return Response::redirect('/login');
        }

        return $next($request);
    }
}
