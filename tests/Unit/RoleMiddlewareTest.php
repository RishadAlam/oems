<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Middleware\AuthMiddleware;
use OEMS\App\Middleware\RoleMiddleware;
use OEMS\Core\Auth;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Session;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\TestCase;

final class RoleMiddlewareTest extends TestCase
{
    private FakeUserRepository $users;

    private Session $session;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->users = new FakeUserRepository();
        $this->session = new Session(false);
    }

    public function testGuestIsRedirectedByAuthenticationMiddleware(): void
    {
        $middleware = new AuthMiddleware(new Auth($this->session, $this->users));

        $response = $middleware->handle(
            Request::create('GET', '/dashboard'),
            static fn (): Response => Response::text('dashboard'),
        );

        $this->assertSame(302, $response->status());
        $this->assertSame('/login', $response->header('Location'));
    }

    public function testParticipantCanEnterParticipantRoute(): void
    {
        $id = $this->users->create([
            'name' => 'Participant',
            'email' => 'participant@example.com',
            'password' => 'hash',
            'role_id' => 3,
            'email_verified_at' => '2026-08-06 10:00:00',
        ]);
        $this->session->put('auth.user_id', $id);
        $middleware = new RoleMiddleware(new Auth($this->session, $this->users));

        $response = $middleware->withArgument('participant')->handle(
            Request::create('GET', '/participant/dashboard'),
            static fn (): Response => Response::text('dashboard'),
        );

        $this->assertSame(200, $response->status());
        $this->assertSame('dashboard', $response->body());
    }

    public function testParticipantCannotEnterOrganizerRoute(): void
    {
        $id = $this->users->create([
            'name' => 'Participant',
            'email' => 'participant@example.com',
            'password' => 'hash',
            'role_id' => 3,
            'email_verified_at' => '2026-08-06 10:00:00',
        ]);
        $this->session->put('auth.user_id', $id);
        $middleware = new RoleMiddleware(new Auth($this->session, $this->users));

        $response = $middleware->withArgument('organizer')->handle(
            Request::create('GET', '/organizer/dashboard'),
            static fn (): Response => Response::text('dashboard'),
        );

        $this->assertSame(403, $response->status());
    }
}

