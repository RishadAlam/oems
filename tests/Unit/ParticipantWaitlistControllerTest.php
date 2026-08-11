<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Controllers\ParticipantWaitlistController;
use OEMS\App\Middleware\CsrfMiddleware;
use OEMS\App\Middleware\RoleMiddleware;
use OEMS\App\Services\WaitlistService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Container;
use OEMS\Core\Request;
use OEMS\Core\Router;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\FakeWaitlistRepository;
use OEMS\Tests\Support\TestCase;

final class ParticipantWaitlistControllerTest extends TestCase
{
    private FakeWaitlistRepository $waitlists;

    private mixed $controller = null;

    protected function setUp(): void
    {
        $_SESSION = [];
        $session = new Session(false);
        $users = $this->users();
        $this->authenticateSession($session, $users, 7);
        $this->waitlists = new FakeWaitlistRepository();
        $this->waitlists->events[41] = [
            'id' => 41,
            'title' => 'Sold out <Craft>',
            'slug' => 'sold-out-craft',
            'ticket_price' => '1250.00',
            'currency' => 'BDT',
        ];
        $auth = new Auth($session, $users);
        if (class_exists(ParticipantWaitlistController::class)) {
            $this->controller = new ParticipantWaitlistController(
                new View(base_path('app/Views')),
                $session,
                new Security($session),
                $auth,
                new Config(['name' => 'OEMS', 'timezone' => 'Asia/Dhaka']),
                new WaitlistService($users, $this->waitlists),
            );
        }
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testParticipantCanJoinViewPositionAndLeaveOwnedWaitlistEntry(): void
    {
        $controller = $this->controller();
        $joined = $controller->store(Request::create('POST', '/participant/events/41/waitlist', input: [
            'user_id' => 99,
        ])->withRouteParameters(['id' => '41']));
        $this->assertSame('/participant/waitlist', $joined->header('Location'));
        $entry = array_values($this->waitlists->entries)[0];
        $this->assertSame(7, $entry['user_id']);

        $body = $controller->index(Request::create('GET', '/participant/waitlist'))->body();
        $this->assertTrue(str_contains($body, 'Sold out &lt;Craft&gt;'));
        $this->assertTrue(str_contains($body, 'Position 1'));
        $this->assertTrue(str_contains($body, '৳1,250'));
        $this->assertTrue(str_contains($body, 'name="reason"'));
        $this->assertTrue(str_contains($body, 'aria-describedby="waitlist-reason-help-'));
        $this->assertTrue(str_contains($body, 'data-form-kind="entry"'));
        $this->assertFalse(str_contains($body, 'method="post" novalidate'));
        $this->assertTrue(str_contains($body, 'data-submit-label="Leaving waitlist…"'));

        $left = $controller->destroy(Request::create('POST', '/participant/waitlist/' . $entry['id'] . '/leave', input: [
            'reason' => 'Schedule conflict',
        ])->withRouteParameters(['id' => (string) $entry['id']]));
        $this->assertSame('/participant/waitlist', $left->header('Location'));
        $this->assertSame('cancelled', $this->waitlists->entries[$entry['id']]['status']);
    }

    public function testInvalidIdentifiersAndReasonsFailWithoutMutating(): void
    {
        $controller = $this->controller();
        $this->assertSame(404, $controller->store(Request::create('POST', '/participant/events/no/waitlist')->withRouteParameters(['id' => 'no']))->status());

        $controller->store(Request::create('POST', '/participant/events/41/waitlist')->withRouteParameters(['id' => '41']));
        $entry = array_values($this->waitlists->entries)[0];
        $response = $controller->destroy(Request::create('POST', '/participant/waitlist/' . $entry['id'] . '/leave', input: [
            'reason' => '',
        ])->withRouteParameters(['id' => (string) $entry['id']]));
        $this->assertSame('/participant/waitlist', $response->header('Location'));
        $this->assertSame('waitlisted', $this->waitlists->entries[$entry['id']]['status']);
    }

    public function testWaitlistRoutesRequireParticipantRoleAndCsrfAndRejectWrongMethods(): void
    {
        [$guest] = $this->router();
        $this->assertSame('/login', $guest->dispatch(Request::create('POST', '/participant/events/41/waitlist'))->header('Location'));

        [$organizer] = $this->router(8, 'organizer');
        $this->assertSame(403, $organizer->dispatch(Request::create('POST', '/participant/events/41/waitlist'))->status());

        [$participant] = $this->router(7, 'participant');
        $this->assertSame(419, $participant->dispatch(Request::create('POST', '/participant/events/41/waitlist', input: ['_token' => 'bad']))->status());
        $this->assertSame(405, $participant->dispatch(Request::create('GET', '/participant/events/41/waitlist'))->status());
        $this->assertSame(405, $participant->dispatch(Request::create('GET', '/participant/waitlist/1/leave'))->status());
    }

    private function controller(): ParticipantWaitlistController
    {
        $this->assertTrue($this->controller instanceof ParticipantWaitlistController, 'Participant waitlist controller is missing.');
        return $this->controller;
    }

    private function users(): FakeUserRepository
    {
        $users = new FakeUserRepository();
        $users->users[7] = ['id' => 7, 'role_id' => 3, 'role_slug' => 'participant', 'name' => 'Participant', 'email' => 'participant@example.test', 'password' => password_hash('secret-password', PASSWORD_DEFAULT), 'status' => 'active', 'email_verified_at' => '2026-08-01 10:00:00'];
        $users->users[8] = ['id' => 8, 'role_id' => 2, 'role_slug' => 'organizer', 'name' => 'Organizer', 'email' => 'organizer@example.test', 'password' => password_hash('secret-password', PASSWORD_DEFAULT), 'status' => 'active', 'email_verified_at' => '2026-08-01 10:00:00'];
        return $users;
    }

    private function router(?int $userId = null, string $role = 'participant'): array
    {
        $_SESSION = [];
        $session = new Session(false);
        $users = $this->users();
        if ($userId !== null) {
            $this->authenticateSession($session, $users, $userId);
        }
        $auth = new Auth($session, $users);
        $container = new Container();
        $router = new Router($container);
        $router->aliasMiddleware('role', new RoleMiddleware($auth));
        $router->aliasMiddleware('csrf', new CsrfMiddleware(new Security($session)));
        $register = require base_path('routes/web.php');
        $register($router);
        return [$router];
    }
}
