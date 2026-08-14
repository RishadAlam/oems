<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Core\View;
use OEMS\Core\Validator;
use OEMS\Tests\Support\TestCase;

final class UiLayoutTest extends TestCase
{
    public function testPublicLayoutUsesTheSharedAccessibleBrand(): void
    {
        $html = $this->renderHome();

        $this->assertTrue(str_contains($html, 'aria-label="OEMS home"'));
        $this->assertTrue(str_contains($html, 'class="brand-mark__logo"'));
        $this->assertTrue(str_contains($html, '<span class="brand-mark__wordmark">OEMS</span>'));
        $this->assertFalse(str_contains($html, 'brand-mark__symbol'));
    }

    public function testPublicNavigationLinksToTheHomePageProcessSection(): void
    {
        $html = $this->renderHome();

        $this->assertSame(2, substr_count($html, 'href="/#how-it-works"'));
        $this->assertTrue(str_contains($html, 'id="how-it-works"'));
    }

    public function testOrganizerMenuKeepsSignupForGuestsAndUsesTheDashboardForAuthenticatedRoles(): void
    {
        $guest = $this->renderHome();

        $this->assertTrue(str_contains(
            $guest,
            'class="nav-link" href="/register?role=organizer">For organizers</a>',
        ));
        $this->assertTrue(str_contains(
            $guest,
            'class="mobile-menu__link" href="/register?role=organizer"><i class="ph ph-microphone-stage" aria-hidden="true"></i><span>For organizers</span></a>',
        ));

        foreach (['super-admin', 'organizer', 'participant'] as $index => $role) {
            $authenticated = $this->renderHome([
                'currentUser' => [
                    'id' => $index + 1,
                    'name' => ucfirst($role) . ' User',
                    'email' => $role . '@example.test',
                    'role_slug' => $role,
                    'role_name' => ucfirst($role),
                ],
            ]);

            $this->assertTrue(str_contains(
                $authenticated,
                'class="nav-link" href="/dashboard">Dashboard</a>',
            ));
            $this->assertTrue(str_contains(
                $authenticated,
                'class="mobile-menu__link" href="/dashboard"><i class="ph ph-squares-four" aria-hidden="true"></i><span>Dashboard</span></a>',
            ));

            $document = new \DOMDocument();
            $previousErrors = libxml_use_internal_errors(true);
            $loaded = $document->loadHTML($authenticated);
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
            $this->assertTrue($loaded);

            $xpath = new \DOMXPath($document);
            $desktopDashboardLinks = $xpath->query(
                '//nav[@aria-label="Primary navigation"]//a[@href="/dashboard" and normalize-space(.)="Dashboard"]',
            );
            $mobileDashboardLinks = $xpath->query(
                '//nav[@aria-label="Mobile navigation"]//a[@href="/dashboard" and normalize-space(.)="Dashboard"]',
            );
            $allHeaderDashboardLinks = $xpath->query(
                '//header[contains(concat(" ", normalize-space(@class), " "), " site-header ")]//a[@href="/dashboard" and normalize-space(.)="Dashboard"]',
            );

            $this->assertSame(1, $desktopDashboardLinks?->length);
            $this->assertSame(1, $mobileDashboardLinks?->length);
            $this->assertSame(2, $allHeaderDashboardLinks?->length);
        }
    }

    public function testPublicLayoutRendersOptionalSeoMetadataAndHexEscapedJsonLd(): void
    {
        $view = new View(base_path('app/Views'));
        $html = $view->render('errors/404', [
            'app' => ['name' => 'OEMS'],
            'currentUser' => null,
            'flash' => [],
            'pageTitle' => 'Safe details',
            'metaDescription' => 'Useful & concise.',
            'canonicalUrl' => 'https://events.example.test/events/safe-details',
            'openGraph' => [
                'type' => 'event',
                'title' => 'Safe details',
                'description' => 'Useful & concise.',
                'url' => 'https://events.example.test/events/safe-details',
            ],
            'jsonLd' => [
                '@context' => 'https://schema.org',
                '@type' => 'Event',
                'name' => '</script><script>alert("x")</script> & friends',
            ],
        ], 'public');

        $this->assertTrue(str_contains($html, '<meta name="description" content="Useful &amp; concise.">'));
        $this->assertTrue(str_contains($html, '<link rel="canonical" href="https://events.example.test/events/safe-details">'));
        $this->assertTrue(str_contains($html, '<meta property="og:type" content="event">'));
        $this->assertTrue(str_contains($html, '\\u003C/script\\u003E\\u003Cscript\\u003E'));
        $this->assertTrue(str_contains($html, '\\u0026 friends'));
        $this->assertFalse(str_contains($html, '</script><script>alert'));
    }

    public function testNewsletterErrorsAreScopedToNewsletterSubmissions(): void
    {
        $view = new View(base_path('app/Views'));
        $data = [
            'app' => ['name' => 'OEMS'],
            'currentUser' => null,
            'flash' => [],
            'pageTitle' => 'Contact',
            'errors' => ['email' => ['Enter a valid email address.']],
        ];

        $contactHtml = $view->render('errors/404', $data + ['old' => ['email' => 'bad']], 'public');
        $newsletterHtml = $view->render('errors/404', $data + ['old' => ['newsletter_email' => 'bad']], 'public');

        $this->assertFalse(str_contains($contactHtml, 'id="newsletter-error"'));
        $this->assertFalse(str_contains($contactHtml, 'newsletter-help newsletter-error'));
        $this->assertTrue(str_contains($newsletterHtml, 'id="newsletter-error"'));
        $this->assertTrue(str_contains($newsletterHtml, 'newsletter-help newsletter-error'));
    }

    public function testNewsletterEmailControlUsesTheGlobalTouchTargetAndFocusContract(): void
    {
        $html = $this->renderHome();
        $css = (string) file_get_contents(base_path('resources/css/app.css'));

        $this->assertTrue(str_contains($html, 'class="newsletter-input"'));
        $this->assertTrue(str_contains($css, '.newsletter-input'));
        $this->assertTrue(str_contains($css, '@apply min-h-12'));
    }

    public function testSharedFormControlsReserveSpaceForIconsAndStyleFilePickers(): void
    {
        $css = (string) file_get_contents(base_path('resources/css/app.css'));

        $this->assertTrue(str_contains($css, '.field-group {'));
        $this->assertTrue(str_contains($css, '@apply grid content-start gap-2;'));
        $this->assertTrue(str_contains($css, '.field-group input:where(:not([type="checkbox"]):not([type="radio"]))'));
        $this->assertTrue(str_contains($css, '.input-with-icon > input:where(:not([type="checkbox"]):not([type="radio"]))'));
        $this->assertTrue(str_contains($css, '.field-group input[type="file"]::file-selector-button'));
    }

