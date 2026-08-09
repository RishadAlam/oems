<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Contracts\EventRepositoryInterface;
use OEMS\App\Controllers\PublicEventController;
use OEMS\App\Services\LocationService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Request;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakeCategoryRepository;
use OEMS\Tests\Support\FakeFavoriteRepository;
use OEMS\Tests\Support\FakeRegistrationRepository;
use OEMS\Tests\Support\FakeReviewRepository;
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

    public function galleryForOwned(int $userId, int $eventId): array { return []; }

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
    public function participantIdsForEventCancellation(int $eventId): array { return []; }
    public function publishOwned(int $userId, int $eventId, array $context): bool { return false; }
    public function forAdmin(?string $status): array { return []; }
    public function countPendingForAdmin(): int { return 0; }
    public function findForAdmin(int $eventId): ?array { return null; }
    public function galleryForAdmin(int $eventId): array { return []; }
    public function transitionAdmin(int $userId, int $eventId, array $context, string $status, ?string $reason): bool { return false; }
    public function replaceGallery(int $eventId, array $images): void {}
    public function deleteGalleryImageOwned(int $userId, int $eventId, int $imageId): ?string { return null; }
}

final class PublicEventControllerTest extends TestCase
{
    private PublicEventRepositorySpy $events;

    private FakeCategoryRepository $categories;

    private PublicEventController $controller;

    private FakeRegistrationRepository $registrations;

