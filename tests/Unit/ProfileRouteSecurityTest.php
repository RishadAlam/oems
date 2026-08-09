<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Controllers\ParticipantRegistrationController;
use OEMS\App\Controllers\ParticipantReviewController;
use OEMS\App\Controllers\ProfileController;
use OEMS\App\Middleware\AuthMiddleware;
use OEMS\App\Middleware\CsrfMiddleware;
use OEMS\App\Middleware\RoleMiddleware;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Container;
use OEMS\Core\Request;
use OEMS\Core\Router;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakeProfileRepository;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\TestCase;

final class ProfileRouteSecurityTest extends TestCase
{
    public function testGuestCannotOpenTheProfileRoute(): void
    {
        [$router] = $this->profileRouter();

        $response = $router->dispatch(Request::create('GET', '/profile'));

        $this->assertSame(302, $response->status());
        $this->assertSame('/login', $response->header('Location'));
    }

    public function testInvalidCsrfTokenCannotUpdateTheProfile(): void
    {
        [$router, $profiles] = $this->profileRouter(7);

        $response = $router->dispatch(Request::create('POST', '/profile', input: [
            '_token' => 'invalid',
            'name' => 'Blocked Update',
            'locale' => 'en',
            'timezone' => 'Asia/Dhaka',
        ]));

        $this->assertSame(419, $response->status());
        $this->assertSame([], $profiles->updates);
    }

    public function testProfileUpdateAlwaysUsesTheAuthenticatedUserId(): void
    {
        [$router, $profiles, $security] = $this->profileRouter(7);

        $response = $router->dispatch(Request::create('POST', '/profile', input: [
            '_token' => $security->csrfToken(),
            'user_id' => '99',
            'name' => 'Authenticated Owner',
            'locale' => 'en',
            'timezone' => 'Asia/Dhaka',
        ]));

        $this->assertSame(302, $response->status());
        $this->assertSame('/profile', $response->header('Location'));
        $this->assertArrayHasKey(7, $profiles->updates);
        $this->assertFalse(array_key_exists(99, $profiles->updates));
    }

    public function testParticipantTransactionRoutesRequireAuthenticationAndParticipantRole(): void
    {
        $routes = [
            ['GET', '/participant/events/future-craft/register'],
            ['POST', '/participant/events/future-craft/register'],
            ['GET', '/participant/registrations'],
            ['GET', '/participant/registrations/12'],
            ['POST', '/participant/registrations/12/cancel'],
            ['GET', '/participant/tickets'],
            ['GET', '/participant/tickets/41'],
            ['GET', '/participant/tickets/41/qr'],
            ['GET', '/participant/tickets/41/pdf'],
        ];

        foreach ($routes as [$method, $uri]) {
            [$guestRouter] = $this->profileRouter();
            $this->assertSame('/login', $guestRouter->dispatch(Request::create($method, $uri))->header('Location'));
            [$organizerRouter] = $this->profileRouter(8, 'organizer');
            $this->assertSame(403, $organizerRouter->dispatch(Request::create($method, $uri))->status());
        }
    }

    public function testParticipantMutationsRequireCsrfAndUnsupportedMethodsReturn405(): void
    {
        [$router, , $security] = $this->profileRouter(7, 'participant');

        foreach (['/participant/events/future-craft/register', '/participant/registrations/12/cancel'] as $uri) {
            $blocked = $router->dispatch(Request::create('POST', $uri, input: ['_token' => 'invalid']));
            $this->assertSame(419, $blocked->status());
        }

        foreach (['/participant/registrations', '/participant/tickets/41/pdf', '/participant/registrations/12/cancel'] as $uri) {
            $this->assertSame(405, $router->dispatch(Request::create('PUT', $uri))->status());
        }
        $this->assertNotSame('invalid', $security->csrfToken());
    }

    public function testParticipantWriteControllersRequireARateLimiterDependency(): void
    {
        foreach ([ParticipantRegistrationController::class, ParticipantReviewController::class] as $controller) {
            $parameter = (new \ReflectionClass($controller))->getConstructor()?->getParameters();
            $limiter = is_array($parameter) ? $parameter[array_key_last($parameter)] : null;

            $this->assertNotNull($limiter);
            $this->assertSame('limiter', $limiter->getName());
            $this->assertFalse($limiter->allowsNull());
            $this->assertFalse($limiter->isDefaultValueAvailable());
        }
    }

    private function profileRouter(?int $authenticatedUserId = null, string $role = 'participant'): array
    {
        $_SESSION = [];
        $session = new Session(false);
        $users = new FakeUserRepository();

        if ($authenticatedUserId !== null) {
            $users->users[$authenticatedUserId] = [
                'id' => $authenticatedUserId,
                'role_id' => $role === 'organizer' ? 2 : 3,
                'name' => 'Profile Owner',
                'email' => 'owner@example.test',
                'password' => password_hash('secure-password', PASSWORD_DEFAULT),
                'status' => 'active',
                'email_verified_at' => '2026-08-06 10:00:00',
            ];
            $session->put('auth.user_id', $authenticatedUserId);
        }

        $auth = new Auth($session, $users);
        $security = new Security($session);
        $profiles = new FakeProfileRepository();
        $container = new Container();
        $controller = new ProfileController(
            new View(base_path('app/Views')),
            $session,
            $security,
            $auth,
            new Config(['name' => 'OEMS']),
            $profiles,
        );
        $container->instance(ProfileController::class, $controller);
        $router = new Router($container);
        $router->aliasMiddleware('auth', new AuthMiddleware($auth));
        $router->aliasMiddleware('role', new RoleMiddleware($auth));
        $router->aliasMiddleware('csrf', new CsrfMiddleware($security));
        $registerRoutes = require base_path('routes/web.php');
        $registerRoutes($router);

        return [$router, $profiles, $security];
    }
}
