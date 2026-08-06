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
