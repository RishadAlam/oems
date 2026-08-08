<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Controllers\DashboardController;
use OEMS\App\Repositories\DashboardMetricsRepository;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Request;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakeEventRepository;
use OEMS\Tests\Support\FakePaymentRepository;
use OEMS\Tests\Support\FakeRegistrationRepository;
use OEMS\Tests\Support\FakeTicketRepository;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\TestCase;
use PDO;

final class DashboardLayoutTest extends TestCase
{
    public function testPlacesDashboardContentInSecondDesktopGridColumn(): void
    {
        $html = $this->renderAdminDashboard();

        $this->assertTrue(
            str_contains($html, 'class="min-w-0 lg:col-start-2"'),
            'Dashboard content must start in the second desktop grid column beside the fixed sidebar.',
        );
    }

    public function testAdminDashboardOmitsPlaceholderPanels(): void
    {
        $html = $this->renderAdminDashboard();

        $this->assertFalse(str_contains($html, 'Foundation readiness'));
        $this->assertFalse(str_contains($html, 'Next delivery'));
    }

    public function testAdminDashboardRendersSuppliedPlatformTotals(): void
    {
        $html = $this->renderAdminDashboard([
            'metrics' => [
                'users' => 12,
                'organizers' => 3,
                'events' => 6,
                'pending_reviews' => 4,
                'registration' => ['active' => 9, 'pending' => 4, 'confirmed' => 5],
                'payment' => ['pending' => 3, 'paid' => 5, 'paid_total' => '825.50'],
                'ticket' => ['issued' => 5, 'checked_in' => 2],
                'reviews' => ['total' => 7, 'pending' => 2, 'published' => 5],
            ],
        ]);

        $this->assertTrue(str_contains($html, '>Users<'));
        $this->assertTrue(str_contains($html, '>12<'));
        $this->assertTrue(str_contains($html, '>Organizers<'));
        $this->assertTrue(str_contains($html, '>3<'));
        $this->assertTrue(str_contains($html, '>Events<'));
        $this->assertTrue(str_contains($html, '>6<'));
        $this->assertTrue(str_contains($html, '>Pending review<'));
        $this->assertTrue(str_contains($html, '>4<'));
        $this->assertTrue(str_contains($html, 'href="/admin/events?status=pending"'));
        $this->assertTrue(str_contains($html, 'Review events'));
        $this->assertTrue(str_contains($html, 'aria-label="Pending payments: 3"'));
        $this->assertTrue(str_contains($html, 'aria-label="Paid total: BDT 825.50"'));
        $this->assertTrue(str_contains($html, 'aria-label="Issued tickets: 5"'));
        $this->assertTrue(str_contains($html, 'aria-label="Checked in: 2"'));
        $this->assertTrue(str_contains($html, 'href="/admin/payments?status=pending"'));
    }

    public function testAdminReviewActionsCanWrapWithTheCompiledStylesheet(): void
    {
        $html = $this->renderAdminDashboard();
        $stylesheet = file_get_contents(base_path('public/assets/css/app.css'));

        $this->assertTrue(str_contains($html, 'flex-wrap'));
        $this->assertTrue(
            is_string($stylesheet) && str_contains($stylesheet, '.flex-wrap{flex-wrap:wrap}'),
            'The committed stylesheet must include the responsive wrapping utility used by the admin review actions.',
        );
    }

    public function testCompactButtonsKeepAMinimumFortyFourPixelInteractiveTargetInCompiledCss(): void
    {
        $html = $this->renderRoleDashboard('dashboard/participant', 'Participant');
        $stylesheet = file_get_contents(base_path('public/assets/css/app.css'));

        $this->assertTrue(str_contains($html, 'button--compact'));
        $this->assertTrue(
            is_string($stylesheet)
                && str_contains($stylesheet, '.button--compact{min-height:calc(var(--spacing) * 11);'),
            'Compact buttons must retain the 44px minimum target in the committed stylesheet.',
        );
    }

    public function testMobileDashboardNavigationExposesItsDisclosureContract(): void
    {
        $html = $this->renderAdminDashboard();

        $this->assertTrue(str_contains($html, 'id="dashboard-sidebar"'));
        $this->assertTrue(str_contains($html, 'data-dashboard-open'));
        $this->assertTrue(str_contains($html, 'aria-controls="dashboard-sidebar"'));
        $this->assertTrue(str_contains($html, 'aria-expanded="false"'));
        $this->assertTrue(str_contains($html, 'data-dashboard-close'));
    }

