<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Support\Money;
use OEMS\Tests\Support\TestCase;

final class MoneyTest extends TestCase
{
    public function testFreeDecisionsUseExactMinorUnitsAndRejectImpreciseInputs(): void
    {
        $this->assertTrue(Money::isFree('0'));
        $this->assertTrue(Money::isFree('0.00'));
        $this->assertTrue(Money::isFree(0));
        $this->assertFalse(Money::isFree('0.01'));
        $this->assertFalse(Money::isFree('0.001'));
        $this->assertFalse(Money::isFree(0.0));
    }

    public function testNormalizationAndPresentationNeverRoundThroughBinaryFloatingPoint(): void
    {
        $this->assertSame('1250.50', Money::normalize('0001250.5'));
        $this->assertSame('900719925474.01', Money::normalize('900719925474.01'));
        $this->assertSame('৳900,719,925,474.01', Money::format('900719925474.01', 'BDT'));
        $this->assertSame('$1,250', Money::format('1250.00', 'USD'));
        $this->assertSame('1,250.50 EUR', Money::format('1250.50', 'EUR'));
        $this->assertNull(Money::normalize('-1.00'));
    }
}
