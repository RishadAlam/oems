<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Support\RememberCookie;
use OEMS\Tests\Support\TestCase;

final class RememberCookieTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['HTTPS']);
    }

    public function testDirectHttpsForcesSecureWhenConfigurationIsFalse(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $cookie = new RememberCookie('OEMS_REMEMBER', false);

        $header = $cookie->header('selector:validator', time() + 3600);

        $this->assertTrue(str_contains($header, '; Secure'));
        $this->assertTrue(str_contains($header, '; HttpOnly'));
        $this->assertTrue(str_contains($header, '; SameSite=Lax'));
    }

    public function testConsumptionResultSerializesReplacementOrExpiry(): void
    {
        $cookie = new RememberCookie('OEMS_REMEMBER', false);
        $replacement = $cookie->forConsumptionResult([
            'authenticated' => true,
            'remember_cookie' => 'replacement-selector:replacement-validator',
            'expires_at' => time() + 3600,
            'forget_cookie' => false,
        ]);
        $expired = $cookie->forConsumptionResult([
            'authenticated' => false,
            'remember_cookie' => null,
            'expires_at' => null,
            'forget_cookie' => true,
        ]);

        $this->assertTrue(is_string($replacement));
        $this->assertTrue(str_contains($replacement, 'replacement-selector%3Areplacement-validator'));
        $this->assertTrue(is_string($expired));
        $this->assertTrue(str_contains($expired, 'Max-Age=0'));
    }
}
