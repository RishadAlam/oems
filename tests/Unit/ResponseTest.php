<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use InvalidArgumentException;
use OEMS\Core\Response;
use OEMS\Tests\Support\TestCase;

final class ResponseTest extends TestCase
{
    public function testWithHeaderReturnsNewResponseWithoutDroppingExistingHeaders(): void
    {
        $response = Response::html('ok')->withHeader('Permissions-Policy', 'geolocation=(self)');

        $this->assertSame('text/html; charset=UTF-8', $response->header('Content-Type'));
        $this->assertSame('geolocation=(self)', $response->header('Permissions-Policy'));
        $this->assertSame('ok', $response->body());
    }

    public function testResponseFactoriesRejectHeaderValueInjection(): void
    {
        $message = null;

        try {
            Response::redirect("/dashboard\r\nX-Injected: true");
        } catch (InvalidArgumentException $exception) {
            $message = $exception->getMessage();
        }

        $this->assertSame('Invalid response header.', $message);
    }

    public function testResponseFactoriesRejectNonPrintingHeaderControls(): void
    {
        $message = null;

        try {
            Response::text('ok', headers: ['X-Trace' => "safe\0hidden"]);
        } catch (InvalidArgumentException $exception) {
            $message = $exception->getMessage();
        }

        $this->assertSame('Invalid response header.', $message);
    }

    public function testPublicConstructorCannotBypassHeaderValidation(): void
    {
        $message = null;

        try {
            new Response('ok', 200, ['X-Trace' => "safe\r\nX-Injected: true"]);
        } catch (InvalidArgumentException $exception) {
            $message = $exception->getMessage();
        }

        $this->assertSame('Invalid response header.', $message);
    }

    public function testSecurityHeadersHardenHtmlWithoutBlockingLocalAssetsOrHttpsMapTiles(): void
    {
        $response = Response::html('ok')->withSecurityHeaders();

        $this->assertSame('nosniff', $response->header('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->header('X-Frame-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $response->header('Referrer-Policy'));
        $this->assertSame('geolocation=(self)', $response->header('Permissions-Policy'));
        $this->assertTrue(str_contains(
            (string) $response->header('Content-Security-Policy'),
            "img-src 'self' data: https:",
        ));
        $this->assertTrue(str_contains(
            (string) $response->header('Content-Security-Policy'),
            "frame-ancestors 'none'",
        ));
    }

    public function testSecurityHeadersDoNotPermitInlineExecutableScripts(): void
    {
        $policy = (string) Response::html('ok')->withSecurityHeaders()->header('Content-Security-Policy');

        $this->assertTrue(str_contains($policy, "script-src 'self'"));
        $this->assertFalse(str_contains($policy, "script-src 'self' 'unsafe-inline'"));
    }

    public function testBinaryResponsePreservesBytesAndUsesOnlySuppliedSafeHeaders(): void
    {
        $this->assertTrue(method_exists(Response::class, 'binary'), 'Binary responses are not implemented.');

        $response = Response::binary("\x00\x89PNG\r\n", 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="ticket.png"',
            'X-Content-Type-Options' => 'nosniff',
        ]);

        $this->assertSame("\x00\x89PNG\r\n", $response->body());
        $this->assertSame(200, $response->status());
        $this->assertSame('image/png', $response->header('content-type'));
        $this->assertSame('inline; filename="ticket.png"', $response->header('Content-Disposition'));
        $this->assertSame('nosniff', $response->header('X-Content-Type-Options'));
    }

    public function testFileResponseKeepsArtifactOutOfBodyAndStreamsEveryByteWithLength(): void
    {
        $path = sys_get_temp_dir() . '/oems-response-stream-' . bin2hex(random_bytes(6));
        $bytes = str_repeat('bounded-stream-chunk-', 8192);
        file_put_contents($path, $bytes);

        try {
            $this->assertTrue(method_exists(Response::class, 'file'), 'File responses are not implemented.');
            $response = Response::file($path, 200, [
                'Content-Type' => 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
            ]);

            $this->assertSame('', $response->body());
            $this->assertSame((string) strlen($bytes), $response->header('Content-Length'));
            ob_start();
            $response->send();
            $streamed = ob_get_clean();

            $this->assertSame($bytes, $streamed);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testStreamingResponseDefersChunkProductionUntilSendAndKeepsBodyEmpty(): void
    {
        $produced = false;
        $response = Response::stream(static function (callable $emit) use (&$produced): void {
            $produced = true;
            $emit('first-');
            $emit('second');
        }, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);

        $this->assertFalse($produced);
        $this->assertSame('', $response->body());
        ob_start();
        $response->send();
        $streamed = ob_get_clean();

        $this->assertTrue($produced);
        $this->assertSame('first-second', $streamed);
        $this->assertSame('text/plain; charset=UTF-8', $response->header('Content-Type'));
    }

    public function testSendingResponseKeepsNativeSessionCookieAlongsideApplicationCookie(): void
    {
        $port = $this->unusedPort();
        $process = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:{$port}", base_path('tests/Fixtures/response-cookie-router.php')],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
        );
        $this->assertTrue(is_resource($process), 'The response cookie fixture must start.');

        try {
            $this->waitForServer($port);
            $context = stream_context_create(['http' => ['ignore_errors' => true]]);
            file_get_contents("http://127.0.0.1:{$port}/", false, $context);
            $cookieHeaders = array_values(array_filter(
                $http_response_header ?? [],
                static fn (string $header): bool => str_starts_with(strtolower($header), 'set-cookie:'),
            ));

            $this->assertSame(2, count($cookieHeaders));
            $this->assertTrue($this->headersContain($cookieHeaders, 'OEMS_RESPONSE_COOKIE_TEST='));
            $this->assertTrue($this->headersContain($cookieHeaders, 'OEMS_REMEMBER=rotated'));
        } finally {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }

            proc_terminate($process);
            proc_close($process);
        }
    }

    private function headersContain(array $headers, string $needle): bool
    {
        foreach ($headers as $header) {
            if (str_contains($header, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function unusedPort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertTrue(is_resource($socket));
        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr((string) $name, strrpos((string) $name, ':') + 1);
    }

    private function waitForServer(int $port): void
    {
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $connection = @fsockopen('127.0.0.1', $port);

            if (is_resource($connection)) {
                fclose($connection);

                return;
            }

            usleep(20_000);
        }

        $this->assertTrue(false, 'The response cookie fixture did not become reachable.');
    }
}