    protected function setUp(): void
    {
        $_SESSION = [];
        $session = new Session(false);
        $this->events = new PublicEventRepositorySpy();
        $this->categories = new FakeCategoryRepository();
        $this->registrations = new FakeRegistrationRepository();
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
            $this->registrations,
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

    public function testIndexMergesValidSessionLocationBoundsAndDistanceSort(): void
    {
        $session = new Session(false);
        $session->put('event_location', [
            'latitude' => '23.810',
            'longitude' => '90.413',
            'radius' => 25,
            'label' => 'Current area',
            'source' => 'device',
            'expires_at' => 1_900_000_000,
        ]);
        $locations = new LocationService(1209600, static fn (): int => 1_800_000_000);
        $controller = new PublicEventController(
            new View(base_path('app/Views')),
            $session,
            new Security($session),
            new Auth($session, new FakeUserRepository()),
            new Config(['name' => 'OEMS', 'url' => 'https://events.example.test', 'timezone' => 'Asia/Dhaka']),
            $this->events,
            $this->categories,
            $this->registrations,
            null,
            null,
            $locations,
        );

        $controller->index(Request::create('GET', '/events', ['radius' => '10', 'sort' => 'distance']));

        $this->assertArrayHasKey('latitude', $this->events->filters);
        $this->assertSame('23.810', $this->events->filters['latitude']);
        $this->assertSame('90.413', $this->events->filters['longitude']);
        $this->assertSame(10, $this->events->filters['radius']);
        $this->assertSame('distance', $this->events->filters['sort']);
        $this->assertArrayHasKey('longitude_wraps', $this->events->filters);
    }

    public function testIndexClearsInvalidSessionLocationAndRejectsDistanceSort(): void
    {
        $session = new Session(false);
        $session->put('event_location', ['latitude' => '91', 'longitude' => '90.413', 'expires_at' => 1_900_000_000]);
        $controller = new PublicEventController(
            new View(base_path('app/Views')),
            $session,
            new Security($session),
            new Auth($session, new FakeUserRepository()),
            new Config(['name' => 'OEMS', 'url' => 'https://events.example.test', 'timezone' => 'Asia/Dhaka']),
            $this->events,
            $this->categories,
            $this->registrations,
            null,
            null,
            new LocationService(1209600, static fn (): int => 1_800_000_000),
        );

        $controller->index(Request::create('GET', '/events', ['sort' => 'distance']));

        $this->assertNull($session->get('event_location'));
        $this->assertSame('soonest', $this->events->filters['sort']);
        $this->assertFalse(array_key_exists('latitude', $this->events->filters));
    }

    public function testIndexMapPayloadContainsOnlyPublishedPublicEventsWithValidCoordinates(): void
    {
        $public = array_merge($this->eventFixture(), [
            'title' => 'Public </script><script>unsafe()</script> map event',
            'venue_latitude' => '23.8103',
            'venue_longitude' => '90.4125',
        ]);
        $restricted = array_merge($this->restrictedEventFixture(), ['id' => 502, 'slug' => 'restricted-map']);
        $invalid = array_merge($this->eventFixture(), [
            'id' => 503,
            'slug' => 'invalid-map',
            'venue_latitude' => '91',
            'venue_longitude' => '90.4',
        ]);
        $this->events->events = [$public, $restricted, $invalid];

        $body = $this->controller->index(Request::create('GET', '/events'))->body();
        $payload = $this->jsonScript($body, 'event-map-data');

        $this->assertSame(1, count($payload['markers'] ?? []));
        $this->assertSame(501, $payload['markers'][0]['id'] ?? null);
        $this->assertSame('23.8103', $payload['markers'][0]['latitude'] ?? null);
        $this->assertTrue(str_contains($body, '\\u003C/script\\u003E'));
        $this->assertFalse(str_contains($body, '</script><script>unsafe()'));
        $this->assertFalse(str_contains(json_encode($payload), 'Secret Hall'));
        $this->assertTrue(str_contains(
            $body,
            'class="event-discovery-layout"',
        ), 'The canonical result list and supplemental map must share the responsive discovery layout.');
    }

    public function testIndexShowsOnlyCoarseDistanceAndCoarsePlaceForRestrictedRows(): void
    {
        $session = new Session(false);
        $session->put('event_location', [
            'latitude' => '23.810',
            'longitude' => '90.413',
            'radius' => 25,
            'label' => 'Current area',
            'source' => 'device',
            'expires_at' => 1_900_000_000,
        ]);
        $this->events->events = [array_merge($this->restrictedEventFixture(), [
            'distance_km' => '7.3',
            'venue_latitude' => '23.8117',
            'venue_longitude' => '90.4159',
        ])];
        $controller = $this->controllerForSession($session, new FakeUserRepository(), new LocationService(1209600, static fn (): int => 1_800_000_000));

        $body = $controller->index(Request::create('GET', '/events'))->body();

        $this->assertTrue(str_contains($body, 'Within 10 km'));
        $this->assertTrue(str_contains($body, '<address>Dhaka, Bangladesh</address>'));
        foreach (['7.3 km away', 'Secret Hall', 'Road 12', '23.8117', '90.4159', 'maps.example.test', 'Use gate B'] as $secret) {
            $this->assertFalse(str_contains($body, $secret), 'Leaked restricted location value: ' . $secret);
        }
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

    public function testShowKeepsCompletedEventDetailVisibleWithRegistrationClosed(): void
    {
        $event = array_merge($this->eventFixture(), ['status' => 'completed']);
        $this->events->events[$event['slug']] = $event;

        $response = $this->controller->show(
            Request::create('GET', '/events/future-craft')->withRouteParameters(['slug' => 'future-craft']),
        );

        $this->assertSame(200, $response->status());
        $this->assertTrue(str_contains($response->body(), 'Event ended'));
        $this->assertFalse(str_contains($response->body(), 'href="/participant/events/future-craft/register"'));
    }

    public function testShowRendersSemanticEventDetailsGalleryAndGuestRegistrationAction(): void
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
        $this->assertTrue(str_contains($body, 'Register and pay'));
        $this->assertTrue(str_contains($body, 'href="/login?return_to=%2Fevents%2Ffuture-craft"'));
        $this->assertTrue(str_contains($body, 'class="favorite-guest-link" href="/login?return_to=%2Fevents%2Ffuture-craft" aria-label="Sign in to save Future Craft"'));
        $this->assertTrue(str_contains($body, '<i class="ph ph-bookmark-simple" aria-hidden="true"></i><span>Sign in to save</span>'));
        $this->assertFalse(str_contains($body, 'Week 3'));
    }

    public function testRestrictedLocationDoesNotLeakExactDataToGuest(): void
    {
        $body = $this->showRestrictedEventAsGuest()->body();

        foreach (['23.8103', '90.4125', 'Secret Hall', 'Road 12', 'maps.example.test', 'Use gate B'] as $secret) {
            $this->assertFalse(str_contains($body, $secret), 'Leaked restricted location value: ' . $secret);
        }

        $this->assertTrue(str_contains($body, 'Dhaka, Bangladesh'));
        $this->assertTrue(str_contains($body, 'Exact location shared after confirmation'));
        $this->assertFalse(str_contains($body, 'application/ld+json'));
    }

    public function testRestrictedLocationStaysPrivateForPendingParticipant(): void
    {
        $this->registrations->registrations[19] = [
            'id' => 19,
            'event_id' => 501,
            'user_id' => 7,
            'status' => 'pending',
        ];

        $body = $this->showRestrictedEventForRole('participant', 7)->body();

        $this->assertTrue(str_contains($body, 'Exact location shared after confirmation'));
        $this->assertFalse(str_contains($body, 'Secret Hall'));
        $this->assertFalse(str_contains($body, 'Use gate B'));
        $this->assertFalse(str_contains($body, 'application/ld+json'));
    }

    public function testRestrictedLocationIsVisibleToConfirmedParticipantOwnerAndSuperAdmin(): void
    {
        $this->registrations->registrations[20] = [
            'id' => 20,
            'event_id' => 501,
            'user_id' => 7,
            'status' => 'confirmed',
        ];

        foreach ([['participant', 7], ['organizer', 9], ['super-admin', 1]] as [$role, $userId]) {
            $body = $this->showRestrictedEventForRole($role, $userId)->body();

            $this->assertTrue(str_contains($body, 'Secret Hall, Road 12, Dhaka, Bangladesh'));
            $this->assertTrue(str_contains($body, 'Use gate B'));
            $this->assertTrue(str_contains($body, 'https://maps.example.test/secret'));
            $this->assertTrue(str_contains($body, '<script type="application/ld+json">'));
            $this->assertTrue(str_contains($body, '"name":"Secret Hall"'));
            $this->assertTrue(str_contains($body, 'id="event-detail-map-data"'));
        }
    }

    public function testPublicExactLocationRemainsVisibleToGuests(): void
    {
        $event = array_merge($this->eventFixture(), [
            'venue_address_line' => 'Main Road',
            'venue_latitude' => '23.8103',
            'venue_longitude' => '90.4125',
            'arrival_notes' => 'Use the east entrance.',
            'venue_map_url' => 'https://maps.example.test/public',
        ]);
        $this->events->events[$event['slug']] = $event;

        $body = $this->controller->show(
            Request::create('GET', '/events/future-craft')->withRouteParameters(['slug' => 'future-craft']),
        )->body();

        $this->assertTrue(str_contains($body, 'Dhaka Arts Hall, Main Road, Dhaka, Bangladesh'));
        $this->assertTrue(str_contains($body, 'Use the east entrance.'));
        $this->assertTrue(str_contains($body, 'https://maps.example.test/public'));
        $this->assertTrue(str_contains($body, '<script type="application/ld+json">'));
        $this->assertTrue(str_contains($body, '"name":"Dhaka Arts Hall"'));
    }

    public function testShowUsesTruthfulUnavailableActionsForSoldOutClosedAndEndedEvents(): void
    {
        $states = [
            'sold-out' => ['available_seats' => 0, 'expected' => 'Sold out'],
            'registration-closed' => ['registration_deadline' => '2026-08-08 18:00:00', 'expected' => 'Registration closed'],
            'event-started' => [
                'start_date' => '2000-08-09 08:00:00',
                'end_date' => '2099-08-10 12:30:00',
                'registration_deadline' => '2099-08-10 09:00:00',
                'expected' => 'Registration closed',
                'description' => 'This event has already started.',
            ],
            'event-ended' => [
                'start_date' => '2026-08-07 10:00:00',
                'end_date' => '2026-08-07 12:30:00',
                'registration_deadline' => '2026-08-06 18:00:00',
                'expected' => 'Event ended',
            ],
        ];

        foreach ($states as $slug => $state) {
            $expected = $state['expected'];
            $description = $state['description'] ?? null;
            unset($state['expected']);
            unset($state['description']);
            $this->events->events[$slug] = array_merge($this->eventFixture(), $state, ['slug' => $slug]);

            $body = $this->controller->show(
                Request::create('GET', '/events/' . $slug)->withRouteParameters(['slug' => $slug]),
            )->body();

            $this->assertTrue(str_contains($body, $expected));
            if (is_string($description)) {
                $this->assertTrue(str_contains($body, $description));
            }
            $this->assertFalse(str_contains($body, 'href="/participant/events/' . $slug . '/register"'));
            $this->assertSame(3, substr_count($body, 'href="/login"'));
        }
    }

    public function testShowLinksAuthenticatedParticipantsToCheckoutOrTheirExistingRegistration(): void
    {
        $event = $this->eventFixture();
        $this->events->events[$event['slug']] = $event;
        $session = new Session(false);
        $users = new FakeUserRepository();
        $users->users[7] = [
            'id' => 7,
            'role_id' => 3,
            'name' => 'Participant',
            'email' => 'participant@example.test',
            'password' => 'hash',
            'status' => 'active',
            'email_verified_at' => '2026-08-01 09:00:00',
        ];
        $session->put('auth.user_id', 7);
        $controller = new PublicEventController(
            new View(base_path('app/Views')),
            $session,
            new Security($session),
            new Auth($session, $users),
            new Config(['name' => 'OEMS', 'url' => 'https://events.example.test', 'timezone' => 'Asia/Dhaka']),
            $this->events,
            $this->categories,
            $this->registrations,
        );

        $checkoutBody = $controller->show(
            Request::create('GET', '/events/future-craft')->withRouteParameters(['slug' => 'future-craft']),
        )->body();
        $this->assertTrue(str_contains($checkoutBody, 'href="/participant/events/future-craft/register"'));
        $this->assertTrue(str_contains($checkoutBody, 'Review the total and submit your payment reference.'));
        $this->assertFalse(str_contains($checkoutBody, 'Sign in with a participant account'));

        $this->registrations->registrations[19] = [
            'id' => 19,
            'event_id' => 501,
            'user_id' => 7,
            'status' => 'pending',
            'registration_status' => 'pending',
        ];
        $existingBody = $controller->show(
            Request::create('GET', '/events/future-craft')->withRouteParameters(['slug' => 'future-craft']),
        )->body();

        $this->assertTrue(str_contains($existingBody, 'View registration'));
        $this->assertTrue(str_contains($existingBody, 'href="/participant/registrations/19"'));
        $this->assertFalse(str_contains($existingBody, 'href="/participant/events/future-craft/register"'));
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
        $this->assertTrue(str_contains($body, '৳600'));
        $this->assertFalse(str_contains($body, '"offers"'));
        $this->assertFalse(str_contains($body, '"@type":"Offer"'));
        $this->assertFalse(str_contains($body, '"availability"'));
        $this->assertFalse(str_contains($body, '"availabilityEnds"'));
        $this->assertFalse(str_contains($body, '"validFrom"'));
        $this->assertFalse(str_contains($body, '</script><script>alert'));
    }

    public function testShowRendersOnlyPublishedReviewsVerifiedAttendanceAndEscapedReplies(): void
    {
        $event = $this->eventFixture();
        $this->events->events[$event['slug']] = $event;
        $reviews = new FakeReviewRepository();
        $reviews->reviews = [
            11 => [
                'id' => 11,
                'event_id' => 501,
                'user_id' => 7,
                'participant_name' => '<script>Reviewer</script>',
                'rating' => 5,
                'review' => '<b>Published review</b>',
                'organizer_reply' => '<img src=x onerror=alert(1)>',
                'verified_attendee' => true,
                'status' => 'published',
                'updated_at' => '2026-08-08 10:00:00',
            ],
            12 => [
                'id' => 12,
                'event_id' => 501,
                'user_id' => 8,
                'participant_name' => 'Pending Reviewer',
                'rating' => 1,
                'review' => 'Pending secret text',
                'organizer_reply' => null,
                'verified_attendee' => false,
                'status' => 'pending',
                'updated_at' => '2026-08-09 10:00:00',
            ],
            13 => [
                'id' => 13,
                'event_id' => 501,
                'user_id' => 9,
                'participant_name' => 'Hidden Reviewer',
                'rating' => 1,
                'review' => 'Hidden secret text',
                'organizer_reply' => null,
                'verified_attendee' => false,
                'status' => 'hidden',
                'updated_at' => '2026-08-10 10:00:00',
            ],
        ];
        $session = new Session(false);
        $controller = new PublicEventController(
            new View(base_path('app/Views')),
            $session,
            new Security($session),
            new Auth($session, new FakeUserRepository()),
            new Config(['name' => 'OEMS', 'url' => 'https://events.example.test', 'timezone' => 'Asia/Dhaka']),
            $this->events,
            $this->categories,
            $this->registrations,
            null,
            $reviews,
        );

        $body = $controller->show(
            Request::create('GET', '/events/future-craft')->withRouteParameters(['slug' => 'future-craft']),
        )->body();

        $this->assertTrue(str_contains($body, '1 published review'));
        $this->assertTrue(str_contains($body, '5.0 average rating'));
        $this->assertTrue(str_contains($body, 'Verified attendee'));
        $this->assertTrue(str_contains($body, '&lt;script&gt;Reviewer&lt;/script&gt;'));
        $this->assertTrue(str_contains($body, '&lt;b&gt;Published review&lt;/b&gt;'));
        $this->assertTrue(str_contains($body, '&lt;img src=x onerror=alert(1)&gt;'));
        $this->assertFalse(str_contains($body, '<b>Published review</b>'));
        $this->assertFalse(str_contains($body, 'Pending secret text'));
        $this->assertFalse(str_contains($body, 'Hidden secret text'));
        $this->assertTrue(str_contains($body, '"aggregateRating"'));
        $this->assertTrue(str_contains($body, '"ratingCount":1'));
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

    public function testParticipantDiscoveryUsesOneBulkFavoriteLookupAndRendersSavedState(): void
    {
        $first = $this->eventFixture();
        $second = array_merge($this->eventFixture(), ['id' => 502, 'title' => 'Second event', 'slug' => 'second-event']);
        $this->events->events = [$first, $second];
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
        $favorites->favorites[7][501] = true;
        $controller = new PublicEventController(
            new View(base_path('app/Views')),
            $session,
            new Security($session),
            new Auth($session, $users),
            new Config(['name' => 'OEMS', 'url' => 'https://events.example.test', 'timezone' => 'Asia/Dhaka']),
            $this->events,
            $this->categories,
            $this->registrations,
            $favorites,
        );

        $body = $controller->index(Request::create('GET', '/events'))->body();

        $this->assertSame(1, $favorites->bulkStateCalls);
        $this->assertTrue(str_contains($body, 'action="/participant/favorites/501/remove"'));
        $this->assertTrue(str_contains($body, 'aria-label="Remove Future Craft from saved events"'));
        $this->assertTrue(str_contains($body, 'action="/participant/favorites/502"'));
        $this->assertTrue(str_contains($body, 'aria-label="Save Second event"'));
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
            'venue_address_line' => null,
            'venue_city' => 'Dhaka',
            'venue_country' => 'Bangladesh',
            'venue_postal_code' => null,
            'venue_latitude' => null,
            'venue_longitude' => null,
            'venue_map_url' => null,
            'location_visibility' => 'public',
            'arrival_notes' => null,
            'organizer_user_id' => 9,
            'organization_name' => 'OEMS Studio',
        ];
    }

    private function restrictedEventFixture(): array
    {
        return array_merge($this->eventFixture(), [
            'venue_name' => 'Secret Hall',
            'venue_address_line' => 'Road 12',
            'venue_latitude' => '23.8103',
            'venue_longitude' => '90.4125',
            'venue_map_url' => 'https://maps.example.test/secret',
            'map_url' => 'https://maps.example.test/event-secret',
            'arrival_notes' => 'Use gate B',
            'location_visibility' => 'registered',
        ]);
    }

    private function showRestrictedEventAsGuest(): \OEMS\Core\Response
    {
        $event = $this->restrictedEventFixture();
        $this->events->events[$event['slug']] = $event;

        return $this->controller->show(
            Request::create('GET', '/events/future-craft')->withRouteParameters(['slug' => 'future-craft']),
        );
    }

    private function showRestrictedEventForRole(string $role, int $userId): \OEMS\Core\Response
    {
        $event = $this->restrictedEventFixture();
        $this->events->events[$event['slug']] = $event;
        $session = new Session(false);
        $session->put('auth.user_id', $userId);
        $users = new FakeUserRepository();
        $users->users[$userId] = [
            'id' => $userId,
            'role_id' => $users->roles[$role]['id'],
            'name' => ucfirst($role),
            'email' => $role . '@example.test',
            'status' => 'active',
            'email_verified_at' => '2026-08-01 10:00:00',
        ];

        return $this->controllerForSession($session, $users)->show(
            Request::create('GET', '/events/future-craft')->withRouteParameters(['slug' => 'future-craft']),
        );
    }

    private function controllerForSession(
        Session $session,
        FakeUserRepository $users,
        ?LocationService $locations = null,
    ): PublicEventController {
        return new PublicEventController(
            new View(base_path('app/Views')),
            $session,
            new Security($session),
            new Auth($session, $users),
            new Config([
                'name' => 'OEMS',
                'url' => 'https://events.example.test',
                'timezone' => 'Asia/Dhaka',
                'map' => [
                    'tile_url' => 'https://tiles.example.test/{z}/{x}/{y}.png',
                    'tile_attribution' => 'Map data',
                    'default_lat' => 23.8103,
                    'default_lng' => 90.4125,
                    'default_zoom' => 11,
                ],
            ]),
            $this->events,
            $this->categories,
            $this->registrations,
            null,
            null,
            $locations,
        );
    }

    private function jsonScript(string $body, string $id): array
    {
        $pattern = '/<script type="application\/json" id="' . preg_quote($id, '/') . '">(.*?)<\/script>/s';
        $this->assertSame(1, preg_match($pattern, $body, $matches));
        $decoded = json_decode($matches[1], true);
        $this->assertTrue(is_array($decoded));

        return $decoded;
    }
}
