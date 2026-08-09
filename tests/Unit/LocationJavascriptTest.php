<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Tests\Support\TestCase;

final class LocationJavascriptTest extends TestCase
{
    public function testLocationProgressiveEnhancementCoversPrivacyStateAndCleanup(): void
    {
        $output = [];
        $exitCode = 1;
        exec('node ' . escapeshellarg(base_path('tests/js/location.test.mjs')) . ' 2>&1', $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
    }
}
