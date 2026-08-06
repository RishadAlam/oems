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
            'pageTitle' => 'Platform overview',
        ], $overrides);

        return $view->render('dashboard/admin', $data, 'dashboard');
    }
}
