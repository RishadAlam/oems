<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Support\StreamHttpClient;
use OEMS\Tests\Support\TestCase;
use RuntimeException;

final class StreamHttpClientTest extends TestCase
{
    public function testRejectsNonHttpStreamSchemesBeforeReadingLocalFiles(): void
    {
        $path = sys_get_temp_dir() . '/oems-http-client-secret-' . bin2hex(random_bytes(6));
        file_put_contents($path, 'local-only-secret');
        $message = null;

        try {
            (new StreamHttpClient('OEMS Test/1.0'))->get('file://' . $path, [], 2);
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();
        } finally {
            unlink($path);
        }

        $this->assertSame('Only HTTP and HTTPS URLs are supported.', $message);
    }

    public function testRedirectResponseIsNotFollowedAndKeepsItsOwnStatusAndBody(): void
    {
        $this->withServer(function (int $port): void {
            $response = (new StreamHttpClient('OEMS Test/1.0'))->get("http://127.0.0.1:{$port}/redirect", [], 2);

            $this->assertSame(302, $response['status']);
            $this->assertSame('redirect response', $response['body']);
        });
    }

    public function testResponseBodyCannotExceedConfiguredByteCeiling(): void
    {
        $this->withServer(function (int $port): void {
            try {
                (new StreamHttpClient('OEMS Test/1.0', 32))->get("http://127.0.0.1:{$port}/oversized", [], 2);
                $this->assertTrue(false, 'Oversized responses must be rejected.');
            } catch (RuntimeException $exception) {
                $this->assertSame('HTTP response exceeded the allowed size.', $exception->getMessage());
            }
        });
    }

    public function testTimeoutIsAWholeRequestDeadlineEvenWhenBytesKeepArriving(): void
    {
        $this->withServer(function (int $port): void {
            $startedAt = microtime(true);

            try {
                (new StreamHttpClient('OEMS Test/1.0'))->get("http://127.0.0.1:{$port}/slow-trickle", [], 1);
                $this->assertTrue(false, 'A trickling response must not extend the whole-request deadline.');
            } catch (RuntimeException $exception) {
                $this->assertSame('HTTP request timed out.', $exception->getMessage());
                $this->assertTrue(microtime(true) - $startedAt < 1.4, 'Deadline must stop the request promptly.');
            }
        });
    }

    private function withServer(\Closure $callback): void
    {
        $port = $this->unusedPort();
        $process = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:{$port}", base_path('tests/Fixtures/geocoding-http-router.php')],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
        );
        $this->assertTrue(is_resource($process), 'The local HTTP fixture must start.');

        try {
            $this->waitForServer($port);
            $callback($port);
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

        $this->assertTrue(false, 'The local HTTP fixture did not become reachable.');
    }
}
