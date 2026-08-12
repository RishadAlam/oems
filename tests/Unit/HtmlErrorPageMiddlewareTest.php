<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Middleware\HtmlErrorPageMiddleware;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\TestCase;

final class HtmlErrorPageMiddlewareTest extends TestCase
{
    public function testBrowserNotFoundUsesTheAccessiblePublicErrorPage(): void
    {
        $_SESSION = [];
        $session = new Session(false);
        $middleware = $this->middleware($session, new FakeUserRepository());

        $response = $middleware->handle(
            Request::create('GET', '/missing', headers: ['Accept' => 'text/html,application/xhtml+xml']),
            static fn (): Response => Response::text('Not Found', 404),
        );

        $this->assertSame(404, $response->status());
        $this->assertSame('text/html; charset=UTF-8', $response->header('Content-Type'));
        $this->assertTrue(str_contains($response->body(), '<h1>This page is not here.</h1>'));
        $this->assertTrue(str_contains($response->body(), '<title>Page not found | OEMS</title>'));
        $this->assertTrue(str_contains($response->body(), 'Return home'));
    }

    public function testAuthenticatedWorkspaceNotFoundKeepsWorkspaceRecoveryNavigation(): void
    {
        $_SESSION = [];
        $session = new Session(false);
        $users = new FakeUserRepository();
        $users->users[7] = [
            'id' => 7,
            'role_id' => 2,
            'name' => 'Ayesha Rahman',
            'email' => 'ayesha@example.test',
            'password' => password_hash('OrganizerPass!2026', PASSWORD_DEFAULT),
            'status' => 'active',
            'email_verified_at' => '2026-08-01 10:00:00',
        ];
        $this->authenticateSession($session, $users, 7);

        $response = $this->middleware($session, $users)->handle(
            Request::create('GET', '/organizer/missing', headers: ['Accept' => 'text/html']),
            static fn (): Response => Response::text('Not Found', 404),
        );

        $this->assertSame(404, $response->status());
        $this->assertTrue(str_contains($response->body(), 'aria-label="Workspace navigation"'));
        $this->assertTrue(str_contains($response->body(), 'href="/organizer/events"'));
        $this->assertTrue(str_contains($response->body(), '<h1>This page is not here.</h1>'));
    }

    public function testBrowserAccessDeniedUsesTheWorkspaceErrorPageWithoutChangingItsStatus(): void
    {
        $_SESSION = [];
        $session = new Session(false);
        $users = new FakeUserRepository();
        $users->users[7] = [
            'id' => 7,
            'role_id' => 1,
            'name' => 'OEMS Administrator',
            'email' => 'admin@example.test',
            'password' => password_hash('AdminPass!2026', PASSWORD_DEFAULT),
            'status' => 'active',
            'email_verified_at' => '2026-08-01 10:00:00',
        ];
        $this->authenticateSession($session, $users, 7);

        $response = $this->middleware($session, $users)->handle(
            Request::create('GET', '/participant/dashboard', headers: ['Accept' => 'text/html']),
            static fn (): Response => Response::text('Forbidden', 403),
        );

        $this->assertSame(403, $response->status());
        $this->assertSame('text/html; charset=UTF-8', $response->header('Content-Type'));
        $this->assertTrue(str_contains($response->body(), '<title>Access denied | OEMS</title>'));
        $this->assertTrue(str_contains($response->body(), '<h1>You cannot open this page.</h1>'));
        $this->assertTrue(str_contains($response->body(), 'href="/dashboard"'));
        $this->assertTrue(str_contains($response->body(), 'aria-label="Workspace navigation"'));
    }

    public function testStructuredAndNonHtmlNotFoundResponsesRemainMachineReadable(): void
    {
        $_SESSION = [];
        $session = new Session(false);
        $middleware = $this->middleware($session, new FakeUserRepository());
        $json = Response::json(['error' => 'not_found'], 404);
        $plain = Response::text('Not Found', 404);

        $jsonResponse = $middleware->handle(
            Request::create('GET', '/api/v1/missing', headers: ['Accept' => 'application/json']),
            static fn (): Response => $json,
        );
        $plainResponse = $middleware->handle(
            Request::create('GET', '/missing.txt', headers: ['Accept' => 'text/plain']),
            static fn (): Response => $plain,
        );

        $this->assertSame($json->body(), $jsonResponse->body());
        $this->assertSame('application/json; charset=UTF-8', $jsonResponse->header('Content-Type'));
        $this->assertSame('Not Found', $plainResponse->body());
        $this->assertSame('text/plain; charset=UTF-8', $plainResponse->header('Content-Type'));
    }

    private function middleware(Session $session, FakeUserRepository $users): HtmlErrorPageMiddleware
    {
        return new HtmlErrorPageMiddleware(
            new View(base_path('app/Views')),
            new Auth($session, $users),
            new Security($session),
            new Config(['name' => 'OEMS']),
        );
    }
}
