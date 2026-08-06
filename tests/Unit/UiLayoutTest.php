<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Core\View;
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

    public function testMobileNavigationStartsCollapsedAndExposesItsControlState(): void
    {
        $html = $this->renderHome();

        $this->assertTrue(str_contains($html, 'data-menu-toggle'));
        $this->assertTrue(str_contains($html, 'aria-expanded="false"'));
        $this->assertTrue(str_contains($html, 'aria-controls="mobile-menu"'));
        $this->assertTrue(str_contains($html, 'id="mobile-menu"'));
    }

    public function testThemeControlsExposeAnIconStateHookAndAccessibleLabel(): void
    {
        $html = $this->renderHome();

        $this->assertTrue(str_contains($html, 'data-theme-toggle'));
        $this->assertTrue(str_contains($html, 'aria-label="Switch to dark theme"'));
        $this->assertTrue(str_contains($html, 'data-theme-icon'));
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
}
