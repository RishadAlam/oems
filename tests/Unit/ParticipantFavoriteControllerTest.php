<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Controllers\ParticipantFavoriteController;
use OEMS\App\Middleware\CsrfMiddleware;
use OEMS\App\Middleware\RoleMiddleware;
use OEMS\App\Services\FavoriteService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Container;
use OEMS\Core\Request;
use OEMS\Core\Router;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakeFavoriteRepository;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\TestCase;

final class ParticipantFavoriteControllerTest extends TestCase
{
    private FakeFavoriteRepository $favorites;

    private Security $security;

    private mixed $controller = null;

    protected function setUp(): void
    {
        $_SESSION = [];
        $session = new Session(false);
        $users = new FakeUserRepository();
        $users->users[7] = [
            'id' => 7,
            'role_id' => 3,
            'name' => 'Participant Owner',
            'email' => 'participant@example.test',
            'status' => 'active',
            'email_verified_at' => '2026-08-01 10:00:00',
        ];
        $this->favorites = new FakeFavoriteRepository();
        $this->favorites->pages[7] = [
            'items' => [
                ['event_id' => 41, 'title' => 'Future Craft', 'slug' => 'future-craft', 'start_date' => '2026-09-22 10:00:00', 'ticket_price' => '0.00', 'currency' => 'BDT', 'is_available' => true, 'event_status' => 'published'],
                ['event_id' => 42, 'title' => 'Unavailable archive', 'slug' => 'unavailable-archive', 'start_date' => '2026-09-23 10:00:00', 'ticket_price' => '600.00', 'currency' => 'BDT', 'is_available' => false, 'event_status' => 'cancelled'],
            ],
            'pagination' => ['page' => 1, 'per_page' => 12, 'total' => 14, 'last_page' => 2],
        ];
        $auth = new Auth($session, $users);
        $session->put('auth.user_id', 7);
        $this->security = new Security($session);

        if (class_exists(ParticipantFavoriteController::class) && class_exists(FavoriteService::class)) {
            $this->controller = new ParticipantFavoriteController(
                new View(base_path('app/Views')),
                $session,
                $this->security,
                $auth,
                new Config(['name' => 'OEMS', 'timezone' => 'Asia/Dhaka']),
                $this->favorites,
                new FavoriteService($this->favorites, $users),
            );
        }
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testWritesUseAuthenticatedParticipantAndAllowOnlySafeReturnLocations(): void
    {
        $controller = $this->controller();

        $save = $controller->store(Request::create('POST', '/participant/favorites/41', input: [
            'user_id' => 88,
            'return_to' => 'https://attacker.example.test',
        ])->withRouteParameters(['id' => '41']));

        $this->assertSame('/participant/favorites', $save->header('Location'));
        $this->assertTrue($this->favorites->existsForParticipant(7, 41));
        $this->assertFalse($this->favorites->existsForParticipant(88, 41));

        $remove = $controller->destroy(Request::create('POST', '/participant/favorites/41/remove', input: [
            'return_to' => '/events/future-craft',
        ])->withRouteParameters(['id' => '41']));

        $this->assertSame('/events/future-craft', $remove->header('Location'));
        $this->assertFalse($this->favorites->existsForParticipant(7, 41));
    }

    public function testFavoriteHistoryShowsAvailableAndUnavailableItemsWithPagination(): void
    {
        $body = $this->controller()->index(Request::create('GET', '/participant/favorites?page=1'))->body();

        $this->assertTrue(str_contains($body, 'Future Craft'));
        $this->assertTrue(str_contains($body, 'Unavailable archive'));
        $this->assertTrue(str_contains($body, 'Unavailable'));
        $this->assertTrue(str_contains($body, 'Page 1 of 2'));
        $this->assertFalse(str_contains($body, '/events/unavailable-archive'));
    }

    public function testFavoriteWriteRoutesRequireParticipantRoleAndCsrf(): void
    {
        [$guestRouter] = $this->router();
        $this->assertSame('/login', $guestRouter->dispatch(Request::create('POST', '/participant/favorites/41'))->header('Location'));

        [$organizerRouter] = $this->router(8, 'organizer');
        $this->assertSame(403, $organizerRouter->dispatch(Request::create('POST', '/participant/favorites/41'))->status());

        [$participantRouter] = $this->router(7, 'participant');
        $this->assertSame(419, $participantRouter->dispatch(Request::create('POST', '/participant/favorites/41', input: ['_token' => 'invalid']))->status());
        $this->assertSame(405, $participantRouter->dispatch(Request::create('GET', '/participant/favorites/41'))->status());
    }

    private function controller(): ParticipantFavoriteController
    {
        $this->assertTrue($this->controller instanceof ParticipantFavoriteController, 'Participant favorite controller is missing.');

        return $this->controller;
    }

    private function router(?int $userId = null, string $role = 'participant'): array
    {
        $_SESSION = [];
        $session = new Session(false);
        $users = new FakeUserRepository();
        if ($userId !== null) {
            $users->users[$userId] = [
                'id' => $userId,
                'role_id' => $role === 'organizer' ? 2 : 3,
                'name' => 'Route user',
                'email' => 'route@example.test',
                'status' => 'active',
                'email_verified_at' => '2026-08-01 10:00:00',
            ];
            $session->put('auth.user_id', $userId);
        }
        $auth = new Auth($session, $users);
        $security = new Security($session);
        $container = new Container();
        $router = new Router($container);
        $router->aliasMiddleware('role', new RoleMiddleware($auth));
        $router->aliasMiddleware('csrf', new CsrfMiddleware($security));
        $registerRoutes = require base_path('routes/web.php');
        $registerRoutes($router);

        return [$router, $security];
    }
}
