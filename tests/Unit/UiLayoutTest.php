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

    private function renderHome(): string
    {
        $view = new View(base_path('app/Views'));

        return $view->render('home/index', [
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
        ], 'public');
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