    public function testDashboardNavigationUsesDecorativeIconsWithoutChangingLinkNames(): void
    {
        $html = $this->renderAdminDashboard();

        $this->assertTrue(str_contains($html, 'class="ph ph-squares-four" aria-hidden="true"'));
        $this->assertTrue(str_contains($html, '>Overview</span>'));
        $this->assertTrue(str_contains($html, 'class="ph ph-compass" aria-hidden="true"'));
        $this->assertTrue(str_contains($html, '>Explore events</span>'));
    }

    public function testAdminMetricsExposeAccessibleSummariesForSuppliedValues(): void
    {
        $html = $this->renderAdminDashboard([
            'metrics' => ['users' => 12, 'organizers' => 3, 'events' => 6],
        ]);

        $this->assertTrue(str_contains($html, 'aria-label="Users: 12"'));
        $this->assertTrue(str_contains($html, 'aria-label="Organizers: 3"'));
        $this->assertTrue(str_contains($html, 'aria-label="Events: 6"'));
    }

    public function testOrganizerDashboardRendersRepositoryMetricsAndRecentEventActions(): void
    {
        $organizer = $this->renderRoleDashboard('dashboard/organizer', 'Organizer', [
            'summary' => [
                'total' => 7,
                'draft' => 2,
                'pending' => 3,
                'published' => 2,
            ],
            'events' => [[
                'id' => 91,
                'title' => 'Repository-backed Summit',
                'slug' => 'repository-backed-summit',
                'status' => 'draft',
                'start_date' => '2026-09-18 09:00:00',
            ]],
            'transactionMetrics' => [
                'registration' => ['active' => 18, 'pending' => 6, 'confirmed' => 12],
                'payment' => ['pending' => 6, 'paid' => 12, 'paid_total' => '4800.00'],
                'ticket' => ['issued' => 12, 'checked_in' => 8],
                'reviews' => ['published' => 5, 'awaiting_reply' => 2],
            ],
        ]);

        $this->assertTrue(str_contains($organizer, 'aria-label="Total events: 7"'));
        $this->assertTrue(str_contains($organizer, 'aria-label="Pending review: 3"'));
        $this->assertTrue(str_contains($organizer, 'aria-label="Published events: 2"'));
        $this->assertTrue(str_contains($organizer, 'href="/organizer/events/create"'));
        $this->assertTrue(str_contains($organizer, 'href="/organizer/events"'));
        $this->assertTrue(str_contains($organizer, 'Repository-backed Summit'));
        $this->assertTrue(str_contains($organizer, 'Draft'));
        $this->assertTrue(str_contains($organizer, 'href="/organizer/events/91/edit"'));
        $this->assertFalse(str_contains($organizer, 'type="button" disabled'));
        $this->assertTrue(str_contains($organizer, 'aria-label="Active registrations: 18"'));
        $this->assertTrue(str_contains($organizer, 'aria-label="Paid total: BDT 4800.00"'));
        $this->assertTrue(str_contains($organizer, 'aria-label="Checked in: 8"'));
        $this->assertTrue(str_contains($organizer, 'aria-label="Reviews awaiting reply: 2"'));
        $this->assertFalse(str_contains($organizer, 'Week 3'));
    }

    public function testOrganizerDashboardEscapesHostileRecentEventContent(): void
    {
        $organizer = $this->renderRoleDashboard('dashboard/organizer', 'Organizer', [
            'summary' => ['total' => 1, 'pending' => 0, 'published' => 0],
            'events' => [[
                'id' => 91,
                'title' => '<img src=x onerror=alert(1)>',
                'status' => 'draft',
                'start_date' => '2026-09-18 09:00:00',
            ]],
        ]);

        $this->assertTrue(str_contains($organizer, '&lt;img src=x onerror=alert(1)&gt;'));
        $this->assertFalse(str_contains($organizer, '<img src=x onerror=alert(1)>'));
    }

    public function testParticipantDashboardKeepsItsAvailableDiscoveryAction(): void
    {
        $participant = $this->renderRoleDashboard('dashboard/participant', 'Participant');

        $this->assertTrue(str_contains($participant, 'href="/events"'));
        $this->assertTrue(str_contains($participant, 'Find an event'));
    }

