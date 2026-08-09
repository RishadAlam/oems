<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Core\Request;
use OEMS\Tests\Support\TestCase;

final class RequestTest extends TestCase
{
    public function testUntrustedPeerCannotSpoofClientIpHeaders(): void
    {
        $request = Request::create(
            'GET',
            '/',
            headers: [
                'Forwarded' => 'for=198.51.100.20',
                'X-Forwarded-For' => '198.51.100.21',
            ],
            server: ['REMOTE_ADDR' => '203.0.113.9'],
            trustedProxies: ['10.0.0.0/8'],
        );

        $this->assertSame('203.0.113.9', $request->ip());
    }

    public function testTrustedProxyUsesFirstUntrustedClientInForwardedChain(): void
    {
        $request = Request::create(
            'GET',
            '/',
            headers: ['Forwarded' => 'for=198.51.100.20;proto=https, for=10.20.30.40'],
            server: ['REMOTE_ADDR' => '10.20.30.50'],
            trustedProxies: ['10.0.0.0/8'],
        );

        $this->assertSame('198.51.100.20', $request->ip());
    }

    public function testTrustedProxySupportsStrictXForwardedForMultiHopChain(): void
    {
        $request = Request::create(
            'GET',
            '/',
            headers: ['X-Forwarded-For' => '2001:db8::25, 192.0.2.44'],
            server: ['REMOTE_ADDR' => '192.0.2.45'],
            trustedProxies: ['192.0.2.0/24'],
        );

        $this->assertSame('2001:db8::25', $request->ip());
    }

    public function testMalformedForwardedChainFallsBackToTrustedPeerAddress(): void
    {
        foreach ([
            ['Forwarded' => 'for=198.51.100.20, for=_hidden'],
            ['X-Forwarded-For' => '198.51.100.20, not-an-ip'],
        ] as $headers) {
            $request = Request::create(
                'GET',
                '/',
                headers: $headers,
                server: ['REMOTE_ADDR' => '10.20.30.50'],
                trustedProxies: ['10.0.0.0/8'],
            );

            $this->assertSame('10.20.30.50', $request->ip());
        }
    }
}
