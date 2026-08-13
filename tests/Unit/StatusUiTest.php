<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Core\View;
use OEMS\Tests\Support\TestCase;
use RuntimeException;

final class StatusUiTest extends TestCase
{
    public function testStatusComponentsMapTheCompleteTaxonomyInSourceAndCompiledCss(): void
    {
        $groups = [
            'info' => ['active', 'published', 'valid', 'sent', 'read', 'info'],
            'success' => ['approved', 'confirmed', 'paid', 'completed', 'used', 'replied', 'subscribed', 'present', 'success'],
            'warning' => ['pending', 'waitlisted', 'queued', 'processing', 'new', 'partially_refunded', 'warning'],
            'danger' => ['rejected', 'failed', 'suspended', 'revoked', 'cancelled', 'danger'],
            'neutral' => ['draft', 'inactive', 'archived', 'hidden', 'refunded', 'absent', 'none', 'not_checked_in', 'unsubscribed', 'neutral', 'muted'],
        ];
        $tokens = [
            'info' => ['--info', '--info-soft'],
            'success' => ['--success', '--success-soft'],
            'warning' => ['--warning', '--warning-soft'],
            'danger' => ['--error', '--error-soft'],
            'neutral' => ['--ink-muted', '--surface-soft'],
        ];

        foreach ([
            'source stylesheet' => 'resources/css/app.css',
            'compiled stylesheet' => 'public/assets/css/app.css',
        ] as $label => $path) {
            $css = $this->stylesheet($path);

            foreach (['status-chip', 'status-badge'] as $component) {
                foreach ($groups as $tone => $states) {
                    foreach ($states as $state) {
                        $rule = $this->statusRule($css, $component, $state);
                        foreach ($tokens[$tone] as $token) {
                            $this->assertTrue(
                                str_contains($rule, 'var(' . $token . ')'),
                                sprintf('%s %s--%s must use %s in the %s.', $label, $component, $state, $token, $tone),
                            );
                        }
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

        $this->assertSame(1, $this->statusCount($available, 'status-badge', 'info', 'Ready'));
        $this->assertSame(3, $this->statusCount($available, 'status-badge', 'success', 'Passing'));
        $this->assertSame(1, $this->statusCount($restricted, 'status-badge', 'danger', 'Unavailable'));
        $this->assertSame(3, $this->statusCount($restricted, 'status-badge', 'danger', 'Needs attention'));
        $this->assertSame(1, $this->statusCount($available, 'status-badge', 'neutral', 'Inactive'));
        $this->assertSame(1, $this->statusCount($restricted, 'status-badge', 'warning', 'Active'));
        foreach ([$available, $restricted] as $document) {
            $this->assertFalse(str_contains($document, 'text-emerald-'));
            $this->assertFalse(str_contains($document, 'text-red-'));
            $this->assertFalse(preg_match('/\\bdark:[^\\s"\\\']+/', $document) === 1);
        }
        $this->assertFalse(str_contains($available . $restricted, 'status-pill'));
    }

    public function testSemanticTokensMeetAaContrastInBothThemes(): void
    {
        $css = $this->stylesheet('resources/css/app.css');
        $pairs = [
            ['--info', '--info-soft'],
            ['--success', '--success-soft'],
            ['--warning', '--warning-soft'],
            ['--error', '--error-soft'],
            ['--ink-muted', '--surface-soft'],
        ];

        foreach (['light' => ':root', 'dark' => '[data-theme="dark"]'] as $theme => $selector) {
            $tokens = $this->themeTokens($css, $selector);

            foreach ($pairs as [$foreground, $background]) {
                $ratio = $this->contrastRatio($tokens[$foreground], $tokens[$background]);
                $this->assertTrue(
                    $ratio >= 4.5,
                    sprintf('%s theme %s on %s contrast must be at least 4.5:1; received %.2f:1.', $theme, $foreground, $background, $ratio),
                );
            }
        }
    }

    public function testViewsDelegateSemanticColorAndThemeAuthorityToSharedComponents(): void
    {
        $palette = '(?:emerald|green|red|rose|amber|yellow|orange|blue|sky|cyan|teal)';
        $pattern = '/\\b(?:text|bg|border)-' . $palette . '-\\d+\\b|\\bdark:[^\\s"\\\']+/';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('app/Views')));

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $view = file_get_contents($file->getPathname());
            if (!is_string($view)) {
                throw new RuntimeException('Unable to read view ' . $file->getPathname() . '.');
            }

            $this->assertSame(
                0,
                preg_match($pattern, $view),
                'View-level semantic palette or dark-mode utility found in ' . $file->getPathname() . '.',
            );
        }
    }

    public function testRemainingPlainStatusSurfacesRenderSharedStatusComponents(): void
    {
        $organizer = $this->render('admin/organizers/show', ['organizer' => [
            'id' => 1, 'organization_name' => 'Example Organization', 'name' => 'Organizer', 'email' => 'organizer@example.test',
            'approval_status' => 'pending', 'user_status' => 'active', 'email_verified_at' => '2026-08-13 10:00:00', 'role_slug' => 'organizer',
        ]]);
        $user = $this->render('admin/users/show', ['managedUser' => [
            'id' => 2, 'name' => 'Managed User', 'email' => 'user@example.test', 'status' => 'suspended', 'role_slug' => 'participant', 'email_verified_at' => '2026-08-13 10:00:00',
        ]]);
        $contact = $this->render('admin/contact/show', ['message' => [
            'id' => 3, 'name' => 'Contact', 'email' => 'contact@example.test', 'subject' => 'Question', 'status' => 'read', 'created_at' => '2026-08-13 10:00:00', 'message' => 'Hello',
        ]]);
        $payment = $this->render('admin/payments/show', [
            'payment' => ['id' => 4, 'payment_status' => 'paid', 'registration_status' => 'confirmed', 'transaction_reference' => 'PAY-4', 'participant_name' => 'Participant', 'participant_email' => 'participant@example.test', 'event_title' => 'Example Event', 'organizer_name' => 'Organizer', 'currency' => 'BDT', 'amount' => '100.00', 'payment_method_name' => 'Manual', 'payment_channel' => 'manual', 'created_at' => '2026-08-13 10:00:00'],
            'paymentAge' => 'today', 'returnFilters' => [], 'actionError' => null, 'confirmation' => null,
        ]);
        $analytics = $this->render('admin/analytics/index', [
            'filterError' => null, 'range' => [], 'filters' => [], 'charts' => [],
            'summary' => ['lifecycle' => ['draft' => 1, 'pending' => 1, 'approved' => 1, 'rejected' => 1, 'published' => 1, 'completed' => 1, 'cancelled' => 1], 'registrations' => ['pending' => 1, 'confirmed' => 1, 'cancelled' => 1, 'waitlisted' => 1, 'refunded' => 1], 'verified_payments' => [], 'active_users' => 1, 'approved_organizers' => 1, 'pending_event_queue' => 1, 'attendance_count' => 1, 'pending_payment_queue' => 1, 'refund_attention_count' => 1],
        ]);
        $favorites = $this->render('participant/favorites/index', ['favorites' => [[
            'event_id' => 5, 'title' => 'Unavailable Event', 'slug' => 'unavailable-event', 'is_available' => false, 'event_status' => 'cancelled', 'category_name' => 'Music', 'start_display' => 'Tomorrow', 'price_display' => 'BDT 100',
        ]], 'pagination' => ['page' => 1, 'last_page' => 1]]);
        $registration = $this->render('participant/registrations/show', ['registration' => [
            'id' => 6, 'registration_number' => 'REG-6', 'event_title' => 'Registered Event', 'registration_status' => 'confirmed', 'payment_status' => 'paid', 'event_status' => 'published', 'registered_display' => 'Today', 'event_start_display' => 'Tomorrow', 'venue_display' => 'Venue', 'amount_display' => '100.00', 'currency' => 'BDT', 'ticket' => ['id' => 9, 'ticket_number' => 'TICKET-9', 'ticket_status' => 'valid'], 'cancellation_state' => ['allowed' => false, 'reason' => null], 'can_cancel' => false,
        ]]);
        $waitlist = $this->render('participant/waitlist/index', ['entries' => [[
            'id' => 7, 'event_title' => 'Waitlisted Event', 'position' => 1, 'start_display' => 'Tomorrow', 'amount_display' => '100.00', 'currency' => 'BDT',
        ]]]);
        $dashboard = $this->render('dashboard/participant', ['metrics' => [], 'workspace' => [
            'tickets' => [['id' => 8, 'event_title' => 'Ticket Event', 'ticket_number' => 'TICKET-8', 'ticket_status' => 'valid']],
            'upcoming' => [['id' => 6, 'event_title' => 'Registered Event', 'event_start_date' => 'Tomorrow', 'payment_status' => 'paid']],
        ], 'unreadNotifications' => 0]);

        $details = '//dl[contains(concat(" ", normalize-space(@class), " "), " organizer-detail-list ")]/div';
        $this->assertRenderedStatuses($organizer, [
            [$details . '[dt[normalize-space(.) = "Approval"]]', 'pending', 'Pending'],
            [$details . '[dt[normalize-space(.) = "Account status"]]', 'active', 'Active'],
            [$details . '[dt[normalize-space(.) = "Email verification"]]', 'success', 'Verified'],
        ]);
        $this->assertRenderedStatuses($user, [
            [$details . '[dt[normalize-space(.) = "Status"]]', 'suspended', 'Suspended'],
            [$details . '[dt[normalize-space(.) = "Email verification"]]', 'success', 'Verified'],
        ]);
        $this->assertRenderedStatuses($contact, [['//dl/div[dt[normalize-space(.) = "Status"]]', 'read', 'Read']]);
        $this->assertRenderedStatuses($payment, [
            ['//div[contains(concat(" ", normalize-space(@class), " "), " dashboard-page-heading ")]', 'paid', 'Paid'],
            ['//div[contains(concat(" ", normalize-space(@class), " "), " admin-evidence-summary ")]', 'paid', 'Paid'],
            [$details . '[dt[normalize-space(.) = "Registration"]]', 'confirmed', 'Confirmed'],
        ]);
        foreach (['draft', 'pending', 'approved', 'rejected', 'published', 'completed', 'cancelled'] as $state) {
            $this->assertRenderedStatuses($analytics, [[
                '//*[@data-lifecycle-status="' . $state . '"]', $state, ucfirst($state),
            ]]);
        }
        foreach (['pending', 'confirmed', 'cancelled', 'waitlisted', 'refunded'] as $state) {
            $this->assertRenderedStatuses($analytics, [[
                '//*[@data-registration-status="' . $state . '"]', $state, ucfirst($state),
            ]]);
        }
        $this->assertRenderedStatuses($favorites, [
            ['//article[contains(concat(" ", normalize-space(@class), " "), " favorite-history ")]', 'muted', 'Unavailable'],
            ['//dl[contains(concat(" ", normalize-space(@class), " "), " favorite-history__details ")]/div[dt[normalize-space(.) = "Status"]]', 'cancelled', 'Cancelled'],
        ]);
        $this->assertRenderedStatuses($registration, [
            ['//section[@aria-labelledby = "registration-status-heading"]', 'confirmed', 'Confirmed'],
            ['//section[@aria-labelledby = "payment-status-heading"]', 'paid', 'Paid'],
        ]);
        $this->assertRenderedStatuses($waitlist, [['//article[@aria-labelledby = "waitlist-event-7"]', 'waitlisted', 'Waitlisted']]);
        $this->assertRenderedStatuses($dashboard, [
            ['//section[.//h2[normalize-space(.) = "Recent tickets"]]', 'valid', 'Valid'],
            ['//section[.//h2[normalize-space(.) = "Upcoming registrations"]]', 'paid', 'Paid'],
        ]);
    }

    public function testSharedStatusQueriesRequireBaseClassesAndPermitBothFamilies(): void
    {
        $modifierOnly = '<section id="status-surface"><span class="status-chip--read">Read</span></section>';
        $chip = '<section id="status-surface"><span class="status-chip status-chip--read">Read</span></section>';
        $badge = '<section id="status-surface"><span class="status-badge status-badge--read">Read</span></section>';

        $this->assertSame(0, $this->sharedStatusCount($modifierOnly, '//*[@id = "status-surface"]', 'read', 'Read'));
        $this->assertSame(1, $this->sharedStatusCount($chip, '//*[@id = "status-surface"]', 'read', 'Read'));
        $this->assertSame(1, $this->sharedStatusCount($badge, '//*[@id = "status-surface"]', 'read', 'Read'));
    }

    public function testStatusRuleLookupRequiresAnExactSelectorBoundary(): void
    {
        $css = '.status-chip--read-legacy { color: var(--warning); } .status-chip--read { color: var(--info); }';

        $this->assertSame(null, $this->findStatusRule('.status-chip--read-legacy { color: var(--warning); }', 'status-chip', 'read'));
        $this->assertSame(null, $this->findStatusRule('/* .status-chip--read */ .other { color: var(--warning); }', 'status-chip', 'read'));
        $this->assertSame(' color: var(--info); ', $this->findStatusRule($css, 'status-chip', 'read'));
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

    private function assertRenderedStatuses(string $html, array $expected): void
    {
        $document = new \DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        $this->assertTrue($loaded, 'Status-bearing view must render parseable HTML.');
        foreach ($expected as [$location, $state, $label]) {

            $this->assertSame(
                1,
                $this->sharedStatusCount($html, $location, $state, $label),
                sprintf('Expected one shared status component for %s at %s.', $label, $location),
            );
        }
    }

    private function sharedStatusCount(string $html, string $location, string $state, string $label): int
    {
        $document = new \DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        $this->assertTrue($loaded, 'Status-bearing view must render parseable HTML.');
        $xpath = new \DOMXPath($document);
        $matches = $xpath->query($location . '//*[('
            . '(contains(concat(" ", normalize-space(@class), " "), " status-chip ")'
            . ' and contains(concat(" ", normalize-space(@class), " "), " status-chip--' . $state . ' "))'
            . ' or (contains(concat(" ", normalize-space(@class), " "), " status-badge ")'
            . ' and contains(concat(" ", normalize-space(@class), " "), " status-badge--' . $state . ' "))'
            . ') and normalize-space(.) = "' . $label . '"]');

        return $matches === false ? 0 : $matches->length;
    }

    private function statusCount(string $html, string $component, string $state, string $label): int
    {
        $document = new \DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        $this->assertTrue($loaded, 'Status-bearing view must render parseable HTML.');
        $xpath = new \DOMXPath($document);
        $matches = $xpath->query(
            '//*[contains(concat(" ", normalize-space(@class), " "), " ' . $component . ' ")'
            . ' and contains(concat(" ", normalize-space(@class), " "), " ' . $component . '--' . $state . ' ")'
            . ' and normalize-space(.) = "' . $label . '"]',
        );

        return $matches === false ? 0 : $matches->length;
    }

    private function stylesheet(string $path): string
    {
        $css = file_get_contents(base_path($path));

        if (!is_string($css)) {
            throw new RuntimeException('Unable to read stylesheet ' . $path . '.');
        }

        return $css;
    }

    private function themeTokens(string $css, string $selector): array
    {
        $matched = preg_match('/' . preg_quote($selector, '/') . '\\s*\\{([^{}]+)\\}/', $css, $matches);
        $this->assertSame(1, $matched, 'Missing theme token block for ' . $selector . '.');

        preg_match_all('/(--[a-z-]+)\\s*:\\s*(#[0-9a-fA-F]{6})\\s*;/', $matches[1], $tokenMatches, PREG_SET_ORDER);
        $tokens = [];

        foreach ($tokenMatches as $token) {
            $tokens[$token[1]] = $token[2];
        }

        foreach (['--info', '--info-soft', '--success', '--success-soft', '--warning', '--warning-soft', '--error', '--error-soft', '--ink-muted', '--surface-soft'] as $required) {
            $this->assertArrayHasKey($required, $tokens, $selector . ' must define ' . $required . '.');
        }

        return $tokens;
    }

    private function contrastRatio(string $first, string $second): float
    {
        $firstLuminance = $this->relativeLuminance($first);
        $secondLuminance = $this->relativeLuminance($second);

        return (max($firstLuminance, $secondLuminance) + 0.05) / (min($firstLuminance, $secondLuminance) + 0.05);
    }

    private function relativeLuminance(string $hex): float
    {
        $channels = array_map(
            static fn (string $channel): float => hexdec($channel) / 255,
            str_split(ltrim($hex, '#'), 2),
        );
        $linear = array_map(
            static fn (float $channel): float => $channel <= 0.03928
                ? $channel / 12.92
                : (($channel + 0.055) / 1.055) ** 2.4,
            $channels,
        );

        return (0.2126 * $linear[0]) + (0.7152 * $linear[1]) + (0.0722 * $linear[2]);
    }

    private function statusRule(string $css, string $component, string $state): string
    {
        $rule = $this->findStatusRule($css, $component, $state);
        $this->assertNotSame(null, $rule, sprintf('Missing semantic rule for %s--%s.', $component, $state));

        return (string) $rule;
    }

    private function findStatusRule(string $css, string $component, string $state): ?string
    {
        $css = preg_replace('/\/\*.*?\*\//s', '', $css) ?? $css;
        $selector = preg_quote('.' . $component . '--' . $state, '/');
        $matched = preg_match('/[^{}]*' . $selector . '(?=[\\s,:.{])[^{}]*\{([^{}]+)\}/', $css, $matches);

        return $matched === 1 ? (string) $matches[1] : null;
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
