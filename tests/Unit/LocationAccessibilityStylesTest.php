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

            $this->assertSame(1, $matched, sprintf('Expected %s in the source stylesheet.', $selector));
            $this->assertFalse(
                str_contains((string) ($matches['rules'] ?? ''), 'focus-visible:outline-2'),
                sprintf('%s must not reduce the global 3px focus outline.', $selector),
            );
            $this->assertFalse(
                str_contains((string) ($matches['rules'] ?? ''), 'focus-visible:outline-offset-2'),
                sprintf('%s must not reduce the global 3px focus offset.', $selector),
            );
        }
    }
}
