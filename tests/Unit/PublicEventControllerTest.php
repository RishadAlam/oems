<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Contracts\EventRepositoryInterface;
use OEMS\App\Controllers\PublicEventController;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Request;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakeCategoryRepository;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\TestCase;

final class PublicEventRepositorySpy implements EventRepositoryInterface
{
    public array $filters = [];

    public array $events = [];

    public array $galleries = [];

    public array $cities = ['Dhaka', 'Sylhet'];

    public function featured(int $limit): array
    {
        return array_slice($this->events, 0, $limit);
    }

    public function publicSearch(array $filters): array
    {
        $this->filters = $filters;

        return array_values($this->events);
    }

    public function publicCities(): array
    {
        return $this->cities;
    }

    public function findPublishedBySlug(string $slug): ?array
    {
        return $this->events[$slug] ?? null;
    }

    public function gallery(int $eventId): array
    {
        return $this->galleries[$eventId] ?? [];
    }

    public function organizerSummary(int $userId): array { return []; }
    public function forOrganizerUser(int $userId, ?string $status): array { return []; }
    public function recentForOrganizerUser(int $userId, int $limit): array { return []; }
    public function findOwned(int $userId, int $eventId): ?array { return null; }
    public function slugExists(string $slug, ?int $exceptId): bool { return false; }
    public function createForUser(int $userId, array $attributes): ?int { return null; }
    public function createWithGalleryForUser(int $userId, array $attributes, array $images): ?int { return null; }
    public function updateOwned(int $userId, int $eventId, array $attributes): bool { return false; }
    public function updateWithGalleryOwned(int $userId, int $eventId, array $attributes, ?array $images): ?array { return null; }
    public function softDeleteOwned(int $userId, int $eventId, array $context): bool { return false; }
    public function transitionOwned(int $userId, int $eventId, array $context, string $status): bool { return false; }
    public function forAdmin(?string $status): array { return []; }
    public function findForAdmin(int $eventId): ?array { return null; }
    public function transitionAdmin(int $userId, int $eventId, array $context, string $status, ?string $reason): bool { return false; }
    public function replaceGallery(int $eventId, array $images): void {}
    public function deleteGalleryImageOwned(int $userId, int $eventId, int $imageId): ?string { return null; }
}

final class PublicEventControllerTest extends TestCase
{
    private PublicEventRepositorySpy $events;

    private FakeCategoryRepository $categories;

    private PublicEventController $controller;

