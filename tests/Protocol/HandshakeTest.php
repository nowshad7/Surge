<?php

declare(strict_types=1);

namespace Nsd7\Surge\Tests\Protocol;

use Nsd7\Surge\Protocol\Handshake;
use Nsd7\Surge\Tests\TestCase;

final class HandshakeTest extends TestCase
{
    public function testComputesRfcAcceptValue(): void
    {
        // The canonical example from RFC 6455 §1.3.
        $this->assertSame(
            's3pPLMBiTxaQ9kYGzzhZRbK+xOo=',
            Handshake::accept('dGhlIHNhbXBsZSBub25jZQ=='),
        );
    }

    public function testParsesValidUpgradeRequest(): void
    {
        $handshake = new Handshake();
        $complete = $handshake->feed($this->request());

        $this->assertTrue($complete);
        $this->assertTrue($handshake->isValidUpgrade());
        $this->assertSame('GET', $handshake->method);
        $this->assertSame('/app/my-key', $handshake->path);
        $this->assertSame('protocol=7', $handshake->query);
    }

    public function testBuffersUntilHeaderBlockComplete(): void
    {
        $request = $this->request();
        $handshake = new Handshake();

        $this->assertFalse($handshake->feed(substr($request, 0, 20)));
        $this->assertTrue($handshake->feed(substr($request, 20)));
    }

    public function testResponseIsSwitchingProtocols(): void
    {
        $handshake = new Handshake();
        $handshake->feed($this->request());
        $response = $handshake->response();

        $this->assertTrue(str_starts_with($response, 'HTTP/1.1 101 Switching Protocols'));
        $this->assertTrue(str_contains($response, 'Sec-WebSocket-Accept: '));
        $this->assertTrue(str_contains($response, 'Upgrade: websocket'));
    }

    public function testRejectsNonWebSocketRequest(): void
    {
        $handshake = new Handshake();
        $handshake->feed("GET / HTTP/1.1\r\nHost: localhost\r\n\r\n");

        $this->assertTrue($handshake->isComplete());
        $this->assertFalse($handshake->isValidUpgrade());
    }

    private function request(): string
    {
        return "GET /app/my-key?protocol=7 HTTP/1.1\r\n"
            . "Host: localhost:9019\r\n"
            . "Upgrade: websocket\r\n"
            . "Connection: Upgrade\r\n"
            . "Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==\r\n"
            . "Sec-WebSocket-Version: 13\r\n\r\n";
    }
}
