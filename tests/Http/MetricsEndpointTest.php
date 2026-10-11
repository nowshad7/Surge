<?php

declare(strict_types=1);

namespace Nsd7\Surge\Tests\Http;

use Nsd7\Surge\Http\PusherHttpApi;
use Nsd7\Surge\Http\Request;
use Nsd7\Surge\Pusher\PusherProtocol;
use Nsd7\Surge\Pusher\Signature;
use Nsd7\Surge\Tests\TestCase;
use Nsd7\Surge\WebSocket\Connection;

final class MetricsEndpointTest extends TestCase
{
    public function testMetricsReflectLiveActivity(): void
    {
        $protocol = new PusherProtocol(new Signature('k', 's'));
        $api = new PusherHttpApi(new Signature('k', 's'), $protocol, '123');

        // One connection subscribes to a public channel, then an event is published.
        $conn = new Connection(1);
        $conn->ingest($this->handshake(), $protocol);
        $conn->ingest($this->clientFrame(json_encode(['event' => 'pusher:subscribe', 'data' => ['channel' => 'news']])), $protocol);
        $protocol->publish('news', 'Headline', ['x' => 1]);

        $response = $api->handle(new Request('GET', '/metrics', '', [], ''));

        $this->assertSame(200, $response->status);
        $this->assertTrue(str_contains($response->contentType, 'text/plain'));
        $this->assertTrue(str_contains($response->body, "surge_connections_total 1\n"));
        $this->assertTrue(str_contains($response->body, "surge_connections_current 1\n"));
        $this->assertTrue(str_contains($response->body, "surge_channels_current 1\n"));
        $this->assertTrue(str_contains($response->body, "surge_events_published_total 1\n"));
        $this->assertTrue(str_contains($response->body, "surge_messages_sent_total 1\n"));
    }

    public function testDisconnectDecrementsGauge(): void
    {
        $protocol = new PusherProtocol(new Signature('k', 's'));

        $conn = new Connection(1);
        $conn->ingest($this->handshake(), $protocol);
        $this->assertSame(1, $protocol->metrics()->snapshot()['surge_connections_current']);

        $protocol->onClose($conn, 1000, 'bye');
        $this->assertSame(0, $protocol->metrics()->snapshot()['surge_connections_current']);
        // The total counter does not decrease.
        $this->assertSame(1, $protocol->metrics()->snapshot()['surge_connections_total']);
    }

    private function handshake(): string
    {
        return "GET /app/k HTTP/1.1\r\n"
            . "Host: localhost\r\n"
            . "Upgrade: websocket\r\n"
            . "Connection: Upgrade\r\n"
            . "Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==\r\n"
            . "Sec-WebSocket-Version: 13\r\n\r\n";
    }
}
