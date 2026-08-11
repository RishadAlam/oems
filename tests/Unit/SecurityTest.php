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

    public function testDirectHttpsForcesTheSessionCookieSecureWhenConfigurationIsFalse(): void
    {
        $code = sprintf(
            <<<'PHP'
require %s;
$_SERVER['HTTPS'] = 'on';
new OEMS\Core\Session(true, ['name' => 'OEMS_HTTPS_TEST', 'secure' => false]);
echo json_encode(session_get_cookie_params(), JSON_THROW_ON_ERROR);
session_destroy();
PHP,
            var_export(base_path('vendor/autoload.php'), true),
        );
        [$status, $output, $error] = $this->runPhpProcess($code);
        $parameters = json_decode($output, true);

        $this->assertSame(0, $status, $error);
        $this->assertTrue(is_array($parameters));
        $this->assertTrue($parameters['secure'] ?? false);
    }

    public function testHttpSessionCookieCannotCollideWithTheSecureSessionCookie(): void
    {
        $httpCode = sprintf(
            <<<'PHP'
require %s;
unset($_SERVER['HTTPS']);
new OEMS\Core\Session(true, ['name' => 'OEMS_SCHEME_TEST', 'secure' => false]);
echo json_encode(['name' => session_name(), 'parameters' => session_get_cookie_params()], JSON_THROW_ON_ERROR);
session_destroy();
PHP,
            var_export(base_path('vendor/autoload.php'), true),
        );
        $httpsCode = sprintf(
            <<<'PHP'
require %s;
$_SERVER['HTTPS'] = 'on';
new OEMS\Core\Session(true, ['name' => 'OEMS_SCHEME_TEST', 'secure' => false]);
echo json_encode(['name' => session_name(), 'parameters' => session_get_cookie_params()], JSON_THROW_ON_ERROR);
session_destroy();
PHP,
            var_export(base_path('vendor/autoload.php'), true),
        );

        [$httpStatus, $httpOutput, $httpError] = $this->runPhpProcess($httpCode);
        [$httpsStatus, $httpsOutput, $httpsError] = $this->runPhpProcess($httpsCode);
        $http = json_decode($httpOutput, true);
        $https = json_decode($httpsOutput, true);

        $this->assertSame(0, $httpStatus, $httpError);
        $this->assertSame(0, $httpsStatus, $httpsError);
        $this->assertTrue(is_array($http));
        $this->assertTrue(is_array($https));
        $this->assertSame('OEMS_SCHEME_TEST_HTTP', $http['name'] ?? null);
        $this->assertSame('OEMS_SCHEME_TEST', $https['name'] ?? null);
        $this->assertFalse($http['parameters']['secure'] ?? true);
        $this->assertTrue($https['parameters']['secure'] ?? false);
    }

    public function testAuthenticatedSessionRegenerationChangesTheLiveIdentifier(): void
    {
        $code = sprintf(
            <<<'PHP'
require %s;
$session = new OEMS\Core\Session(true, ['name' => 'OEMS_ROTATION_TEST']);
$before = session_id();
$rotated = $session->regenerate();
$after = session_id();
echo json_encode(['before' => $before, 'after' => $after, 'rotated' => $rotated], JSON_THROW_ON_ERROR);
session_destroy();
PHP,
            var_export(base_path('vendor/autoload.php'), true),
        );
        [$status, $output, $error] = $this->runPhpProcess($code);
        $result = json_decode($output, true);

        $this->assertSame(0, $status, $error);
        $this->assertTrue(is_array($result));
        $this->assertTrue($result['rotated'] ?? false);
        $this->assertTrue(is_string($result['before'] ?? null) && $result['before'] !== '');
        $this->assertTrue(is_string($result['after'] ?? null) && $result['after'] !== '');
        $this->assertNotSame($result['before'], $result['after']);
    }

    /** @return array{int, string, string} */
    private function runPhpProcess(string $code): array
    {
        $process = proc_open(
            [PHP_BINARY, '-r', $code],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
        );
        $this->assertTrue(is_resource($process));
        fclose($pipes[0]);
        $output = trim((string) stream_get_contents($pipes[1]));
        $error = trim((string) stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output, $error];
    }
}
