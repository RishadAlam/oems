<?php

declare(strict_types=1);

namespace OEMS\App\Middleware;

use Closure;
use OEMS\Core\Auth;
use OEMS\Core\Middleware;
use OEMS\Core\Request;
use OEMS\Core\Response;

final class GuestMiddleware implements Middleware
{
    public function __construct(private readonly Auth $auth)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->auth->check()) {
            return Response::redirect('/dashboard');
        }

        return $next($request);
    }
}

