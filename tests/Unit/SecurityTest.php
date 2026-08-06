<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Tests\Support\TestCase;

final class SecurityTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testGeneratedCsrfTokenValidates(): void
    {
        $security = new Security(new Session(false));

        $token = $security->csrfToken();

        $this->assertTrue($security->verifyCsrf($token));
        $this->assertSame($token, $security->csrfToken());
    }

    public function testDifferentCsrfTokenIsRejected(): void
    {
        $security = new Security(new Session(false));
        $security->csrfToken();

        $this->assertFalse($security->verifyCsrf(str_repeat('a', 64)));
        $this->assertFalse($security->verifyCsrf(null));
    }
}

