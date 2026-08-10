<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\App\Services\PublicEventApiService;
use OEMS\Core\RateLimiter;
use OEMS\Core\Request;
use OEMS\Core\Response;

final class ApiEventController
{
    public function __construct(
        private readonly PublicEventApiService $events,
        private readonly RateLimiter $limiter,
    ) {
    }

    public function index(Request $request): Response
    {
        if (($limited = $this->rateLimit($request)) !== null) {
            return $limited;
        }
        $result = $this->events->index($request->all());
        if (!($result['success'] ?? false)) {
            return $this->error($result['errors'] ?? [], 422);
        }

        return $this->cached($request, [
            'data' => $result['events'],
            'meta' => [
                'pagination' => $result['pagination'],
                'filters' => $result['filters'],
                'version' => 'v1',
            ],
        ]);
    }

    public function show(Request $request): Response
    {
        if (($limited = $this->rateLimit($request)) !== null) {
            return $limited;
        }
        if ($request->all() !== []) {
            return $this->error(['query' => ['This endpoint does not accept query parameters.']], 422);
        }
        $result = $this->events->detail($request->route('slug'));
        if (!($result['success'] ?? false)) {
            return $this->error(['event' => ['Event not found.']], 404);
        }

        return $this->cached($request, [
            'data' => $result['event'],
            'meta' => ['version' => 'v1'],
        ]);
    }

    public function calendar(Request $request): Response
    {
        if (($limited = $this->rateLimit($request)) !== null) {
            return $limited;
        }
        if (array_diff(array_keys($request->all()), ['month']) !== []) {
            return $this->error(['query' => ['Only the month parameter is supported.']], 422);
        }
        $result = $this->events->calendar($request->query('month', ''));
        if (!($result['success'] ?? false)) {
            return $this->error($result['errors'] ?? [], 422);
        }

        return $this->cached($request, [
            'data' => $result['events'],
            'meta' => [
                'month' => $result['month'],
                'label' => $result['label'],
                'previous_month' => $result['previous_month'],
                'next_month' => $result['next_month'],
                'version' => 'v1',
            ],
        ]);
    }

    private function cached(Request $request, array $payload): Response
    {
        $response = Response::json($payload, 200, [
            'Cache-Control' => 'public, max-age=60',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $etag = '"' . hash('sha256', $response->body()) . '"';
        $headers = array_merge($response->headers(), ['ETag' => $etag]);
        $candidates = array_map('trim', explode(',', (string) $request->header('if-none-match', '')));

        return in_array($etag, $candidates, true)
            ? new Response('', 304, $headers)
            : new Response($response->body(), 200, $headers);
    }

    private function error(array $errors, int $status): Response
    {
        return Response::json(['errors' => $errors, 'meta' => ['version' => 'v1']], $status, [
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function rateLimit(Request $request): ?Response
    {
        $key = 'public-event-api:' . $request->ip();
        if ($this->limiter->consumeAttempt($key)) {
            return null;
        }

        return Response::json([
            'errors' => ['rate_limit' => ['Too many event API requests. Try again shortly.']],
            'meta' => ['version' => 'v1'],
        ], 429, [
            'Cache-Control' => 'no-store',
            'Retry-After' => (string) max(1, $this->limiter->availableIn($key)),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
