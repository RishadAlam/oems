<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use DateTimeImmutable;
use OEMS\App\Contracts\GeocoderInterface;
use OEMS\App\Contracts\GeocodingCacheRepositoryInterface;
use OEMS\App\Controllers\OrganizerEventController;
use OEMS\App\Controllers\OrganizerVenueController;
use OEMS\App\Middleware\CsrfMiddleware;
use OEMS\App\Middleware\RoleMiddleware;
use OEMS\App\Services\EventService;
use OEMS\App\Services\ImageUploadService;
use OEMS\App\Services\VenueService;
use OEMS\App\Services\LocationService;
use OEMS\App\Services\VenueGeocodingService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Container;
use OEMS\Core\Request;
use OEMS\Core\RateLimiter;
use OEMS\Core\Router;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakeCategoryRepository;
use OEMS\Tests\Support\FakeEventRepository;
use OEMS\Tests\Support\FakeOrganizerRepository;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\FakeVenueRepository;
use OEMS\Tests\Support\TestCase;

final class OrganizerEventControllerTest extends TestCase
{
    private Session $session;

    private Security $security;

    private FakeEventRepository $events;

    private FakeVenueRepository $venues;

    private OrganizerEventController $controller;

    private OrganizerVenueController $venueController;

    protected function setUp(): void
    {
        $_SESSION = [];
        $_SERVER['REQUEST_URI'] = '/organizer/events';
        $this->session = new Session(false);
        $this->security = new Security($this->session);
        $users = $this->users('organizer');
        $auth = new Auth($this->session, $users);
        $this->events = new FakeEventRepository();
        $this->events->events = [
            11 => $this->eventFixture(11, 10, 'draft', 'Dhaka Product Lab'),
            12 => $this->eventFixture(12, 20, 'draft', 'Foreign Event'),
            13 => $this->eventFixture(13, 10, 'approved', 'Approved Forum'),
        ];
        $this->venues = new FakeVenueRepository();
        $categories = new FakeCategoryRepository();
        $service = new EventService(
            $this->events,
            $categories,
            $this->venues,
            new ImageUploadService(sys_get_temp_dir() . '/oems-controller-test-uploads', requireHttpUpload: false),
            new FakeOrganizerRepository(),
        );
        $dependencies = [
            new View(base_path('app/Views')),
            $this->session,
            $this->security,
            $auth,
            new Config(['name' => 'OEMS']),
        ];
        $this->controller = new OrganizerEventController(
            ...$dependencies,
            events: $this->events,
            categories: $categories,
            venues: $this->venues,
            eventService: $service,
        );
        $this->venueController = new OrganizerVenueController(
            ...$dependencies,
            venues: $this->venues,
            venueService: new VenueService($this->venues),
            geocoding: new VenueGeocodingService(
                new class implements GeocodingCacheRepositoryInterface {
                    public function findFresh(string $queryHash, string $provider, DateTimeImmutable $now): ?array { return null; }
                    public function upsert(string $queryHash, string $query, string $provider, array $results, DateTimeImmutable $expiresAt): void {}
                },
                new class implements GeocoderInterface {
                    public function search(string $query, int $limit): array { return []; }
                },
                'Controller route test',
            ),
            locations: new LocationService(),
            rateLimiter: new RateLimiter(sys_get_temp_dir() . '/oems-event-controller-geocode'),
        );
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        unset($_SERVER['REQUEST_URI']);
    }

