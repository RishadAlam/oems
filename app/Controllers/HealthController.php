<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\App\Services\HealthCheckService;
use OEMS\Core\Request;
use OEMS\Core\Response;

final class HealthController
{
    public function __construct(private readonly HealthCheckService $health)
    {
    }

    public function live(Request $request): Response
    {
        return Response::json($this->health->live(), headers: ['Cache-Control' => 'no-store']);
    }

    public function ready(Request $request): Response
    {
        $result = $this->health->ready();

        return Response::json($result, $result['status'] === 'ok' ? 200 : 503, ['Cache-Control' => 'no-store']);
    }
}
