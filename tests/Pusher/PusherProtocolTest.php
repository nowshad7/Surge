<?php

declare(strict_types=1);

namespace Nsd7\Surge\Tests\Pusher;

use Nsd7\Surge\Pusher\PusherProtocol;
use Nsd7\Surge\Pusher\Signature;
use Nsd7\Surge\Tests\TestCase;
use Nsd7\Surge\WebSocket\Connection;

final class PusherProtocolTest extends TestCase
{
    private function protocol(): PusherProtocol
    {
        return new PusherProtocol(new Signature('app-key', 'app-secret'));
    }

    /** Open a connection against the protocol and return it (connection_established drained). */
    private function open(PusherProtocol $protocol, int $id): Connection
    {
        $connection = new Connection($id);
        $connection->ingest($this->handshake(), $protocol);
        return $connection;
    }

    private function socketId(Connection $connection): string
    {
        return (string) $connection->attributes['socket_id'];
    }

    public function testSendsConnectionEstablishedOnOpen(): void
    {
        $protocol = $this->protocol();
        $connection = new Connection(1);
        $connection->ingest($this->handshake(), $protocol);

        $messages = $this->drainServerMessages($connection);
        $this->assertCount(1, $messages);
        $this->assertSame('pusher:connection_established', $messages[0]['event']);

        $data = json_decode($messages[0]['data'], true);
        $this->assertTrue(isset($data['socket_id']));
        $this->assertSame(120, $data['activity_timeout']);
    }

    public function testSubscribesToPublicChannel(): void
    {
        $protocol = $this->protocol();
        $connection = $this->open($protocol, 1);
        $this->drainServerMessages($connection);

        $connection->ingest($this->frame(['event' => 'pusher:subscribe', 'data' => ['channel' => 'news']]), $protocol);

        $messages = $this->drainServerMessages($connection);
        $this->assertCount(1, $messages);
        $this->assertSame('pusher_internal:subscription_succeeded', $messages[0]['event']);
        $this->assertSame('news', $messages[0]['channel']);
    }

    public function testPublishReachesAllSubscribers(): void
    {
        $protocol = $this->protocol();
        $a = $this->open($protocol, 1);
        $b = $this->open($protocol, 2);

        foreach ([$a, $b] as $conn) {
            $conn->ingest($this->frame(['event' => 'pusher:subscribe', 'data' => ['channel' => 'orders']]), $protocol);
        }
        $this->drainServerMessages($a);
        $this->drainServerMessages($b);

        $sent = $protocol->publish('orders', 'OrderShipped', ['id' => 42]);
        $this->assertSame(2, $sent);

        foreach ([$a, $b] as $conn) {
            $messages = $this->drainServerMessages($conn);
            $this->assertCount(1, $messages);
            $this->assertSame('OrderShipped', $messages[0]['event']);
            $this->assertSame('orders', $messages[0]['channel']);
            $this->assertSame(['id' => 42], json_decode($messages[0]['data'], true));
        }
    }

    public function testPublishCanExcludeOneSocket(): void
    {
        $protocol = $this->protocol();
        $a = $this->open($protocol, 1);
        $b = $this->open($protocol, 2);
        foreach ([$a, $b] as $conn) {
            $conn->ingest($this->frame(['event' => 'pusher:subscribe', 'data' => ['channel' => 'c']]), $protocol);
        }
        $this->drainServerMessages($a);
        $this->drainServerMessages($b);

        $sent = $protocol->publish('c', 'Evt', [], exceptSocketId: $this->socketId($a));

        $this->assertSame(1, $sent);
        $this->assertCount(0, $this->drainServerMessages($a)); // excluded
        $this->assertCount(1, $this->drainServerMessages($b));
    }

    public function testPrivateChannelRejectsBadSignature(): void
    {
        $protocol = $this->protocol();
        $connection = $this->open($protocol, 1);
        $this->drainServerMessages($connection);

        $connection->ingest($this->frame([
            'event' => 'pusher:subscribe',
            'data'  => ['channel' => 'private-room', 'auth' => 'app-key:deadbeef'],
        ]), $protocol);

        $messages = $this->drainServerMessages($connection);
        $this->assertSame('pusher:subscription_error', $messages[0]['event']);
    }

