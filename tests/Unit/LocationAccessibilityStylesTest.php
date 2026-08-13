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
        ] as $selector) {
            $pattern = '/'.preg_quote($selector, '/').'\s*\{(?<rules>[^}]*)\}/';
            $matched = preg_match($pattern, $stylesheet, $matches);
            $rules = (string) ($matches['rules'] ?? '');

            $this->assertSame(1, $matched, sprintf('Expected %s in the source stylesheet.', $selector));
            $this->assertRetainsGlobalFocusIndicator($selector, $rules);
        }

        $this->assertAllEventViewRulesRetainGlobalFocusIndicator($stylesheet, '.event-view-switch button');
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
        $this->assertAllEventViewRulesRetainGlobalFocusIndicator($stylesheet, '.event-view-control');
    }

    private function assertAllEventViewRulesRetainGlobalFocusIndicator(string $stylesheet, string $selector): void
    {
        $matchingRules = $this->sourceRuleBlocksContaining($stylesheet, $selector);

        $this->assertFalse($matchingRules === [], sprintf('Expected source rules containing %s.', $selector));
        foreach ($matchingRules as $matchingRule) {
            $this->assertRetainsGlobalFocusIndicator($matchingRule['selector'], $matchingRule['rules']);
        }
    }

    /** @return list<array{selector: string, rules: string}> */
    private function sourceRuleBlocksContaining(string $stylesheet, string $selector): array
    {
        preg_match_all('/(?<selector>[^{}]+)\{(?<rules>[^{}]*)\}/', $stylesheet, $matches, PREG_SET_ORDER);

        return array_values(array_filter(array_map(
            static fn (array $match): array => [
                'selector' => trim((string) ($match['selector'] ?? '')),
                'rules' => (string) ($match['rules'] ?? ''),
            ],
            $matches,
        ), static fn (array $match): bool => str_contains($match['selector'], $selector)));
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
        $this->assertFalse(
            preg_match('/focus-visible:\[outline(?:-width|-offset)?:[^\]]*\]/', $rules) === 1,
            sprintf('%s must not apply an arbitrary focus-visible outline width or offset property.', $selector),
        );
    }
}