    public function testParticipantDashboardRendersRealTransactionAndReviewMetrics(): void
    {
        $participant = $this->renderRoleDashboard('dashboard/participant', 'Participant', [
            'metrics' => [
                'registration' => ['active' => 3, 'pending' => 1, 'confirmed' => 2],
                'payment' => ['pending' => 1, 'paid' => 2, 'paid_total' => '250.50'],
                'ticket' => ['issued' => 2, 'checked_in' => 1],
                'reviews' => ['submitted' => 4, 'pending' => 1, 'published' => 3],
            ],
        ]);

        $this->assertTrue(str_contains($participant, 'aria-label="Active registrations: 3"'));
        $this->assertTrue(str_contains($participant, 'aria-label="Pending registrations: 1"'));
        $this->assertTrue(str_contains($participant, 'aria-label="Confirmed registrations: 2"'));
        $this->assertTrue(str_contains($participant, 'aria-label="Pending payments: 1"'));
        $this->assertTrue(str_contains($participant, 'aria-label="Paid total: BDT 250.50"'));
        $this->assertTrue(str_contains($participant, 'aria-label="Issued tickets: 2"'));
        $this->assertTrue(str_contains($participant, 'aria-label="Checked in: 1"'));
        $this->assertTrue(str_contains($participant, 'aria-label="Submitted reviews: 4"'));
        $this->assertFalse(str_contains($participant, 'Unread updates'));
    }

    public function testParticipantNavigationIncludesRegistrationsAndTicketsOnlyForParticipants(): void
    {
        $participant = $this->renderRoleDashboard('dashboard/participant', 'Participant');
        $organizer = $this->renderRoleDashboard('dashboard/organizer', 'Organizer', [
            'summary' => [],
            'events' => [],
        ]);

        $this->assertTrue(str_contains($participant, 'href="/participant/registrations"'));
        $this->assertTrue(str_contains($participant, '>Registrations</span>'));
        $this->assertTrue(str_contains($participant, 'href="/participant/tickets"'));
        $this->assertTrue(str_contains($participant, '>Tickets</span>'));
        $this->assertFalse(str_contains($organizer, 'href="/participant/registrations"'));
        $this->assertFalse(str_contains($organizer, 'href="/participant/tickets"'));
    }

    public function testPaymentAdministrationNavigationIsVisibleOnlyToSuperAdministrators(): void
    {
        $admin = $this->renderAdminDashboard();
        $participant = $this->renderRoleDashboard('dashboard/participant', 'Participant');
        $organizer = $this->renderRoleDashboard('dashboard/organizer', 'Organizer', ['summary' => [], 'events' => []]);

        $this->assertTrue(str_contains($admin, 'href="/admin/payments"'));
        $this->assertTrue(str_contains($admin, '>Payment review</span>'));
        $this->assertFalse(str_contains($participant, 'href="/admin/payments"'));
        $this->assertFalse(str_contains($organizer, 'href="/admin/payments"'));
    }

    public function testOrganizerControllerLoadsAuthenticatedRepositorySummaryAndRecentEvents(): void
    {
        [$controller, $events] = $this->dashboardController('organizer', 10);
        $events->events[91] = [
            'id' => 91,
            'user_id' => 10,
            'title' => 'Controller Repository Summit',
            'slug' => 'controller-repository-summit',
            'status' => 'draft',
            'start_date' => '2026-09-18 09:00:00',
            'deleted_at' => null,
        ];

        $response = $controller->organizer(Request::create('GET', '/organizer/dashboard'));

        $this->assertSame(200, $response->status());
        $this->assertTrue(str_contains($response->body(), 'aria-label="Total events: 1"'));
        $this->assertTrue(str_contains($response->body(), 'Controller Repository Summit'));
    }

    public function testAdminControllerCountsPendingRepositoryEvents(): void
    {
        [$controller, $events] = $this->dashboardController('super-admin', 99);
        $events->events = [
            91 => ['id' => 91, 'status' => 'pending'],
            92 => ['id' => 92, 'status' => 'published'],
        ];

        $response = $controller->admin(Request::create('GET', '/admin/dashboard'));

        $this->assertSame(200, $response->status());
        $this->assertTrue(str_contains($response->body(), 'aria-label="Pending review: 1"'));
        $this->assertSame(1, $events->countPendingForAdminCalls);
        $this->assertSame(0, $events->forAdminCalls);
    }

    public function testParticipantControllerUsesAggregateSummaryApisForEveryMetricFamily(): void
    {
        [$controller, , $registrations, $payments, $tickets] = $this->dashboardController('participant', 10);
        $registrations->registrations[1] = ['id' => 1, 'user_id' => 10, 'status' => 'confirmed'];
        $payments->payments[1] = ['id' => 1, 'participant_id' => 10, 'status' => 'paid', 'amount' => '75.25'];
        $tickets->tickets[1] = ['id' => 1, 'participant_id' => 10, 'registration_status' => 'confirmed', 'status' => 'used'];
        $tickets->attendance[1] = ['status' => 'present'];

        $response = $controller->participant(Request::create('GET', '/participant/dashboard'));

        $this->assertSame(200, $response->status());
        $this->assertTrue(str_contains($response->body(), 'aria-label="Confirmed registrations: 1"'));
        $this->assertTrue(str_contains($response->body(), 'aria-label="Paid total: BDT 75.25"'));
        $this->assertTrue(str_contains($response->body(), 'aria-label="Checked in: 1"'));
    }

