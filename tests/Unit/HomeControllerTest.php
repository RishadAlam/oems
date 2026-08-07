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
