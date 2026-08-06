<?php

declare(strict_types=1);

namespace OEMS\App\Middleware;

use Closure;
use OEMS\Core\Auth;
use OEMS\Core\Middleware;
use OEMS\Core\Request;
use OEMS\Core\Response;

final class RoleMiddleware implements Middleware
{
    private array $roles = [];

    public function __construct(private readonly Auth $auth)
    {
    }

    public function withArgument(string $roles): self
    {
        $clone = clone $this;
        $clone->roles = array_values(array_filter(array_map('trim', explode(',', $roles))));

        return $clone;
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->auth->guest()) {
            return Response::redirect('/login');
        }

        if ($this->roles === [] || !$this->auth->hasRole(...$this->roles)) {
            return Response::text('Forbidden', 403);
        }

        return $next($request);
    }
}

