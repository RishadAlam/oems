<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Router;
use OEMS\Tests\Support\TestCase;

final class RouterTest extends TestCase
{
    public function testDispatchesAStaticGetRoute(): void
    {
        $router = new Router();
        $router->get('/health', static fn (): Response => Response::text('healthy'));

        $response = $router->dispatch(Request::create('GET', '/health'));

        $this->assertSame(200, $response->status());
        $this->assertSame('healthy', $response->body());
    }

    public function testInjectsNamedPathParametersIntoTheRequest(): void
    {
        $router = new Router();
        $router->get('/events/{slug}', static function (Request $request): Response {
            return Response::text((string) $request->route('slug'));
        });

        $response = $router->dispatch(Request::create('GET', '/events/dhaka-design-week'));

        $this->assertSame('dhaka-design-week', $response->body());
    }

    public function testReturnsNotFoundForAnUnknownPath(): void
    {
        $router = new Router();

        $response = $router->dispatch(Request::create('GET', '/missing'));

        $this->assertSame(404, $response->status());
    }

    public function testReturnsMethodNotAllowedForAKnownPath(): void
    {
        $router = new Router();
        $router->post('/login', static fn (): Response => Response::text('ok'));

        $response = $router->dispatch(Request::create('GET', '/login'));

        $this->assertSame(405, $response->status());
        $this->assertSame('POST', $response->header('Allow'));
    }
}

