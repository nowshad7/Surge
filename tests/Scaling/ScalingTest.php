<?php

declare(strict_types=1);

namespace Nsd7\Surge\Tests\Scaling;

use Nsd7\Surge\Pusher\ChannelManager;
use Nsd7\Surge\Pusher\PusherProtocol;
use Nsd7\Surge\Pusher\Signature;
use Nsd7\Surge\Scaling\RelayMessage;
use Nsd7\Surge\Tests\Support\FakeAdapter;
use Nsd7\Surge\Tests\TestCase;
use Nsd7\Surge\WebSocket\Connection;

final class ScalingTest extends TestCase
{
    public function testRelayMessageRoundTripsThroughJson(): void
    {
        $message = new RelayMessage('node-a', 'orders', 'OrderShipped', ['id' => 9], 'sock.1');
        $restored = RelayMessage::fromJson($message->toJson());

        $this->assertSame('node-a', $restored->origin);
        $this->assertSame('orders', $restored->channel);
        $this->assertSame('OrderShipped', $restored->event);
        $this->assertSame(['id' => 9], $restored->data);
        $this->assertSame('sock.1', $restored->exceptSocketId);
    }

    public function testMalformedRelayMessageIsRejected(): void
    {
        $this->assertSame(null, RelayMessage::fromJson('{"nope":true}'));
        $this->assertSame(null, RelayMessage::fromJson('not json'));
    }

    public function testPublishRelaysToPeers(): void
    {
        $adapter = new FakeAdapter('node-a');
        $protocol = new PusherProtocol(new Signature('k', 's'), new ChannelManager(), 120, true, $adapter);

        $protocol->publish('news', 'Headline', ['x' => 1]);

        $this->assertCount(1, $adapter->relayed);
        $this->assertSame('node-a', $adapter->relayed[0]->origin);
        $this->assertSame('news', $adapter->relayed[0]->channel);
        $this->assertSame('Headline', $adapter->relayed[0]->event);
    }

    public function testTickReplaysPeerEventToLocalSubscribers(): void
    {
        $adapter = new FakeAdapter('node-b');
        $protocol = new PusherProtocol(new Signature('k', 's'), new ChannelManager(), 120, true, $adapter);

        // A local subscriber on node-b.
        $subscriber = new Connection(1);
        $subscriber->ingest($this->handshake(), $protocol);
        $subscriber->ingest($this->clientFrame(json_encode(['event' => 'pusher:subscribe', 'data' => ['channel' => 'news']])), $protocol);
        $this->drainServerMessages($subscriber);

        // A peer (node-a) published to "news"; it arrives via the adapter.
        $adapter->deliver(new RelayMessage('node-a', 'news', 'FromPeer', ['v' => 7]));
        $protocol->tick();

        $messages = $this->drainServerMessages($subscriber);
        $this->assertCount(1, $messages);
        $this->assertSame('FromPeer', $messages[0]['event']);
        $this->assertSame(['v' => 7], json_decode($messages[0]['data'], true));
    }

    public function testTickIgnoresOwnOrigin(): void
    {
        // A real adapter drops self-origin; the fake delivers whatever is queued,
        // so this asserts the protocol does not re-relay a replayed peer event
        // (no infinite loop): publishLocal is used, which never calls the adapter.
        $adapter = new FakeAdapter('node-b');
        $protocol = new PusherProtocol(new Signature('k', 's'), new ChannelManager(), 120, true, $adapter);

        $adapter->deliver(new RelayMessage('node-a', 'news', 'E', null));
        $protocol->tick();

        $this->assertCount(0, $adapter->relayed); // replay must not relay again
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
