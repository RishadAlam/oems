<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Controllers\AdminPaymentController;
use OEMS\App\Middleware\CsrfMiddleware;
use OEMS\App\Middleware\RoleMiddleware;
use OEMS\App\Repositories\PaymentRepository;
use OEMS\App\Repositories\RegistrationRepository;
use OEMS\App\Repositories\TicketRepository;
use OEMS\App\Services\RegistrationService;
use OEMS\App\Services\TicketArtifactService;
use OEMS\App\Services\TicketService;
use OEMS\App\Services\TransactionMailer;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Container;
use OEMS\Core\Request;
use OEMS\Core\Router;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakeEmailLogRepository;
use OEMS\Tests\Support\FakeMailTransport;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\TestCase;
use PDO;

final class AdminPaymentControllerTest extends TestCase
{
    private PDO $connection;

    private Session $session;

    private Security $security;

    private FakeUserRepository $users;

    private PaymentRepository $payments;

    private AdminPaymentController $controller;

    private string $ticketRoot;

    protected function setUp(): void
    {
        $_SESSION = [];
        $_SERVER['REQUEST_URI'] = '/admin/payments';
        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->createSchema();
        $this->seedRows();
        $this->session = new Session(false);
        $this->security = new Security($this->session);
        $this->users = $this->users('super-admin', $this->session);
        $auth = new Auth($this->session, $this->users);
        $this->payments = new PaymentRepository($this->connection);
        $registrations = new RegistrationRepository($this->connection);
        $tickets = new TicketRepository($this->connection);
        $this->ticketRoot = sys_get_temp_dir() . '/oems-admin-payments-' . bin2hex(random_bytes(6));
        $ticketService = new TicketService(
            $this->connection,
            $tickets,
            new TicketArtifactService($this->ticketRoot, 'uploads/tickets'),
        );
        $service = new RegistrationService(
            $this->connection,
            $this->users,
            $registrations,
            $this->payments,
            $ticketService,
            new TransactionMailer(
                new FakeMailTransport('<admin-payment-message>'),
                new FakeEmailLogRepository(),
                new Config(['url' => 'http://localhost:8000']),
            ),
        );
        $this->controller = new AdminPaymentController(
            new View(base_path('app/Views')),
            $this->session,
            $this->security,
            $auth,
            new Config(['name' => 'OEMS', 'timezone' => 'Asia/Dhaka']),
            $this->payments,
            $service,
        );
    }

    protected function tearDown(): void
    {
        if (is_dir($this->ticketRoot)) {
            foreach (glob($this->ticketRoot . '/*') ?: [] as $path) {
                unlink($path);
            }
            rmdir($this->ticketRoot);
        }

        $_SESSION = [];
        unset($_SERVER['REQUEST_URI']);
    }

    public function testQueueDefaultsToPendingAndUsesRealFilteredPagination(): void
    {
        $pending = $this->controller->index(Request::create('GET', '/admin/payments'));
        $paid = $this->controller->index(Request::create(
            'GET',
            '/admin/payments?status=paid&search=settled&page=1&per_page=1',
            query: ['status' => 'paid', 'search' => 'settled', 'page' => '1', 'per_page' => '1'],
        ));
        $unknown = $this->controller->index(Request::create(
            'GET',
            '/admin/payments?status[]=paid',
            query: ['status' => ['paid']],
        ));

        $this->assertSame(200, $pending->status());
        $this->assertTrue(str_contains($pending->body(), 'REF-PENDING-OLD'));
        $this->assertTrue(str_contains($pending->body(), 'REF-PENDING-NEW'));
        $this->assertFalse(str_contains($pending->body(), 'REF-SETTLED'));
        $this->assertTrue(str_contains($pending->body(), '3 matching payments'));
        $this->assertTrue(str_contains($paid->body(), 'REF-SETTLED'));
        $this->assertFalse(str_contains($paid->body(), 'REF-PENDING-OLD'));
        $this->assertTrue(str_contains($paid->body(), 'Page 1 of 1'));
        $this->assertTrue(str_contains($unknown->body(), 'REF-PENDING-OLD'));
    }

