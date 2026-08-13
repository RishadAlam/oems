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

    public function __construct(private readonly Auth|Closure $auth)
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
        $auth = $this->auth instanceof Closure ? ($this->auth)() : $this->auth;
        if ($auth->guest()) {
            return Response::redirect($this->loginLocation($request));
        }

        if ($this->roles === [] || !$auth->hasRole(...$this->roles)) {
            return Response::text('Forbidden', 403);
        }

        return $next($request);
    }

    private function loginLocation(Request $request): string
    {
        $token = $request->query('token');

        if (
            $request->method() !== 'GET'
            || $request->path() !== '/organizer/check-in'
            || !is_scalar($token)
            || preg_match('/\A[a-f0-9]{64}\z/i', (string) $token) !== 1
        ) {
            return '/login';
        }

        $returnTo = '/organizer/check-in?token=' . (string) $token;

        return '/login?return_to=' . rawurlencode($returnTo);
    }
}