    public function testOrganizerCanRenderEventIndexCreateEditAndShowPages(): void
    {
        $index = $this->controller->index(Request::create('GET', '/organizer/events'));
        $create = $this->controller->create(Request::create('GET', '/organizer/events/create'));
        $edit = $this->controller->edit($this->routed('GET', '/organizer/events/11/edit', '11'));
        $show = $this->controller->show($this->routed('GET', '/organizer/events/11', '11'));

        $this->assertSame(200, $index->status());
        $this->assertTrue(str_contains($index->body(), 'Dhaka Product Lab'));
        $this->assertFalse(str_contains($index->body(), 'Foreign Event'));
        $this->assertTrue(str_contains($index->body(), 'href="/organizer/events"'));
        $this->assertTrue(str_contains($index->body(), 'href="/organizer/venues"'));
        $this->assertTrue(str_contains($index->body(), 'data-auto-submit'));
        $this->assertTrue(str_contains($index->body(), 'data-form-kind="filter"'));
        $this->assertTrue(str_contains($index->body(), '>Apply</button>'));
        $this->assertTrue(str_contains($index->body(), 'class="organizer-table__action" data-label="Action"'));
        $this->assertFalse(str_contains($index->body(), 'onchange='));
        $this->assertSame(200, $create->status());
        $this->assertTrue(str_contains($create->body(), 'Create event'));
        $this->assertTrue(str_contains($create->body(), 'type="datetime-local"'));
        $this->assertTrue(str_contains($create->body(), 'accept="image/jpeg,image/png,image/webp"'));
        $this->assertTrue(str_contains($create->body(), 'data-form-kind="entry"'));
        $this->assertFalse(str_contains($create->body(), 'method="post" enctype="multipart/form-data" novalidate'));
        $this->assertTrue(str_contains($create->body(), 'name="title" type="text" minlength="5" maxlength="180"'));
        $this->assertTrue(str_contains($create->body(), 'name="description" rows="8" minlength="30" maxlength="20000"'));
        $this->assertTrue(str_contains($create->body(), 'data-after-field="start_date"'));
        $this->assertTrue(str_contains($create->body(), 'data-before-or-equal-field="start_date"'));
        $this->assertTrue(str_contains($create->body(), 'data-max-bytes="5242880"'));
        $this->assertTrue(str_contains($create->body(), 'data-max-files="6"'));
        $this->assertTrue(str_contains($create->body(), 'data-submit-label="Creating draft…"'));
        $this->assertSame(200, $edit->status());
        $this->assertTrue(str_contains($edit->body(), 'Edit event'));
        $this->assertSame(0, preg_match('/id="waitlist_enabled"[^>]*\schecked(?:\s|>)/', $edit->body()));
        $this->assertSame(200, $show->status());
        $this->assertTrue(str_contains($show->body(), 'Draft'));
        $this->assertTrue(str_contains($show->body(), 'data-form-kind="action"'));
        $this->assertTrue(str_contains($show->body(), 'data-submit-label="Submitting for review…"'));
        $this->assertFalse(str_contains($show->body(), 'Register now'));
        $this->assertFalse(str_contains($show->body(), 'Checkout'));

        $this->events->events[11]['status'] = 'published';
        $published = $this->controller->show($this->routed('GET', '/organizer/events/11', '11'));
        $this->assertTrue(str_contains($published->body(), '/organizer/events/11/announcements'));
        $this->assertTrue(str_contains($published->body(), 'Announcements'));
    }

    public function testOwnedShowAndEditRenderEscapedBannerGalleryTagsAndMapEvidence(): void
    {
        $this->events->events[11] = array_merge($this->events->events[11], [
            'title' => 'Hostile <script>alert(1)</script> event',
            'description' => 'Description <img src=x onerror=alert(2)> evidence.',
            'banner' => '/uploads/events/banner&quot;-literal.webp',
            'map_url' => 'https://example.test/map?place=one&mode=walk',
            'tags' => ['design', '<script>alert(3)</script>'],
        ]);
        $this->events->galleries[11] = [[
            'image_path' => '/uploads/events/gallery-one.png',
            'alt_text' => '"><script>alert(4)</script>',
        ]];

        $show = $this->controller->show($this->routed('GET', '/organizer/events/11', '11'));
        $edit = $this->controller->edit($this->routed('GET', '/organizer/events/11/edit', '11'));
        $body = $show->body();

        $this->assertSame(200, $show->status());
        $this->assertTrue(str_contains($body, 'Hostile &lt;script&gt;alert(1)&lt;/script&gt; event'));
        $this->assertTrue(str_contains($body, 'Description &lt;img src=x onerror=alert(2)&gt; evidence.'));
        $this->assertTrue(str_contains($body, '/uploads/events/banner&amp;quot;-literal.webp'));
        $this->assertTrue(str_contains($body, '/uploads/events/gallery-one.png'));
        $this->assertTrue(str_contains($body, '&quot;&gt;&lt;script&gt;alert(4)&lt;/script&gt;'));
        $this->assertTrue(str_contains($body, '&lt;script&gt;alert(3)&lt;/script&gt;'));
        $this->assertTrue(str_contains($body, 'https://example.test/map?place=one&amp;mode=walk'));
        $this->assertFalse(str_contains($body, '<script>alert'));

        $this->assertSame(200, $edit->status());
        $this->assertTrue(str_contains($edit->body(), 'Current banner'));
        $this->assertTrue(str_contains($edit->body(), '/uploads/events/banner&amp;quot;-literal.webp'));
        $this->assertTrue(str_contains($edit->body(), 'Current gallery'));
        $this->assertTrue(str_contains($edit->body(), '/uploads/events/gallery-one.png'));
        $this->assertTrue(str_contains($edit->body(), 'New gallery images replace the current gallery.'));
    }

