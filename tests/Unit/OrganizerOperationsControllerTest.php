<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Controllers\OrganizerCheckInController;
use OEMS\App\Controllers\OrganizerParticipantController;
use OEMS\App\Middleware\CsrfMiddleware;
use OEMS\App\Middleware\RoleMiddleware;
use OEMS\App\Services\TicketArtifactService;
use OEMS\App\Services\TicketService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Container;
use OEMS\Core\RateLimiter;
use OEMS\Core\Request;
use OEMS\Core\Router;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakeRegistrationRepository;
use OEMS\Tests\Support\FakeTicketRepository;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\TestCase;
use PDO;

final class OrganizerOperationsControllerTest extends TestCase
{
    private Session $session;

    private Security $security;

    private FakeRegistrationRepository $registrations;

    private FakeTicketRepository $tickets;

    private OrganizerParticipantController $participants;

    private OrganizerCheckInController $checkIn;

    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $_SESSION = [];
        $_SERVER['REQUEST_URI'] = '/organizer/events/10/participants';
        $this->temporaryDirectory = sys_get_temp_dir() . '/oems-organizer-operations-' . bin2hex(random_bytes(6));
        mkdir($this->temporaryDirectory, 0775, true);
        $this->session = new Session(false);
        $this->session->put('auth.user_id', 10);
        $this->security = new Security($this->session);
        $users = $this->users('organizer');
        $auth = new Auth($this->session, $users);
        $config = new Config(['name' => 'OEMS', 'url' => 'https://oems.test']);
        $view = new View(base_path('app/Views'));
        $this->registrations = new FakeRegistrationRepository();
        $this->registrations->organizerEvents[10] = [
            'event_id' => 10,
            'event_title' => "Eligible Event\r\nInjected",
            'event_slug' => "eligible-event\r\nX-Bad: yes",
            'organizer_user_id' => 10,
        ];
        $this->registrations->registrations[101] = [
            'id' => 101,
            'event_id' => 10,
            'user_id' => 1,
            'participant_name' => "=<strong>2+2</strong>\r\nInjected",
            'participant_email' => "\tformula@example.test",
            'registration_number' => '+REG-101',
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'ticket_number' => '-OEMS-AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
            'ticket_status' => 'valid',
            'attendance_status' => 'not_checked_in',
            'scanned_at' => null,
            'registered_at' => '2026-08-08 10:00:00',
            'organizer_user_id' => 10,
        ];
        $this->tickets = new FakeTicketRepository();
        $rawToken = str_repeat('a', 64);
        $this->tickets->tickets[20] = [
            'id' => 20,
            'registration_id' => 101,
            'participant_id' => 1,
            'organizer_user_id' => 10,
            'event_id' => 10,
            'ticket_number' => 'OEMS-AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
            'qr_payload_hash' => hash('sha256', $rawToken),
            'status' => 'valid',
            'registration_status' => 'confirmed',
        ];
        $connection = new PDO('sqlite::memory:');
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $ticketService = new TicketService(
            $connection,
            $this->tickets,
            new TicketArtifactService($this->temporaryDirectory . '/tickets'),
        );
        $this->participants = new OrganizerParticipantController(
            $view,
            $this->session,
            $this->security,
            $auth,
            $config,
            $this->registrations,
        );
        $this->checkIn = new OrganizerCheckInController(
            $view,
            $this->session,
            $this->security,
            $auth,
            $config,
            $this->registrations,
            $ticketService,
            new RateLimiter($this->temporaryDirectory . '/limits', 2, 900),
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->temporaryDirectory);
    }

    public function testParticipantWorkspaceAppliesFiltersAndEscapesOperationalRows(): void
    {
        $response = $this->participants->index($this->routed('GET', '/organizer/events/10/participants', '10', query: [
            'registration_status' => 'confirmed',
            'search' => '=<strong>2+2</strong>',
        ]));

        $this->assertSame(200, $response->status());
        $this->assertTrue(str_contains($response->body(), 'Eligible Event'));
        $this->assertTrue(str_contains($response->body(), '=&lt;strong&gt;2+2&lt;/strong&gt;'));
        $this->assertFalse(str_contains($response->body(), '<strong>2+2</strong>'));
        $this->assertTrue(str_contains($response->body(), 'participant-name'));
        $this->assertTrue(str_contains($response->body(), 'Payment'));
        foreach (['Participant', 'Registration', 'Payment', 'Ticket', 'Attendance'] as $label) {
            $this->assertTrue(str_contains($response->body(), 'data-label="' . $label . '"'));
        }
        $this->assertFalse(str_contains($response->body(), 'qr_payload_hash'));
        $this->assertFalse(str_contains($response->body(), 'gateway_response'));
        $this->assertSame(404, $this->participants->index($this->routed('GET', '/organizer/events/99/participants', '99'))->status());
        $this->assertSame(404, $this->participants->index($this->routed('GET', '/organizer/events/0/participants', '0'))->status());
    }

    public function testParticipantSearchNormalizesWhitespaceAndRejectsOverlengthForUiAndCsv(): void
    {
        $normalized = $this->participants->index($this->routed(
            'GET',
            '/organizer/events/10/participants',
            '10',
            query: ['search' => "  formula@example.test\t\n  "],
        ));
        $overlength = str_repeat('x', 121);
        $rejectedUi = $this->participants->index($this->routed(
            'GET',
            '/organizer/events/10/participants',
            '10',
            query: ['search' => $overlength],
        ));
        $rejectedCsv = $this->participants->export($this->routed(
            'GET',
            '/organizer/events/10/participants.csv',
            '10',
            query: ['search' => $overlength],
        ));

        $this->assertSame(200, $normalized->status());
        $this->assertTrue(str_contains($normalized->body(), 'formula@example.test'));
        $this->assertTrue(str_contains($normalized->body(), 'name="search" type="search" maxlength="120" value="formula@example.test"'));
        $this->assertSame(422, $rejectedUi->status());
        $this->assertSame(422, $rejectedCsv->status());
        $this->assertFalse(str_contains($rejectedUi->body(), 'formula@example.test'));
        $this->assertFalse(str_contains($rejectedUi->body(), $overlength));
    }

    public function testParticipantFakeCountIsIndependentOfThePageLimit(): void
    {
        foreach (range(102, 226) as $registrationId) {
            $this->registrations->registrations[$registrationId] = [
                'id' => $registrationId,
                'event_id' => 10,
                'participant_name' => 'Participant ' . $registrationId,
                'participant_email' => 'participant' . $registrationId . '@example.test',
                'status' => 'confirmed',
                'registered_at' => '2026-08-08 10:00:00',
                'organizer_user_id' => 10,
            ];
        }

        $this->assertSame(100, count($this->registrations->forOrganizerEvent(10, 10, [], 100, 0)));
        $this->assertSame(126, $this->registrations->countForOrganizerEvent(10, 10, []));
    }

    public function testCsvExportNeutralizesFormulasControlsAndUnsafeFilenameData(): void
    {
        $response = $this->participants->export($this->routed('GET', '/organizer/events/10/participants.csv', '10'));

        $this->assertSame(200, $response->status());
        $this->assertSame('', $response->body());
        $this->assertSame('text/csv; charset=UTF-8', $response->header('Content-Type'));
        $this->assertSame('attachment; filename="eligible-event-participants.csv"', $response->header('Content-Disposition'));
        $this->assertSame('nosniff', $response->header('X-Content-Type-Options'));
        $this->assertSame('private, no-store', $response->header('Cache-Control'));
        $this->assertSame([], $this->registrations->organizerPageRequests);
        ob_start();
        $response->send();
        $csv = ob_get_clean();

        $this->assertTrue(str_starts_with($csv, "\xEF\xBB\xBF"));
        $this->assertTrue(str_contains($csv, "'=<strong>2+2</strong> Injected"));
        $this->assertTrue(str_contains($csv, "' formula@example.test"));
        $this->assertTrue(str_contains($csv, "'+REG-101"));
        $this->assertTrue(str_contains($csv, "'-OEMS-"));
        $this->assertFalse(str_contains($csv, "\r"));
        $this->assertFalse(str_contains((string) $response->header('Content-Disposition'), "\n"));
    }

    public function testLargeCsvExportStreamsEveryPageOnlyWhenResponseIsSent(): void
    {
        foreach (range(102, 350) as $registrationId) {
            $this->registrations->registrations[$registrationId] = [
                'id' => $registrationId,
                'event_id' => 10,
                'participant_name' => 'Participant ' . $registrationId,
                'participant_email' => 'participant' . $registrationId . '@example.test',
                'registration_number' => 'REG-' . $registrationId,
                'status' => 'confirmed',
                'payment_status' => 'paid',
                'ticket_status' => 'none',
                'attendance_status' => 'not_checked_in',
                'registered_at' => '2026-08-08 10:00:00',
                'organizer_user_id' => 10,
            ];
        }

        $response = $this->participants->export($this->routed('GET', '/organizer/events/10/participants.csv', '10'));

        $this->assertSame('', $response->body());
        $this->assertSame([], $this->registrations->organizerPageRequests);
        ob_start();
        $response->send();
        $csv = ob_get_clean();

        $this->assertSame([
            ['limit' => 100, 'offset' => 0],
            ['limit' => 100, 'offset' => 100],
            ['limit' => 100, 'offset' => 200],
        ], $this->registrations->organizerPageRequests);
        $this->assertSame(251, substr_count($csv, "\n"));
        $this->assertTrue(str_contains($csv, 'Participant 350'));
        $this->assertTrue(str_contains($csv, 'participant350@example.test'));
    }

    public function testCheckInWorkspaceKeepsManualFallbackAndDoesNotEchoScanSecrets(): void
    {
        $rawToken = str_repeat('a', 64);
        $workspace = $this->checkIn->index($this->routed('GET', '/organizer/events/10/check-in', '10'));
        $first = $this->checkIn->store($this->routed('POST', '/organizer/events/10/check-in', '10', input: [
            'code' => '/organizer/check-in?token=' . $rawToken,
        ], server: ['REMOTE_ADDR' => '127.0.0.1']));

        $this->assertSame(200, $workspace->status());
        $this->assertTrue(str_contains($workspace->body(), 'name="code"'));
        $this->assertTrue(str_contains($workspace->body(), 'data-check-in-camera'));
        $this->assertSame('/organizer/events/10/check-in', $first->header('Location'));
        $this->assertSame('Ticket checked in.', $this->session->get('_flash.success'));
        $this->assertFalse(str_contains(serialize($_SESSION), $rawToken));

        $duplicate = $this->checkIn->store($this->routed('POST', '/organizer/events/10/check-in', '10', input: [
            'code' => 'OEMS-AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
        ]));
        $this->assertSame('/organizer/events/10/check-in', $duplicate->header('Location'));
        $this->assertTrue(str_contains((string) $this->session->get('_flash.info'), 'already checked in'));
    }

    public function testFailedScansAreRateLimitedWithoutRetainingSubmittedValues(): void
    {
        $secret = str_repeat('f', 64);
        $this->checkIn->store($this->routed('POST', '/organizer/events/10/check-in', '10', input: ['code' => $secret]));
        $this->checkIn->store($this->routed('POST', '/organizer/events/10/check-in', '10', input: ['code' => $secret]));
        $limited = $this->checkIn->store($this->routed('POST', '/organizer/events/10/check-in', '10', input: ['code' => $secret]));

        $this->assertSame(429, $limited->status());
        $this->assertFalse(str_contains($limited->body(), $secret));
        $this->assertFalse(str_contains(serialize($_SESSION), $secret));
    }

    public function testOrganizerOperationRoutesEnforceRoleAndCheckInCsrf(): void
    {
        foreach (['participant', 'organizer'] as $role) {
            [$router, $security] = $this->routerForRole($role);
            $list = $router->dispatch(Request::create('GET', '/organizer/events/10/participants'));
            $scan = $router->dispatch(Request::create('POST', '/organizer/events/10/check-in', input: [
                '_token' => $role === 'organizer' ? 'invalid' : $security->csrfToken(),
                'code' => 'OEMS-AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
            ]));

            $this->assertSame($role === 'organizer' ? 200 : 403, $list->status());
            $this->assertSame($role === 'organizer' ? 419 : 403, $scan->status());
        }
    }

    private function routerForRole(string $role): array
    {
        $_SESSION = [];
        $session = new Session(false);
        $session->put('auth.user_id', 10);
        $security = new Security($session);
        $auth = new Auth($session, $this->users($role));
        $container = new Container();
        $container->instance(OrganizerParticipantController::class, $this->participants);
        $container->instance(OrganizerCheckInController::class, $this->checkIn);
        $router = new Router($container);
        $router->aliasMiddleware('role', new RoleMiddleware($auth));
        $router->aliasMiddleware('csrf', new CsrfMiddleware($security));
        $register = require base_path('routes/web.php');
        $register($router);

        return [$router, $security];
    }

    private function users(string $role): FakeUserRepository
    {
        $users = new FakeUserRepository();
        $users->users[10] = [
            'id' => 10,
            'role_id' => $role === 'organizer' ? 2 : 3,
            'name' => 'Organizer User',
            'email' => 'organizer@example.test',
            'status' => 'active',
            'email_verified_at' => '2026-08-08 10:00:00',
        ];

        return $users;
    }

    private function routed(
        string $method,
        string $uri,
        string $id,
        array $query = [],
        array $input = [],
        array $server = [],
    ): Request {
        return Request::create($method, $uri, query: $query, input: $input, server: $server)
            ->withRouteParameters(['id' => $id]);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            if (in_array($entry, ['.', '..'], true)) {
                continue;
            }

            $path = $directory . '/' . $entry;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
