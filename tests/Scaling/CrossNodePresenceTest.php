<?php

declare(strict_types=1);

namespace Nsd7\Surge\Tests\Scaling;

use Nsd7\Surge\Pusher\ChannelManager;
use Nsd7\Surge\Pusher\PusherProtocol;
use Nsd7\Surge\Pusher\Signature;
use Nsd7\Surge\Tests\Support\BusAdapter;
use Nsd7\Surge\Tests\Support\TestBus;
use Nsd7\Surge\Tests\TestCase;
use Nsd7\Surge\WebSocket\Connection;

/**
 * Cross-node presence, exercised with two protocol instances wired through an
 * in-memory relay bus (deterministic; no real Redis needed). Verifies that
 * joins/leaves on one node reach subscribers on another and that rosters
 * converge.
 */
final class CrossNodePresenceTest extends TestCase
{
    private Signature $sig;

    public function testMemberAddedAndRemovedPropagateAcrossNodes(): void
    {
        $this->sig = new Signature('k', 's');
        $bus = new TestBus();
        $nodeA = new PusherProtocol($this->sig, new ChannelManager(), 120, true, new BusAdapter($bus, 'A'));
        $nodeB = new PusherProtocol($this->sig, new ChannelManager(), 120, true, new BusAdapter($bus, 'B'));

        $a = $this->open($nodeA, 1);
        $b = $this->open($nodeB, 2);
        $this->drainServerMessages($a);
        $this->drainServerMessages($b);

        $this->joinPresence($nodeA, $a, '1');
        $this->drainServerMessages($a);

        $this->joinPresence($nodeB, $b, '2');
        $this->drainServerMessages($b);

        $this->settle($nodeA, $nodeB);

        // Node A's subscriber learns user 2 (on node B) joined.
        $this->assertTrue(
            $this->sawMember($this->drainServerMessages($a), 'pusher_internal:member_added', '2'),
            'node A should see member_added for the remote user 2',
        );

        // Node B's subscriber learns user 1 (on node A) joined, via the sync.
        $this->assertTrue(
            $this->sawMember($this->drainServerMessages($b), 'pusher_internal:member_added', '1'),
            'node B should see member_added for the remote user 1',
        );

        // User 2 disconnects from node B.
        $nodeB->onClose($b, 1000, 'bye');
        $this->settle($nodeA, $nodeB);

        // Node A's subscriber learns user 2 left.
        $this->assertTrue(
            $this->sawMember($this->drainServerMessages($a), 'pusher_internal:member_removed', '2'),
            'node A should see member_removed for the remote user 2',
        );
    }

    public function testRosterIncludesRemoteMembersForLaterJoiners(): void
    {
        $this->sig = new Signature('k', 's');
        $bus = new TestBus();
        $nodeA = new PusherProtocol($this->sig, new ChannelManager(), 120, true, new BusAdapter($bus, 'A'));
        $nodeB = new PusherProtocol($this->sig, new ChannelManager(), 120, true, new BusAdapter($bus, 'B'));

        $a = $this->open($nodeA, 1);
        $b = $this->open($nodeB, 2);
        $this->drainServerMessages($a);
        $this->drainServerMessages($b);

        $this->joinPresence($nodeA, $a, '1');
        $this->joinPresence($nodeB, $b, '2');
        $this->drainServerMessages($a);
        $this->drainServerMessages($b);
        $this->settle($nodeA, $nodeB);
        $this->drainServerMessages($a);

        // A new subscriber joins on node A; its roster must include the remote
        // user 2 (from node B) as well as the local users.
        $c = $this->open($nodeA, 3);
        $this->drainServerMessages($c);
        $this->joinPresence($nodeA, $c, '3');

        $messages = $this->drainServerMessages($c);
        $succeeded = $messages[0];
        $this->assertSame('pusher_internal:subscription_succeeded', $succeeded['event']);

        $roster = json_decode($succeeded['data'], true);
        $ids = $roster['presence']['ids'];
        sort($ids);
        $this->assertSame(['1', '2', '3'], array_map('strval', $ids));
        $this->assertSame(3, $roster['presence']['count']);
    }

    /** Tick both nodes until the bus is quiet (cascading sync settles fast). */
    private function settle(PusherProtocol $a, PusherProtocol $b): void
    {
        for ($i = 0; $i < 6; $i++) {
            $a->tick();
            $b->tick();
        }
    }

    private function open(PusherProtocol $protocol, int $id): Connection
    {
        $connection = new Connection($id);
        $connection->ingest($this->handshake(), $protocol);
        return $connection;
    }

    private function joinPresence(PusherProtocol $protocol, Connection $conn, string $userId): void
    {
        $socketId = (string) $conn->attributes['socket_id'];
        $channelData = json_encode(['user_id' => $userId, 'user_info' => ['name' => "u{$userId}"]]);
        $auth = $this->sig->channelToken($socketId, 'presence-lobby', $channelData);

        $conn->ingest($this->clientFrame(json_encode([
            'event' => 'pusher:subscribe',
            'data'  => ['channel' => 'presence-lobby', 'auth' => $auth, 'channel_data' => $channelData],
        ])), $protocol);
    }

    /** @param list<array<string,mixed>> $messages */
    private function sawMember(array $messages, string $event, string $userId): bool
    {
        foreach ($messages as $m) {
            if (($m['event'] ?? null) === $event) {
                $data = json_decode($m['data'] ?? 'null', true);
                if ((string) ($data['user_id'] ?? '') === $userId) {
                    return true;
                }
            }
        }

        return false;
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