    public function testProfileNavigationStaysActiveForATrailingSlashUrl(): void
    {
        $previousUri = $_SERVER['REQUEST_URI'] ?? null;
        $_SERVER['REQUEST_URI'] = '/profile/';
        $html = $this->renderAdminDashboard();

        if ($previousUri === null) {
            unset($_SERVER['REQUEST_URI']);
        } else {
            $_SERVER['REQUEST_URI'] = $previousUri;
        }

        $this->assertTrue(str_contains(
            $html,
            'class="dashboard-nav-link dashboard-nav-link--active" href="/profile" aria-current="page"',
        ));
    }

    private function renderAdminDashboard(array $overrides = []): string
    {
        $view = new View(base_path('app/Views'));
        $data = array_merge([
            'app' => ['name' => 'OEMS'],
            'csrfToken' => 'test-token',
            'currentUser' => [
                'name' => 'Super Admin',
                'email' => 'admin@oems.local',
                'role_name' => 'Super Admin',
                'role_slug' => 'super-admin',
            ],
            'flash' => [],
            'metrics' => [
                'users' => 0,
                'organizers' => 0,
                'events' => 0,
                'pending_reviews' => 0,
                'registration' => ['active' => 0, 'pending' => 0, 'confirmed' => 0],
                'payment' => ['pending' => 0, 'paid' => 0, 'paid_total' => '0.00'],
                'ticket' => ['issued' => 0, 'checked_in' => 0],
                'reviews' => ['total' => 0, 'pending' => 0, 'published' => 0],
            ],
            'pageTitle' => 'Platform overview',
        ], $overrides);

        return $view->render('dashboard/admin', $data, 'dashboard');
    }

    private function renderRoleDashboard(string $template, string $roleName, array $overrides = []): string
    {
        $view = new View(base_path('app/Views'));

        return $view->render($template, array_merge([
            'app' => ['name' => 'OEMS'],
            'csrfToken' => 'test-token',
            'currentUser' => [
                'name' => $roleName . ' User',
                'email' => strtolower($roleName) . '@oems.local',
                'role_name' => $roleName,
                'role_slug' => strtolower($roleName),
            ],
            'flash' => [],
            'pageTitle' => $roleName . ' dashboard',
        ], $overrides), 'dashboard');
    }

    private function dashboardController(string $role, int $userId): array
    {
        $_SESSION = [];
        $_SERVER['REQUEST_URI'] = $role === 'organizer' ? '/organizer/dashboard' : '/admin/dashboard';
        $session = new Session(false);
        $session->put('auth.user_id', $userId);
        $users = new FakeUserRepository();
        $users->users[$userId] = [
            'id' => $userId,
            'role_id' => $role === 'organizer' ? 2 : 1,
            'name' => $role === 'organizer' ? 'Organizer User' : 'Super Admin',
            'email' => $role . '@oems.local',
            'status' => 'active',
        ];
        $events = new FakeEventRepository();
        $registrations = new FakeRegistrationRepository();
        $payments = new FakePaymentRepository();
        $tickets = new FakeTicketRepository();
        $connection = new PDO('sqlite::memory:');
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, deleted_at TEXT NULL)');
        $connection->exec('CREATE TABLE organizers (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL)');
        $connection->exec('CREATE TABLE events (id INTEGER PRIMARY KEY, organizer_id INTEGER NOT NULL, deleted_at TEXT NULL)');
        $connection->exec('CREATE TABLE reviews (id INTEGER PRIMARY KEY, event_id INTEGER NOT NULL, user_id INTEGER NOT NULL, status TEXT NOT NULL, organizer_reply TEXT NULL)');

        return [
            new DashboardController(
                new View(base_path('app/Views')),
                $session,
                new Security($session),
                new Auth($session, $users),
                new Config(['name' => 'OEMS']),
                new DashboardMetricsRepository($connection),
                $events,
                $registrations,
                $payments,
                $tickets,
            ),
            $events,
            $registrations,
            $payments,
            $tickets,
        ];
    }
}
