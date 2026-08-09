<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Controllers\DashboardController;
use OEMS\App\Repositories\DashboardMetricsRepository;
use OEMS\App\Services\DashboardLayoutDataProvider;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Request;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakeEventRepository;
use OEMS\Tests\Support\FakeNotificationRepository;
use OEMS\Tests\Support\FakePaymentRepository;
use OEMS\Tests\Support\FakeRegistrationRepository;
use OEMS\Tests\Support\FakeTicketRepository;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\TestCase;
use PDO;

final class DashboardLayoutTest extends TestCase
{
    public function testDashboardLayoutBootstrapsThemeWithoutInlineExecutableJavascript(): void
    {
        $html = $this->renderAdminDashboard();

        $this->assertTrue(str_contains($html, '<script src="/assets/js/theme.js"></script>'));
        $this->assertTrue(strpos($html, '/assets/js/theme.js') < strpos($html, '/assets/css/app.css'));
        preg_match_all('/<script\b(?![^>]*\bsrc=)([^>]*)>/i', $html, $matches);
        $this->assertSame([], $matches[0] ?? []);
    }

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
                'payment' => ['pending' => 3, 'paid' => 5, 'paid_total' => '9007199254740993.24'],
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
        $this->assertTrue(str_contains($html, 'aria-label="Paid total: BDT 9007199254740993.24"'));
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
        $this->assertTrue(str_contains($participant, 'unread updates'));
    }

    public function testParticipantDashboardRendersScopedUpcomingActivityFavoritesReviewActionsAndNotifications(): void
    {
        $participant = $this->renderRoleDashboard('dashboard/participant', 'Participant', [
            'workspace' => [
                'upcoming' => [[
                    'id' => 12,
                    'event_title' => 'Scoped future summit',
                    'event_slug' => 'scoped-future-summit',
                    'event_start_date' => '2026-09-01 10:00:00',
                    'registration_status' => 'confirmed',
                    'payment_status' => 'paid',
                ]],
                'favorite_count' => 3,
                'review_actions' => 2,
                'tickets' => [[
                    'id' => 29,
                    'registration_id' => 12,
                    'ticket_number' => 'TICKET-029',
                    'ticket_status' => 'valid',
                    'issued_at' => '2026-09-01 09:00:00',
                    'event_title' => '<script>alert(1)</script> summit',
                ]],
                'recent_notifications' => [[
                    'id' => 38,
                    'type' => 'ticket_issued',
                    'title' => '<img src=x onerror=alert(1)>',
                    'message' => 'Your ticket is ready',
                    'action_url' => '/participant/tickets/29',
                    'read_at' => null,
                    'created_at' => '2026-09-01 09:05:00',
                ]],
            ],
            'unreadNotifications' => 4,
        ]);

        $this->assertTrue(str_contains($participant, 'Scoped future summit'));
        $this->assertTrue(str_contains($participant, 'href="/participant/registrations/12"'));
        $this->assertTrue(str_contains($participant, '3 saved events'));
        $this->assertTrue(str_contains($participant, '2 reviews ready'));
        $this->assertTrue(str_contains($participant, '4 unread updates'));
        $this->assertTrue(str_contains($participant, 'href="/participant/notifications"'));
        $this->assertTrue(str_contains($participant, 'aria-label="4 unread notifications"'));
        $this->assertTrue(str_contains($participant, 'Recent tickets'));
        $this->assertTrue(str_contains($participant, 'TICKET-029'));
        $this->assertTrue(str_contains($participant, '&lt;script&gt;alert(1)&lt;/script&gt; summit'));
        $this->assertFalse(str_contains($participant, '<script>alert(1)</script> summit'));
        $this->assertTrue(str_contains($participant, 'href="/participant/tickets/29"'));
        $this->assertTrue(str_contains($participant, 'Recent updates'));
        $this->assertTrue(str_contains($participant, '&lt;img src=x onerror=alert(1)&gt;'));
        $this->assertFalse(str_contains($participant, '<img src=x onerror=alert(1)>'));
        $this->assertTrue(str_contains($participant, 'href="/participant/notifications"'));
    }

    public function testParticipantDashboardFallsBackFromAnUnsafeStoredNotificationAction(): void
    {
        $participant = $this->renderRoleDashboard('dashboard/participant', 'Participant', [
            'workspace' => [
                'recent_notifications' => [[
                    'id' => 52,
                    'title' => 'Unsafe stored update',
                    'message' => 'This still has a useful safe destination.',
                    'action_url' => 'javascript:alert(1)',
                ]],
            ],
        ]);

        $this->assertTrue(str_contains($participant, 'Unsafe stored update'));
        $this->assertTrue(str_contains($participant, 'href="/participant/notifications"'));
        $this->assertFalse(str_contains($participant, 'javascript:alert(1)'));
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

    public function testViewLevelUnreadBadgeProviderAppliesToEveryParticipantDashboardRenderOnly(): void
    {
        $notifications = new FakeNotificationRepository();
        $notifications->createForUser(17, [
            'type' => 'ticket_issued',
            'title' => 'Ticket ready',
            'message' => 'Your ticket is ready.',
            'action_url' => '/participant/tickets/7',
            'data' => [],
        ]);
        $provider = new DashboardLayoutDataProvider($notifications);
        $view = new View(
            base_path('app/Views'),
            static fn (array $data, string $layout): array => $provider->forLayout($data, $layout),
        );

        $participant = $view->render('dashboard/participant', [
            'app' => ['name' => 'OEMS'],
            'csrfToken' => 'test-token',
            'currentUser' => ['id' => 17, 'name' => 'Participant User', 'email' => 'participant@oems.local', 'role_name' => 'Participant', 'role_slug' => 'participant'],
            'flash' => [],
            'pageTitle' => 'Participant dashboard',
            'metrics' => [],
            'workspace' => [],
        ], 'dashboard');
        $organizer = $view->render('dashboard/organizer', [
            'app' => ['name' => 'OEMS'],
            'csrfToken' => 'test-token',
            'currentUser' => ['id' => 18, 'name' => 'Organizer User', 'email' => 'organizer@oems.local', 'role_name' => 'Organizer', 'role_slug' => 'organizer'],
            'flash' => [],
            'pageTitle' => 'Organizer dashboard',
            'summary' => [],
            'events' => [],
            'transactionMetrics' => [],
        ], 'dashboard');

        $this->assertTrue(str_contains($participant, 'aria-label="1 unread notifications"'));
        $this->assertFalse(str_contains($organizer, 'aria-label="1 unread notifications"'));
        $this->assertSame([], $provider->forLayout(['currentUser' => ['id' => 17, 'role_slug' => 'participant']], 'public'));
        $this->assertSame([], $provider->forLayout(['currentUser' => ['id' => 18, 'role_slug' => 'organizer']], 'dashboard'));
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

    public function testParticipantControllerRendersOnlyOwnedRecentTicketAndNotificationRecords(): void
    {
        [$controller, , , , , $connection] = $this->dashboardController('participant', 10);
        $connection->exec("INSERT INTO users (id, deleted_at) VALUES (10, NULL), (11, NULL)");
        $connection->exec("INSERT INTO events (id, organizer_id, title, slug, start_date, end_date, status, deleted_at) VALUES
            (71, 1, 'Owned controller summit', 'owned-controller-summit', '2099-09-01 09:00:00', '2099-09-01 11:00:00', 'published', NULL),
            (72, 1, 'Foreign controller summit', 'foreign-controller-summit', '2099-09-02 09:00:00', '2099-09-02 11:00:00', 'published', NULL),
            (73, 1, 'Deleted controller summit', 'deleted-controller-summit', '2099-09-03 09:00:00', '2099-09-03 11:00:00', 'published', '2026-08-01 00:00:00')");
        $connection->exec("INSERT INTO registrations (id, user_id, event_id, status, registration_number) VALUES
            (81, 10, 71, 'confirmed', 'REG-OWNED'), (82, 11, 72, 'confirmed', 'REG-FOREIGN'), (83, 10, 73, 'confirmed', 'REG-DELETED')");
        $connection->exec("INSERT INTO tickets (id, registration_id, ticket_number, status, issued_at) VALUES
            (91, 81, 'TICKET-OWNED', 'valid', '2026-08-01 09:00:00'),
            (92, 82, 'TICKET-FOREIGN', 'valid', '2026-08-02 09:00:00'),
            (93, 83, 'TICKET-DELETED', 'valid', '2026-08-03 09:00:00')");
        $connection->exec("INSERT INTO notifications (id, user_id, type, title, message, action_url, read_at, created_at) VALUES
            (101, 10, 'ticket_issued', 'Owned update', 'Ticket ready', '/participant/tickets/91', NULL, '2026-08-01 10:00:00'),
            (102, 11, 'ticket_issued', 'Foreign update', 'Not yours', '/participant/tickets/92', NULL, '2026-08-02 10:00:00')");

        $response = $controller->participant(Request::create('GET', '/participant/dashboard'));

        $this->assertSame(200, $response->status());
        $this->assertTrue(str_contains($response->body(), 'TICKET-OWNED'));
        $this->assertTrue(str_contains($response->body(), 'Owned update'));
        $this->assertFalse(str_contains($response->body(), 'TICKET-FOREIGN'));
        $this->assertFalse(str_contains($response->body(), 'Foreign update'));
        $this->assertFalse(str_contains($response->body(), 'TICKET-DELETED'));
        $this->assertTrue(str_contains($response->body(), 'href="/participant/tickets/91"'));
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
        $_SERVER['REQUEST_URI'] = match ($role) {
            'organizer' => '/organizer/dashboard',
            'participant' => '/participant/dashboard',
            default => '/admin/dashboard',
        };
        $session = new Session(false);
        $users = new FakeUserRepository();
        $users->users[$userId] = [
            'id' => $userId,
            'role_id' => match ($role) {
                'organizer' => 2,
                'participant' => 3,
                default => 1,
            },
            'name' => $role === 'organizer' ? 'Organizer User' : ($role === 'participant' ? 'Participant User' : 'Super Admin'),
            'email' => $role . '@oems.local',
            'status' => 'active',
        ];
        $this->authenticateSession($session, $users, $userId);
        $events = new FakeEventRepository();
        $registrations = new FakeRegistrationRepository();
        $payments = new FakePaymentRepository();
        $tickets = new FakeTicketRepository();
        $connection = new PDO('sqlite::memory:');
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, deleted_at TEXT NULL)');
        $connection->exec('CREATE TABLE organizers (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL)');
        $connection->exec('CREATE TABLE events (id INTEGER PRIMARY KEY, organizer_id INTEGER NOT NULL, title TEXT NOT NULL DEFAULT \'\', slug TEXT NOT NULL DEFAULT \'\', start_date TEXT NOT NULL DEFAULT \'2099-01-01 00:00:00\', end_date TEXT NOT NULL DEFAULT \'2099-01-01 02:00:00\', status TEXT NOT NULL DEFAULT \'published\', deleted_at TEXT NULL)');
        $connection->exec('CREATE TABLE registrations (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, event_id INTEGER NOT NULL, status TEXT NOT NULL, registration_number TEXT NOT NULL DEFAULT \'\')');
        $connection->exec('CREATE TABLE payments (id INTEGER PRIMARY KEY, registration_id INTEGER NOT NULL, status TEXT NOT NULL)');
        $connection->exec('CREATE TABLE favorites (user_id INTEGER NOT NULL, event_id INTEGER NOT NULL)');
        $connection->exec('CREATE TABLE reviews (id INTEGER PRIMARY KEY, event_id INTEGER NOT NULL, user_id INTEGER NOT NULL, status TEXT NOT NULL, organizer_reply TEXT NULL)');
        $connection->exec('CREATE TABLE tickets (id INTEGER PRIMARY KEY, registration_id INTEGER NOT NULL, ticket_number TEXT NOT NULL, status TEXT NOT NULL, issued_at TEXT NOT NULL)');
        $connection->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, type TEXT NOT NULL, title TEXT NOT NULL, message TEXT NOT NULL, action_url TEXT NULL, read_at TEXT NULL, created_at TEXT NOT NULL)');

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
            $connection,
        ];
    }
}
