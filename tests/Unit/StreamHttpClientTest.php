<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Support\StreamHttpClient;
use OEMS\Tests\Support\TestCase;

final class StreamHttpClientTest extends TestCase
{
    public function testRedirectResponseIsNotFollowedAndKeepsItsOwnStatusAndBody(): void
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
            $response = (new StreamHttpClient('OEMS Test/1.0'))->get("http://127.0.0.1:{$port}/redirect", [], 2);

            $this->assertSame(302, $response['status']);
            $this->assertSame('redirect response', $response['body']);
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