    public function testDetailShowsEscapedEvidenceBeforeSeparateActionsWithoutGatewaySecrets(): void
    {
        $response = $this->controller->show($this->routed('GET', '/admin/payments/201', '201'));
        $body = $response->body();
        $evidence = strpos($body, 'REF-PENDING-OLD');
        $verify = strpos($body, 'action="/admin/payments/201/verify"');
        $reject = strpos($body, 'action="/admin/payments/201/reject"');

        $this->assertSame(200, $response->status());
        $this->assertNotSame(false, $evidence);
        $this->assertNotSame(false, $verify);
        $this->assertNotSame(false, $reject);
        $this->assertTrue($evidence < $verify);
        $this->assertTrue($verify < $reject);
        $this->assertTrue(str_contains($body, '&lt;script&gt;alert(1)&lt;/script&gt;'));
        $this->assertTrue(str_contains($body, 'Seat held while payment is pending'));
        $this->assertTrue(str_contains($body, 'maxlength="500"'));
        $this->assertTrue(str_contains($body, 'id="verify-note-help"'));
        $this->assertTrue(str_contains($body, 'id="reject-note-help"'));
        $this->assertFalse(str_contains($body, 'gateway_response'));
        $this->assertFalse(str_contains($body, 'private-account-token'));
        $this->assertFalse(str_contains($body, 'formaction='));
    }

    public function testDetailPreservesAllowListedQueueFiltersFromTheGetQuery(): void
    {
        $request = Request::create(
            'GET',
            '/admin/payments/201?status=all&search=participant&page=2&per_page=1',
            query: ['status' => 'all', 'search' => 'participant', 'page' => '2', 'per_page' => '1'],
        )->withRouteParameters(['id' => '201']);

        $response = $this->controller->show($request);

        $this->assertSame(200, $response->status());
        $this->assertTrue(str_contains(
            $response->body(),
            'href="/admin/payments?status=all&amp;search=participant&amp;page=2&amp;per_page=1"',
        ));
        $this->assertTrue(str_contains($response->body(), 'name="status" value="all"'));
    }

    public function testHiddenParticipantAndEventPaymentsCannotBeShownVerifiedOrRejected(): void
    {
        $this->assertSame(404, $this->controller->show($this->routed('GET', '/admin/payments/205', '205'))->status());
        $this->assertSame(404, $this->controller->show($this->routed('GET', '/admin/payments/206', '206'))->status());

        $verified = $this->controller->verify($this->routed('POST', '/admin/payments/205/verify', '205'));
        $rejected = $this->controller->reject($this->routed('POST', '/admin/payments/206/reject', '206'));

        $this->assertSame(404, $verified->status());
        $this->assertSame(404, $rejected->status());
        $this->assertSame('pending', $this->paymentStatus(205));
        $this->assertSame('pending', $this->paymentStatus(206));
        $this->assertSame('pending', $this->registrationStatus(105));
        $this->assertSame('pending', $this->registrationStatus(106));
        $this->assertSame(0, $this->ticketCount(105));
        $this->assertSame(0, $this->ticketCount(106));
        $this->assertSame(0, $this->availableSeats(18));
    }

    public function testVerifyAndRejectUseAtomicServiceFlowsAndRepeatTruthfully(): void
    {
        $verified = $this->controller->verify($this->routed('POST', '/admin/payments/201/verify', '201', [
            'note' => '  Reference matched the bank record.  ',
        ]));

        $this->assertSame('/admin/payments/201', $verified->header('Location'));
        $this->assertSame('paid', $this->paymentStatus(201));
        $this->assertSame('confirmed', $this->registrationStatus(101));
        $this->assertSame(1, $this->ticketCount(101));
        $this->assertSame('Reference matched the bank record.', $this->paymentValue(201, 'review_note'));
        $this->assertTrue(str_contains((string) $this->session->get('_flash.success'), 'ticket issued'));

        $repeat = $this->controller->verify($this->routed('POST', '/admin/payments/201/verify', '201'));
        $this->assertSame('/admin/payments/201', $repeat->header('Location'));
        $this->assertSame(1, $this->ticketCount(101));

        $rejected = $this->controller->reject($this->routed('POST', '/admin/payments/203/reject', '203'));
        $this->assertSame('/admin/payments/203', $rejected->header('Location'));
        $this->assertSame('failed', $this->paymentStatus(203), (string) $this->session->get('_flash.error', ''));
        $this->assertSame('cancelled', $this->registrationStatus(103));
        $this->assertSame(1, $this->availableSeats(17));
        $this->assertTrue(str_contains((string) $this->session->get('_flash.success'), 'seat released'));

        $repeatRejected = $this->controller->reject($this->routed('POST', '/admin/payments/203/reject', '203'));
        $this->assertSame('/admin/payments/203', $repeatRejected->header('Location'));
        $this->assertSame(1, $this->availableSeats(17));
    }

