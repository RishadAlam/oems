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

    public function testResponsiveToolbarBottomAlignsLabeledControlsAndUnlabeledActions(): void
    {
        $css = (string) file_get_contents(base_path('public/assets/css/app.css'));

        $this->assertTrue(
            preg_match(
                '/\.organizer-toolbar form\{(?=[^}]*flex-direction:row)(?=[^}]*align-items:flex-end)[^}]*\}/',
                $css,
            ) === 1,
            'Responsive toolbar controls and actions must share one lower edge.',
        );
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
        $this->assertTrue(str_contains($html, 'src="/assets/js/location.js?v=20260811-geolocation-secure"'));
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
        $this->assertTrue(str_contains($css, '.home-featured .event-card__footer {'));
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
