<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Controllers\OrganizerAnnouncementController;
use OEMS\App\Middleware\CsrfMiddleware;
use OEMS\App\Middleware\RoleMiddleware;
use OEMS\App\Services\AnnouncementService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Container;
use OEMS\Core\Request;
use OEMS\Core\Router;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakeAnnouncementRepository;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\TestCase;

final class OrganizerAnnouncementControllerTest extends TestCase
{
    private Session $session;

    private Security $security;

    private FakeAnnouncementRepository $announcements;

    private OrganizerAnnouncementController $controller;

    protected function setUp(): void
    {
        $_SESSION = [];
        $_SERVER['REQUEST_URI'] = '/organizer/events/11/announcements';
        $this->session = new Session(false);
        $this->security = new Security($this->session);
        $users = $this->users('organizer', $this->session);
        $auth = new Auth($this->session, $users);
        $this->announcements = new FakeAnnouncementRepository();
        $this->announcements->events[11] = [
            'id' => 11,
            'user_id' => 10,
            'title' => '<b>Published event</b>',
            'status' => 'published',
            'organizer_approval_status' => 'approved',
            'organizer_user_status' => 'active',
            'organizer_email_verified_at' => '2026-08-01 09:00:00',
            'organizer_deleted_at' => null,
            'organizer_role' => 'organizer',
            'deleted_at' => null,
        ];
        $this->announcements->events[12] = array_merge($this->announcements->events[11], [
            'id' => 12,
            'user_id' => 20,
            'title' => 'Foreign event',
        ]);
        $this->controller = new OrganizerAnnouncementController(
            new View(base_path('app/Views')),
            $this->session,
            $this->security,
            $auth,
            new Config(['name' => 'OEMS']),
            new AnnouncementService($this->announcements),
        );
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        unset($_SERVER['REQUEST_URI']);
    }

    public function testHistoryAndComposerAreEscapedResponsiveAndAccessible(): void
    {
        $this->announcements->announcements[3] = [
            'id' => 3,
            'event_id' => 11,
            'subject' => '<script>alert(1)</script>',
            'message' => "Line one\n<img src=x onerror=alert(2)>",
            'audience' => 'confirmed',
            'recipient_count' => 4,
            'sent_at' => '2026-08-10 11:00:00',
            'author_name' => '<b>Organizer</b>',
        ];

        $index = $this->controller->index($this->routed('GET', '/organizer/events/11/announcements', '11'));
        $create = $this->controller->create($this->routed('GET', '/organizer/events/11/announcements/create', '11'));

        $this->assertSame(200, $index->status());
        $this->assertTrue(str_contains($index->body(), '&lt;b&gt;Published event&lt;/b&gt;'));
        $this->assertTrue(str_contains($index->body(), '&lt;script&gt;alert(1)&lt;/script&gt;'));
        $this->assertTrue(str_contains($index->body(), '&lt;img src=x onerror=alert(2)&gt;'));
        $this->assertTrue(str_contains($index->body(), '&lt;b&gt;Organizer&lt;/b&gt;'));
        $this->assertFalse(str_contains($index->body(), '<script>alert'));
        $this->assertTrue(str_contains($index->body(), 'data-label="Announcement"'));
        $this->assertTrue(str_contains($index->body(), '4 recipients'));
        $this->assertSame(200, $create->status());
        $this->assertTrue(str_contains($create->body(), 'maxlength="180"'));
        $this->assertTrue(str_contains($create->body(), 'maxlength="1000"'));
        $this->assertTrue(str_contains($create->body(), 'aria-describedby="announcement-message-help"'));
        $this->assertTrue(str_contains($create->body(), 'Review announcement'));
    }

    public function testInvalidComposerInputPreservesOnlyBoundedScalarFields(): void
    {
        $response = $this->controller->store($this->routed('POST', '/organizer/events/11/announcements', '11', [
            'subject' => ['nested'],
            'message' => str_repeat('m', 1001),
            'request_key' => str_repeat('a', 64),
            'password' => 'must-not-survive',
        ]));

        $this->assertSame('/organizer/events/11/announcements/create', $response->header('Location'));
        $this->assertArrayHasKey('subject', $this->session->get('_flash.errors', []));
        $this->assertArrayHasKey('message', $this->session->get('_flash.errors', []));
        $old = $this->session->get('_flash.old', []);
        $this->assertFalse(array_key_exists('subject', $old));
        $this->assertSame(1000, mb_strlen((string) ($old['message'] ?? '')));
        $this->assertFalse(array_key_exists('request_key', $old));
        $this->assertFalse(array_key_exists('password', $old));
    }

