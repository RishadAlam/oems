<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Controllers\AdminEventController;
use OEMS\App\Middleware\CsrfMiddleware;
use OEMS\App\Middleware\RoleMiddleware;
use OEMS\App\Repositories\EventRepository;
use OEMS\App\Services\EventService;
use OEMS\App\Services\ImageUploadService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Container;
use OEMS\Core\Request;
use OEMS\Core\Router;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakeCategoryRepository;
use OEMS\Tests\Support\FakeOrganizerRepository;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\FakeVenueRepository;
use OEMS\Tests\Support\TestCase;
use PDO;

final class AdminEventControllerTest extends TestCase
{
    private PDO $connection;

    private Session $session;

    private Security $security;

    private EventRepository $events;

    private AdminEventController $controller;

    protected function setUp(): void
    {
        $_SESSION = [];
        $_SERVER['REQUEST_URI'] = '/admin/events';
        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->createSchema();
        $this->seedEvents();
        $this->session = new Session(false);
        $this->security = new Security($this->session);
        $auth = new Auth($this->session, $this->users('super-admin'));
        $this->events = new EventRepository($this->connection);
        $service = new EventService(
            $this->events,
            new FakeCategoryRepository(),
            new FakeVenueRepository(),
            new ImageUploadService(sys_get_temp_dir() . '/oems-admin-event-test-uploads', requireHttpUpload: false),
            new FakeOrganizerRepository(),
        );
        $this->controller = new AdminEventController(
            new View(base_path('app/Views')),
            $this->session,
            $this->security,
            $auth,
            new Config(['name' => 'OEMS']),
            $this->events,
            $service,
        );
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        unset($_SERVER['REQUEST_URI']);
    }

    public function testModerationQueueDefaultsToPendingAndAcceptsOnlyKnownFilters(): void
    {
        $pending = $this->controller->index(Request::create('GET', '/admin/events'));
        $approved = $this->controller->index(Request::create(
            'GET',
            '/admin/events?status=approved',
            query: ['status' => 'approved'],
        ));
        $unknown = $this->controller->index(Request::create(
            'GET',
            '/admin/events?status[]=pending',
            query: ['status' => ['pending']],
        ));

        $this->assertSame(200, $pending->status());
        $this->assertTrue(str_contains($pending->body(), 'Pending Accessibility Forum'));
        $this->assertTrue(str_contains($pending->body(), 'Pending Community Lab'));
        $this->assertFalse(str_contains($pending->body(), 'Approved Product Summit'));
        $this->assertTrue(str_contains($approved->body(), 'Approved Product Summit'));
        $this->assertFalse(str_contains($approved->body(), 'Pending Community Lab'));
        $this->assertTrue(str_contains($unknown->body(), 'Pending Accessibility Forum'));
        $this->assertFalse(str_contains($unknown->body(), 'Approved Product Summit'));
    }

    public function testShowPresentsEventEvidenceBeforeSeparateExplicitActions(): void
    {
        $response = $this->controller->show($this->routed('GET', '/admin/events/11', '11'));
        $body = $response->body();
        $evidence = strpos($body, 'A full event proposal with schedule, venue, pricing, and accessibility evidence.');
        $approve = strpos($body, 'action="/admin/events/11/approve"');
        $reject = strpos($body, 'action="/admin/events/11/reject"');

        $this->assertSame(200, $response->status());
        $this->assertNotSame(false, $evidence);
        $this->assertNotSame(false, $approve);
        $this->assertNotSame(false, $reject);
        $this->assertTrue($evidence < $approve);
        $this->assertTrue($approve < $reject);
        $this->assertTrue(str_contains($body, '<textarea'));
        $this->assertTrue(str_contains($body, 'name="reason"'));
        $this->assertTrue(str_contains($body, 'Pending review'));
        $this->assertTrue(str_contains($body, '/uploads/events/pending-gallery.jpg'));
        $this->assertTrue(str_contains($body, 'Pending gallery evidence'));
        $this->assertFalse(str_contains($body, 'formaction='));
    }