    public function testOppositeTerminalActionIsAConflictAndInvalidNotePreservesOnlySafeFields(): void
    {
        $conflict = $this->controller->reject($this->routed('POST', '/admin/payments/204/reject', '204'));
        $invalidNote = $this->controller->verify($this->routed('POST', '/admin/payments/202/verify', '202', [
            'note' => str_repeat('n', 501),
            'status' => 'paid',
            'search' => 'other participant',
            'page' => '2',
            'per_page' => '1',
            'gateway_response' => 'must-not-survive',
            'return_to' => 'https://attacker.example',
        ]));

        $this->assertSame(409, $conflict->status());
        $this->assertTrue(str_contains($conflict->body(), 'already verified'));
        $this->assertSame('paid', $this->paymentStatus(204));
        $this->assertSame('/admin/payments/202?status=paid&search=other%20participant&page=2&per_page=1', $invalidNote->header('Location'));
        $this->assertArrayHasKey('note', $this->session->get('_flash.errors', []));
        $old = $this->session->get('_flash.old', []);
        $this->assertArrayHasKey('note', $old);
        $this->assertFalse(array_key_exists('gateway_response', $old));
        $this->assertFalse(array_key_exists('return_to', $old));
        $this->assertSame('pending', $this->paymentStatus(202));
    }

    public function testMalformedAndMissingPaymentIdsReturnNotFound(): void
    {
        foreach (['999', '0', '-1', 'payment'] as $id) {
            $this->assertSame(404, $this->controller->show($this->routed('GET', '/admin/payments/' . $id, $id))->status());
        }

        $this->assertSame(404, $this->controller->verify($this->routed('POST', '/admin/payments/0/verify', '0'))->status());
    }

    public function testRoutesRequireSuperAdministratorAndMutationCsrfAndRejectWrongMethods(): void
    {
        foreach (['organizer', 'participant'] as $role) {
            $router = $this->routerForRole($role);
            $this->assertSame(403, $router['router']->dispatch(Request::create('GET', '/admin/payments'))->status());
            $this->assertSame(403, $router['router']->dispatch(Request::create('GET', '/admin/payments/201'))->status());
            $this->assertSame(403, $router['router']->dispatch(Request::create('POST', '/admin/payments/201/verify', input: [
                '_token' => $router['security']->csrfToken(),
            ]))->status());
        }

        $administrator = $this->routerForRole('super-admin');
        $this->assertSame(419, $administrator['router']->dispatch(Request::create('POST', '/admin/payments/201/verify', input: [
            '_token' => 'invalid',
        ]))->status());
        $this->assertSame(419, $administrator['router']->dispatch(Request::create('POST', '/admin/payments/203/reject', input: [
            '_token' => 'invalid',
        ]))->status());
        $this->assertSame(405, $administrator['router']->dispatch(Request::create('GET', '/admin/payments/201/verify'))->status());
        $this->assertSame(405, $administrator['router']->dispatch(Request::create('PUT', '/admin/payments/201'))->status());
    }

    private function routerForRole(string $role): array
    {
        $_SESSION = [];
        $session = new Session(false);
        $security = new Security($session);
        $auth = new Auth($session, $this->users($role, $session));
        $container = new Container();
        $container->instance(AdminPaymentController::class, $this->controller);
        $router = new Router($container);
        $router->aliasMiddleware('role', new RoleMiddleware($auth));
        $router->aliasMiddleware('csrf', new CsrfMiddleware($security));
        $registerRoutes = require base_path('routes/web.php');
        $registerRoutes($router);

        return ['router' => $router, 'security' => $security];
    }

    private function users(string $role, Session $session): FakeUserRepository
    {
        $users = new FakeUserRepository();
        $users->users = [
            1 => ['id' => 1, 'role_id' => 3, 'name' => 'Participant One', 'email' => 'participant@example.test', 'status' => 'active', 'email_verified_at' => '2026-08-01 00:00:00'],
            3 => ['id' => 3, 'role_id' => 3, 'name' => 'Other Participant', 'email' => 'other@example.test', 'status' => 'active', 'email_verified_at' => '2026-08-01 00:00:00'],
            9 => ['id' => 9, 'role_id' => $role === 'super-admin' ? 1 : ($role === 'organizer' ? 2 : 3), 'name' => 'Administrator', 'email' => 'admin@example.test', 'status' => 'active', 'email_verified_at' => '2026-08-01 00:00:00'],
        ];
        $this->authenticateSession($session, $users, 9);

        return $users;
    }

    private function routed(string $method, string $uri, string $id, array $input = []): Request
    {
        return Request::create($method, $uri, input: $input)->withRouteParameters(['id' => $id]);
    }

