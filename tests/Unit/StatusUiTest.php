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

    public function testFinalReviewStatusTablesRenderSharedComponentsWithoutChangingTheirCopy(): void
    {
        $organizerAnalytics = $this->render('organizer/analytics/index', [
            'filterError' => null,
            'range' => [],
            'eventId' => null,
            'charts' => [],
            'summary' => [
                'lifecycle' => ['draft' => 1, 'pending' => 1, 'approved' => 1, 'rejected' => 1, 'published' => 1, 'completed' => 1, 'cancelled' => 1],
                'registrations' => ['pending' => 1, 'confirmed' => 1, 'cancelled' => 1, 'waitlisted' => 1, 'refunded' => 1],
                'verified_payments' => [],
            ],
            'rows' => [],
        ]);
        $users = $this->render('admin/users/index', [
            'result' => [
                'items' => [[
                    'id' => 20,
                    'name' => 'Verified User',
                    'email' => 'verified@example.test',
                    'role_name' => 'Participant',
                    'status' => 'active',
                    'email_verified_at' => '2026-08-13 10:00:00',
                ]],
                'pagination' => ['total' => 1, 'page' => 1, 'last_page' => 1, 'per_page' => 10],
            ],
            'filters' => [],
        ]);
        $organizers = $this->render('admin/organizers/index', [
            'result' => [
                'items' => [[
                    'id' => 21,
                    'organization_name' => 'Active Organization',
                    'name' => 'Organizer',
                    'email' => 'organizer@example.test',
                    'user_status' => 'active',
                    'approval_status' => 'pending',
                    'event_count' => 1,
                ]],
                'pagination' => ['total' => 1, 'page' => 1, 'last_page' => 1, 'per_page' => 10],
            ],
            'filters' => [],
        ]);

        foreach (['draft', 'pending', 'approved', 'rejected', 'published', 'completed', 'cancelled'] as $state) {
            $this->assertRenderedStatuses($organizerAnalytics, [[
                '//*[@data-lifecycle-status="' . $state . '"]', $state, ucfirst($state),
            ]]);
        }
        foreach (['pending', 'confirmed', 'cancelled', 'waitlisted', 'refunded'] as $state) {
            $this->assertRenderedStatuses($organizerAnalytics, [[
                '//*[@data-registration-status="' . $state . '"]', $state, ucfirst($state),
            ]]);
        }
        $this->assertRenderedStatuses($users, [[
            '//td[@data-label = "Verification"]', 'success', 'Email verified',
        ]]);
        $this->assertRenderedStatuses($organizers, [[
            '//td[@data-label = "Organization"]', 'active', 'Active',
        ]]);

        $reportCases = [
            'events' => [
                ['event_status' => 'Lifecycle status'],
                ['event_status' => 'published'],
                [['//td[@data-label = "Lifecycle status"]', 'published', 'published']],
            ],
            'registrations' => [
                ['event_status' => 'Event status', 'registration_status' => 'Registration status'],
                ['event_status' => 'completed', 'registration_status' => 'confirmed'],
                [
                    ['//td[@data-label = "Event status"]', 'completed', 'completed'],
                    ['//td[@data-label = "Registration status"]', 'confirmed', 'confirmed'],
                ],
            ],
            'payments' => [
                ['event_status' => 'Event status', 'payment_status' => 'Payment status'],
                ['event_status' => 'cancelled', 'payment_status' => 'paid'],
                [
                    ['//td[@data-label = "Event status"]', 'cancelled', 'cancelled'],
                    ['//td[@data-label = "Payment status"]', 'paid', 'paid'],
                ],
            ],
            'attendance' => [
                ['event_status' => 'Event status', 'attendance_status' => 'Attendance status'],
                ['event_status' => 'published', 'attendance_status' => 'present'],
                [
                    ['//td[@data-label = "Event status"]', 'published', 'published'],
                    ['//td[@data-label = "Attendance status"]', 'present', 'present'],
                ],
            ],
            'organizers' => [
                ['approval_status' => 'Approval status'],
                ['approval_status' => 'rejected'],
                [['//td[@data-label = "Approval status"]', 'rejected', 'rejected']],
            ],
        ];

        foreach ($reportCases as $reportType => [$columns, $row, $expected]) {
            $report = $this->render('admin/reports/index', [
                'reportType' => $reportType,
                'range' => [],
                'filters' => [],
                'filterError' => null,
                'columns' => $columns,
                'rows' => [$row],
            ]);
            $this->assertRenderedStatuses($report, $expected);
        }
    }

    public function testDynamicStatusSurfacesKeepHostileWhitespaceValuesNeutralAndVisible(): void
    {
        $hostile = "unknown\tstatus-chip--danger\nstatus-badge--success <unsafe>";

        foreach ($this->dynamicStatusDocuments($hostile) as $surface => [$html, $expectedCount]) {
            $this->assertFalse(str_contains($html, '<unsafe>'), $surface . ' must not render hostile status markup.');
            $this->assertHostileStatusesAreNeutral($html, $expectedCount, $surface);
        }
    }

    public function testDynamicStatusSurfacesRenderMissingValuesAsNeutralUnknown(): void
    {
        foreach ($this->dynamicStatusDocuments('') as $surface => [$html, $expectedCount]) {
            $this->assertUnknownStatusesAreNeutral($html, $expectedCount, $surface);
        }
    }

    public function testEveryDynamicStatusModifierInPhpViewsUsesTheCentralDomainGuard(): void
    {
        $root = base_path('app/Views');
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        $unguarded = [];
        $dynamicCount = 0;

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $view = file_get_contents($file->getPathname());
            $this->assertTrue(is_string($view), 'Unable to read ' . $file->getPathname() . '.');
            preg_match_all('/status-(?:chip|badge)--<\?=(.*?)\?>/s', (string) $view, $matches);

            foreach ($matches[1] ?? [] as $expression) {
                $dynamicCount++;
                if (!str_contains($expression, 'status_modifier(')) {
                    $relative = ltrim(substr($file->getPathname(), strlen($root)), DIRECTORY_SEPARATOR);
                    $unguarded[] = $relative . ': ' . trim(preg_replace('/\s+/', ' ', $expression) ?? $expression);
                }
            }
        }

        $this->assertTrue($dynamicCount > 0, 'The project-wide discovery must find dynamic status modifiers.');
        $this->assertSame(
            [],
            $unguarded,
            "Every dynamic status modifier must fail closed through status_modifier().\n" . implode("\n", $unguarded),
        );
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

    private function dynamicStatusDocuments(string $status): array
    {
        $event = [
            'id' => 3,
            'title' => 'Event',
            'slug' => 'event',
            'status' => $status,
            'organizer_approval_status' => 'approved',
            'description' => 'Description',
            'start_date' => '2026-08-20 10:00:00',
            'end_date' => '2026-08-20 12:00:00',
            'registration_deadline' => '2026-08-19 10:00:00',
            'capacity' => 100,
            'available_seats' => 80,
            'ticket_price' => '100.00',
            'currency' => 'BDT',
            'deleted_at' => '2026-08-13 10:00:00',
            'registration_count' => 0,
            'restorable' => true,
        ];
        $pagination = ['total' => 1, 'page' => 1, 'last_page' => 1, 'per_page' => 10];

        return [
            'administrator analytics' => [$this->render('admin/analytics/index', [
                'filterError' => null, 'range' => [], 'filters' => [], 'charts' => [],
                'summary' => [
                    'lifecycle' => [], 'registrations' => [], 'verified_payments' => [],
                    'top_events' => [['event_id' => 1, 'event_status' => $status, 'registration_count' => 1]],
                ],
            ]), 1],
            'administrator contact detail' => [$this->render('admin/contact/show', ['message' => [
                'id' => 2, 'name' => 'Contact', 'email' => 'contact@example.test', 'subject' => 'Question',
                'status' => $status, 'created_at' => '2026-08-13 10:00:00', 'message' => 'Hello',
            ]]), 1],
            'administrator event detail' => [$this->render('admin/events/show', [
                'event' => $event,
                'gallery' => [],
            ]), 3],
            'administrator organizer detail' => [$this->render('admin/organizers/show', ['organizer' => [
                'id' => 4, 'organization_name' => 'Organization', 'name' => 'Organizer',
                'email' => 'organizer@example.test', 'approval_status' => $status,
                'user_status' => $status, 'email_verified_at' => null, 'role_slug' => 'organizer',
            ]]), 2],
            'administrator payment detail' => [$this->render('admin/payments/show', [
                'payment' => [
                    'id' => 5, 'payment_status' => $status, 'registration_status' => $status,
                    'transaction_reference' => 'PAY-5', 'participant_name' => 'Participant',
                    'participant_email' => 'participant@example.test', 'event_title' => 'Event',
                    'organizer_name' => 'Organizer', 'currency' => 'BDT', 'amount' => '100.00',
                    'payment_method_name' => 'Manual', 'payment_channel' => 'manual',
                    'created_at' => '2026-08-13 10:00:00',
                ],
                'paymentAge' => 'today', 'returnFilters' => [], 'actionError' => null, 'confirmation' => null,
            ]), 3],
            'administrator user detail' => [$this->render('admin/users/show', ['managedUser' => [
                'id' => 6, 'name' => 'User', 'email' => 'user@example.test', 'status' => $status,
                'role_slug' => 'participant', 'email_verified_at' => null,
            ]]), 1],
            'participant dashboard' => [$this->render('dashboard/participant', [
                'metrics' => [], 'workspace' => [
                    'tickets' => [['id' => 7, 'event_title' => 'Ticket event', 'ticket_number' => 'OEMS-123', 'ticket_status' => $status]],
                    'upcoming' => [['id' => 8, 'event_title' => 'Upcoming event', 'event_start_date' => 'Tomorrow', 'payment_status' => $status]],
                ], 'unreadNotifications' => 0,
            ]), 2],
            'organizer participants' => [$this->render('organizer/participants/index', [
                'event' => ['event_id' => 9, 'event_title' => 'Event'], 'filters' => [], 'total' => 1,
                'page' => 1, 'lastPage' => 1, 'participants' => [[
                    'id' => 10, 'participant_name' => 'Participant', 'participant_email' => 'participant@example.test',
                    'registration_number' => 'REG-10', 'registration_status' => $status,
                    'payment_status' => $status, 'ticket_number' => 'OEMS-10',
                    'ticket_status' => $status, 'attendance_status' => $status, 'scanned_at' => null,
                ]],
            ]), 4],
            'participant favorites' => [$this->render('participant/favorites/index', [
                'favorites' => [[
                    'event_id' => 11, 'title' => 'Saved event', 'is_available' => false,
                    'event_status' => $status, 'start_display' => 'Tomorrow', 'price_display' => 'BDT 100',
                ]], 'pagination' => ['page' => 1, 'last_page' => 1],
            ]), 1],
            'participant registration detail' => [$this->render('participant/registrations/show', ['registration' => [
                'id' => 12, 'registration_number' => 'REG-12', 'event_title' => 'Event',
                'registration_status' => $status, 'payment_status' => $status, 'event_status' => 'published',
                'registered_display' => 'Today', 'event_start_display' => 'Tomorrow', 'amount_display' => '100.00',
                'currency' => 'BDT', 'ticket' => null, 'cancellation_state' => ['allowed' => false, 'reason' => null],
                'can_cancel' => false,
            ]]), 2],
            'organizer dashboard' => [$this->render('dashboard/organizer', [
                'events' => [$event],
            ]), 1],
            'participant reviews' => [$this->render('participant/reviews/index', [
                'eligibleEvents' => [],
                'reviews' => [[
                    'id' => 13, 'event_id' => 3, 'event_title' => 'Event', 'status' => $status,
                    'review' => 'Review', 'rating' => 5,
                ]],
            ]), 1],
            'administrator reviews' => [$this->render('admin/reviews/index', [
                'reviews' => [[
                    'id' => 14, 'event_title' => 'Event', 'participant_name' => 'Participant',
                    'status' => $status, 'review' => 'Review', 'rating' => 5,
                ]],
                'status' => null,
            ]), 1],
            'organizer analytics' => [$this->render('organizer/analytics/index', [
                'filterError' => null, 'range' => [], 'eventId' => null, 'charts' => [],
                'summary' => ['lifecycle' => [], 'registrations' => [], 'verified_payments' => []],
                'rows' => [[
                    'event_id' => 3, 'event_title' => 'Event', 'event_status' => $status,
                    'registration_counts' => [], 'verified_payments' => [],
                ]],
            ]), 1],
            'participant registration list' => [$this->render('participant/registrations/index', [
                'registrations' => [[
                    'id' => 15, 'registration_number' => 'REG-15', 'event_title' => 'Event',
                    'event_start_display' => 'Tomorrow', 'amount_display' => 'BDT 100',
                    'registration_status' => $status,
                ]],
            ]), 1],
            'organizer event detail' => [$this->render('organizer/events/show', [
                'event' => $event,
                'gallery' => [],
            ]), 1],
            'organizer event trash' => [$this->render('organizer/events/trash', ['events' => [$event]]), 1],
            'organizer event list' => [$this->render('organizer/events/index', [
                'events' => [$event], 'statuses' => [], 'status' => null,
            ]), 1],
            'participant certificates' => [$this->render('participant/certificates/index', [
                'certificates' => [[
                    'id' => 16, 'certificate_number' => 'CERT-16', 'event_title' => 'Event',
                    'completion_display' => 'Today', 'issued_display' => 'Today', 'status' => $status,
                ]],
            ]), 1],
            'participant ticket detail' => [$this->render('participant/tickets/show', ['ticket' => [
                'id' => 17, 'ticket_number' => 'TICKET-17', 'ticket_status' => $status,
                'event_title' => 'Event', 'event_slug' => 'event', 'event_start_display' => 'Tomorrow',
                'registration_id' => 15, 'registration_number' => 'REG-15', 'issued_display' => 'Today',
            ]]), 1],
            'participant ticket list' => [$this->render('participant/tickets/index', [
                'tickets' => [[
                    'id' => 17, 'ticket_number' => 'TICKET-17', 'ticket_status' => $status,
                    'event_title' => 'Event', 'event_start_display' => 'Tomorrow',
                ]],
            ]), 1],
            'administrator event trash' => [$this->render('admin/events/trash', ['events' => [$event]]), 1],
            'administrator payment list' => [$this->render('admin/payments/index', [
                'payments' => [[
                    'id' => 18, 'participant_name' => 'Participant', 'event_title' => 'Event',
                    'payment_status' => $status,
                ]],
                'filters' => [], 'perPage' => 10, 'statuses' => [], 'total' => 1,
                'page' => 1, 'lastPage' => 1,
            ]), 1],
            'administrator contact list' => [$this->render('admin/contact/index', [
                'filters' => ['search' => '', 'status' => ''],
                'messages' => [[
                    'id' => 19, 'name' => 'Contact', 'email' => 'contact@example.test',
                    'subject' => 'Question', 'status' => $status, 'created_at' => 'Today',
                ]],
            ]), 1],
            'administrator blog' => [$this->render('admin/blog/index', [
                'filters' => [],
                'posts' => [[
                    'id' => 20, 'title' => 'Post', 'slug' => 'post', 'status' => $status,
                    'updated_at' => '2026-08-13 10:00:00',
                ]],
                'pagination' => ['page' => 1, 'last_page' => 1],
            ]), 1],
            'administrator newsletter' => [$this->render('admin/newsletter/index', [
                'campaigns' => [[
                    'id' => 21, 'subject' => 'Campaign', 'message' => 'Message', 'status' => $status,
                    'queued_count' => 0, 'recipient_count' => 0, 'created_at' => 'Today',
                ]],
            ]), 1],
            'administrator event list' => [$this->render('admin/events/index', [
                'events' => [$event], 'statuses' => [], 'status' => null,
            ]), 1],
            'administrator organizer list' => [$this->render('admin/organizers/index', [
                'result' => [
                    'items' => [[
                        'id' => 22, 'organization_name' => 'Organization', 'name' => 'Organizer',
                        'email' => 'organizer@example.test', 'user_status' => $status,
                        'approval_status' => $status, 'event_count' => 1,
                    ]],
                    'pagination' => $pagination,
                ],
                'filters' => [],
            ]), 2],
            'administrator user list' => [$this->render('admin/users/index', [
                'result' => [
                    'items' => [[
                        'id' => 23, 'name' => 'User', 'email' => 'user@example.test',
                        'role_name' => 'Participant', 'status' => $status, 'email_verified_at' => null,
                    ]],
                    'pagination' => $pagination,
                ],
                'filters' => [],
            ]), 1],
            'event report' => [$this->render('admin/reports/index', [
                'reportType' => 'events', 'range' => [], 'filters' => [], 'filterError' => null,
                'columns' => ['event_status' => 'Lifecycle status'],
                'rows' => [['event_status' => $status]],
            ]), 1],
            'registration report' => [$this->render('admin/reports/index', [
                'reportType' => 'registrations', 'range' => [], 'filters' => [], 'filterError' => null,
                'columns' => ['event_status' => 'Event status', 'registration_status' => 'Registration status'],
                'rows' => [['event_status' => $status, 'registration_status' => $status]],
            ]), 2],
            'payment report' => [$this->render('admin/reports/index', [
                'reportType' => 'payments', 'range' => [], 'filters' => [], 'filterError' => null,
                'columns' => ['event_status' => 'Event status', 'payment_status' => 'Payment status'],
                'rows' => [['event_status' => $status, 'payment_status' => $status]],
            ]), 2],
            'attendance report' => [$this->render('admin/reports/index', [
                'reportType' => 'attendance', 'range' => [], 'filters' => [], 'filterError' => null,
                'columns' => ['event_status' => 'Event status', 'attendance_status' => 'Attendance status'],
                'rows' => [['event_status' => $status, 'attendance_status' => $status]],
            ]), 2],
            'organizer report' => [$this->render('admin/reports/index', [
                'reportType' => 'organizers', 'range' => [], 'filters' => [], 'filterError' => null,
                'columns' => ['approval_status' => 'Approval status'],
                'rows' => [['approval_status' => $status]],
            ]), 1],
        ];
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

    private function assertHostileStatusesAreNeutral(string $html, int $expectedCount, string $surface): void
    {
        $document = new \DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        $this->assertTrue($loaded, $surface . ' must render parseable HTML.');
        $xpath = new \DOMXPath($document);
        $matches = $xpath->query(
            '//*[contains(normalize-space(.), "status-chip--danger status-badge--success <unsafe>")'
            . ' and (contains(concat(" ", normalize-space(@class), " "), " status-chip ")'
            . ' or contains(concat(" ", normalize-space(@class), " "), " status-badge "))]',
        );

        $this->assertSame($expectedCount, $matches === false ? 0 : $matches->length, $surface . ' hostile status count.');
        if ($matches === false) {
            return;
        }

        foreach ($matches as $match) {
            preg_match_all('/\bstatus-(?:chip|badge)--[^\s]+/', (string) $match->attributes?->getNamedItem('class')?->nodeValue, $modifiers);
            $this->assertSame(1, count($modifiers[0] ?? []), $surface . ' must emit exactly one guarded modifier.');
            $this->assertTrue(
                in_array(($modifiers[0] ?? [])[0] ?? '', ['status-chip--neutral', 'status-badge--neutral'], true),
                $surface . ' hostile state must use the neutral modifier.',
            );
        }
    }

    private function assertUnknownStatusesAreNeutral(string $html, int $expectedCount, string $surface): void
    {
        $document = new \DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        $this->assertTrue($loaded, $surface . ' must render parseable HTML.');
        $xpath = new \DOMXPath($document);
        $matches = $xpath->query(
            '//*[normalize-space(.) = "Unknown" and ('
            . 'contains(concat(" ", normalize-space(@class), " "), " status-chip ")'
            . ' or contains(concat(" ", normalize-space(@class), " "), " status-badge "))]',
        );

        $this->assertSame($expectedCount, $matches === false ? 0 : $matches->length, $surface . ' missing status count.');
        if ($matches === false) {
            return;
        }

        foreach ($matches as $match) {
            preg_match_all('/\bstatus-(?:chip|badge)--[^\s]+/', (string) $match->attributes?->getNamedItem('class')?->nodeValue, $modifiers);
            $this->assertSame(1, count($modifiers[0] ?? []), $surface . ' must emit exactly one missing-value modifier.');
            $this->assertTrue(
                in_array(($modifiers[0] ?? [])[0] ?? '', ['status-chip--neutral', 'status-badge--neutral'], true),
                $surface . ' missing state must use the neutral modifier.',
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
