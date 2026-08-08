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
}
