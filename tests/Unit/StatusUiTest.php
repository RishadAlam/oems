<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Core\View;
use OEMS\Tests\Support\TestCase;
use RuntimeException;

final class StatusUiTest extends TestCase
{
    public function testStatusComponentsMapEveryKnownStateToItsSemanticTone(): void
    {
        $css = file_get_contents(base_path('resources/css/app.css'));

        if (!is_string($css)) {
            throw new RuntimeException('Unable to read the source stylesheet.');
        }

        $expectedGroups = [
            ['tokens' => ['--info-soft', '--info'], 'states' => ['active', 'published', 'valid', 'sent']],
            ['tokens' => ['--success-soft', '--success'], 'states' => ['approved', 'confirmed', 'paid', 'completed', 'used', 'replied', 'subscribed', 'present']],
            ['tokens' => ['--warning-soft', '--warning'], 'states' => ['pending', 'waitlisted', 'queued', 'processing', 'new', 'read']],
            ['tokens' => ['--error-soft', '--error'], 'states' => ['rejected', 'failed', 'suspended', 'revoked', 'cancelled']],
            ['tokens' => ['--surface-soft', '--ink-muted'], 'states' => ['draft', 'inactive', 'archived', 'hidden', 'refunded', 'partially_refunded', 'absent', 'none', 'not_checked_in', 'unsubscribed']],
        ];

        foreach (['status-chip', 'status-badge'] as $component) {
            foreach ($expectedGroups as $group) {
                foreach ($group['states'] as $state) {
                    $rule = $this->statusRule($css, $component, $state);
                    foreach ($group['tokens'] as $token) {
                        $this->assertTrue(
                            str_contains($rule, 'var(' . $token . ')'),
                            sprintf('%s--%s must use %s.', $component, $state, $token),
                        );
                    }
                }
            }
        }
    }

    public function testStatusComponentsHaveAVisibleNeutralDefault(): void
    {
        $css = file_get_contents(base_path('resources/css/app.css'));

        if (!is_string($css)) {
            throw new RuntimeException('Unable to read the source stylesheet.');
        }

        $matched = preg_match('/\.status-chip\s*,\s*\.status-badge\s*\{([^{}]+)\}/', $css, $matches);
        $this->assertSame(1, $matched, 'Status chips and badges must share one base rule.');
        $this->assertTrue(str_contains($matches[1], 'var(--surface-soft)'), 'Unknown statuses must have a neutral background.');
        $this->assertTrue(str_contains($matches[1], 'var(--ink-muted)'), 'Unknown statuses must have neutral readable text.');
    }

    public function testAccountAndCategoryViewsRenderTheirRealStatusNames(): void
    {
        $users = $this->render('admin/users/index', [
            'result' => [
                'items' => [
                    ['id' => 1, 'name' => 'Active User', 'email' => 'active@example.test', 'status' => 'active', 'role_name' => 'Participant'],
                    ['id' => 2, 'name' => 'Suspended User', 'email' => 'suspended@example.test', 'status' => 'suspended', 'role_name' => 'Organizer'],
                ],
                'pagination' => ['total' => 2, 'page' => 1, 'last_page' => 1, 'per_page' => 10],
            ],
            'filters' => [],
        ]);
        $categories = $this->render('admin/categories/index', [
            'categories' => [
                ['id' => 1, 'name' => 'Active category', 'slug' => 'active', 'sort_order' => 1, 'is_active' => true],
                ['id' => 2, 'name' => 'Inactive category', 'slug' => 'inactive', 'sort_order' => 2, 'is_active' => false],
            ],
        ]);

        $this->assertTrue(str_contains($users, 'status-chip--active'));
        $this->assertTrue(str_contains($users, 'status-chip--suspended'));
        $this->assertFalse(str_contains($users, 'status-chip--approved'));
        $this->assertFalse(str_contains($users, 'status-chip--cancelled'));
        $this->assertTrue(str_contains($categories, 'status-chip--active'));
        $this->assertTrue(str_contains($categories, 'status-chip--inactive'));
        $this->assertFalse(str_contains($categories, 'status-chip--approved'));
        $this->assertFalse(str_contains($categories, 'status-chip--cancelled'));
    }

    public function testCmsViewsRenderPublicationAndAvailabilityStatesWithoutColorProxies(): void
    {
        $html = $this->render('admin/cms/index', [
            'pages' => [
                ['title' => 'About', 'slug' => 'about', 'status' => 'published'],
                ['title' => 'Privacy', 'slug' => 'privacy', 'status' => 'draft'],
            ],
            'faqs' => [
                ['id' => 1, 'question' => 'Active question', 'sort_order' => 1, 'is_active' => true],
                ['id' => 2, 'question' => 'Inactive question', 'sort_order' => 2, 'is_active' => false],
            ],
            'banners' => [
                ['id' => 1, 'title' => 'Active banner', 'is_active' => true],
                ['id' => 2, 'title' => 'Inactive banner', 'is_active' => false],
            ],
        ]);

        $this->assertTrue(str_contains($html, 'status-chip--published'));
        $this->assertTrue(str_contains($html, 'status-chip--draft'));
        $this->assertTrue(str_contains($html, 'status-chip--active'));
        $this->assertTrue(str_contains($html, 'status-chip--inactive'));
        $this->assertFalse(str_contains($html, 'status-chip--approved'));
        $this->assertFalse(str_contains($html, 'status-chip--pending'));
        $this->assertFalse(str_contains($html, 'status-chip--cancelled'));
    }

