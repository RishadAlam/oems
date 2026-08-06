<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Core\View;
use OEMS\Tests\Support\TestCase;

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
            ],
        ]);

        $this->assertTrue(str_contains($html, '>Users<'));
        $this->assertTrue(str_contains($html, '>12<'));
        $this->assertTrue(str_contains($html, '>Organizers<'));
        $this->assertTrue(str_contains($html, '>3<'));
        $this->assertTrue(str_contains($html, '>Events<'));
        $this->assertTrue(str_contains($html, '>6<'));
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
            ],
            'pageTitle' => 'Platform overview',
        ], $overrides);

        return $view->render('dashboard/admin', $data, 'dashboard');
    }
}
