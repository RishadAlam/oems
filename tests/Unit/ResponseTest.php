<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Core\Response;
use OEMS\Tests\Support\TestCase;

final class ResponseTest extends TestCase
{
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
}