    public function testValidReviewCreatesServerBoundConfirmationThenSendsAndReplaysTruthfully(): void
    {
        $review = $this->controller->store($this->routed('POST', '/organizer/events/11/announcements', '11', [
            'subject' => '  Doors open earlier  ',
            'message' => '  Arrive at 8:30 AM.  ',
        ]));
        preg_match('/name="request_key" value="([a-f0-9]{64})"/', $review->body(), $match);
        $requestKey = $match[1] ?? '';

        $this->assertSame(200, $review->status());
        $this->assertSame(64, strlen($requestKey));
        $this->assertTrue(str_contains($review->body(), 'Confirm announcement send'));
        $this->assertTrue(str_contains($review->body(), 'This will notify every currently eligible participant'));
        $this->assertTrue(str_contains($review->body(), 'Doors open earlier'));

        $sent = $this->controller->store($this->routed('POST', '/organizer/events/11/announcements', '11', [
            'confirm_send' => '1',
            'request_key' => $requestKey,
        ], ['User-Agent' => 'Organizer browser'], ['REMOTE_ADDR' => '203.0.113.30']));

        $this->assertSame('/organizer/events/11/announcements', $sent->header('Location'));
        $this->assertSame('Announcement sent to 2 confirmed participants.', $this->session->get('_flash.success'));
        $this->assertSame(1, count($this->announcements->deliveries));
        $this->assertSame('203.0.113.30', $this->announcements->deliveries[0]['context']['ip_address'] ?? null);

        $replay = $this->controller->store($this->routed('POST', '/organizer/events/11/announcements', '11', [
            'confirm_send' => '1',
            'request_key' => $requestKey,
        ]));

        $this->assertSame('/organizer/events/11/announcements', $replay->header('Location'));
        $this->assertTrue(str_contains((string) $this->session->get('_flash.info'), 'already sent'));
        $this->assertSame(1, count($this->announcements->deliveries));
    }

    public function testForgedConfirmationIsConflictAndDoesNotDeliver(): void
    {
        $response = $this->controller->store($this->routed('POST', '/organizer/events/11/announcements', '11', [
            'confirm_send' => '1',
            'request_key' => str_repeat('f', 64),
        ]));

        $this->assertSame(409, $response->status());
        $this->assertTrue(str_contains($response->body(), 'invalid or has expired'));
        $this->assertSame([], $this->announcements->deliveries);
    }

    public function testZeroRecipientOutcomeIsExplicitAndLeavesIntentAvailableForRetry(): void
    {
        $review = $this->controller->store($this->routed('POST', '/organizer/events/11/announcements', '11', [
            'subject' => 'Event reminder',
            'message' => 'Please bring your ticket.',
        ]));
        preg_match('/name="request_key" value="([a-f0-9]{64})"/', $review->body(), $match);
        $this->announcements->forcedDeliveryResult = ['status' => 'no_recipients'];

        $response = $this->controller->store($this->routed('POST', '/organizer/events/11/announcements', '11', [
            'confirm_send' => '1',
            'request_key' => $match[1] ?? '',
        ]));

        $this->assertSame(409, $response->status());
        $this->assertTrue(str_contains($response->body(), 'No active, verified participants'));
        $this->assertTrue(str_contains($response->body(), 'Confirm announcement send'));
    }

    public function testMissingForeignAndMalformedEventIdsReturnNotFound(): void
    {
        foreach (['12', '999', '0', '-1', 'event'] as $id) {
            $this->assertSame(404, $this->controller->index($this->routed('GET', '/organizer/events/' . $id . '/announcements', $id))->status());
            $this->assertSame(404, $this->controller->create($this->routed('GET', '/organizer/events/' . $id . '/announcements/create', $id))->status());
        }
    }

    public function testAnnouncementRoutesRequireOrganizerRoleCsrfAndCorrectMethods(): void
    {
        foreach (['/organizer/events/11/announcements', '/organizer/events/11/announcements/create'] as $uri) {
            $participant = $this->router('participant');
            $this->assertSame(403, $participant['router']->dispatch(Request::create('GET', $uri))->status());
        }

        $participant = $this->router('participant');
        $this->assertSame(403, $participant['router']->dispatch(Request::create('POST', '/organizer/events/11/announcements', input: [
            '_token' => $participant['security']->csrfToken(),
        ]))->status());
        $organizer = $this->router('organizer');
        $this->assertSame(419, $organizer['router']->dispatch(Request::create('POST', '/organizer/events/11/announcements', input: [
            '_token' => 'invalid',
        ]))->status());
        $this->assertSame(405, $organizer['router']->dispatch(Request::create('PUT', '/organizer/events/11/announcements'))->status());
    }

    private function router(string $role): array
    {
        $_SESSION = [];
        $session = new Session(false);
        $security = new Security($session);
        $users = $this->users($role, $session);
        $auth = new Auth($session, $users);
        $container = new Container();
        $container->instance(OrganizerAnnouncementController::class, $this->controller);
        $router = new Router($container);
        $router->aliasMiddleware('role', new RoleMiddleware($auth));
        $router->aliasMiddleware('csrf', new CsrfMiddleware($security));
        $routes = require base_path('routes/web.php');
        $routes($router);

        return ['router' => $router, 'security' => $security];
    }

    private function users(string $role, Session $session): FakeUserRepository
    {
        $users = new FakeUserRepository();
        $users->users[10] = [
            'id' => 10,
            'role_id' => $role === 'organizer' ? 2 : 3,
            'name' => 'Organizer',
            'email' => 'organizer@example.test',
            'password' => password_hash('DemoPass!2026', PASSWORD_DEFAULT),
            'status' => 'active',
            'email_verified_at' => '2026-08-01 09:00:00',
        ];
        $this->authenticateSession($session, $users, 10);

        return $users;
    }

    private function routed(
        string $method,
        string $uri,
        string $id,
        array $input = [],
        array $headers = [],
        array $server = [],
    ): Request {
        return Request::create($method, $uri, input: $input, headers: $headers, server: $server)
            ->withRouteParameters(['id' => $id]);
    }
}