    private function createSchema(): void
    {
        $this->connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, email TEXT NOT NULL, deleted_at TEXT NULL)');
        $this->connection->exec('CREATE TABLE organizers (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, organization_name TEXT NOT NULL, approval_status TEXT NOT NULL)');
        $this->connection->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT NOT NULL, slug TEXT NOT NULL, is_active INTEGER NOT NULL)');
        $this->connection->exec('CREATE TABLE venues (id INTEGER PRIMARY KEY, name TEXT NOT NULL, address_line TEXT NULL, city TEXT NULL, country TEXT NULL, postal_code TEXT NULL, latitude NUMERIC NULL, longitude NUMERIC NULL, map_url TEXT NULL)');
        $this->connection->exec('CREATE TABLE events (id INTEGER PRIMARY KEY, organizer_id INTEGER NOT NULL, category_id INTEGER NOT NULL, venue_id INTEGER NULL, title TEXT NOT NULL, slug TEXT NOT NULL, start_date TEXT NOT NULL, registration_deadline TEXT NOT NULL, capacity INTEGER NOT NULL, available_seats INTEGER NOT NULL, ticket_price NUMERIC NOT NULL, currency TEXT NOT NULL, status TEXT NOT NULL, location_visibility TEXT NOT NULL DEFAULT "public", arrival_notes TEXT NULL, deleted_at TEXT NULL, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $this->connection->exec('CREATE TABLE registrations (id INTEGER PRIMARY KEY AUTOINCREMENT, event_id INTEGER NOT NULL, user_id INTEGER NOT NULL, coupon_id INTEGER NULL, registration_number TEXT NOT NULL UNIQUE, status TEXT NOT NULL, amount NUMERIC NOT NULL, currency TEXT NOT NULL, registered_at TEXT NOT NULL, cancelled_at TEXT NULL, cancellation_reason TEXT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE (event_id, user_id))');
        $this->connection->exec('CREATE TABLE payment_methods (id INTEGER PRIMARY KEY, name TEXT NOT NULL, slug TEXT NOT NULL UNIQUE, configuration TEXT NULL, is_active INTEGER NOT NULL)');
        $this->connection->exec('CREATE TABLE payments (id INTEGER PRIMARY KEY AUTOINCREMENT, registration_id INTEGER NOT NULL, payment_method_id INTEGER NULL, transaction_reference TEXT NULL UNIQUE, amount NUMERIC NOT NULL, currency TEXT NOT NULL, status TEXT NOT NULL, gateway_response TEXT NULL, paid_at TEXT NULL, refunded_at TEXT NULL, reviewed_by INTEGER NULL, reviewed_at TEXT NULL, review_note TEXT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $this->connection->exec('CREATE TABLE tickets (id INTEGER PRIMARY KEY AUTOINCREMENT, registration_id INTEGER NOT NULL UNIQUE, ticket_number TEXT NOT NULL UNIQUE, qr_payload_hash TEXT NOT NULL UNIQUE, qr_path TEXT NULL, pdf_path TEXT NULL, status TEXT NOT NULL, issued_at TEXT NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $this->connection->exec('CREATE TABLE attendance (id INTEGER PRIMARY KEY AUTOINCREMENT, registration_id INTEGER NOT NULL UNIQUE, ticket_id INTEGER NOT NULL UNIQUE, scanned_by INTEGER NOT NULL, status TEXT NOT NULL, scanned_at TEXT NOT NULL, scanner_ip TEXT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
    }

    private function seedRows(): void
    {
        $this->connection->exec("INSERT INTO users (id, name, email, deleted_at) VALUES (1, '<script>alert(1)</script>', 'participant@example.test', NULL), (2, 'Organizer', 'organizer@example.test', NULL), (3, 'Other Participant', 'other@example.test', NULL), (4, 'Settled Participant', 'settled@example.test', NULL), (5, 'Deleted Participant', 'deleted@example.test', '2026-08-08 00:00:00'), (9, 'Administrator', 'admin@example.test', NULL)");
        $this->connection->exec("INSERT INTO organizers (id, user_id, organization_name, approval_status) VALUES (1, 2, 'Organizer Company', 'approved')");
        $this->connection->exec("INSERT INTO categories (id, name, slug, is_active) VALUES (1, 'Active', 'active', 1)");
        $this->connection->exec("INSERT INTO venues (id, name) VALUES (1, 'Main Hall')");
        $this->connection->exec("INSERT INTO events (id, organizer_id, category_id, venue_id, title, slug, start_date, registration_deadline, capacity, available_seats, ticket_price, currency, status, deleted_at) VALUES (11, 1, 1, 1, 'Paid Event', 'paid-event', datetime('now', '+10 days'), datetime('now', '+9 days'), 4, 1, 125.50, 'BDT', 'published', NULL), (17, 1, 1, 1, 'Reject Event', 'reject-event', datetime('now', '+10 days'), datetime('now', '+9 days'), 1, 0, 80, 'BDT', 'published', NULL), (18, 1, 1, 1, 'Deleted Event', 'deleted-event', datetime('now', '+10 days'), datetime('now', '+9 days'), 1, 0, 90, 'BDT', 'published', '2026-08-08 00:00:00')");
        $this->connection->exec("INSERT INTO registrations (id, event_id, user_id, registration_number, status, amount, currency, registered_at) VALUES (101, 11, 1, 'REG-101', 'pending', 125.50, 'BDT', CURRENT_TIMESTAMP), (102, 11, 3, 'REG-102', 'pending', 125.50, 'BDT', CURRENT_TIMESTAMP), (103, 17, 1, 'REG-103', 'pending', 80, 'BDT', CURRENT_TIMESTAMP), (104, 11, 4, 'REG-104', 'confirmed', 125.50, 'BDT', CURRENT_TIMESTAMP), (105, 11, 5, 'REG-105', 'pending', 125.50, 'BDT', CURRENT_TIMESTAMP), (106, 18, 3, 'REG-106', 'pending', 90, 'BDT', CURRENT_TIMESTAMP)");
        $this->connection->exec("INSERT INTO payment_methods (id, name, slug, configuration, is_active) VALUES (2, 'Manual payment', 'manual', '{}', 1)");
        $this->connection->exec("INSERT INTO payments (id, registration_id, payment_method_id, transaction_reference, amount, currency, status, gateway_response, paid_at, reviewed_by, reviewed_at, review_note, created_at, updated_at) VALUES (201, 101, 2, 'REF-PENDING-OLD', 125.50, 'BDT', 'pending', '{\"channel\":\"bank\",\"credential\":\"private-account-token\"}', NULL, NULL, NULL, NULL, '2026-08-01 09:00:00', '2026-08-01 09:00:00'), (202, 102, 2, 'REF-PENDING-NEW', 125.50, 'BDT', 'pending', '{\"channel\":\"mobile\"}', NULL, NULL, NULL, NULL, '2026-08-02 09:00:00', '2026-08-02 09:00:00'), (203, 103, 2, 'REF-REJECT', 80, 'BDT', 'pending', '{\"channel\":\"cash\"}', NULL, NULL, NULL, NULL, '2026-08-03 09:00:00', '2026-08-03 09:00:00'), (204, 104, 2, 'REF-SETTLED', 125.50, 'BDT', 'paid', NULL, '2026-08-04 09:00:00', 9, '2026-08-04 09:00:00', 'Verified', '2026-08-04 09:00:00', '2026-08-04 09:00:00'), (205, 105, 2, 'REF-DELETED-PARTICIPANT', 125.50, 'BDT', 'pending', NULL, NULL, NULL, NULL, NULL, '2026-08-05 09:00:00', '2026-08-05 09:00:00'), (206, 106, 2, 'REF-DELETED-EVENT', 90, 'BDT', 'pending', NULL, NULL, NULL, NULL, NULL, '2026-08-06 09:00:00', '2026-08-06 09:00:00')");
        $this->connection->exec("INSERT INTO tickets (id, registration_id, ticket_number, qr_payload_hash, status, issued_at) VALUES (301, 104, 'OEMS-SETTLED', '" . str_repeat('a', 64) . "', 'valid', '2026-08-04 09:00:00')");
    }

    private function paymentStatus(int $id): string
    {
        return (string) $this->paymentValue($id, 'status');
    }

    private function paymentValue(int $id, string $field): mixed
    {
        $statement = $this->connection->prepare("SELECT {$field} FROM payments WHERE id = :id");
        $statement->execute(['id' => $id]);

        return $statement->fetchColumn();
    }

    private function registrationStatus(int $id): string
    {
        return (string) $this->connection->query("SELECT status FROM registrations WHERE id = {$id}")->fetchColumn();
    }

    private function ticketCount(int $registrationId): int
    {
        return (int) $this->connection->query("SELECT COUNT(*) FROM tickets WHERE registration_id = {$registrationId}")->fetchColumn();
    }

    private function availableSeats(int $eventId): int
    {
        return (int) $this->connection->query("SELECT available_seats FROM events WHERE id = {$eventId}")->fetchColumn();
    }
}