    public function testRejectionRequiresReasonAndInvalidTransitionsDoNotWriteActivity(): void
    {
        $publishPending = $this->controller->publish($this->routed(
            'POST',
            '/admin/events/12/publish',
            '12',
        ));
        $missingReason = $this->controller->reject($this->routed(
            'POST',
            '/admin/events/12/reject',
            '12',
            ['reason' => '   '],
        ));
        $completeApproved = $this->controller->complete($this->routed(
            'POST',
            '/admin/events/13/complete',
            '13',
        ));

        $this->assertSame('/admin/events/12', $publishPending->header('Location'));
        $this->assertSame('That event status transition is not allowed.', $this->session->get('_flash.error'));
        $this->assertSame('/admin/events/12', $missingReason->header('Location'));
        $this->assertArrayHasKey('reason', $this->session->get('_flash.errors', []));
        $this->assertSame('/admin/events/13', $completeApproved->header('Location'));
        $this->assertSame('pending', $this->eventStatus(12));
        $this->assertSame('approved', $this->eventStatus(13));
        $this->assertSame(0, $this->activityCount());
    }

    public function testEveryModerationActionUsesCasLifecycleAndWritesRealActivityLog(): void
    {
        $approve = $this->controller->approve($this->routed('POST', '/admin/events/11/approve', '11'));
        $reject = $this->controller->reject($this->routed(
            'POST',
            '/admin/events/12/reject',
            '12',
            ['reason' => '  Add step-free access evidence.  '],
        ));
        $publish = $this->controller->publish($this->routed('POST', '/admin/events/13/publish', '13'));
        $complete = $this->controller->complete($this->routed('POST', '/admin/events/14/complete', '14'));
        $cancel = $this->controller->cancel($this->routed('POST', '/admin/events/15/cancel', '15'));

        $this->assertSame('/admin/events/11', $approve->header('Location'));
        $this->assertSame('/admin/events/12', $reject->header('Location'));
        $this->assertSame('/admin/events/13', $publish->header('Location'));
        $this->assertSame('/admin/events/14', $complete->header('Location'));
        $this->assertSame('/admin/events/15', $cancel->header('Location'));
        $this->assertSame('approved', $this->eventStatus(11));
        $this->assertSame('rejected', $this->eventStatus(12));
        $this->assertSame('Add step-free access evidence.', $this->eventValue(12, 'rejection_reason'));
        $this->assertSame('published', $this->eventStatus(13));
        $this->assertSame('completed', $this->eventStatus(14));
        $this->assertSame('cancelled', $this->eventStatus(15));
        $this->assertSame(
            ['event.approved', 'event.rejected', 'event.published', 'event.completed', 'event.cancelled'],
            $this->activityActions(),
        );
        $this->assertSame(99, (int) $this->connection->query('SELECT user_id FROM activity_logs ORDER BY id LIMIT 1')->fetchColumn());
    }

    public function testMalformedAndMissingEventIdsReturnNotFound(): void
    {
        foreach (['999', '0', '-1', 'event'] as $id) {
            $response = $this->controller->show($this->routed('GET', '/admin/events/' . $id, $id));
            $this->assertSame(404, $response->status());
        }

        $response = $this->controller->approve($this->routed('POST', '/admin/events/0/approve', '0'));
        $this->assertSame(404, $response->status());
        $this->assertSame(0, $this->activityCount());
    }