    public function testPrivateChannelAcceptsValidSignature(): void
    {
        $protocol = $this->protocol();
        $signature = new Signature('app-key', 'app-secret');
        $connection = $this->open($protocol, 1);
        $this->drainServerMessages($connection);

        $socketId = $this->socketId($connection);
        $auth = $signature->channelToken($socketId, 'private-room');

        $connection->ingest($this->frame([
            'event' => 'pusher:subscribe',
            'data'  => ['channel' => 'private-room', 'auth' => $auth],
        ]), $protocol);

        $messages = $this->drainServerMessages($connection);
        $this->assertSame('pusher_internal:subscription_succeeded', $messages[0]['event']);
    }

    public function testPresenceEmitsMemberAddedAndRemoved(): void
    {
        $protocol = $this->protocol();
        $signature = new Signature('app-key', 'app-secret');

        $a = $this->open($protocol, 1);
        $b = $this->open($protocol, 2);
        $this->drainServerMessages($a);
        $this->drainServerMessages($b);

        $subscribe = function (Connection $conn, string $uid, string $name) use ($protocol, $signature) {
            $channelData = json_encode(['user_id' => $uid, 'user_info' => ['name' => $name]]);
            $auth = $signature->channelToken($this->socketId($conn), 'presence-lobby', $channelData);
            $conn->ingest($this->frame([
                'event' => 'pusher:subscribe',
                'data'  => ['channel' => 'presence-lobby', 'auth' => $auth, 'channel_data' => $channelData],
            ]), $protocol);
        };

        $subscribe($a, '1', 'Ann');
        $this->drainServerMessages($a);

        $subscribe($b, '2', 'Bob');
        // B's own subscription_succeeded carries the full roster.
        $bMessages = $this->drainServerMessages($b);
        $this->assertSame('pusher_internal:subscription_succeeded', $bMessages[0]['event']);
        $roster = json_decode($bMessages[0]['data'], true);
        $this->assertSame(2, $roster['presence']['count']);

        // A is told Bob joined.
        $aMessages = $this->drainServerMessages($a);
        $this->assertSame('pusher_internal:member_added', $aMessages[0]['event']);
        $this->assertSame('2', json_decode($aMessages[0]['data'], true)['user_id']);

        // B leaves -> A is told Bob left.
        $protocol->onClose($b, 1000, 'bye');
        $aMessages = $this->drainServerMessages($a);
        $this->assertSame('pusher_internal:member_removed', $aMessages[0]['event']);
        $this->assertSame('2', json_decode($aMessages[0]['data'], true)['user_id']);
    }

    public function testClientEventBroadcastsToOthersOnly(): void
    {
        $protocol = $this->protocol();
        $signature = new Signature('app-key', 'app-secret');
        $a = $this->open($protocol, 1);
        $b = $this->open($protocol, 2);
        $this->drainServerMessages($a);
        $this->drainServerMessages($b);

        foreach ([$a, $b] as $conn) {
            $auth = $signature->channelToken($this->socketId($conn), 'private-chat');
            $conn->ingest($this->frame(['event' => 'pusher:subscribe', 'data' => ['channel' => 'private-chat', 'auth' => $auth]]), $protocol);
        }
        $this->drainServerMessages($a);
        $this->drainServerMessages($b);

        $a->ingest($this->frame(['event' => 'client-typing', 'channel' => 'private-chat', 'data' => ['who' => 'Ann']]), $protocol);

        $this->assertCount(0, $this->drainServerMessages($a)); // sender excluded
        $bMessages = $this->drainServerMessages($b);
        $this->assertSame('client-typing', $bMessages[0]['event']);
    }

    public function testRespondsToPusherPing(): void
    {
        $protocol = $this->protocol();
        $connection = $this->open($protocol, 1);
        $this->drainServerMessages($connection);

        $connection->ingest($this->frame(['event' => 'pusher:ping', 'data' => (object) []]), $protocol);

        $messages = $this->drainServerMessages($connection);
        $this->assertSame('pusher:pong', $messages[0]['event']);
    }

    private function frame(array $message): string
    {
        return $this->clientFrame(json_encode($message));
    }

    private function handshake(): string
    {
        return "GET /app/app-key HTTP/1.1\r\n"
            . "Host: localhost\r\n"
            . "Upgrade: websocket\r\n"
            . "Connection: Upgrade\r\n"
            . "Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==\r\n"
            . "Sec-WebSocket-Version: 13\r\n\r\n";
    }
}
