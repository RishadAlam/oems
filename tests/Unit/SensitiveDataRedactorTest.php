<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Core\SensitiveDataRedactor;
use OEMS\Tests\Support\TestCase;

final class SensitiveDataRedactorTest extends TestCase
{
    public function testRedactsAccountTokensFromSensitivePaths(): void
    {
        $this->assertSame(
            '/verify-email/[redacted]',
            SensitiveDataRedactor::requestPath('/verify-email/' . str_repeat('a', 64)),
        );
        $this->assertSame(
            '/reset-password/[redacted]',
            SensitiveDataRedactor::requestPath('/reset-password/' . str_repeat('b', 64)),
        );
    }

    public function testLeavesOrdinaryPathsUnchanged(): void
    {
        $this->assertSame(
            '/events/dhaka-design-week',
            SensitiveDataRedactor::requestPath('/events/dhaka-design-week'),
        );
    }
}