    public function testPendingOrganizerSeesApprovalGuidanceInsteadOfAnImpossibleSubmitAction(): void
    {
        $this->events->events[11]['organizer_approval_status'] = 'pending';

        $body = $this->controller->show($this->routed('GET', '/organizer/events/11', '11'))->body();

        $this->assertFalse(str_contains($body, 'action="/organizer/events/11/submit"'));
        $this->assertFalse(str_contains($body, 'data-submit-label="Submitting for review…"'));
        $this->assertTrue(str_contains($body, 'Organization approval pending'));
        $this->assertTrue(str_contains(
            $body,
            'You can keep editing this draft. Submit for review becomes available after an administrator approves your organization profile.',
        ));
    }

    public function testEventHelpTextIdsMergeWithSimultaneousValidationErrors(): void
    {
        $this->session->flash('errors', [
            'description' => ['Describe the event.'],
            'tags' => ['Enter fewer tags.'],
            'gallery' => ['Choose fewer images.'],
            'location_visibility' => ['Choose who may see the location.'],
            'arrival_notes' => ['Add a venue before arrival notes.'],
        ]);

        $body = $this->controller->create(Request::create('GET', '/organizer/events/create'))->body();

        $this->assertTrue(str_contains($body, 'id="description-help"'));
        $this->assertTrue(str_contains($body, 'id="description" name="description"'));
        $this->assertTrue(str_contains($body, 'aria-describedby="description-help description-error"'));
        $this->assertTrue(str_contains($body, 'id="tags-help"'));
        $this->assertTrue(str_contains($body, 'aria-describedby="tags-help tags-error"'));
        $this->assertTrue(str_contains($body, 'id="gallery-help"'));
        $this->assertTrue(str_contains($body, 'aria-describedby="gallery-help gallery-error"'));
        $this->assertTrue(str_contains($body, 'id="location-visibility-help"'));
        $this->assertTrue(str_contains($body, 'aria-describedby="location-visibility-help location-visibility-error"'));
        $this->assertTrue(str_contains($body, 'id="arrival-notes-help"'));
        $this->assertTrue(str_contains($body, 'aria-describedby="arrival-notes-help arrival-notes-error"'));
    }

    public function testEventFormUsesAccessibleLocationVisibilityChoices(): void
    {
        $body = $this->controller->create(Request::create('GET', '/organizer/events/create'))->body();

        $this->assertSame(2, substr_count($body, 'name="location_visibility" type="radio"'));
        $this->assertTrue(str_contains($body, 'id="location_visibility_public"'));
        $this->assertTrue(str_contains($body, 'id="location_visibility_registered"'));
        $this->assertTrue(str_contains($body, 'Public exact location'));
        $this->assertTrue(str_contains($body, 'Confirmed participants only'));
        $this->assertFalse(str_contains($body, '<select id="location_visibility"'));
    }

    public function testIndexAcceptsOnlyKnownStatusFilters(): void
    {
        $filtered = $this->controller->index(Request::create(
            'GET',
            '/organizer/events?status=draft',
            query: ['status' => 'draft'],
        ));
        $unknown = $this->controller->index(Request::create(
            'GET',
            '/organizer/events?status[]=draft',
            query: ['status' => ['draft']],
        ));

        $this->assertTrue(str_contains($filtered->body(), 'Dhaka Product Lab'));
        $this->assertFalse(str_contains($filtered->body(), 'Approved Forum'));
        $this->assertTrue(str_contains($unknown->body(), 'Dhaka Product Lab'));
        $this->assertTrue(str_contains($unknown->body(), 'Approved Forum'));
    }