    public function testEveryEventModerationRouteRequiresSuperAdministratorRoleAndPostsRequireCsrf(): void
    {
        foreach (['/admin/events', '/admin/events/11'] as $uri) {
            $router = $this->routerForRole('organizer');
            $this->assertSame(403, $router['router']->dispatch(Request::create('GET', $uri))->status());
        }

        foreach (['approve', 'reject', 'publish', 'complete', 'cancel'] as $action) {
            $uri = '/admin/events/11/' . $action;
            $organizer = $this->routerForRole('organizer');
            $blockedRole = $organizer['router']->dispatch(Request::create('POST', $uri, input: [
                '_token' => $organizer['security']->csrfToken(),
            ]));
            $this->assertSame(403, $blockedRole->status());

            $administrator = $this->routerForRole('super-admin');
            $blockedCsrf = $administrator['router']->dispatch(Request::create('POST', $uri, input: [
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
        $users = $this->users($role, $session);
        $auth = new Auth($session, $users);
        $container = new Container();
        $container->instance(AdminEventController::class, $this->controller);
        $router = new Router($container);
        $router->aliasMiddleware('role', new RoleMiddleware($auth));
        $router->aliasMiddleware('csrf', new CsrfMiddleware($security));
        $registerRoutes = require base_path('routes/web.php');
        $registerRoutes($router);

        return ['router' => $router, 'security' => $security];
    }

    private function users(string $role, ?Session $session = null): FakeUserRepository
    {
        $session ??= $this->session;
        $users = new FakeUserRepository();
        $users->users[99] = [
            'id' => 99,
            'role_id' => $role === 'super-admin' ? 1 : 2,
            'name' => 'Nadia Administrator',
            'email' => 'nadia@example.test',
            'password' => password_hash('DemoPass!2026', PASSWORD_DEFAULT),
            'status' => 'active',
            'email_verified_at' => '2026-08-06 10:00:00',
        ];
        $this->authenticateSession($session, $users, 99);

        return $users;
    }

    private function routed(string $method, string $uri, string $id, array $input = []): Request
    {
        return Request::create($method, $uri, input: $input)->withRouteParameters(['id' => $id]);
    }

    private function createSchema(): void
    {
        $this->connection->exec('CREATE TABLE organizers (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL UNIQUE, organization_name TEXT NOT NULL)');
        $this->connection->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT NOT NULL, slug TEXT NOT NULL UNIQUE, is_active INTEGER NOT NULL DEFAULT 1)');
        $this->connection->exec('CREATE TABLE venues (id INTEGER PRIMARY KEY, organizer_id INTEGER NULL, name TEXT NOT NULL, address_line TEXT NULL, city TEXT NOT NULL, country TEXT NOT NULL, postal_code TEXT NULL, latitude REAL NULL, longitude REAL NULL, map_url TEXT NULL)');
        $this->connection->exec(
            'CREATE TABLE events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                organizer_id INTEGER NOT NULL,
                category_id INTEGER NOT NULL,
                venue_id INTEGER NULL,
                title TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                description TEXT NOT NULL,
                banner TEXT NULL,
                map_url TEXT NULL,
                speaker TEXT NULL,
                start_date TEXT NOT NULL,
                end_date TEXT NOT NULL,
                registration_deadline TEXT NOT NULL,
                capacity INTEGER NOT NULL,
                available_seats INTEGER NOT NULL,
                ticket_price REAL NOT NULL DEFAULT 0,
                currency TEXT NOT NULL DEFAULT "BDT",
                tags TEXT NULL,
                status TEXT NOT NULL DEFAULT "draft",
                rejection_reason TEXT NULL,
                approved_by INTEGER NULL,
                approved_at TEXT NULL,
                published_at TEXT NULL,
                is_featured INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                deleted_at TEXT NULL
            )',
        );
        $this->connection->exec('CREATE TABLE activity_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NULL, action TEXT NOT NULL, subject_type TEXT NULL, subject_id INTEGER NULL, description TEXT NOT NULL, properties TEXT NULL, ip_address TEXT NULL, user_agent TEXT NULL, created_at TEXT NOT NULL)');
        $this->connection->exec('CREATE TABLE event_gallery (id INTEGER PRIMARY KEY AUTOINCREMENT, event_id INTEGER NOT NULL, image_path TEXT NOT NULL, alt_text TEXT NULL, sort_order INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL)');
        $this->connection->exec('CREATE TABLE registrations (id INTEGER PRIMARY KEY AUTOINCREMENT, event_id INTEGER NOT NULL, user_id INTEGER NOT NULL, status TEXT NOT NULL, cancelled_at TEXT NULL, cancellation_reason TEXT NULL, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $this->connection->exec('CREATE TABLE payments (id INTEGER PRIMARY KEY AUTOINCREMENT, registration_id INTEGER NOT NULL, status TEXT NOT NULL, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $this->connection->exec('CREATE TABLE tickets (id INTEGER PRIMARY KEY AUTOINCREMENT, registration_id INTEGER NOT NULL, status TEXT NOT NULL, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $this->connection->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, type TEXT NOT NULL, title TEXT NOT NULL, message TEXT NOT NULL, action_url TEXT NULL, data TEXT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
    }

    private function seedEvents(): void
    {
        $this->connection->exec("INSERT INTO organizers (id, user_id, organization_name) VALUES (1, 10, 'Open Events Bangladesh')");
        $this->connection->exec("INSERT INTO categories (id, name, slug, is_active) VALUES (1, 'Technology', 'technology', 1)");
        $this->connection->exec("INSERT INTO venues (id, organizer_id, name, city, country) VALUES (1, 1, 'Dhaka Community Hall', 'Dhaka', 'Bangladesh')");

        $insert = $this->connection->prepare(
            'INSERT INTO events
                (id, organizer_id, category_id, venue_id, title, slug, description, banner, map_url, speaker, start_date, end_date, registration_deadline, capacity, available_seats, ticket_price, currency, tags, status, approved_by, approved_at, published_at, is_featured, created_at, updated_at, deleted_at)
             VALUES
                (:id, 1, 1, 1, :title, :slug, :description, :banner, :map_url, :speaker, :start_date, :end_date, :registration_deadline, 120, 96, 750, "BDT", :tags, :status, :approved_by, :approved_at, :published_at, 0, :created_at, :created_at, NULL)',
        );
        $fixtures = [
            11 => ['Pending Accessibility Forum', 'pending-accessibility-forum', 'pending'],
            12 => ['Pending Community Lab', 'pending-community-lab', 'pending'],
            13 => ['Approved Product Summit', 'approved-product-summit', 'approved'],
            14 => ['Published Design Conference', 'published-design-conference', 'published'],
            15 => ['Approved Civic Workshop', 'approved-civic-workshop', 'approved'],
        ];

        foreach ($fixtures as $id => [$title, $slug, $status]) {
            $insert->execute([
                'id' => $id,
                'title' => $title,
                'slug' => $slug,
                'description' => 'A full event proposal with schedule, venue, pricing, and accessibility evidence.',
                'banner' => '/uploads/events/proposal.jpg',
                'map_url' => 'https://example.test/map',
                'speaker' => 'Samira Chowdhury',
                'start_date' => '2026-09-15 18:00:00',
                'end_date' => '2026-09-15 21:00:00',
                'registration_deadline' => '2026-09-14 18:00:00',
                'tags' => '["product","community"]',
                'status' => $status,
                'approved_by' => $status === 'approved' || $status === 'published' ? 77 : null,
                'approved_at' => $status === 'approved' || $status === 'published' ? '2026-08-06 12:00:00' : null,
                'published_at' => $status === 'published' ? '2026-08-07 12:00:00' : null,
                'created_at' => sprintf('2026-08-0%d 10:00:00', $id - 10),
            ]);
        }

        $this->connection->exec(
            "INSERT INTO event_gallery (event_id, image_path, alt_text, sort_order, created_at)
             VALUES (11, '/uploads/events/pending-gallery.jpg', 'Pending gallery evidence', 1, CURRENT_TIMESTAMP)",
        );
    }

    private function eventStatus(int $eventId): string
    {
        return (string) $this->eventValue($eventId, 'status');
    }

    private function eventValue(int $eventId, string $column): mixed
    {
        $statement = $this->connection->prepare("SELECT {$column} FROM events WHERE id = :id");
        $statement->execute(['id' => $eventId]);

        return $statement->fetchColumn();
    }

    private function activityCount(): int
    {
        return (int) $this->connection->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn();
    }

    private function activityActions(): array
    {
        $actions = $this->connection->query('SELECT action FROM activity_logs ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);

        return is_array($actions) ? $actions : [];
    }
}