    public function testEveryTopLevelFilterToolbarUsesTheSharedDirectChildContract(): void
    {
        $views = [
            'app/Views/admin/users/index.php' => ['/admin/users', 3],
            'app/Views/admin/organizers/index.php' => ['/admin/organizers', 2],
            'app/Views/admin/events/index.php' => ['/admin/events', 1],
            'app/Views/admin/reviews/index.php' => ['/admin/reviews', 1],
            'app/Views/organizer/events/index.php' => ['/organizer/events', 1],
        ];
        $violations = [];

        foreach ($views as $view => [$route, $fieldCount]) {
            $source = file_get_contents(base_path($view));

            if ($source === false) {
                $violations[] = $view . ' could not be read.';
                continue;
            }

            $markup = preg_replace('/<\?(?:php|=).*?\?>/s', '', $source);
            $document = new \DOMDocument();
            $previousErrors = libxml_use_internal_errors(true);
            $loaded = $document->loadHTML($markup ?? '');
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);

            if ($loaded === false) {
                $violations[] = $view . ' must contain parseable toolbar markup.';
                continue;
            }

            $xpath = new \DOMXPath($document);
            $toolbars = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " filter-toolbar ")]');

            if ($toolbars === false || $toolbars->length !== 1) {
                $violations[] = $view . ' must render exactly one .filter-toolbar.';
                continue;
            }

            $toolbar = $toolbars->item(0);
            $summaries = $xpath->query('./p[contains(concat(" ", normalize-space(@class), " "), " result-summary ") and contains(concat(" ", normalize-space(@class), " "), " filter-toolbar__summary ")]', $toolbar);
            $forms = $xpath->query('./form[contains(concat(" ", normalize-space(@class), " "), " filter-toolbar__form ")]', $toolbar);
            $legacyToolbars = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " organizer-toolbar ")]');

            if ($summaries === false || $summaries->length !== 1) {
                $violations[] = $view . ' must provide one direct <p.result-summary.filter-toolbar__summary>.';
            } else {
                $summary = $summaries->item(0);
                $summaryChildren = [
                    '.result-summary__count' => $xpath->query('./*[contains(concat(" ", normalize-space(@class), " "), " result-summary__count ")]', $summary),
                    '.result-summary__copy' => $xpath->query('./*[contains(concat(" ", normalize-space(@class), " "), " result-summary__copy ")]', $summary),
                    '.sr-only' => $xpath->query('./*[contains(concat(" ", normalize-space(@class), " "), " sr-only ")]', $summary),
                ];

                if (
                    !$summary instanceof \DOMElement
                    || $summary->getAttribute('role') !== 'status'
                    || $summary->getAttribute('aria-live') !== 'polite'
                    || $summary->getAttribute('aria-atomic') !== 'true'
                ) {
                    $violations[] = $view . ' must expose its filter summary as one atomic polite status.';
                }

                foreach ($summaryChildren as $class => $children) {
                    if ($children === false || $children->length !== 1) {
                        $violations[] = $view . ' must provide exactly one direct ' . $class . ' in its result summary.';
                    }
                }

                $counts = $summaryChildren['.result-summary__count'];
                $copies = $summaryChildren['.result-summary__copy'];

                if (
                    $counts instanceof \DOMNodeList
                    && $counts->length === 1
                    && $counts->item(0) instanceof \DOMElement
                    && $counts->item(0)->getAttribute('aria-hidden') !== 'true'
                ) {
                    $violations[] = $view . ' must hide the visible result count from assistive technology.';
                }

                if (
                    $copies instanceof \DOMNodeList
                    && $copies->length === 1
                    && $copies->item(0) instanceof \DOMElement
                ) {
                    $copy = $copies->item(0);
                    $contexts = $xpath->query('./*[contains(concat(" ", normalize-space(@class), " "), " result-summary__context ")]', $copy);
                    $subjects = $xpath->query('./*[contains(concat(" ", normalize-space(@class), " "), " result-summary__subject ")]', $copy);

                    if ($copy->getAttribute('aria-hidden') !== 'true') {
                        $violations[] = $view . ' must hide the visible result copy from assistive technology.';
                    }

                    if ($contexts === false || $contexts->length !== 1) {
                        $violations[] = $view . ' must provide one .result-summary__context inside its visible copy.';
                    }

                    if ($subjects === false || $subjects->length !== 1) {
                        $violations[] = $view . ' must provide one .result-summary__subject inside its visible copy.';
                    }
                }
            }

            if ($forms === false || $forms->length !== 1) {
                $violations[] = $view . ' must provide one direct .filter-toolbar__form.';
            } else {
                $form = $forms->item(0);
                $method = strtolower((string) $form?->attributes?->getNamedItem('method')?->nodeValue);
                $action = (string) $form?->attributes?->getNamedItem('action')?->nodeValue;
                $ariaLabel = trim((string) $form?->attributes?->getNamedItem('aria-label')?->nodeValue);
                $formKind = (string) $form?->attributes?->getNamedItem('data-form-kind')?->nodeValue;
                $role = (string) $form?->attributes?->getNamedItem('role')?->nodeValue;
                $fields = $xpath->query('./*[contains(concat(" ", normalize-space(@class), " "), " filter-toolbar__field ")]', $form);
                $actions = $xpath->query('./*[contains(concat(" ", normalize-space(@class), " "), " filter-toolbar__actions ")]', $form);
                $children = $xpath->query('./*', $form);
                $bareControls = $xpath->query('./label | ./input | ./select | ./button', $form);

                if ($method !== 'get' || $action !== $route || $formKind !== 'filter' || $role !== 'search' || preg_match('/(?:filter|search)/i', $ariaLabel) !== 1) {
                    $violations[] = $view . ' must keep its labelled GET search filter form for ' . $route . '.';
                }

                if ($fields === false || $fields->length !== $fieldCount) {
                    $violations[] = sprintf('%s must provide %d direct .filter-toolbar__field units.', $view, $fieldCount);
                }

                if ($actions === false || $actions->length !== 1) {
                    $violations[] = $view . ' must provide one direct .filter-toolbar__actions unit.';
                } else {
                    $actionButtons = $xpath->query('./button[translate(@type, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz") = "submit"]', $actions->item(0));
                    $allActionButtons = $xpath->query('./button', $actions->item(0));

                    if ($actionButtons === false || $allActionButtons === false || $actionButtons->length !== 1 || $allActionButtons->length !== 1) {
                        $violations[] = $view . ' must provide exactly one direct submit button in .filter-toolbar__actions.';
                    }
                }

                if ($children === false || $children->length !== $fieldCount + 1) {
                    $violations[] = $view . ' must contain only its expected field wrappers and one actions wrapper as direct filter-form children.';
                }

                if ($bareControls === false || $bareControls->length !== 0) {
                    $violations[] = $view . ' must not leave label, input, select, or button elements as direct filter-form children.';
                }

                if ($fields !== false) {
                    foreach ($fields as $field) {
                        $labels = $xpath->query('./label', $field);
                        $controls = $xpath->query('./input | ./select', $field);
                        $visibleLabels = [];
                        $visibleControls = [];

                        if ($labels !== false) {
                            foreach ($labels as $label) {
                                if ($label instanceof \DOMElement && $this->isVisibleToolbarElement($label)) {
                                    $visibleLabels[] = $label;
                                }
                            }
                        }

                        if ($controls !== false) {
                            foreach ($controls as $control) {
                                if ($control instanceof \DOMElement && $this->isVisibleToolbarElement($control)) {
                                    $visibleControls[] = $control;
                                }
                            }
                        }

                        if ($labels === false || $controls === false || count($visibleLabels) !== 1 || count($visibleControls) !== 1) {
                            $violations[] = $view . ' must give every .filter-toolbar__field exactly one direct visible label and one direct visible input or select.';
                            continue;
                        }

                        $labelFor = $visibleLabels[0]->getAttribute('for');
                        $controlId = $visibleControls[0]->getAttribute('id');

                        if ($labelFor === '' || $controlId === '' || $labelFor !== $controlId) {
                            $violations[] = $view . ' must match every field label for attribute to its direct control id.';
                        }
                    }
                }
            }

            if ($legacyToolbars === false || $legacyToolbars->length !== 0) {
                $violations[] = $view . ' must not retain the legacy .organizer-toolbar class.';
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    public function testOperationalListsUseTheSharedResponsiveTableContract(): void
    {
        $views = [
            'app/Views/admin/newsletter/index.php' => ['Newsletter campaigns', 'Action'],
            'app/Views/admin/contact/index.php' => ['Contact messages', 'Action'],
            'app/Views/organizer/coupons/index.php' => ['Organizer coupons', 'Actions'],
        ];

        foreach ($views as $view => [$caption, $actionLabel]) {
            $source = (string) file_get_contents(base_path($view));
            $markup = preg_replace('/<\?(?:php|=).*?\?>/s', '', $source) ?? '';
            $document = new \DOMDocument();
            $previousErrors = libxml_use_internal_errors(true);
            $loaded = $document->loadHTML($markup);
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);

            $this->assertTrue($loaded, $view . ' must contain parseable table markup.');
            $xpath = new \DOMXPath($document);
            $tables = $xpath->query(
                '//div[contains(concat(" ", normalize-space(@class), " "), " organizer-table-wrap ")]'
                . '/table[contains(concat(" ", normalize-space(@class), " "), " operations-table ")]'
                . '[contains(concat(" ", normalize-space(@class), " "), " organizer-table ")]',
            );

            $this->assertSame(
                1,
                $tables?->length,
                $view . ' must adopt the shared responsive operations table.',
            );

            $table = $tables?->item(0);
            if (!$table instanceof \DOMElement) {
                continue;
            }

            $captions = $xpath->query(
                './caption[contains(concat(" ", normalize-space(@class), " "), " sr-only ") and normalize-space(.)="' . $caption . '"]',
                $table,
            );
            $actions = $xpath->query(
                './/td[contains(concat(" ", normalize-space(@class), " "), " organizer-table__action ") and @data-label="' . $actionLabel . '"]',
                $table,
            );
            $legacyWrappers = $xpath->query(
                '//*[contains(concat(" ", normalize-space(@class), " "), " table-shell ")]',
            );

            $this->assertSame(1, $captions?->length, $view . ' must expose one descriptive table caption.');
            $this->assertSame(1, $actions?->length, $view . ' must identify its responsive action cell.');
            $this->assertSame(0, $legacyWrappers?->length, $view . ' must not use the unstyled legacy wrapper.');
        }
    }

    public function testEveryFilteredResultCountUsesTheSharedSemanticSummary(): void
    {
        $views = [
            'app/Views/admin/users/index.php' => ['Matching', 'Users'],
            'app/Views/admin/organizers/index.php' => ['Matching', 'Organizers'],
            'app/Views/admin/events/index.php' => ['In queue', 'Events'],
            'app/Views/admin/reviews/index.php' => ['In queue', 'Reviews'],
            'app/Views/organizer/events/index.php' => ['Matching', 'Events'],
            'app/Views/admin/payments/index.php' => ['Matching', 'Payments'],
            'app/Views/organizer/participants/index.php' => ['Matching', 'Registrations'],
        ];
        $panelViews = [
            'app/Views/admin/payments/index.php',
            'app/Views/organizer/participants/index.php',
        ];
        $violations = [];

        foreach ($views as $view => [$expectedContext, $expectedSubject]) {
            $source = file_get_contents(base_path($view));

            if ($source === false) {
                $violations[] = $view . ' could not be read.';
                continue;
            }

            $markup = preg_replace('/<\?(?:php|=).*?\?>/s', '', $source);
            foreach ($this->resultSummaryMarkupViolations(
                $markup ?? '',
                $view,
                $expectedContext,
                $expectedSubject,
            ) as $violation) {
                $violations[] = $violation;
            }
            $document = new \DOMDocument();
            $previousErrors = libxml_use_internal_errors(true);
            $loaded = $document->loadHTML($markup ?? '');
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);

            if ($loaded === false) {
                $violations[] = $view . ' must contain parseable result-summary markup.';
                continue;
            }

            $xpath = new \DOMXPath($document);
            $summaries = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " result-summary ")]');

            if ($summaries === false || $summaries->length !== 1 || !$summaries->item(0) instanceof \DOMElement) {
                continue;
            }

            if (in_array($view, $panelViews, true)) {
                $headings = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " dashboard-panel__heading--with-summary ")]');

                if ($headings === false || $headings->length !== 1) {
                    $violations[] = $view . ' must adapt its panel heading with .dashboard-panel__heading--with-summary.';
                } else {
                    $heading = $headings->item(0);
                    $headingMains = $xpath->query('./div[contains(concat(" ", normalize-space(@class), " "), " dashboard-panel__heading-main ")]', $heading);
                    $headingSummary = $xpath->query('./p[contains(concat(" ", normalize-space(@class), " "), " result-summary ") and contains(concat(" ", normalize-space(@class), " "), " shrink-0 ") and @role = "status" and @aria-live = "polite" and @aria-atomic = "true"]', $heading);
                    $headingChildren = $xpath->query('./*', $heading);

                    if ($headingMains === false || $headingMains->length !== 1) {
                        $violations[] = $view . ' must wrap its panel heading content in one direct .dashboard-panel__heading-main.';
                    }

                    if ($headingSummary === false || $headingSummary->length !== 1) {
                        $violations[] = $view . ' must place its non-shrinking atomic result summary alongside .dashboard-panel__heading-main.';
                    }

                    if ($headingChildren === false || $headingChildren->length !== 2) {
                        $violations[] = $view . ' must keep the panel heading to its heading main and result summary children.';
                    }
                }
            } else {
                $toolbars = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " filter-toolbar ")]');
                $toolbarSummary = $toolbars === false || $toolbars->length !== 1
                    ? false
                    : $xpath->query('./p[contains(concat(" ", normalize-space(@class), " "), " result-summary ")]', $toolbars->item(0));

                if ($toolbarSummary === false || $toolbarSummary->length !== 1) {
                    $violations[] = $view . ' must keep its result summary as a direct .filter-toolbar child.';
                }
            }
        }

        $allViews = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('app/Views'), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($allViews as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $view = substr($file->getPathname(), strlen(base_path()) + 1);
            $source = file_get_contents($file->getPathname());

            if ($source !== false && $this->htmlSourceHasClassToken($source, 'result-summary') && !array_key_exists($view, $views)) {
                $violations[] = $view . ' must not use .result-summary outside the seven permitted filtered-result surfaces.';
            }
        }

        $this->assertTrue($this->htmlSourceHasClassToken('<p class="result-summary filter-toolbar__summary">', 'result-summary'));
        $this->assertTrue($this->htmlSourceHasClassToken("<p class='filter-toolbar__summary result-summary'>", 'result-summary'));
        $this->assertFalse($this->htmlSourceHasClassToken('<link href="/assets/css/app.css?v=20260813-global-status-v1">', 'result-summary'));

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    public function testResultSummaryMarkupContractRejectsMalformedMutations(): void
    {
        $valid = <<<'HTML'
            <p class="result-summary" role="status" aria-live="polite" aria-atomic="true">
                <strong class="result-summary__count" aria-hidden="true">1</strong>
                <span class="result-summary__copy" aria-hidden="true">
                    <span class="result-summary__context">Matching</span>
                    <span class="result-summary__subject">Users</span>
                </span>
                <span class="sr-only">1 matching user</span>
            </p>
            HTML;

        $fixtures = [
            'duplicate malformed summary' => $valid . '<p class="result-summary">Unannounced duplicate</p>',
            'hidden screen-reader phrase' => str_replace('class="sr-only"', 'class="sr-only" aria-hidden="true"', $valid),
            'empty screen-reader phrase' => str_replace('>1 matching user</span>', '> </span>', $valid),
            'nested screen-reader phrase' => str_replace('>1 matching user</span>', '><strong>1 matching user</strong></span>', $valid),
            'wrong visible label' => str_replace('>Users</span>', '>Accounts</span>', $valid),
        ];

        foreach ($fixtures as $label => $markup) {
            $this->assertNotSame(
                [],
                $this->resultSummaryMarkupViolations($markup, $label, 'Matching', 'Users'),
                $label . ' must fail the shared result-summary contract.',
            );
        }
    }

    public function testResultSummaryUsesOneSharedResponsiveVisualContract(): void
    {
        $sourceCss = (string) file_get_contents(base_path('resources/css/app.css'));
        $compiledCss = (string) file_get_contents(base_path('public/assets/css/app.css'));
        $violations = [];
        $sourceRules = [
            '.result-summary' => ['flex', 'min-h-12', 'w-full', 'min-w-0', 'items-center', 'gap-3', 'sm:w-auto'],
            '.result-summary__count' => ['grid', 'min-h-11', 'min-w-11', 'shrink-0', 'place-items-center', 'rounded-[12px]', 'bg-[var(--accent-soft)]', 'px-2.5', 'text-lg', 'font-bold', 'tabular-nums', 'text-[var(--accent)]'],
            '.result-summary__copy' => ['grid', 'min-w-0', 'gap-0.5'],
            '.result-summary__context' => ['text-xs', 'font-bold', 'text-[var(--ink-muted)]'],
            '.result-summary__subject' => ['text-sm', 'font-semibold', 'leading-5', 'text-[var(--ink)]'],
            '.dashboard-panel__heading--with-summary' => ['flex-col', 'gap-4', 'sm:flex-row', 'sm:items-center', 'sm:justify-between'],
            '.dashboard-panel__heading-main' => ['flex', 'min-w-0', 'items-start', 'gap-3'],
        ];

        foreach ($sourceRules as $selector => $utilities) {
            if (!$this->cssRuleApplyContainsUtilities($sourceCss, $selector, $utilities)) {
                $violations[] = 'source CSS must keep ' . $selector . ' scoped to: ' . implode(', ', $utilities) . '.';
            }

            $rules = $this->cssExactSelectorRuleBodies($sourceCss, $selector);
            $actualUtilities = $rules === [] ? [] : $this->cssRuleBodyAppliedUtilities($rules[0]);
            $expectedUtilities = $utilities;
            sort($actualUtilities);
            sort($expectedUtilities);

            if (count($rules) !== 1 || $actualUtilities !== $expectedUtilities) {
                $violations[] = 'source CSS must define exactly one ' . $selector . ' rule with no decorative or non-OEMS utilities.';
            }

            foreach ($rules as $rule) {
                $rawDeclarations = preg_replace('/@apply\s+[^;]+;/', '', $rule) ?? $rule;
                $rawDeclarations = preg_replace('/\/\*.*?\*\//s', '', $rawDeclarations) ?? $rawDeclarations;

                if (trim($rawDeclarations) !== '') {
                    $violations[] = 'source CSS must not add raw declarations or non-approved color tokens to ' . $selector . '.';
                }
            }
        }

        foreach ([
            '.result-summary' => ['display:flex', ['min-height:calc(var(--spacing) * 12)', 'min-height:3rem'], 'width:100%', 'min-width:0', 'align-items:center'],
            '.result-summary__count' => ['display:grid', ['min-height:calc(var(--spacing) * 11)', 'min-height:2.75rem'], ['min-width:calc(var(--spacing) * 11)', 'min-width:2.75rem'], 'border-radius:12px', 'background-color:var(--accent-soft)', ['font-size:var(--text-lg)', 'font-size:1.125rem'], 'font-weight:var(--font-weight-bold)', '--tw-numeric-spacing:tabular-nums', 'font-variant-numeric:var(--tw-ordinal,) var(--tw-slashed-zero,) var(--tw-numeric-figure,) var(--tw-numeric-spacing,) var(--tw-numeric-fraction,)', 'color:var(--accent)'],
            '.result-summary__copy' => ['display:grid', 'min-width:0'],
            '.dashboard-panel__heading--with-summary' => ['flex-direction:column'],
            '.dashboard-panel__heading-main' => ['display:flex', 'min-width:0', 'align-items:flex-start'],
        ] as $selector => $declarations) {
            if (!$this->cssRuleContainsTokens($compiledCss, $selector, $declarations)) {
                $violations[] = 'compiled CSS must keep ' . $selector . ' scoped to: ' . $this->describeCssTokens($declarations) . '.';
            }
        }

        foreach ([
            '.result-summary' => ['width:auto'],
            '.filter-toolbar__summary' => ['flex:none'],
            '.dashboard-panel__heading--with-summary' => ['flex-direction:row', 'align-items:center', 'justify-content:space-between'],
        ] as $selector => $declarations) {
            if (!$this->cssMediaRuleContainsTokens($compiledCss, '40rem', $selector, $declarations)) {
                $violations[] = 'compiled CSS must keep ' . $selector . ' responsive at 40rem: ' . implode(', ', $declarations) . '.';
            }
        }

        if ($this->cssHasSelector($sourceCss, '.filter-toolbar__summary strong') || $this->cssHasSelector($compiledCss, '.filter-toolbar__summary strong')) {
            $violations[] = 'CSS must not retain the legacy .filter-toolbar__summary strong selector.';
        }

        $approvedCompiledColorDeclarations = [
            '.result-summary' => [],
            '.result-summary__count' => ['background-color:var(--accent-soft)', 'color:var(--accent)'],
            '.result-summary__copy' => [],
            '.result-summary__context' => ['color:var(--ink-muted)'],
            '.result-summary__subject' => ['color:var(--ink)'],
            '.dashboard-panel__heading--with-summary' => [],
            '.dashboard-panel__heading-main' => [],
        ];
        $approvedCompiledCustomProperties = [
            '.result-summary' => [],
            '.result-summary__count' => ['--tw-font-weight:var(--font-weight-bold)', '--tw-numeric-spacing:tabular-nums'],
            '.result-summary__copy' => [],
            '.result-summary__context' => ['--tw-font-weight:var(--font-weight-bold)'],
            '.result-summary__subject' => ['--tw-font-weight:var(--font-weight-semibold)', '--tw-leading:calc(var(--spacing) * 5)'],
            '.dashboard-panel__heading--with-summary' => [],
            '.dashboard-panel__heading-main' => [],
        ];

        foreach (['source' => $sourceCss, 'compiled' => $compiledCss] as $artifact => $css) {
            foreach (array_keys($sourceRules) as $selector) {
                foreach ($this->cssExactSelectorRuleBodies($css, $selector) as $rule) {
                    if (
                        preg_match('/(?:^|;)(?:box-shadow|transition(?:-[a-z-]+)?|animation(?:-[a-z-]+)?|position)\s*:/', $rule) === 1
                        || preg_match('/#[0-9a-f]{3,8}\b/i', $rule) === 1
                    ) {
                        $violations[] = $artifact . ' CSS must not add shadows, motion, positioning, or non-token literal colors to ' . $selector . '.';
                    }

                    if (
                        $artifact === 'compiled'
                        && preg_match('/\b(?:rgb|hsl|hwb|lab|lch|oklab|oklch|color|color-mix)\(/i', $rule) === 1
                    ) {
                        $violations[] = 'compiled CSS must not add functional, named, or foreign color forms to ' . $selector . '.';
                    }

                    if (
                        $artifact === 'compiled'
                        && !$this->cssRuleHasOnlyApprovedColorAndCustomDeclarations(
                            $rule,
                            $approvedCompiledColorDeclarations[$selector],
                            $approvedCompiledCustomProperties[$selector],
                        )
                    ) {
                        $violations[] = 'compiled CSS must use only approved OEMS color declarations and generated Tailwind custom properties for ' . $selector . '.';
                    }

                    if (
                        $selector === '.result-summary'
                        && (str_contains($rule, 'position:absolute') || preg_match('/(?:^|;)width:(?:\d+(?:\.\d+)?(?:px|rem)|calc\([^)]*\))/', $rule) === 1)
                    ) {
                        $violations[] = $artifact . ' CSS must not absolutely position or give .result-summary a literal fixed width.';
                    }

                    $radii = [];
                    preg_match_all('/(?:^|;)border-radius:([^;]+)/', $rule, $matches);

                    foreach ($matches[1] ?? [] as $radius) {
                        $radii[] = trim($radius);
                    }

                    if (
                        ($selector === '.result-summary__count' && $radii !== [] && $radii !== ['12px'])
                        || ($selector !== '.result-summary__count' && $radii !== [])
                    ) {
                        $violations[] = $artifact . ' CSS must not add non-OEMS radii to ' . $selector . '.';
                    }
                }
            }
        }

        foreach ([
            ['caret-color:red', [], []],
            ['text-decoration-color:var(--foreign)', [], []],
            ['--foreign:var(--accent)', [], []],
        ] as [$fixtureRule, $approvedColors, $approvedCustomProperties]) {
            $this->assertFalse(
                $this->cssRuleHasOnlyApprovedColorAndCustomDeclarations(
                    $fixtureRule,
                    $approvedColors,
                    $approvedCustomProperties,
                ),
                'The compiled result-summary contract must reject ' . $fixtureRule . '.',
            );
        }

        $this->assertTrue(
            $this->cssRuleHasOnlyApprovedColorAndCustomDeclarations(
                'background-color:var(--accent-soft);color:var(--accent);--tw-font-weight:var(--font-weight-bold);--tw-numeric-spacing:tabular-nums',
                $approvedCompiledColorDeclarations['.result-summary__count'],
                $approvedCompiledCustomProperties['.result-summary__count'],
            ),
            'The compiled result-summary contract must allow its approved count tile declarations.',
        );

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    public function testCompiledFilterToolbarUsesOneResponsiveAlignmentContract(): void
    {
        $sourceCss = (string) file_get_contents(base_path('resources/css/app.css'));
        $compiledCss = (string) file_get_contents(base_path('public/assets/css/app.css'));
        $violations = [];

        $sourceRules = [
            '.filter-toolbar__form' => ['grid', 'w-full', 'grid-cols-1', 'sm:grid-cols-2', 'xl:w-max', 'xl:flex', 'xl:flex-none', 'xl:flex-nowrap', 'xl:items-end'],
            '.filter-toolbar__field' => ['grid', 'content-start'],
            '.filter-toolbar__actions' => ['flex', 'w-full'],
        ];

        $sourceToolbarRules = $this->cssExactSelectorRuleBodies($sourceCss, '.filter-toolbar');
        $requiredSourceToolbarUtilities = ['flex', 'w-full', 'flex-col', 'sm:flex-row', 'sm:flex-wrap'];
        $allowedSourceToolbarFlowUtilities = ['flex-col', 'sm:flex-row', 'sm:flex-wrap'];

        if (count($sourceToolbarRules) !== 1) {
            $violations[] = 'source CSS must define exactly one .filter-toolbar rule.';
        } elseif (!$this->cssRuleBodyHasExactFlexDirectionAndWrapUtilities(
            $sourceToolbarRules[0],
            $requiredSourceToolbarUtilities,
            $allowedSourceToolbarFlowUtilities,
        )) {
            $violations[] = 'source CSS must require flex, w-full, flex-col, sm:flex-row, and sm:flex-wrap while rejecting every other direction or wrap utility.';
        }

        foreach ($sourceRules as $selector => $tokens) {
            if (!$this->cssRuleApplyContainsUtilities($sourceCss, $selector, $tokens)) {
                $violations[] = 'source CSS must keep ' . $selector . ' scoped to: ' . implode(', ', $tokens) . '.';
            }
        }

        foreach ([
            '.filter-toolbar__field input' => ['min-h-12'],
            '.filter-toolbar__field select' => ['min-h-12'],
            '.filter-toolbar__actions .button' => ['min-h-12'],
        ] as $selector => $tokens) {
            if (!$this->cssRuleApplyContainsUtilities($sourceCss, $selector, $tokens)) {
                $violations[] = 'source CSS must keep 48-pixel controls scoped to ' . $selector . '.';
            }
        }

        if ($this->cssHasSelector($sourceCss, '.organizer-toolbar')) {
            $violations[] = 'source CSS must not retain the legacy .organizer-toolbar selector.';
        }

        $compiledResponsiveToolbarRules = [];

        foreach ($this->cssMediaRuleBodies($compiledCss, '40rem') as $mediaCss) {
            $compiledResponsiveToolbarRules = [
                ...$compiledResponsiveToolbarRules,
                ...$this->cssExactSelectorRuleBodies($mediaCss, '.filter-toolbar'),
            ];
        }

        $compiledBaseToolbarRules = $this->cssExactSelectorRuleBodies($compiledCss, '.filter-toolbar');

        foreach ($compiledResponsiveToolbarRules as $responsiveToolbarRule) {
            $responsiveRuleIndex = array_search($responsiveToolbarRule, $compiledBaseToolbarRules, true);

            if ($responsiveRuleIndex !== false) {
                unset($compiledBaseToolbarRules[$responsiveRuleIndex]);
            }
        }

        $compiledBaseToolbarRules = array_values($compiledBaseToolbarRules);
        $flowProperties = ['flex-flow', 'flex-direction', 'flex-wrap'];

        if (count($compiledBaseToolbarRules) !== 1) {
            $violations[] = 'compiled CSS must define exactly one base .filter-toolbar rule.';
        } elseif (!$this->cssRuleBodyHasExactDeclarationsAmongProperties(
            $compiledBaseToolbarRules[0],
            $flowProperties,
            [['flex-direction', 'column']],
        )) {
            $violations[] = 'compiled CSS base .filter-toolbar rule must contain only flex-direction:column among flow declarations.';
        }

        $compiledBaseRules = [
            '.filter-toolbar' => ['width:100%'],
            '.filter-toolbar__form' => ['width:100%', 'display:grid', 'grid-template-columns:repeat(1,minmax(0,1fr))'],
            '.filter-toolbar__field' => ['display:grid'],
            '.filter-toolbar__actions' => ['width:100%'],
            '.filter-toolbar__field input' => [['min-height:calc(var(--spacing) * 12)', 'min-height:3rem']],
            '.filter-toolbar__field select' => [['min-height:calc(var(--spacing) * 12)', 'min-height:3rem']],
            '.filter-toolbar__actions .button' => [['min-height:calc(var(--spacing) * 12)', 'min-height:3rem']],
        ];

        foreach ($compiledBaseRules as $selector => $declarations) {
            if (!$this->cssRuleContainsTokens($compiledCss, $selector, $declarations)) {
                $violations[] = 'compiled CSS must keep ' . $selector . ' scoped to: ' . $this->describeCssTokens($declarations) . '.';
            }
        }

        if (count($compiledResponsiveToolbarRules) !== 1) {
            $violations[] = 'compiled CSS must define exactly one 40rem .filter-toolbar rule.';
        } elseif (!$this->cssRuleBodyHasExactDeclarationsAmongProperties(
            $compiledResponsiveToolbarRules[0],
            $flowProperties,
            [['flex-flow', 'wrap']],
        )) {
            $violations[] = 'compiled CSS 40rem .filter-toolbar rule must contain only flex-flow:wrap among flow declarations.';
        }

        foreach ([
            '.filter-toolbar' => ['align-items:flex-end'],
            '.filter-toolbar__form' => ['grid-template-columns:repeat(2,minmax(0,1fr))'],
        ] as $selector => $declarations) {
            if (!$this->cssMediaRuleContainsTokens($compiledCss, '40rem', $selector, $declarations)) {
                $violations[] = 'compiled CSS must keep ' . $selector . ' scoped to its 40rem responsive rule: ' . implode(', ', $declarations) . '.';
            }
        }

        if (!$this->cssMediaRuleContainsTokens($compiledCss, '80rem', '.filter-toolbar__form', ['display:flex', 'flex-wrap:nowrap', 'align-items:flex-end', 'width:max-content', 'flex:none'])) {
            $violations[] = 'compiled CSS must keep the atomic lower-edge alignment scoped to the 80rem .filter-toolbar__form rule.';
        }

        if ($this->cssHasSelector($compiledCss, '.organizer-toolbar')) {
            $violations[] = 'compiled CSS must not retain the legacy .organizer-toolbar selector.';
        }

        $this->assertTrue(
            $this->cssMediaRuleContainsTokens(
                '@media ( min-width : 40rem ) { .filter-toolbar-fixture { flex-direction:row; flex-wrap:wrap; } }',
                '40rem',
                '.filter-toolbar-fixture',
                ['flex-direction:row', 'flex-wrap:wrap'],
            ),
            'The media-rule parser must tolerate whitespace around the min-width query.',
        );

        $unexpectedUtilities = [
            'flex-' . 'row!',
            'sm:' . 'flex-' . 'col!',
            'md:' . 'flex-row-' . 'reverse',
            'flex-col-' . 'reverse',
            'flex-' . 'nowrap',
            'lg:' . 'flex-wrap-' . 'reverse',
        ];

        foreach ($unexpectedUtilities as $unexpectedUtility) {
            $this->assertFalse(
                $this->cssRuleBodyHasExactFlexDirectionAndWrapUtilities(
                    '@apply flex w-full flex-col sm:flex-row sm:flex-wrap ' . $unexpectedUtility . ';',
                    $requiredSourceToolbarUtilities,
                    $allowedSourceToolbarFlowUtilities,
                ),
                'The source toolbar contract must reject ' . $unexpectedUtility . '.',
            );
        }

        foreach ([
            ['flex-direction:column!important;', [['flex-direction', 'column']]],
            ['flex-direction:column;flex-wrap:nowrap;', [['flex-direction', 'column']]],
            ['flex-flow:wrap!important;', [['flex-flow', 'wrap']]],
            ['flex-flow:wrap;flex-direction:row;', [['flex-flow', 'wrap']]],
        ] as [$fixtureRule, $expectedDeclarations]) {
            $this->assertFalse(
                $this->cssRuleBodyHasExactDeclarationsAmongProperties(
                    $fixtureRule,
                    $flowProperties,
                    $expectedDeclarations,
                ),
                'The compiled toolbar contract must reject important or extra flow declarations.',
            );
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    public function testInternalDashboardViewsUseOnePageHeadingStructure(): void
    {
        $viewPaths = ['app/Views/auth/change-password.php'];

        foreach (['admin', 'dashboard', 'organizer', 'participant', 'profile'] as $directory) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    base_path('app/Views/' . $directory),
                    \FilesystemIterator::SKIP_DOTS,
                ),
            );

            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                    $viewPaths[] = 'app/Views/' . $directory . '/' . substr($file->getPathname(), strlen(base_path('app/Views/' . $directory)) + 1);
                }
            }
        }

        $violations = [];

        foreach (array_unique($viewPaths) as $view) {
            $source = file_get_contents(base_path($view));

            if ($source === false) {
                $violations[] = $view . ' could not be read.';
                continue;
            }

            $markup = preg_replace('/<\?(?:php|=).*?\?>/s', '', $source);
            $violations = [
                ...$violations,
                ...$this->dashboardPageHeadingMarkupViolations($markup ?? '', $view),
            ];
        }

        $applicationViews = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('app/Views'), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($applicationViews as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $view = substr($file->getPathname(), strlen(base_path()) + 1);
            $source = file_get_contents($file->getPathname());

            if ($source === false) {
                $violations[] = $view . ' could not be read.';
            } elseif (str_contains($source, 'dashboard-page-header')) {
                $violations[] = $view . ' must not retain the obsolete .dashboard-page-header token.';
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    public function testDashboardPageHeadingStructuralGuardRejectsASecondDocumentHeading(): void
    {
        $markup = <<<'HTML'
            <header class="dashboard-page-heading">
                <div><h1>Canonical title</h1></div>
            </header>
            <section><h1>Unexpected second title</h1></section>
            HTML;

        $this->assertSame(
            ['fixture must render exactly one document H1; found 2.'],
            $this->dashboardPageHeadingMarkupViolations($markup, 'fixture'),
        );
    }

    public function testDashboardPageHeadingPreservesTheResponsiveTypeAndLayoutContract(): void
    {
        $sourceCss = (string) file_get_contents(base_path('resources/css/app.css'));
        $compiledCss = (string) file_get_contents(base_path('public/assets/css/app.css'));
        $violations = [];

        foreach ([
            '.dashboard-page-heading' => ['flex', 'flex-col', 'sm:flex-row', 'sm:items-end', 'sm:justify-between'],
            '.dashboard-page-heading > :first-child' => ['min-w-0'],
            '.dashboard-page-heading > :not(:first-child)' => ['self-start', 'sm:self-auto'],
            '.dashboard-page-heading h1' => ['text-3xl', 'sm:text-4xl', '[overflow-wrap:anywhere]'],
            '.dashboard-page-heading p:not(.dashboard-kicker)' => ['text-sm', 'leading-6', 'text-[var(--ink-muted)]'],
        ] as $selector => $utilities) {
            if (!$this->cssRuleApplyContainsUtilities($sourceCss, $selector, $utilities)) {
                $violations[] = 'source CSS must keep ' . $selector . ' scoped to: ' . implode(', ', $utilities) . '.';
            }
        }

        if (!$this->cssRuleOutsideMediaContainsTokens($compiledCss, '.dashboard-page-heading', ['display:flex', 'flex-direction:column'])) {
            $violations[] = 'compiled CSS must preserve the base column .dashboard-page-heading layout.';
        }

        if (!$this->cssRuleOutsideMediaContainsTokens($compiledCss, '.dashboard-page-heading > :first-child', ['min-width:0'])) {
            $violations[] = 'compiled CSS must keep the dashboard heading text zone shrink-safe.';
        }

        if (!$this->cssRuleOutsideMediaContainsTokens($compiledCss, '.dashboard-page-heading > :not(:first-child)', ['align-self:flex-start'])) {
            $violations[] = 'compiled CSS must keep mobile dashboard heading actions intrinsically aligned.';
        }

        if (!$this->cssMediaRuleContainsTokens(
            $compiledCss,
            '40rem',
            '.dashboard-page-heading',
            ['flex-direction:row', 'justify-content:space-between', 'align-items:flex-end'],
        )) {
            $violations[] = 'compiled CSS must preserve the 40rem bottom-aligned space-between .dashboard-page-heading row.';
        }

        if (!$this->cssMediaRuleContainsTokens(
            $compiledCss,
            '40rem',
            '.dashboard-page-heading > :not(:first-child)',
            ['align-self:auto'],
        )) {
            $violations[] = 'compiled CSS must restore automatic action-zone alignment in the 40rem dashboard heading row.';
        }

        if (!$this->cssRuleOutsideMediaContainsTokens(
            $compiledCss,
            '.dashboard-page-heading h1',
            ['font-size:var(--text-3xl)', 'overflow-wrap:anywhere'],
        )) {
            $violations[] = 'compiled CSS must preserve the base 30-pixel .dashboard-page-heading H1 size and arbitrary overflow wrapping.';
        }

        if (!$this->cssMediaRuleContainsTokens($compiledCss, '40rem', '.dashboard-page-heading h1', ['font-size:var(--text-4xl)'])) {
            $violations[] = 'compiled CSS must preserve the 40rem 36-pixel .dashboard-page-heading H1 size.';
        }

        foreach ([$sourceCss, $compiledCss] as $css) {
            if ($this->cssHasSelector($css, '.dashboard-page-header')) {
                $violations[] = 'source and compiled CSS must not retain the obsolete .dashboard-page-header selector.';
                break;
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    public function testAnalyticsDashboardUsesTheSharedResponsiveVisualContract(): void
    {
        $sourceCss = (string) file_get_contents(base_path('resources/css/app.css'));
        $compiledCss = (string) file_get_contents(base_path('public/assets/css/app.css'));
        $violations = [];

        foreach ([
            '.analytics-filter__heading' => ['flex', 'min-w-0', 'flex-col', 'md:flex-row', 'md:items-center', 'md:justify-between'],
            '.analytics-filter__form' => ['grid', 'min-w-0', 'md:grid-cols-2', 'xl:items-end'],
            '.analytics-kpi-grid' => ['grid', 'sm:grid-cols-2', 'xl:grid-cols-4'],
            '.analytics-kpi' => ['min-w-0', 'rounded-[16px]', 'border', 'bg-[var(--surface-raised)]'],
            '.analytics-kpi > strong' => ['tabular-nums', 'text-3xl'],
            '.analytics-chart-grid' => ['grid', 'items-start', 'xl:grid-cols-[minmax(0,1.6fr)_minmax(18rem,0.8fr)]'],
            '.analytics-chart-card' => ['min-w-0', 'rounded-[18px]', 'border', 'bg-[var(--surface-raised)]'],
            '.analytics-data-disclosure summary' => ['min-h-11', 'cursor-pointer', 'focus-visible:outline-none'],
            '.analytics-data-disclosure__content' => ['max-h-[28rem]', 'overflow-auto'],
        ] as $selector => $utilities) {
            if (!$this->cssRuleApplyContainsUtilities($sourceCss, $selector, $utilities)) {
                $violations[] = 'source CSS must keep ' . $selector . ' scoped to: ' . implode(', ', $utilities) . '.';
            }
        }

        foreach ([
            '.analytics-filter__heading' => ['display:flex', 'min-width:0', 'flex-direction:column'],
            '.analytics-kpi-grid' => ['display:grid'],
            '.analytics-kpi' => ['min-width:0', 'border-radius:16px'],
            '.analytics-kpi > strong' => ['--tw-numeric-spacing:tabular-nums', 'font-variant-numeric:var('],
            '.analytics-chart-grid' => ['display:grid', 'align-items:flex-start'],
            '.analytics-chart-card' => ['min-width:0', 'border-radius:18px'],
            '.analytics-data-disclosure__content' => ['max-height:28rem', 'overflow:auto'],
        ] as $selector => $tokens) {
            if (!$this->cssRuleOutsideMediaContainsTokens($compiledCss, $selector, $tokens)) {
                $violations[] = 'compiled CSS must keep the base ' . $selector . ' contract: ' . implode(', ', $tokens) . '.';
            }
        }

        if (!$this->cssMediaRuleContainsTokens($compiledCss, '40rem', '.analytics-kpi-grid', ['grid-template-columns:repeat(2,minmax(0,1fr))'])) {
            $violations[] = 'compiled CSS must render two analytics KPI columns from 40rem.';
        }

        if (!$this->cssMediaRuleContainsTokens($compiledCss, '80rem', '.analytics-kpi-grid', ['grid-template-columns:repeat(4,minmax(0,1fr))'])) {
            $violations[] = 'compiled CSS must render four analytics KPI columns from 80rem.';
        }

        if (!$this->cssMediaRuleContainsTokens($compiledCss, '80rem', '.analytics-chart-grid', ['grid-template-columns:minmax(0,1.6fr) minmax(18rem,.8fr)'])) {
            $violations[] = 'compiled CSS must use the asymmetric content-sized analytics chart grid from 80rem.';
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    public function testCssBaseRuleParserExcludesMediaRulesButRetainsLayerRules(): void
    {
        $this->assertFalse(
            $this->cssRuleOutsideMediaContainsTokens(
                '@media (min-width:40rem) { .fixture { display:flex; } }',
                '.fixture',
                ['display:flex'],
            ),
            'A rule nested in @media must not satisfy a base-rule assertion.',
        );
        $this->assertTrue(
            $this->cssRuleOutsideMediaContainsTokens(
                '@layer components { .fixture { display:flex; } }',
                '.fixture',
                ['display:flex'],
            ),
            'A base rule nested in @layer must remain visible to base-rule assertions.',
        );
        $this->assertTrue(
            $this->cssRuleOutsideMediaContainsTokens(
                '@layer components { .fixture>:first-child { min-width:0; } }',
                '.fixture > :first-child',
                ['min-width:0'],
            ),
            'Exact selector matching must tolerate minified combinator whitespace.',
        );
    }

    public function testTailwindBuildScansOnlyExplicitApplicationSources(): void
    {
        $sourceCss = (string) file_get_contents(base_path('resources/css/app.css'));

        $this->assertSame(1, substr_count($sourceCss, '@import "tailwindcss" source(none);'));
        $this->assertTrue(str_contains($sourceCss, '@source "../../app/Views/**/*.php";'));
        $this->assertTrue(str_contains($sourceCss, '@source "../../public/**/*.js";'));
        $this->assertFalse(str_contains($sourceCss, '@import "tailwindcss";'));
    }

    public function testSingleFieldToolbarsUseTheCompactAtomicLayoutContract(): void
    {
        foreach ([
            'app/Views/admin/events/index.php',
            'app/Views/admin/reviews/index.php',
            'app/Views/organizer/events/index.php',
        ] as $view) {
            $source = (string) file_get_contents(base_path($view));

            $this->assertTrue(
                str_contains($source, 'filter-toolbar__form filter-toolbar__form--compact'),
                $view . ' must use the compact single-field toolbar form.',
            );
        }

        $sourceCss = (string) file_get_contents(base_path('resources/css/app.css'));

        foreach ([
            '.filter-toolbar__form' => ['xl:w-max', 'xl:flex-none', 'xl:flex-nowrap', 'xl:items-end'],
            '.filter-toolbar__field' => ['xl:w-40', 'xl:flex-none'],
            '.filter-toolbar__field--search' => ['xl:w-64', 'xl:flex-none'],
            '.filter-toolbar__form--compact' => ['sm:w-max', 'sm:flex-none', 'sm:grid-cols-[12rem_auto]', 'sm:items-end'],
            '.filter-toolbar__form--compact .filter-toolbar__field' => ['sm:' . 'w-48'],
            '.filter-toolbar__form--compact .filter-toolbar__actions' => ['sm:col-span-1', 'sm:w-auto'],
            '.filter-toolbar__form--compact .filter-toolbar__actions .button' => ['sm:w-auto'],
        ] as $selector => $utilities) {
            $this->assertTrue(
                $this->cssRuleApplyContainsUtilities($sourceCss, $selector, $utilities),
                $selector . ' must preserve the compact atomic toolbar contract.',
            );
        }

        $compiledCss = (string) file_get_contents(base_path('public/assets/css/app.css'));

        foreach ([
            '.filter-toolbar__form--compact' => ['flex:none', 'grid-template-columns:12rem auto', 'align-items:flex-end', 'width:max-content'],
            '.filter-toolbar__form--compact .filter-toolbar__field' => [['width:calc(var(--spacing) * 48)', 'width:12rem']],
            '.filter-toolbar__form--compact .filter-toolbar__actions' => ['grid-column:span 1/span 1', 'width:auto'],
            '.filter-toolbar__form--compact .filter-toolbar__actions .button' => ['width:auto'],
        ] as $selector => $declarations) {
            $this->assertTrue(
                $this->cssMediaRuleContainsTokens($compiledCss, '40rem', $selector, $declarations),
                $selector . ' must be present in the compiled 40rem compact toolbar contract.',
            );
        }
    }

    public function testSectionedFormsRenderOnlyOneDividerBeforeActions(): void
    {
        $css = (string) file_get_contents(base_path('public/assets/css/app.css'));

        foreach (['profile-form-section', 'organizer-form__section'] as $sectionClass) {
            $matched = preg_match(
                '/(?:\.[A-Za-z0-9_-]+,)*\.' . preg_quote($sectionClass, '/') . '(?:,\.[A-Za-z0-9_-]+)*\{([^}]*)\}/',
                $css,
                $baseRule,
            );

            $this->assertSame(1, $matched);
            $this->assertFalse(
                str_contains($baseRule[1], 'border-bottom-width'),
                $sectionClass . ' must not draw a divider after every section.',
            );
        }

        $this->assertTrue(
            preg_match(
                '/\.profile-form-section~\.profile-form-section,.organizer-form__section~\.organizer-form__section\{(?=[^}]*border-top-width:1px)(?=[^}]*padding-top:calc\(var\(--spacing\) \* 8\))[^}]*\}/',
                $css,
            ) === 1,
            'Later form sections must receive one leading divider with consistent spacing.',
        );

        foreach (['profile-form-actions', 'organizer-form__actions'] as $actionClass) {
            $this->assertTrue(
                preg_match(
                    '/\.' . preg_quote($actionClass, '/') . '\{[^}]*border-top-width:1px[^}]*\}/',
                    $css,
                ) === 1,
                $actionClass . ' must retain one intentional footer divider.',
            );
        }

        preg_match_all('/\.profile-form-actions\{([^}]*)\}/', $css, $profileActionRules);
        $this->assertFalse(
            str_contains(implode('', $profileActionRules[1]), 'position:sticky'),
            'The profile action footer must not overlay scrolling section dividers.',
        );
    }

    public function testPublicMapAssetsAreSelfHostedAndLoadedOnlyWhenRequested(): void
    {
        $view = new View(base_path('app/Views'));
        $html = $view->render('errors/404', [
            'app' => ['name' => 'OEMS'],
            'currentUser' => null,
            'flash' => [],
            'pageTitle' => 'Map preview',
            'leafletEnabled' => true,
        ], 'public');

        $this->assertTrue(str_contains($html, 'href="/assets/vendor/leaflet/leaflet.css"'));
        $this->assertTrue(str_contains($html, 'src="/assets/vendor/leaflet/leaflet.js"'));
        $this->assertTrue(str_contains($html, 'src="/assets/js/location.js?v=20260813-event-view-v1"'));
        $this->assertFalse(str_contains($html, 'unpkg.com'));
        $this->assertFalse(str_contains($html, 'jsdelivr.net'));
    }

    public function testMobileNavigationStartsCollapsedAndExposesItsControlState(): void
    {
        $html = $this->renderHome();

        $this->assertTrue(str_contains($html, 'data-menu-toggle'));
        $this->assertTrue(str_contains($html, 'aria-expanded="false"'));
        $this->assertTrue(str_contains($html, 'aria-controls="mobile-menu"'));
        $this->assertTrue(str_contains($html, 'id="mobile-menu"'));
        $this->assertTrue(str_contains($html, 'class="mobile-menu lg:hidden"'));
    }

    public function testMobileNavigationRestoresFocusAndClosesAtTheDesktopBreakpoint(): void
    {
        $javascript = file_get_contents(base_path('public/assets/js/app.js'));

        $this->assertTrue($javascript !== false);
        $this->assertTrue(str_contains($javascript, "link.addEventListener('click', () => closeMobileMenu());"));
        $this->assertTrue(str_contains($javascript, "window.matchMedia('(min-width: 64rem)')"));
        $this->assertTrue(str_contains($javascript, "desktopMenuQuery.addEventListener?.('change', syncMenuToViewport);"));
    }

    public function testThemeControlsExposeAnIconStateHookAndAccessibleLabel(): void
    {
        $html = $this->renderHome();

        $this->assertTrue(str_contains($html, 'data-theme-toggle'));
        $this->assertTrue(str_contains($html, 'aria-label="Switch to dark theme"'));
        $this->assertTrue(str_contains($html, 'data-theme-icon'));
    }

    public function testPublicAndAuthLayoutsBootstrapThemeWithoutInlineExecutableJavascript(): void
    {
        $public = $this->renderHome();
        $auth = (new View(base_path('app/Views')))->render('auth/login', [
            'app' => ['name' => 'OEMS'],
            'csrfToken' => 'test-token',
            'flash' => [],
            'errors' => [],
            'old' => [],
            'pageTitle' => 'Sign in',
        ], 'auth');

        foreach ([$public, $auth] as $html) {
            $this->assertTrue(str_contains($html, '<script src="/assets/js/theme.js?v=20260811-form-controls-fix"></script>'));
            $this->assertTrue(
                strpos($html, '/assets/js/theme.js') < strpos($html, '/assets/css/app.css'),
                'The synchronous local theme bootstrap must run before stylesheet rendering.',
            );
            $this->assertSame([], $this->inlineExecutableScripts($html));
        }
    }

    public function testVerificationRecoveryUsesSharedResponsiveSemanticComponents(): void
    {
        $view = new View(base_path('app/Views'));
        $shared = [
            'app' => ['name' => 'OEMS'],
            'siteSettings' => [],
            'currentUser' => null,
            'csrfToken' => 'safe-token',
            'flash' => [],
            'errors' => [],
            'old' => [],
        ];
        $login = $view->render('auth/login', $shared + ['pageTitle' => 'Sign in'], 'auth');
        $recovery = $view->render('auth/resend-verification', $shared + [
            'pageTitle' => 'Resend verification email',
        ], 'auth');
        $source = (string) file_get_contents(base_path('resources/css/app.css'));
        $compiled = (string) file_get_contents(base_path('public/assets/css/app.css'));

        $this->assertTrue(str_contains($login, 'href="/verify-email/resend"'));
        $this->assertTrue(str_contains($login, '>Resend verification email<'));
        $this->assertTrue(str_contains($recovery, 'data-submit-label="Sending verification email…"'));
        $this->assertTrue(str_contains($recovery, 'aria-describedby="verification-email-help"'));
        $this->assertTrue(str_contains($source, '.email-verification-notice {'));
        $this->assertTrue(str_contains($source, '.email-verification-notice__action {'));
        $this->assertTrue(str_contains($source, 'bg-[var(--warning-soft)]'));
        $this->assertTrue(str_contains($source, 'sm:col-start-2'));
        $this->assertTrue(str_contains($source, '@apply w-full sm:w-auto;'));
        $this->assertTrue(str_contains($compiled, '.email-verification-notice{'));
        $this->assertTrue(str_contains($compiled, '.email-verification-notice__action{'));
        $this->assertFalse(str_contains($source, 'dark:text-'));
        $this->assertFalse(str_contains($source, 'dark:bg-'));
    }

    public function testPasswordVisibilityControlStartsHiddenWithAccurateState(): void
    {
        $view = new View(base_path('app/Views'));
        $html = $view->render('auth/login', [
            'app' => ['name' => 'OEMS'],
            'csrfToken' => 'test-token',
            'flash' => [],
            'errors' => [],
            'old' => [],
            'pageTitle' => 'Sign in',
        ], 'auth');

        $this->assertTrue(str_contains($html, 'data-password-toggle'));
        $this->assertTrue(str_contains($html, 'aria-controls="password"'));
        $this->assertTrue(str_contains($html, 'aria-pressed="false"'));
        $this->assertTrue(str_contains($html, 'aria-label="Show password"'));
        $this->assertTrue(str_contains($html, 'class="ph ph-eye"'));
    }

    public function testEventDiscoveryExposesSearchAndSemanticEventMetadata(): void
    {
        $html = $this->renderHome();

        $this->assertTrue(str_contains($html, 'role="search" aria-label="Search events"'));
        $this->assertTrue(str_contains($html, '<time datetime="2026-08-22T10:00:00+06:00">'));
        $this->assertTrue(str_contains($html, '<address>Dhanmondi, Dhaka</address>'));
        $this->assertTrue(str_contains($html, 'href="/events" class="button button--primary"'));
        $this->assertTrue(str_contains($html, 'href="/register?role=organizer" class="button button--quiet"'));
    }

    public function testHomepageFeaturedEventsUseBalancedResponsiveCards(): void
    {
        $html = $this->renderHome([
            'featuredEvents' => [
                [
                    'title' => 'Dhaka Tech Summit 2026',
                    'slug' => 'dhaka-tech-summit-2026',
                    'category' => 'Technology',
                    'date' => 'Sep 18, 2026',
                    'datetime' => '2026-09-18T09:00:00+06:00',
                    'time' => '9:00 AM',
                    'venue' => 'Bangabandhu International Conference Center, Sher-e-Bangla Nagar, Dhaka, Bangladesh',
                    'price' => '৳2,500',
                    'image' => '/assets/images/event-technology.webp',
                    'alt' => 'People attending the Dhaka Tech Summit',
                ],
                [
                    'title' => 'Startup Growth Forum 2026 with founders and product leaders',
                    'slug' => 'startup-growth-forum-2026',
                    'category' => 'Business',
                    'date' => 'Oct 5, 2026',
                    'datetime' => '2026-10-05T10:00:00+06:00',
                    'time' => '10:00 AM',
                    'venue' => 'Dhaka, Bangladesh',
                    'price' => '৳1,200',
                    'image' => '/assets/images/event-business.webp',
                    'alt' => 'A team collaborating at the Startup Growth Forum',
                ],
            ],
        ]);

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);
        $cards = $xpath->query('//section[contains(concat(" ", normalize-space(@class), " "), " home-featured ")]//article[contains(concat(" ", normalize-space(@class), " "), " home-event-card ")]');

        $this->assertSame(2, $cards?->length ?? 0);
        $this->assertFalse(str_contains($html, 'event-card--wide'));

        if ($cards !== false) {
            foreach ($cards as $card) {
                $this->assertSame(1, $xpath->query('.//time', $card)?->length ?? 0);
                $this->assertSame(1, $xpath->query('.//address', $card)?->length ?? 0);
                $this->assertSame(2, $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " event-card__meta ")]', $card)?->length ?? 0);
            }
        }

        $sourceCss = (string) file_get_contents(base_path('resources/css/app.css'));
        $compiledCss = (string) file_get_contents(base_path('public/assets/css/app.css'));

        $this->assertTrue($this->cssRuleApplyContainsUtilities(
            $sourceCss,
            '.home-featured__grid',
            ['grid-cols-1', 'lg:grid-cols-2'],
        ));
        $this->assertTrue($this->cssRuleOutsideMediaContainsTokens(
            $compiledCss,
            '.home-featured__grid',
            ['grid-template-columns:repeat(1,minmax(0,1fr))'],
        ));
        $this->assertTrue($this->cssMediaRuleContainsTokens(
            $compiledCss,
            '64rem',
            '.home-featured__grid',
            ['grid-template-columns:repeat(2,minmax(0,1fr))'],
        ));
        $this->assertTrue($this->cssRuleOutsideMediaContainsTokens(
            $compiledCss,
            '.home-featured .event-card__media img',
            ['aspect-ratio:16/9'],
        ));
        $this->assertTrue($this->cssRuleOutsideMediaContainsTokens(
            $compiledCss,
            '.home-event-card h3',
            ['display:-webkit-box', '-webkit-box-orient:vertical', '-webkit-line-clamp:2', 'overflow:hidden', 'min-height:'],
        ));
        $this->assertTrue($this->cssRuleOutsideMediaContainsTokens(
            $compiledCss,
            '.home-event-card .event-card__footer',
            ['margin-top:auto'],
        ));
        $this->assertTrue($this->cssRuleOutsideMediaContainsTokens(
            $compiledCss,
            '.home-event-card h3 a',
            ['display:inline-flex', ['min-height:2.75rem', 'min-height:calc(var(--spacing) * 11)'], 'align-items:center'],
        ));
        $this->assertTrue($this->cssRuleOutsideMediaContainsTokens(
            $compiledCss,
            '.home-event-card .event-card__footer .text-link',
            [['min-height:2.75rem', 'min-height:calc(var(--spacing) * 11)']],
        ));
    }

    public function testHomepageSeparatesDiscoveryParticipantAndOrganizerJourneys(): void
    {
        $html = $this->renderHome();

        preg_match('/<section id="how-it-works".*?<\/section>/s', $html, $journeySection);
        $this->assertTrue(isset($journeySection[0]));

        $this->assertTrue(str_contains($html, 'aria-labelledby="home-hero-title"'));
        $this->assertTrue(str_contains($html, 'id="browse-categories"'));
        $this->assertTrue(str_contains($html, 'aria-labelledby="home-categories-title"'));
        $this->assertTrue(str_contains($html, 'class="home-featured section-space"'));
        $this->assertTrue(str_contains($html, 'aria-labelledby="home-featured-title"'));
        $this->assertTrue(str_contains($html, 'class="home-journey home-journey--participant"'));
        $this->assertTrue(str_contains($html, 'class="home-journey home-journey--organizer"'));
        $this->assertTrue(str_contains($html, 'For participants'));
        $this->assertTrue(str_contains($html, 'For organizers'));
        $this->assertTrue(str_contains($journeySection[0], 'class="home-journeys__label"'));
        $this->assertFalse(str_contains($journeySection[0], 'eyebrow--inverse'));
        $this->assertTrue(str_contains($journeySection[0], 'aria-labelledby="participant-journey-title"'));
        $this->assertTrue(str_contains($journeySection[0], 'aria-labelledby="organizer-journey-title"'));
        $this->assertSame(6, substr_count($journeySection[0], 'class="home-journey__step-icon"'));
        $this->assertTrue(str_contains($journeySection[0], 'class="ph ph-compass"'));
        $this->assertTrue(str_contains($journeySection[0], 'class="ph ph-qr-code"'));
        $this->assertTrue(str_contains($journeySection[0], 'class="ph ph-note-pencil"'));
        $this->assertTrue(str_contains($journeySection[0], 'class="ph ph-scan"'));
        $this->assertTrue(str_contains($html, 'class="organizer-callout__points"'));
        $this->assertTrue(str_contains($html, 'Manage guests and check-ins'));
    }

    public function testHomeBannersRenderSafelyInProviderOrderWithOptionalContent(): void
    {
        $html = $this->renderHome([
            'homeBanners' => [
                [
                    'id' => 7,
                    'title' => 'August <community> & friends',
                    'subtitle' => 'Three practical sessions for "local" organizers.',
                    'image_path' => '/uploads/banners/community-series.webp',
                    'link_url' => '/events?category=community&format=series',
                    'starts_at' => null,
                    'ends_at' => null,
                    'sort_order' => 10,
                ],
                [
                    'id' => 8,
                    'title' => 'Title-only <notice>',
                    'subtitle' => '',
                    'image_path' => '/uploads/banners/title-only.webp',
                    'link_url' => '',
                    'starts_at' => null,
                    'ends_at' => null,
                    'sort_order' => 20,
                ],
            ],
        ]);

        $this->assertTrue(str_contains($html, 'class="home-announcements"'));
        $this->assertSame(2, substr_count($html, 'class="home-announcement"'));
        $this->assertTrue(str_contains($html, 'class="home-announcement__media"'));
        $this->assertTrue(str_contains($html, 'class="home-announcement__body"'));
        $this->assertTrue(str_contains($html, 'alt="Promotion: August &lt;community&gt; &amp; friends"'));
        $this->assertTrue(str_contains($html, '<h2 id="home-announcement-title-0">August &lt;community&gt; &amp; friends</h2>'));
        $this->assertTrue(str_contains($html, 'Three practical sessions for &quot;local&quot; organizers.'));
        $this->assertTrue(str_contains($html, 'href="/events?category=community&amp;format=series"'));
        $this->assertTrue(strpos($html, 'August &lt;community&gt;') < strpos($html, 'Title-only &lt;notice&gt;'));
        $this->assertFalse(str_contains($html, 'August <community>'));
        $this->assertFalse(str_contains($html, 'Title-only <notice>'));

        preg_match('/<article class="home-announcement"[^>]*aria-labelledby="home-announcement-title-1"[^>]*>(.*?)<\/article>/s', $html, $titleOnlyBanner);
        $this->assertTrue(isset($titleOnlyBanner[1]));
        $this->assertFalse(str_contains($titleOnlyBanner[1], '<p>'));
        $this->assertFalse(str_contains($titleOnlyBanner[1], 'class="text-link"'));
        $this->assertTrue(str_contains($html, 'Find your next standout event.'));
        $this->assertFalse(str_contains($html, 'class="dashboard-panel overflow-hidden p-0"'));
    }

    public function testHomepageCssControlsResponsiveDensityAndLongAnnouncementCopy(): void
    {
        $css = (string) file_get_contents(base_path('resources/css/app.css'));

        preg_match('/\.home-journeys__surface\s*\{([^}]+)\}/', $css, $journeySurfaceRule);
        preg_match('/\.home-journeys__grid\s*\{([^}]+)\}/', $css, $journeyGridRule);
        $this->assertTrue(isset($journeySurfaceRule[1]));
        $this->assertTrue(isset($journeyGridRule[1]));

        $this->assertTrue(str_contains($css, '.home-announcement__body h2,'));
        $this->assertTrue(str_contains($css, 'overflow-wrap: anywhere;'));
        $this->assertTrue(str_contains($css, 'lg:h-[clamp(280px,24vw,340px)]'));
        $this->assertTrue(str_contains($css, '.home-categories__grid'));
        $this->assertTrue(str_contains($css, '@apply grid grid-cols-2'));
        $this->assertTrue(str_contains($css, '.home-featured__grid'));
        $this->assertTrue(str_contains($css, '.home-journeys__grid'));
        $this->assertTrue(str_contains($journeyGridRule[1], 'gap-5'));
        $this->assertTrue(str_contains($journeyGridRule[1], 'lg:grid-cols-2'));
        $this->assertFalse(str_contains($journeyGridRule[1], 'border-t'));
        $this->assertTrue(str_contains($journeySurfaceRule[1], 'border-[var(--line)]'));
        $this->assertTrue(str_contains($journeySurfaceRule[1], 'bg-[var(--surface-soft)]'));
        $this->assertFalse(str_contains($journeySurfaceRule[1], 'bg-[#101a36]'));
        $this->assertFalse(str_contains($css, 'lg:min-h-[590px]'));
        $this->assertTrue(str_contains($css, '.home-featured .event-card {'));
        $this->assertTrue(str_contains($css, '@apply flex h-full flex-col;'));
        $this->assertTrue(str_contains($css, '.home-event-card .event-card__footer {'));
        $this->assertTrue(str_contains($css, '@apply mt-auto;'));
        $this->assertTrue(str_contains($css, '.home-journeys__label'));
        $this->assertTrue(str_contains($css, '.home-journey__step-icon'));
        $this->assertTrue(str_contains($css, '.home-journey__steps li:not(:last-child)::after'));
        $this->assertTrue(str_contains($css, '.organizer-callout > .page-shell'));
        $this->assertTrue(str_contains($css, 'text-[var(--ink-muted)]'));
    }

    public function testRegistrationRoleChoicesAreNativeAndSelfDescribing(): void
    {
        $view = new View(base_path('app/Views'));
        $html = $view->render('auth/register', [
            'app' => ['name' => 'OEMS'],
            'csrfToken' => 'test-token',
            'flash' => [],
            'errors' => [],
            'old' => [],
            'pageTitle' => 'Create account',
        ], 'auth');

        $this->assertTrue(str_contains($html, 'type="radio" name="role" value="participant"'));
        $this->assertTrue(str_contains($html, 'aria-describedby="participant-role-description"'));
        $this->assertTrue(str_contains($html, 'id="participant-role-description"'));
        $this->assertTrue(str_contains($html, 'class="ph ph-ticket" aria-hidden="true"'));
        $this->assertTrue(str_contains($html, 'type="radio" name="role" value="organizer"'));
        $this->assertTrue(str_contains($html, 'aria-describedby="organizer-role-description"'));
        $this->assertTrue(str_contains($html, 'class="ph ph-microphone-stage" aria-hidden="true"'));
    }

    public function testPasswordConfirmationMismatchIsAssociatedAcrossAccountForms(): void
    {
        $view = new View(base_path('app/Views'));
        $errors = Validator::validate(
            ['password' => 'secure-password', 'password_confirmation' => 'different-password'],
            ['password' => 'required|string|min:8|confirmed'],
        );
        $forms = [
            $view->render('auth/register', [
                'app' => ['name' => 'OEMS'],
                'csrfToken' => 'test-token',
                'flash' => [],
                'errors' => $errors,
                'old' => [],
                'pageTitle' => 'Create account',
            ], 'auth'),
            $view->render('auth/reset-password', [
                'app' => ['name' => 'OEMS'],
                'csrfToken' => 'test-token',
                'flash' => [],
                'errors' => $errors,
                'old' => [],
                'pageTitle' => 'Reset password',
                'token' => 'safe-token',
            ], 'auth'),
            $view->render('auth/change-password', [
                'app' => ['name' => 'OEMS'],
                'csrfToken' => 'test-token',
                'currentUser' => [
                    'id' => 7,
                    'name' => 'Test Participant',
                    'email' => 'participant@example.test',
                    'role_name' => 'Participant',
                    'role_slug' => 'participant',
                ],
                'flash' => [],
                'errors' => $errors,
                'old' => [],
                'pageTitle' => 'Change password',
            ], 'dashboard'),
        ];

        foreach ($forms as $html) {
            $this->assertTrue(str_contains(
                $html,
                'id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" maxlength="128" required data-form-label="Password confirmation" data-match-field="password" aria-invalid="true" aria-describedby="password-confirmation-error"',
            ));
            $this->assertTrue(str_contains(
                $html,
                '<p id="password-confirmation-error" class="field-error" role="alert">Password confirmation does not match.</p>',
            ));
        }
    }

    public function testErrorPagesOfferARecognizableRecoveryAction(): void
    {
        $view = new View(base_path('app/Views'));
        $html = $view->render('errors/404', [
            'app' => ['name' => 'OEMS'],
            'currentUser' => null,
            'flash' => [],
            'pageTitle' => 'Page not found',
        ], 'public');

        $this->assertTrue(str_contains($html, 'class="error-state"'));
        $this->assertTrue(str_contains($html, 'class="ph ph-map-trifold" aria-hidden="true"'));
        $this->assertTrue(str_contains($html, 'href="/"'));
        $this->assertTrue(str_contains($html, 'Return home'));
    }

    private function resultSummaryMarkupViolations(
        string $markup,
        string $label,
        string $expectedContext,
        string $expectedSubject,
    ): array {
        $document = new \DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($markup);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        if ($loaded === false) {
            return [$label . ' must contain parseable result-summary markup.'];
        }

        $xpath = new \DOMXPath($document);
        $summaries = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " result-summary ")]');

        if ($summaries === false || $summaries->length !== 1 || !$summaries->item(0) instanceof \DOMElement) {
            return [$label . ' must render exactly one .result-summary.'];
        }

        $summary = $summaries->item(0);
        $violations = [];
        foreach (['role' => 'status', 'aria-live' => 'polite', 'aria-atomic' => 'true'] as $attribute => $expected) {
            if ($summary->getAttribute($attribute) !== $expected) {
                $violations[] = $label . ' result summary must set ' . $attribute . '="' . $expected . '".';
            }
        }

        $queries = [
            '.result-summary__count' => './*[contains(concat(" ", normalize-space(@class), " "), " result-summary__count ")]',
            '.result-summary__copy' => './*[contains(concat(" ", normalize-space(@class), " "), " result-summary__copy ")]',
            '.sr-only' => './*[contains(concat(" ", normalize-space(@class), " "), " sr-only ")]',
            '.result-summary__context' => './*[contains(concat(" ", normalize-space(@class), " "), " result-summary__copy ")]/*[contains(concat(" ", normalize-space(@class), " "), " result-summary__context ")]',
            '.result-summary__subject' => './*[contains(concat(" ", normalize-space(@class), " "), " result-summary__copy ")]/*[contains(concat(" ", normalize-space(@class), " "), " result-summary__subject ")]',
        ];
        $children = [];

        foreach ($queries as $class => $query) {
            $children[$class] = $xpath->query($query, $summary);
            if ($children[$class] === false || $children[$class]->length !== 1) {
                $violations[] = $label . ' must provide exactly one ' . $class . ' in its result summary.';
            }
        }

        $directChildren = $xpath->query('./*', $summary);
        if ($directChildren === false || $directChildren->length !== 3) {
            $violations[] = $label . ' must keep result-summary to its count, visible copy, and screen-reader phrase only.';
        }

        $counts = $children['.result-summary__count'];
        $copies = $children['.result-summary__copy'];
        foreach (['.result-summary__count' => $counts, '.result-summary__copy' => $copies] as $class => $nodes) {
            if (
                $nodes instanceof \DOMNodeList
                && $nodes->length === 1
                && $nodes->item(0) instanceof \DOMElement
                && $nodes->item(0)->getAttribute('aria-hidden') !== 'true'
            ) {
                $violations[] = $label . ' must hide visible ' . $class . ' content from assistive technology.';
            }
        }

        if ($counts instanceof \DOMNodeList && $counts->length === 1) {
            $countChildren = $xpath->query('./*', $counts->item(0));
            if ($countChildren === false || $countChildren->length !== 0) {
                $violations[] = $label . ' must not nest presentation content inside .result-summary__count.';
            }
        }

        if ($copies instanceof \DOMNodeList && $copies->length === 1) {
            $copyChildren = $xpath->query('./*', $copies->item(0));
            if ($copyChildren === false || $copyChildren->length !== 2) {
                $violations[] = $label . ' must keep visible result copy to its context and subject only.';
            }
        }

        foreach (['.result-summary__context' => $expectedContext, '.result-summary__subject' => $expectedSubject] as $class => $expected) {
            $nodes = $children[$class];
            if (
                $nodes instanceof \DOMNodeList
                && $nodes->length === 1
                && trim((string) $nodes->item(0)?->textContent) !== $expected
            ) {
                $violations[] = $label . ' must use "' . $expected . '" for ' . $class . '.';
            }
        }

        $screenReaderPhrases = $children['.sr-only'];
        if ($screenReaderPhrases instanceof \DOMNodeList && $screenReaderPhrases->length === 1) {
            $screenReaderPhrase = $screenReaderPhrases->item(0);
            $screenReaderChildren = $xpath->query('./*', $screenReaderPhrase);
            if ($screenReaderChildren === false || $screenReaderChildren->length !== 0) {
                $violations[] = $label . ' must keep its screen-reader result phrase as plain text.';
            }
            if ($screenReaderPhrase instanceof \DOMElement && $screenReaderPhrase->hasAttribute('aria-hidden')) {
                $violations[] = $label . ' must not hide its screen-reader result phrase from assistive technology.';
            }
            if (trim((string) $screenReaderPhrase?->textContent) === '') {
                $violations[] = $label . ' must expose nonempty accessible result text.';
            }
        }

        $icons = $xpath->query('.//i | .//*[contains(concat(" ", normalize-space(@class), " "), " ph ")]', $summary);
        if ($icons !== false && $icons->length !== 0) {
            $violations[] = $label . ' must not add an icon to its result summary.';
        }

        $nestedCards = $xpath->query('.//article | .//*[contains(concat(" ", normalize-space(@class), " "), " dashboard-panel ")] | .//*[contains(concat(" ", normalize-space(@class), " "), " card ")]', $summary);
        if ($nestedCards !== false && $nestedCards->length !== 0) {
            $violations[] = $label . ' must not nest a card inside its result summary.';
        }

        return $violations;
    }

    private function cssRuleContainsTokens(string $css, string $selector, array $tokens): bool
    {
        $matched = preg_match_all(
            '/([^{}]*' . preg_quote($selector, '/') . '(?![A-Za-z0-9_-])[^{}]*)\\{([^}]*)\\}/',
            $css,
            $rules,
        );

        if ($matched === false || $matched === 0) {
            return false;
        }

        foreach ($rules[2] as $rule) {
            $containsTokens = true;

            foreach ($tokens as $token) {
                $alternatives = is_array($token) ? $token : [$token];
                $hasAlternative = false;

                foreach ($alternatives as $alternative) {
                    if (str_contains($rule, $alternative)) {
                        $hasAlternative = true;
                        break;
                    }
                }

                if (!$hasAlternative) {
                    $containsTokens = false;
                    break;
                }
            }

            if ($containsTokens) {
                return true;
            }
        }

        return false;
    }

    private function cssRuleOutsideMediaContainsTokens(string $css, string $selector, array $tokens): bool
    {
        foreach ($this->cssExactSelectorRuleBodiesOutsideMedia($css, $selector) as $rule) {
            $containsTokens = true;

            foreach ($tokens as $token) {
                $alternatives = is_array($token) ? $token : [$token];
                $hasAlternative = false;

                foreach ($alternatives as $alternative) {
                    if (str_contains($rule, $alternative)) {
                        $hasAlternative = true;
                        break;
                    }
                }

                if (!$hasAlternative) {
                    $containsTokens = false;
                    break;
                }
            }

            if ($containsTokens) {
                return true;
            }
        }

        return false;
    }

    private function cssExactSelectorRuleBodiesOutsideMedia(string $css, string $selector): array
    {
        $bodies = [];
        $frames = [];
        $statementStart = 0;
        $length = strlen($css);

        for ($index = 0; $index < $length; $index++) {
            if ($css[$index] === '/' && ($css[$index + 1] ?? '') === '*') {
                $commentEnd = strpos($css, '*/', $index + 2);
                $index = $commentEnd === false ? $length : $commentEnd + 1;
                continue;
            }

            if ($css[$index] === '"' || $css[$index] === "'") {
                $quote = $css[$index];

                for ($index++; $index < $length; $index++) {
                    if ($css[$index] === '\\') {
                        $index++;
                    } elseif (($css[$index] ?? '') === $quote) {
                        break;
                    }
                }

                continue;
            }

            if ($css[$index] === '{') {
                $prelude = trim(substr($css, $statementStart, $index - $statementStart));
                $parentIsInMedia = $frames === [] ? false : $frames[array_key_last($frames)]['inMedia'];
                $isAtRule = str_starts_with(ltrim($prelude), '@');
                $frames[] = [
                    'bodyStart' => $index + 1,
                    'inMedia' => $parentIsInMedia || preg_match('/\A@media\b/i', $prelude) === 1,
                    'selector' => $isAtRule ? null : $this->normalizeCssSelector($prelude),
                ];
                $statementStart = $index + 1;
                continue;
            }

            if ($css[$index] === '}') {
                $frame = array_pop($frames);

                if (
                    is_array($frame)
                    && $frame['selector'] === $this->normalizeCssSelector($selector)
                    && $frame['inMedia'] === false
                ) {
                    $bodies[] = substr($css, $frame['bodyStart'], $index - $frame['bodyStart']);
                }

                $statementStart = $index + 1;
                continue;
            }

            if ($css[$index] === ';') {
                $statementStart = $index + 1;
            }
        }

        return $bodies;
    }

    private function normalizeCssSelector(string $selector): string
    {
        return preg_replace('/\s*([>+~])\s*/', '$1', trim($selector)) ?? trim($selector);
    }

    private function dashboardPageHeadingMarkupViolations(string $markup, string $label): array
    {
        $document = new \DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($markup);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        if ($loaded === false) {
            return [$label . ' must contain parseable page-heading markup.'];
        }

        $xpath = new \DOMXPath($document);
        $headings = $xpath->query('//h1');

        if ($headings === false || $headings->length === 0) {
            return [];
        }

        if ($headings->length !== 1) {
            return [$label . ' must render exactly one document H1; found ' . $headings->length . '.'];
        }

        $roots = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " dashboard-page-heading ")]');

        if ($roots === false || $roots->length !== 1) {
            return [$label . ' must render exactly one .dashboard-page-heading root.'];
        }

        $rootHeadings = $xpath->query('.//h1', $roots->item(0));

        if ($rootHeadings === false || $rootHeadings->length !== 1) {
            return [$label . ' must render its sole document H1 inside .dashboard-page-heading.'];
        }

        return [];
    }

    private function cssRuleApplyContainsUtilities(string $css, string $selector, array $utilities): bool
    {
        $matched = preg_match_all(
            '/([^{}]*' . preg_quote($selector, '/') . '(?![A-Za-z0-9_-])[^{}]*)\\{([^}]*)\\}/',
            $css,
            $rules,
        );

        if ($matched === false || $matched === 0) {
            return false;
        }

        foreach ($rules[2] as $rule) {
            if ($this->cssRuleBodyApplyContainsUtilities($rule, $utilities)) {
                return true;
            }
        }

        return false;
    }

    private function htmlSourceHasClassToken(string $source, string $class): bool
    {
        return preg_match(
            '/\bclass\s*=\s*(["\'])(?:[^"\']*\s)?' . preg_quote($class, '/') . '(?:\s[^"\']*)?\1/i',
            $source,
        ) === 1;
    }

    private function cssRuleBodyApplyContainsUtilities(string $rule, array $utilities): bool
    {
        $appliedUtilities = $this->cssRuleBodyAppliedUtilities($rule);

        foreach ($utilities as $utility) {
            if (!in_array($utility, $appliedUtilities, true)) {
                return false;
            }
        }

        return true;
    }

    private function cssRuleBodyAppliedUtilities(string $rule): array
    {
        preg_match_all('/@apply\\s+([^;]+);/', $rule, $applyDeclarations);
        $appliedUtilities = [];

        foreach ($applyDeclarations[1] as $declaration) {
            $appliedUtilities = [...$appliedUtilities, ...(preg_split('/\\s+/', trim($declaration)) ?: [])];
        }

        return $appliedUtilities;
    }

    private function cssRuleBodyHasExactFlexDirectionAndWrapUtilities(
        string $rule,
        array $requiredUtilities,
        array $allowedFlowUtilities,
    ): bool
    {
        $appliedUtilities = $this->cssRuleBodyAppliedUtilities($rule);

        foreach ($requiredUtilities as $utility) {
            if (!in_array($utility, $appliedUtilities, true)) {
                return false;
            }
        }

        $flowUtilities = [];

        foreach ($appliedUtilities as $utility) {
            $variants = explode(':', $utility);
            $utilityName = trim($variants[array_key_last($variants)], '!');

            if (preg_match('/\Aflex-(?:(?:row|col)(?:-reverse)?|wrap(?:-reverse)?|no(?:wrap))\z/', $utilityName) === 1) {
                $flowUtilities[] = $utility;
            }
        }

        sort($flowUtilities);
        sort($allowedFlowUtilities);

        return $flowUtilities === $allowedFlowUtilities;
    }

    private function cssExactSelectorRuleBodies(string $css, string $selector): array
    {
        $matched = preg_match_all(
            '/(?:\\A|(?<=[{}]))\\s*' . preg_quote($selector, '/') . '\\s*\\{([^}]*)\\}/',
            $css,
            $rules,
        );

        return $matched === false ? [] : $rules[1];
    }

    private function cssRuleBodyHasExactDeclarationsAmongProperties(
        string $rule,
        array $properties,
        array $expectedDeclarations,
    ): bool
    {
        $flowDeclarations = [];

        foreach (explode(';', $rule) as $declaration) {
            $parts = explode(':', $declaration, 2);

            if (count($parts) !== 2) {
                continue;
            }

            $property = trim($parts[0]);

            if (in_array($property, $properties, true)) {
                $flowDeclarations[] = [$property, trim($parts[1])];
            }
        }

        return $flowDeclarations === $expectedDeclarations;
    }

    private function cssRuleHasOnlyApprovedColorAndCustomDeclarations(
        string $rule,
        array $approvedColorDeclarations,
        array $approvedCustomProperties,
    ): bool {
        $colorDeclarations = [];
        $customProperties = [];

        foreach ($this->cssDeclarations($rule) as [$property, $value]) {
            if ($property === 'color' || str_ends_with($property, '-color')) {
                $colorDeclarations[] = $property . ':' . $value;
            }

            if (str_starts_with($property, '--')) {
                $customProperties[] = $property . ':' . $value;
            }
        }

        sort($colorDeclarations);
        sort($customProperties);
        sort($approvedColorDeclarations);
        sort($approvedCustomProperties);

        return $colorDeclarations === $approvedColorDeclarations && $customProperties === $approvedCustomProperties;
    }

    private function cssDeclarations(string $rule): array
    {
        $declarations = [];

        foreach (explode(';', $rule) as $declaration) {
            $parts = explode(':', $declaration, 2);

            if (count($parts) !== 2) {
                continue;
            }

            $property = strtolower(trim($parts[0]));
            $value = trim($parts[1]);

            if ($property !== '' && $value !== '') {
                $declarations[] = [$property, $value];
            }
        }

        return $declarations;
    }

    private function cssMediaRuleContainsTokens(string $css, string $breakpoint, string $selector, array $tokens): bool
    {
        foreach ($this->cssMediaRuleBodies($css, $breakpoint) as $mediaCss) {
            if (
                $this->cssRuleContainsTokens($mediaCss, $selector, $tokens)
                || $this->cssRuleContainsTokens($mediaCss, $this->normalizeCssSelector($selector), $tokens)
            ) {
                return true;
            }
        }

        return false;
    }

    private function cssMediaRuleBodies(string $css, string $breakpoint): array
    {
        $matched = preg_match_all(
            '/@media\\s*\\(\\s*min-width\\s*:\\s*' . preg_quote($breakpoint, '/') . '\\s*\\)\\s*\\{/',
            $css,
            $mediaQueries,
            PREG_OFFSET_CAPTURE,
        );

        if ($matched === false || $matched === 0) {
            return [];
        }

        $bodies = [];

        foreach ($mediaQueries[0] as [$query, $start]) {
            $openBrace = $start + strrpos($query, '{');
            $depth = 0;
            $length = strlen($css);

            for ($index = $openBrace; $index < $length; $index++) {
                if ($css[$index] === '{') {
                    $depth++;
                } elseif ($css[$index] === '}') {
                    $depth--;

                    if ($depth === 0) {
                        $bodies[] = substr($css, $openBrace + 1, $index - $openBrace - 1);
                        break;
                    }
                }
            }
        }

        return $bodies;
    }

    private function cssHasSelector(string $css, string $selector): bool
    {
        return preg_match('/' . preg_quote($selector, '/') . '(?![A-Za-z0-9_-])/', $css) === 1;
    }

    private function describeCssTokens(array $tokens): string
    {
        $descriptions = [];

        foreach ($tokens as $token) {
            $descriptions[] = is_array($token) ? implode(' or ', $token) : $token;
        }

        return implode(', ', $descriptions);
    }

    private function isVisibleToolbarElement(\DOMElement $element): bool
    {
        $classes = preg_split('/\\s+/', trim($element->getAttribute('class'))) ?: [];

        if (
            in_array('hidden', $classes, true)
            || in_array('sr-only', $classes, true)
            || in_array('invisible', $classes, true)
            || in_array('opacity-0', $classes, true)
            || $element->hasAttribute('hidden')
        ) {
            return false;
        }

        if (strtolower($element->getAttribute('aria-hidden')) === 'true') {
            return false;
        }

        $style = preg_replace('/\\s+/', '', strtolower($element->getAttribute('style'))) ?? '';

        if (
            str_contains($style, 'display:none')
            || str_contains($style, 'visibility:hidden')
            || preg_match('/(?:^|;)opacity:0(?:!important)?(?:;|$)/', $style) === 1
        ) {
            return false;
        }

        return !($element->tagName === 'input' && strtolower($element->getAttribute('type')) === 'hidden');
    }

    private function renderHome(array $overrides = []): string
    {
        $view = new View(base_path('app/Views'));

        $data = [
            'app' => ['name' => 'OEMS'],
            'currentUser' => null,
            'flash' => [],
            'pageTitle' => 'Events worth showing up for',
            'featuredEvents' => [
                [
                    'title' => 'Designing for public life',
                    'slug' => 'designing-for-public-life',
                    'category' => 'Creative workshop',
                    'date' => 'August 22',
                    'datetime' => '2026-08-22T10:00:00+06:00',
                    'time' => '10:00 AM',
                    'venue' => 'Dhanmondi, Dhaka',
                    'price' => 'Free',
                    'image' => '/assets/images/event-creative.webp',
                    'alt' => 'A collaborative design workshop around a studio table',
                ],
            ],
        ];

        return $view->render('home/index', array_replace($data, $overrides), 'public');
    }

    private function inlineExecutableScripts(string $html): array
    {
        preg_match_all('/<script\b(?![^>]*\bsrc=)([^>]*)>/i', $html, $matches);

        return array_values(array_filter(
            $matches[1] ?? [],
            static fn (string $attributes): bool => preg_match(
                '/\btype=["\'](?:application\/ld\+json|application\/json)["\']/i',
                $attributes,
            ) !== 1,
        ));
    }
}