    protected function setUp(): void
    {
        $_SESSION = [];
        $session = new Session(false);
        $this->events = new PublicEventRepositorySpy();
        $this->categories = new FakeCategoryRepository();
        $this->controller = new PublicEventController(
            new View(base_path('app/Views')),
            $session,
            new Security($session),
            new Auth($session, new FakeUserRepository()),
            new Config([
                'name' => 'OEMS',
                'url' => 'https://events.example.test',
                'timezone' => 'Asia/Dhaka',
            ]),
            $this->events,
            $this->categories,
        );
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testIndexSendsNormalizedAllowListedFiltersToTheRepositoryAndPreservesThem(): void
    {
        $this->events->events = [$this->eventFixture()];

        $response = $this->controller->index(Request::create('GET', '/events', [
            'search' => '  Future craft  ',
            'category' => ' TECHNOLOGY ',
            'city' => ' dhaka ',
            'date' => 'THIS_WEEK',
            'price' => 'FREE',
            'sort' => 'LATEST',
        ]));

        $this->assertSame([
            'search' => 'Future craft',
            'category' => 'technology',
            'city' => 'Dhaka',
            'date' => 'this_week',
            'price' => 'free',
            'sort' => 'latest',
        ], $this->events->filters);
        $this->assertTrue(str_contains($response->body(), 'value="Future craft"'));
        $this->assertTrue(str_contains($response->body(), 'value="technology" selected'));
        $this->assertTrue(str_contains($response->body(), 'value="Dhaka" selected'));
        $this->assertTrue(str_contains($response->body(), 'value="this_week" selected'));
        $this->assertTrue(str_contains($response->body(), 'value="free" selected'));
        $this->assertTrue(str_contains($response->body(), 'value="latest" selected'));
        $this->assertTrue(str_contains($response->body(), 'href="/events/future-craft"'));
        $this->assertTrue(str_contains($response->body(), 'href="/events"'));
    }

    public function testIndexFallsBackToSafeFilterDefaultsForUnsupportedValues(): void
    {
        $this->controller->index(Request::create('GET', '/events', [
            'category' => 'archived',
            'city' => 'Unknown',
            'date' => 'tomorrow',
            'price' => 'any-price',
            'sort' => 'DROP TABLE events',
        ]));

        $this->assertSame([
            'search' => '',
            'category' => '',
            'city' => '',
            'date' => 'upcoming',
            'price' => '',
            'sort' => 'soonest',
        ], $this->events->filters);
    }

    public function testShowReturnsBranded404ForUnpublishedOrDeletedEvents(): void
    {
        $this->events->events['draft-event'] = array_merge($this->eventFixture(), [
            'slug' => 'draft-event',
            'status' => 'draft',
        ]);
        $this->events->events['deleted-event'] = array_merge($this->eventFixture(), [
            'slug' => 'deleted-event',
            'deleted_at' => '2026-08-07 10:00:00',
        ]);

        foreach (['draft-event', 'deleted-event', 'missing-event'] as $slug) {
            $response = $this->controller->show(
                Request::create('GET', '/events/' . $slug)->withRouteParameters(['slug' => $slug]),
            );

            $this->assertSame(404, $response->status());
            $this->assertTrue(str_contains($response->body(), 'class="error-state"'));
            $this->assertTrue(str_contains($response->body(), 'Return home'));
        }
    }

    public function testShowRendersSemanticEventDetailsGalleryAndHonestRegistrationState(): void
    {
        $event = $this->eventFixture();
        $this->events->events[$event['slug']] = $event;
        $this->events->galleries[501] = [
            ['image_path' => '/uploads/events/workshop.jpg', 'alt_text' => 'Guests making objects together'],
        ];

        $response = $this->controller->show(
            Request::create('GET', '/events/future-craft')->withRouteParameters(['slug' => 'future-craft']),
        );
        $body = $response->body();

        $this->assertSame(200, $response->status());
        $this->assertTrue(str_contains($body, '<time datetime="2026-08-22T10:00:00+06:00">'));
        $this->assertTrue(str_contains($body, '<time datetime="2026-08-22T12:30:00+06:00">'));
        $this->assertTrue(str_contains($body, '<address'));
        $this->assertTrue(str_contains($body, 'Dhaka Arts Hall'));
        $this->assertTrue(str_contains($body, 'Guests making objects together'));
        $this->assertTrue(str_contains($body, 'OEMS Studio'));
        $this->assertTrue(str_contains($body, 'craft'));
        $this->assertTrue(str_contains($body, '120 total places'));
        $this->assertTrue(str_contains($body, 'Registration opens in Week 3'));
        $this->assertFalse(str_contains($body, '>Register now<'));
        $this->assertFalse(str_contains($body, '>Get tickets<'));
    }

    public function testShowRendersCanonicalOpenGraphAndHexEscapedJsonLd(): void
    {
        $event = array_merge($this->eventFixture(), [
            'title' => 'Craft </script><script>alert("x")</script> & Friends',
            'description' => 'A practical gathering for makers & neighbors.',
        ]);
        $this->events->events[$event['slug']] = $event;

        $body = $this->controller->show(
            Request::create('GET', '/events/future-craft')->withRouteParameters(['slug' => 'future-craft']),
        )->body();

        $this->assertTrue(str_contains($body, '<link rel="canonical" href="https://events.example.test/events/future-craft">'));
        $this->assertTrue(str_contains($body, '<meta property="og:type" content="event">'));
        $this->assertTrue(str_contains($body, '<meta property="og:url" content="https://events.example.test/events/future-craft">'));
        $this->assertTrue(str_contains($body, '<script type="application/ld+json">'));
        $this->assertTrue(str_contains($body, '\\u003C/script\\u003E\\u003Cscript\\u003E'));
        $this->assertTrue(str_contains($body, '\\u0026 Friends'));
        $this->assertTrue(str_contains($body, '"availabilityEnds":"2026-08-21T18:00:00+06:00"'));
        $this->assertFalse(str_contains($body, '"validFrom"'));
        $this->assertFalse(str_contains($body, '</script><script>alert'));
    }

    public function testShowUsesTheRenderedFallbackBannerInShareMetadata(): void
    {
        $event = array_merge($this->eventFixture(), ['banner' => null]);
        $this->events->events[$event['slug']] = $event;

        $body = $this->controller->show(
            Request::create('GET', '/events/future-craft')->withRouteParameters(['slug' => 'future-craft']),
        )->body();

        $this->assertTrue(str_contains($body, '<meta property="og:image" content="https://events.example.test/assets/images/event-creative.webp">'));
        $this->assertTrue(str_contains($body, '"image":["https://events.example.test/assets/images/event-creative.webp"]'));
    }

    private function eventFixture(): array
    {
        return [
            'id' => 501,
            'title' => 'Future Craft',
            'slug' => 'future-craft',
            'description' => 'A practical gathering for curious makers.',
            'banner' => '/uploads/events/future-craft.jpg',
            'speaker' => 'Samira Noor',
            'start_date' => '2026-08-22 10:00:00',
            'end_date' => '2026-08-22 12:30:00',
            'registration_deadline' => '2026-08-21 18:00:00',
            'capacity' => 120,
            'available_seats' => 120,
            'ticket_price' => '600.00',
            'currency' => 'BDT',
            'tags' => ['craft', 'community'],
            'status' => 'published',
            'deleted_at' => null,
            'category_name' => 'Technology',
            'category_slug' => 'technology',
            'venue_name' => 'Dhaka Arts Hall',
            'venue_city' => 'Dhaka',
            'venue_country' => 'Bangladesh',
            'organization_name' => 'OEMS Studio',
        ];
    }
}