    public function testInvalidCreateFlashesOnlyWhitelistedScalarOldInput(): void
    {
        $input = $this->validInput();
        $input['title'] = ['unsafe'];
        $input['tags'] = ['nested'];
        $input['organizer_id'] = '20';

        $response = $this->controller->store(Request::create('POST', '/organizer/events', input: $input));
        $old = $this->session->get('_flash.old', []);

        $this->assertSame('/organizer/events/create', $response->header('Location'));
        $this->assertArrayHasKey('title', $this->session->get('_flash.errors', []));
        $this->assertFalse(array_key_exists('title', $old));
        $this->assertFalse(array_key_exists('tags', $old));
        $this->assertFalse(array_key_exists('organizer_id', $old));
        foreach ($old as $value) {
            $this->assertTrue(is_scalar($value));
        }
    }

    public function testInvalidCreateRedirectsWithValidationErrorsWithoutPersisting(): void
    {
        $response = $this->controller->store(Request::create('POST', '/organizer/events', input: [
            'title' => 'Tiny',
        ]));

        $this->assertSame(302, $response->status());
        $this->assertSame('/organizer/events/create', $response->header('Location'));
        $this->assertArrayHasKey('category_id', $this->session->get('_flash.errors', []));
        $this->assertSame(3, count($this->events->events));
    }

    public function testForeignMissingAndMalformedEventIdsReturnNotFound(): void
    {
        foreach (['12', '999', '0', '-4', 'event'] as $id) {
            $response = $this->controller->show($this->routed('GET', '/organizer/events/' . $id, $id));
            $this->assertSame(404, $response->status());
        }

        $response = $this->controller->update($this->routed(
            'POST',
            '/organizer/events/12',
            '12',
            $this->validInput(),
        ));
        $this->assertSame(404, $response->status());
        $this->assertSame([], $this->events->galleryForOwned(10, 12));
    }

    public function testSuccessfulCreateAndUpdateFlashConfirmation(): void
    {
        $create = $this->controller->store(Request::create(
            'POST',
            '/organizer/events',
            input: $this->validInput(),
        ));
        $createdId = max(array_keys($this->events->events));

        $this->assertSame('/organizer/events/' . $createdId, $create->header('Location'));
        $this->assertSame('Event draft created.', $this->session->get('_flash.success'));

        $input = $this->validInput();
        $input['title'] = 'Updated Dhaka Product Lab';
        $update = $this->controller->update($this->routed(
            'POST',
            '/organizer/events/11',
            '11',
            $input,
        ));

        $this->assertSame('/organizer/events/11', $update->header('Location'));
        $this->assertSame('Event updated successfully.', $this->session->get('_flash.success'));
        $this->assertSame('Updated Dhaka Product Lab', $this->events->events[11]['title']);
    }

    public function testStatusActionsRedirectAndApplyOwnedLifecycleRules(): void
    {
        $submit = $this->controller->submit($this->routed('POST', '/organizer/events/11/submit', '11'));
        $cancel = $this->controller->cancel($this->routed('POST', '/organizer/events/13/cancel', '13'));

        $this->assertSame('/organizer/events/11', $submit->header('Location'));
        $this->assertSame('pending', $this->events->events[11]['status']);
        $this->assertSame('/organizer/events/13', $cancel->header('Location'));
        $this->assertSame('cancelled', $this->events->events[13]['status']);

        $delete = $this->controller->delete($this->routed('POST', '/organizer/events/13/delete', '13'));
        $this->assertSame('/organizer/events', $delete->header('Location'));
        $this->assertSame('Event deleted.', $this->session->get('_flash.success'));
        $this->assertNotNull($this->events->events[13]['deleted_at']);
    }

