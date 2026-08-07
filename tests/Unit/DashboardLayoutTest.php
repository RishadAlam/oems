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
        $this->assertTrue(str_contains($organizer, 'Registration and ticket revenue begin in Week 3'));
        $this->assertFalse(str_contains($organizer, 'Participants: 0'));
        $this->assertFalse(str_contains($organizer, 'Revenue: ৳0'));
    }

    public function testParticipantDashboardKeepsItsAvailableDiscoveryAction(): void
    {
        $participant = $this->renderRoleDashboard('dashboard/participant', 'Participant');

        $this->assertTrue(str_contains($participant, 'href="/events"'));
        $this->assertTrue(str_contains($participant, 'Find an event'));
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
            ],
            'flash' => [],
            'metrics' => [
                'users' => 0,
                'organizers' => 0,
                'events' => 0,
                'pending_reviews' => 0,
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
        $connection = new PDO('sqlite::memory:');
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, deleted_at TEXT NULL)');
        $connection->exec('CREATE TABLE organizers (id INTEGER PRIMARY KEY)');
        $connection->exec('CREATE TABLE events (id INTEGER PRIMARY KEY, deleted_at TEXT NULL)');

        return [
            new DashboardController(
                new View(base_path('app/Views')),
                $session,
                new Security($session),
                new Auth($session, $users),
                new Config(['name' => 'OEMS']),
                new DashboardMetricsRepository($connection),
                $events,
            ),
            $events,
        ];
    }
}
