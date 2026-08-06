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

        $this->assertTrue(str_contains(
            $html,
            '<article><span>Users</span><strong>12</strong><small>Registered accounts</small></article>',
        ));
        $this->assertTrue(str_contains(
            $html,
            '<article><span>Organizers</span><strong>3</strong><small>Organizer profiles</small></article>',
        ));
        $this->assertTrue(str_contains(
            $html,
            '<article><span>Events</span><strong>6</strong><small>Event records</small></article>',
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
