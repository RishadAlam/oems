<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Tests\Support\TestCase;

final class LocationAccessibilityStylesTest extends TestCase
{
    public function testLocationControlsRetainTheGlobalThreePixelFocusIndicator(): void
    {
        $stylesheet = file_get_contents(base_path('resources/css/app.css'));

        $this->assertTrue(is_string($stylesheet));
        $this->assertTrue(str_contains($stylesheet, 'outline: 3px solid var(--accent);'));
        $this->assertTrue(str_contains($stylesheet, 'outline-offset: 3px;'));

        foreach ([
            '.venue-search-result',
            '.venue-coordinate-details summary',
            '.event-view-switch button',
        ] as $selector) {
            $pattern = '/'.preg_quote($selector, '/').'\s*\{(?<rules>[^}]*)\}/';
            $matched = preg_match($pattern, $stylesheet, $matches);
            $rules = (string) ($matches['rules'] ?? '');

            $this->assertSame(1, $matched, sprintf('Expected %s in the source stylesheet.', $selector));
            $this->assertRetainsGlobalFocusIndicator($selector, $rules);
        }
    }

    public function testDiscoveryMapStylesScopeTheResponsiveViewContract(): void
    {
        $stylesheet = file_get_contents(base_path('resources/css/app.css'));

        $this->assertTrue(is_string($stylesheet));
        $this->assertTrue(str_contains($stylesheet, '.event-discovery-layout[data-event-discovery-view="map"]'));
        $this->assertFalse(str_contains($stylesheet, '.event-discovery-layout:not(.event-discovery-layout--empty) {'));
        $this->assertTrue(str_contains($stylesheet, '@media (min-width: 1024px)'));
        $this->assertTrue(str_contains($stylesheet, '.event-view-control'));
    }

    public function testEventViewControlRetainsTheFortyFourPixelTargetAndGlobalFocusIndicator(): void
    {
        $stylesheet = file_get_contents(base_path('resources/css/app.css'));

        $this->assertTrue(is_string($stylesheet));
        $pattern = '/'.preg_quote('.event-view-control', '/').'\s*\{(?<rules>[^}]*)\}/';
        $matched = preg_match($pattern, $stylesheet, $matches);
        $rules = (string) ($matches['rules'] ?? '');

        $this->assertSame(1, $matched, 'Expected .event-view-control in the source stylesheet.');
        $this->assertTrue(
            str_contains($rules, 'min-h-11'),
            '.event-view-control must retain the 44px minimum target size.',
        );
        $this->assertRetainsGlobalFocusIndicator('.event-view-control', $rules);
    }

    private function assertRetainsGlobalFocusIndicator(string $selector, string $rules): void
    {
        $this->assertFalse(
            preg_match('/(?:^|[;\n])\s*outline(?:-width|-offset)?\s*:/', $rules) === 1,
            sprintf('%s must not override the global 3px focus width or offset.', $selector),
        );
        $this->assertFalse(
            preg_match(
                '/focus-visible:outline(?!(?:-\[var\(--accent\)\])(?=[\s;]|$))(?:-[^\s;]+)?/',
                $rules,
            ) === 1,
            sprintf('%s must not apply a focus-visible outline width or offset utility.', $selector),
        );
    }
}
