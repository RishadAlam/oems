<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Controllers\ParticipantNotificationController;
use OEMS\App\Middleware\CsrfMiddleware;
use OEMS\App\Middleware\RoleMiddleware;
use OEMS\App\Services\NotificationService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Container;
use OEMS\Core\Request;
use OEMS\Core\Router;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakeNotificationRepository;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\TestCase;

final class ParticipantNotificationControllerTest extends TestCase
{
    private FakeNotificationRepository $repository;

    private ParticipantNotificationController $controller;

    protected function setUp(): void
    {
        $_SESSION = [];
        $session = new Session(false);
        $users = new FakeUserRepository();
        $users->users[7] = ['id' => 7, 'role_id' => 3, 'name' => 'Participant', 'email' => 'participant@example.test', 'status' => 'active', 'email_verified_at' => '2026-08-01 09:00:00'];
        $session->put('auth.user_id', 7);
        $security = new Security($session);
        $this->repository = new FakeNotificationRepository();
        $this->repository->createForUser(7, ['type' => 'ticket_issued', 'title' => 'Ready', 'message' => 'Your ticket is ready.', 'action_url' => '/participant/tickets/4', 'data' => []]);
        $this->repository->createForUser(8, ['type' => 'ticket_issued', 'title' => 'Private', 'message' => 'Not yours.', 'action_url' => '/participant/tickets/5', 'data' => []]);
        $this->controller = new ParticipantNotificationController(new View(base_path('app/Views')), $session, $security, new Auth($session, $users), new Config(['name' => 'OEMS']), $this->repository);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testOnlyParticipantOwnerCanReadOwnNotifications(): void
    {
        $index = $this->controller->index(Request::create('GET', '/participant/notifications'));
        $foreign = $this->controller->markRead(Request::create('POST', '/participant/notifications/2/read')->withRouteParameters(['id' => '2']));
        $owned = $this->controller->markRead(Request::create('POST', '/participant/notifications/1/read')->withRouteParameters(['id' => '1']));

        $this->assertTrue(str_contains($index->body(), 'Your ticket is ready.'));
        $this->assertFalse(str_contains($index->body(), 'Not yours.'));
        $this->assertSame('/participant/notifications', $foreign->header('Location'));
        $this->assertNull($this->repository->notifications[2]['read_at']);
        $this->assertSame('/participant/notifications', $owned->header('Location'));
        $this->assertNotNull($this->repository->notifications[1]['read_at']);
    }

    public function testReadRoutesRequireParticipantRoleCsrfAndPostMethod(): void
    {
        $_SESSION = [];
        $session = new Session(false);
        $users = new FakeUserRepository();
        $users->users[7] = ['id' => 7, 'role_id' => 3, 'name' => 'Participant', 'email' => 'participant@example.test', 'status' => 'active', 'email_verified_at' => '2026-08-01 09:00:00'];
        $session->put('auth.user_id', 7);
        $auth = new Auth($session, $users);
        $security = new Security($session);
        $container = new Container();
        $router = new Router($container);
        $router->aliasMiddleware('role', new RoleMiddleware($auth));
        $router->aliasMiddleware('csrf', new CsrfMiddleware($security));
        $routes = require base_path('routes/web.php');
        $routes($router);

        $this->assertSame(419, $router->dispatch(Request::create('POST', '/participant/notifications/1/read', input: ['_token' => 'invalid']))->status());
        $this->assertSame(405, $router->dispatch(Request::create('GET', '/participant/notifications/1/read'))->status());
    }

    public function testNotificationHistoryProvidesPaginationNavigation(): void
    {
        for ($id = 0; $id < 20; $id++) {
            $this->repository->createForUser(7, [
                'type' => 'ticket_issued',
                'title' => 'Ticket update',
                'message' => 'Your ticket update is ready.',
                'action_url' => '/participant/tickets/4',
                'data' => [],
            ]);
        }

        $body = $this->controller->index(Request::create('GET', '/participant/notifications?page=1', query: ['page' => 1]))->body();

        $this->assertTrue(str_contains($body, 'Page 1 of 2'));
        $this->assertTrue(str_contains($body, 'href="/participant/notifications?page=2"'));
    }
}
