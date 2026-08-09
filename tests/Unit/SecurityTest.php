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

    public function testSessionStartRejectsAnUninitializedAttackerSuppliedIdentifier(): void
    {
        $identifier = 'attackerfixedsessionid123456';
        $code = sprintf(
            <<<'PHP'
require %s;
ini_set('session.use_cookies', '1');
ini_set('session.use_strict_mode', '0');
session_id(%s);
new OEMS\Core\Session(true, ['name' => 'OEMS_SECURITY_TEST']);
$actual = session_id();
session_destroy();
echo $actual;
PHP,
            var_export(base_path('vendor/autoload.php'), true),
            var_export($identifier, true),
        );
        $process = proc_open(
            [PHP_BINARY, '-r', $code],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
        );
        $this->assertTrue(is_resource($process));
        fclose($pipes[0]);
        $actual = trim((string) stream_get_contents($pipes[1]));
        $error = trim((string) stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);

        $this->assertSame(0, $status, $error);
        $this->assertTrue($actual !== '', 'Session start did not produce an identifier.');
        $this->assertNotSame($identifier, $actual);
    }
}