    public function testApprovedEventCanBePublishedAndOnlyApprovedViewShowsPublishButton(): void
    {
        $approved = $this->controller->show($this->routed('GET', '/organizer/events/13', '13'));
        $this->assertTrue(str_contains($approved->body(), '/organizer/events/13/publish'));
        $this->assertTrue(str_contains($approved->body(), 'Publish event'));

        $published = $this->controller->publish($this->routed('POST', '/organizer/events/13/publish', '13'));
        $this->assertSame('/organizer/events/13', $published->header('Location'));
        $this->assertSame('published', $this->events->events[13]['status']);
        $this->assertSame('Event published.', $this->session->get('_flash.success'));

        $after = $this->controller->show($this->routed('GET', '/organizer/events/13', '13'));
        $this->assertFalse(str_contains($after->body(), '/organizer/events/13/publish'));
    }

    public function testConcurrentPublicationWinnerRedirectsWithTruthfulSuccess(): void
    {
        $this->events->publishLostToConcurrentWinner = true;

        $response = $this->controller->publish($this->routed('POST', '/organizer/events/13/publish', '13'));

        $this->assertSame('/organizer/events/13', $response->header('Location'));
        $this->assertSame(302, $response->status());
        $this->assertSame('published', $this->events->events[13]['status']);
        $this->assertSame('Event published.', $this->session->get('_flash.success'));
    }

    public function testWrongStatePublishIsConflictWhileForeignPublishIsNotFound(): void
    {
        $wrongState = $this->controller->publish($this->routed('POST', '/organizer/events/11/publish', '11'));
        $foreign = $this->controller->publish($this->routed('POST', '/organizer/events/12/publish', '12'));

        $this->assertSame(409, $wrongState->status());
        $this->assertSame(404, $foreign->status());
        $this->assertSame('draft', $this->events->events[11]['status']);
    }

    public function testBusinessRuleFailureFlashesErrorAndReturnsToOwnedEvent(): void
    {
        $response = $this->controller->submit($this->routed('POST', '/organizer/events/13/submit', '13'));

        $this->assertSame('/organizer/events/13', $response->header('Location'));
        $this->assertSame(
            'Only saved drafts may be submitted. Edit a rejected event before resubmitting it.',
            $this->session->get('_flash.error'),
        );
    }

    public function testOrganizerTrashRendersScopedRecoveryAndRestoresRetainedDraft(): void
    {
        $this->events->events[11]['deleted_at'] = '2026-08-10 12:00:00';
        $this->events->events[12]['deleted_at'] = '2026-08-10 12:00:00';

        $trash = $this->controller->trash(Request::create('GET', '/organizer/events/trash'));
        $restore = $this->controller->restore($this->routed('POST', '/organizer/events/trash/11/restore', '11', [
            'deleted_at' => '2026-08-10 12:00:00',
        ]));

        $this->assertSame(200, $trash->status());
        $this->assertTrue(str_contains($trash->body(), 'Dhaka Product Lab'));
        $this->assertFalse(str_contains($trash->body(), 'Foreign Event'));
        $this->assertTrue(str_contains($trash->body(), 'data-label="Lifecycle"'));
        $this->assertTrue(str_contains($trash->body(), '/organizer/events/trash/11/restore'));
        $this->assertTrue(str_contains($trash->body(), 'data-form-kind="action"'));
        $this->assertTrue(str_contains($trash->body(), 'data-submit-label="Restoring event…"'));
        $this->assertSame('/organizer/events/11', $restore->header('Location'));
        $this->assertNull($this->events->events[11]['deleted_at']);
        $this->assertSame('draft', $this->events->events[11]['status']);
        $this->assertSame('Event restored as a draft.', $this->session->get('_flash.success'));
    }

    public function testOrganizerTrashRestoreRejectsForeignAndStaleRows(): void
    {
        $this->events->events[11]['deleted_at'] = '2026-08-10 12:00:00';
        $this->events->events[12]['deleted_at'] = '2026-08-10 12:00:00';

        $foreign = $this->controller->restore($this->routed('POST', '/organizer/events/trash/12/restore', '12', ['deleted_at' => '2026-08-10 12:00:00']));
        $stale = $this->controller->restore($this->routed('POST', '/organizer/events/trash/11/restore', '11', ['deleted_at' => 'old']));

        $this->assertSame(404, $foreign->status());
        $this->assertSame(409, $stale->status());
        $this->assertNotNull($this->events->events[11]['deleted_at']);
    }

