<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Controllers\HomeController;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Request;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\FakeFavoriteRepository;
use OEMS\Tests\Support\FakeEventRepository;
use OEMS\Tests\Support\TestCase;

final class HomeControllerTest extends TestCase
{
    private HomeController $controller;

    private FakeEventRepository $events;

    protected function setUp(): void
    {
        $_SESSION = [];
        $session = new Session(false);
        $this->events = new FakeEventRepository();
        $this->controller = new HomeController(
            new View(base_path('app/Views')),
            $session,
            new Security($session),
            new Auth($session, new FakeUserRepository()),
            new Config(['name' => 'OEMS']),
            $this->events,
        );
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testHomeUsesFeaturedRepositoryEventsAndLinksCardsBySlug(): void
    {
        $this->events->events[41] = $this->eventFixture(41, 'Database Design Circle', 'database-design-circle');
        $this->events->events[42] = $this->eventFixture(42, 'Community Sound Lab', 'community-sound-lab');
        $this->events->events[43] = $this->eventFixture(43, 'Third Event', 'third-event');

        $response = $this->controller->index(Request::create('GET', '/'));

        $this->assertSame(200, $response->status());
        $this->assertTrue(str_contains($response->body(), 'Database Design Circle'));
        $this->assertTrue(str_contains($response->body(), 'Community Sound Lab'));
        $this->assertFalse(str_contains($response->body(), 'Third Event'));
        $this->assertTrue(str_contains($response->body(), 'href="/events/database-design-circle"'));
        $this->assertFalse(str_contains($response->body(), 'Designing for public life'));
        $this->assertFalse(str_contains($response->body(), 'This week in Dhaka'));
        $this->assertFalse(str_contains($response->body(), 'weekend'));
    }

    public function testHomeRendersAnHonestEmptyFeaturedState(): void
    {
        $response = $this->controller->index(Request::create('GET', '/'));

        $this->assertSame(200, $response->status());
        $this->assertTrue(str_contains($response->body(), 'No featured events yet'));
        $this->assertTrue(str_contains($response->body(), 'Browse all events'));
    }

    public function testParticipantFeaturedCardsUseOneBulkFavoriteLookupAndAccessibleControls(): void
    {
        $this->events->events[41] = $this->eventFixture(41, 'Saved featured event', 'saved-featured-event');
        $this->events->events[42] = $this->eventFixture(42, 'Unsaved featured event', 'unsaved-featured-event');
        $session = new Session(false);
        $users = new FakeUserRepository();
        $users->users[7] = [
            'id' => 7,
            'role_id' => 3,
            'name' => 'Participant',
            'email' => 'participant@example.test',
            'status' => 'active',
            'email_verified_at' => '2026-08-01 10:00:00',
        ];
        $session->put('auth.user_id', 7);
        $favorites = new FakeFavoriteRepository();
        $favorites->favorites[7][41] = true;
        $controller = new HomeController(
            new View(base_path('app/Views')),
            $session,
            new Security($session),
            new Auth($session, $users),
            new Config(['name' => 'OEMS']),
            $this->events,
            $favorites,
        );

        $body = $controller->index(Request::create('GET', '/'))->body();

        $this->assertSame(1, $favorites->bulkStateCalls);
        $this->assertTrue(str_contains($body, 'action="/participant/favorites/41/remove"'));
        $this->assertTrue(str_contains($body, 'aria-label="Remove Saved featured event from saved events"'));
        $this->assertTrue(str_contains($body, 'action="/participant/favorites/42"'));
        $this->assertTrue(str_contains($body, 'aria-label="Save Unsaved featured event"'));
    }

    private function eventFixture(int $id, string $title, string $slug): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'slug' => $slug,
            'description' => 'A repository-backed featured event.',
            'banner' => '/assets/images/event-creative.webp',
            'start_date' => '2026-08-22 10:00:00',
            'end_date' => '2026-08-22 12:00:00',
            'ticket_price' => '0.00',
            'currency' => 'BDT',
            'category_name' => 'Technology',
            'venue_name' => 'Dhaka Hall',
            'venue_city' => 'Dhaka',
            'venue_country' => 'Bangladesh',
            'status' => 'published',
            'deleted_at' => null,
        ];
    }
}