    public function testProfileTrustStatesUseDistinctSemanticTones(): void
    {
        $profile = [
            'name' => 'Profile User',
            'email' => 'profile@example.test',
            'status' => 'active',
            'email_verified_at' => '2026-08-13 10:00:00',
            'role_name' => 'Participant',
            'role_slug' => 'participant',
            'locale' => 'en',
            'timezone' => 'Asia/Dhaka',
        ];
        $active = $this->render('profile/edit', ['profile' => $profile]);
        $inactive = $this->render('profile/edit', ['profile' => array_merge($profile, [
            'status' => 'inactive',
            'email_verified_at' => null,
        ])]);

        $this->assertTrue(str_contains($active, '<dt>Account</dt><dd class="profile-identity__status--info">'));
        $this->assertTrue(str_contains($active, '<dt>Email</dt><dd class="profile-identity__status--success">'));
        $this->assertTrue(str_contains($inactive, '<dt>Account</dt><dd class="profile-identity__status--neutral">'));
        $this->assertTrue(str_contains($inactive, '<dt>Email</dt><dd class="profile-identity__status--warning">'));
    }

    public function testOperationsStatesUseSharedBadgesInsteadOfUnstyledStatusPills(): void
    {
        $available = $this->render('admin/operations/index', [
            'readiness' => ['status' => 'ok', 'checks' => ['database' => true, 'schema' => true, 'storage' => true]],
            'maintenanceEnabled' => false,
        ]);
        $restricted = $this->render('admin/operations/index', [
            'readiness' => ['status' => 'unavailable', 'checks' => []],
            'maintenanceEnabled' => true,
        ]);

        $this->assertTrue(str_contains($available, 'status-badge status-badge--success">Ready'));
        $this->assertTrue(str_contains($available, 'status-badge status-badge--neutral">Inactive'));
        $this->assertTrue(str_contains($restricted, 'status-badge status-badge--danger">Unavailable'));
        $this->assertTrue(str_contains($restricted, 'status-badge status-badge--warning">Active'));
        $this->assertFalse(str_contains($available . $restricted, 'status-pill'));
    }

    public function testDetailRowsStayNeutralUntilAStatusComponentAddsMeaning(): void
    {
        $css = file_get_contents(base_path('resources/css/app.css'));

        if (!is_string($css)) {
            throw new RuntimeException('Unable to read the source stylesheet.');
        }

        $profileDefault = $this->cssRule($css, '/(?:^|\})\s*\.profile-identity dd\s*\{([^{}]+)\}/', 'profile identity values');
        $profileInfo = $this->cssRule($css, '/\.profile-identity__status--info\s*\{([^{}]+)\}/', 'profile informational state');
        $profileNeutral = $this->cssRule($css, '/\.profile-identity__status--neutral\s*\{([^{}]+)\}/', 'profile neutral state');
        $detailDefault = $this->cssRule($css, '/\.status-list dd\s*,\s*\.readiness-grid dd\s*\{([^{}]+)\}/', 'detail values');

        $this->assertTrue(str_contains($profileDefault, 'var(--ink-muted)'), 'Profile values must default to neutral text.');
        $this->assertFalse(str_contains($profileDefault, 'var(--success)'), 'Profile values must not default to success green.');
        $this->assertTrue(str_contains($profileInfo, 'var(--info)'), 'Active account state must be informational.');
        $this->assertTrue(str_contains($profileNeutral, 'var(--ink-muted)'), 'Inactive account state must be neutral.');
        $this->assertTrue(str_contains($detailDefault, 'var(--ink)'), 'Detail values must use ordinary foreground text.');
        $this->assertFalse(str_contains($detailDefault, 'var(--success)'), 'Detail values must not default to success green.');
    }

    private function statusRule(string $css, string $component, string $state): string
    {
        $selector = preg_quote('.' . $component . '--' . $state, '/');
        $matched = preg_match('/[^{}]*' . $selector . '[^{}]*\{([^{}]+)\}/', $css, $matches);

        $this->assertSame(1, $matched, sprintf('Missing semantic rule for %s--%s.', $component, $state));

        return (string) ($matches[1] ?? '');
    }

    private function cssRule(string $css, string $pattern, string $label): string
    {
        $matched = preg_match($pattern, $css, $matches);
        $this->assertSame(1, $matched, sprintf('Missing CSS rule for %s.', $label));

        return (string) ($matches[1] ?? '');
    }

    private function render(string $template, array $data): string
    {
        $_SERVER['REQUEST_URI'] = '/' . $template;

        return (new View(base_path('app/Views')))->render($template, array_merge([
            'app' => ['name' => 'OEMS'],
            'csrfToken' => 'test-token',
            'currentUser' => [
                'name' => 'Admin User',
                'email' => 'admin@example.test',
                'role_name' => 'Super Admin',
                'role_slug' => 'super-admin',
            ],
            'flash' => [],
            'errors' => [],
            'old' => [],
        ], $data));
    }
}