    public function testEveryEventPostRouteRequiresOrganizerRoleAndCsrf(): void
    {
        $uris = [
            '/organizer/events',
            '/organizer/events/11',
            '/organizer/events/11/submit',
            '/organizer/events/11/publish',
            '/organizer/events/11/cancel',
            '/organizer/events/11/delete',
            '/organizer/events/trash/11/restore',
        ];

        foreach ($uris as $uri) {
            $participant = $this->routerForRole('participant');
            $blockedRole = $participant['router']->dispatch(Request::create('POST', $uri, input: [
                '_token' => $participant['security']->csrfToken(),
            ]));
            $this->assertSame(403, $blockedRole->status());

            $organizer = $this->routerForRole('organizer');
            $blockedCsrf = $organizer['router']->dispatch(Request::create('POST', $uri, input: [
                '_token' => 'invalid',
            ]));
            $this->assertSame(419, $blockedCsrf->status());
        }
    }

    private function routerForRole(string $role): array
    {
        $_SESSION = [];
        $session = new Session(false);
        $security = new Security($session);
        $users = $this->users($role);
        $auth = new Auth($session, $users);
        $container = new Container();
        $container->instance(OrganizerEventController::class, $this->controller);
        $container->instance(OrganizerVenueController::class, $this->venueController);
        $router = new Router($container);
        $router->aliasMiddleware('role', new RoleMiddleware($auth));
        $router->aliasMiddleware('csrf', new CsrfMiddleware($security));
        $registerRoutes = require base_path('routes/web.php');
        $registerRoutes($router);

        return ['router' => $router, 'security' => $security];
    }

    private function users(string $role): FakeUserRepository
    {
        $users = new FakeUserRepository();
        $roleId = $role === 'organizer' ? 2 : 3;
        $users->users[10] = [
            'id' => 10,
            'role_id' => $roleId,
            'name' => 'Amina Rahman',
            'email' => 'amina@example.test',
            'password' => password_hash('DemoPass!2026', PASSWORD_DEFAULT),
            'status' => 'active',
            'email_verified_at' => '2026-08-06 10:00:00',
        ];
        $this->authenticateSession($this->session, $users, 10);

        return $users;
    }

    private function routed(string $method, string $uri, string $id, array $input = []): Request
    {
        return Request::create($method, $uri, input: $input)->withRouteParameters(['id' => $id]);
    }

    private function validInput(): array
    {
        return [
            'category_id' => '1',
            'venue_id' => '1',
            'title' => 'Dhaka Product Design Forum',
            'description' => 'A practical forum for product teams building accessible services in Bangladesh.',
            'map_url' => 'https://www.google.com/maps/place',
            'speaker' => 'Samira Chowdhury',
            'start_date' => '2026-09-15T18:00',
            'end_date' => '2026-09-15T21:00',
            'registration_deadline' => '2026-09-14T18:00',
            'capacity' => '80',
            'ticket_price' => '500',
            'tags' => 'product, design',
            'location_visibility' => 'public',
            'arrival_notes' => 'Use the north entrance.',
        ];
    }

    private function eventFixture(int $id, int $userId, string $status, string $title): array
    {
        return [
            'id' => $id,
            'user_id' => $userId,
            'category_id' => 1,
            'category_name' => 'Technology',
            'venue_id' => 1,
            'venue_name' => 'Owned Hall',
            'venue_city' => 'Dhaka',
            'title' => $title,
            'slug' => strtolower(str_replace(' ', '-', $title)),
            'description' => 'A complete description for this organizer event and its planned program.',
            'banner' => null,
            'map_url' => 'https://www.google.com/maps/place',
            'speaker' => 'Samira Chowdhury',
            'start_date' => '2026-09-15 18:00:00',
            'end_date' => '2026-09-15 21:00:00',
            'registration_deadline' => '2026-09-14 18:00:00',
            'capacity' => 80,
            'available_seats' => 80,
            'ticket_price' => '500.00',
            'currency' => 'BDT',
            'tags' => ['product', 'design'],
            'status' => $status,
            'rejection_reason' => null,
            'deleted_at' => null,
            'location_visibility' => 'registered',
            'arrival_notes' => 'Use the north entrance.',
            'waitlist_enabled' => 0,
            'organizer_approval_status' => 'approved',
        ];
    }
}
