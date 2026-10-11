<?php

declare(strict_types=1);

namespace Nsd7\Surge\Pusher;

use Nsd7\Surge\Contract\Client;
use Nsd7\Surge\Contract\EventHandler;
use Nsd7\Surge\Contract\ScalingAdapter;
use Nsd7\Surge\Contract\Tickable;
use Nsd7\Surge\Pusher\Channel\Channel;
use Nsd7\Surge\Pusher\Channel\PresenceChannel;
use Nsd7\Surge\Scaling\LocalAdapter;
use Nsd7\Surge\Scaling\RelayMessage;
use Nsd7\Surge\Support\Metrics;

/**
 * Implements the Pusher WebSocket protocol so Laravel Echo (and any Pusher
 * client) can talk to Surge unchanged: connection_established, subscribe /
 * unsubscribe for public/private/presence channels, protocol ping/pong, and
 * client-* events.
 *
 * Server-originated application events (what Laravel's backend broadcasts)
 * enter through publish(), which the HTTP trigger API and scaling adapter call.
 *
 * @implements EventHandler
 */
final class PusherProtocol implements EventHandler, Tickable
{
    private const SOCKET_ID = 'socket_id';

    /** @var array<string, Client> socket_id => client */
    private array $socketIndex = [];

    private readonly ScalingAdapter $scaling;
    private readonly Metrics $metrics;

    public function __construct(
        private readonly Signature $signature,
        private readonly ChannelManager $channels = new ChannelManager(),
        private readonly int $activityTimeout = 120,
        private readonly bool $clientEventsEnabled = true,
        ?ScalingAdapter $scaling = null,
        ?Metrics $metrics = null,
    ) {
        $this->scaling = $scaling ?? new LocalAdapter();
        $this->metrics = $metrics ?? new Metrics();
    }

    public function channels(): ChannelManager
    {
        return $this->channels;
    }

    public function metrics(): Metrics
    {
        return $this->metrics;
    }

    /** Refresh the live gauges (current connections and channels). */
    private function syncGauges(): void
    {
        $this->metrics->setGauge('surge_connections_current', count($this->socketIndex));
        $this->metrics->setGauge('surge_channels_current', $this->channels->totalChannels());
    }

    public function onOpen(Client $connection): void
    {
        $socketId = $this->generateSocketId();
        $connection->setAttribute(self::SOCKET_ID, $socketId);
        $this->socketIndex[$socketId] = $connection;

        $this->metrics->increment('surge_connections_total');
        $this->syncGauges();

        $connection->send(Message::connectionEstablished($socketId, $this->activityTimeout));
    }

    public function onMessage(Client $connection, string $message, bool $binary): void
    {
        $this->metrics->increment('surge_messages_received_total');

        $decoded = json_decode($message, true);
        if (!is_array($decoded) || !isset($decoded['event']) || !is_string($decoded['event'])) {
            $connection->send(Message::error('Invalid message format'));
            return;
        }

        $event = $decoded['event'];
        $data = $decoded['data'] ?? null;

        if (str_starts_with($event, 'client-')) {
            $this->handleClientEvent($connection, $event, $decoded);
            return;
        }

        match ($event) {
            'pusher:ping'        => $connection->send(Message::pong()),
            'pusher:subscribe'   => $this->subscribe($connection, is_array($data) ? $data : []),
            'pusher:unsubscribe' => $this->unsubscribe($connection, is_array($data) ? (string) ($data['channel'] ?? '') : ''),
            default              => null, // unknown pusher: events are ignored
        };
    }

    public function onClose(Client $connection, int $code, string $reason): void
    {
        foreach ($this->channels->channelsFor($connection) as $name) {
            $this->leave($connection, $name, notify: true);
        }
        $this->channels->forget($connection);

        $socketId = $connection->getAttribute(self::SOCKET_ID);
        if (is_string($socketId)) {
            unset($this->socketIndex[$socketId]);
        }

        $this->syncGauges();
    }

    public function onError(Client $connection, \Throwable $error): void
    {
        // Protocol-level errors already closed the socket; nothing to add.
    }

    /* ---------------- Subscription handling ---------------- */

    private function subscribe(Client $connection, array $data): void
    {
        $name = (string) ($data['channel'] ?? '');
        if ($name === '') {
            $connection->send(Message::error('Subscribe requires a channel'));
            return;
        }

        $socketId = (string) $connection->getAttribute(self::SOCKET_ID);
        $type = Channel::classify($name);

        if ($type !== 'public') {
            $auth = (string) ($data['auth'] ?? '');
            $channelData = isset($data['channel_data']) ? (string) $data['channel_data'] : null;

            if (!$this->signature->verifyChannel($auth, $socketId, $name, $type === 'presence' ? $channelData : null)) {
                $connection->send(Message::encode('pusher:subscription_error', [
                    'type'   => 'AuthError',
                    'error'  => 'Invalid signature',
                    'status' => 401,
                ], $name));
                return;
            }
        }

        $existed = $this->channels->find($name) !== null;
        $channel = $this->channels->findOrCreate($name);

        if ($channel instanceof PresenceChannel) {
            // First local subscriber on this presence channel: ask peers to
            // re-announce their members so our roster converges.
            if (!$existed) {
                $this->scaling->publishToPeers(RelayMessage::presence(
                    $this->scaling->nodeId(),
                    RelayMessage::PRESENCE_SYNC,
                    $name,
                ));
            }
            $this->joinPresence($connection, $channel, (string) ($data['channel_data'] ?? ''));
        } else {
            $channel->subscribe($connection);
            $connection->send(Message::encode('pusher_internal:subscription_succeeded', (object) [], $name));
        }

        $this->channels->track($connection, $name);
        $this->syncGauges();
    }

    private function joinPresence(Client $connection, PresenceChannel $channel, string $channelData): void
    {
        $parsed = json_decode($channelData, true);
        $userId = isset($parsed['user_id']) ? (string) $parsed['user_id'] : '';
        $userInfo = $parsed['user_info'] ?? null;

        if ($userId === '') {
            $connection->send(Message::encode('pusher:subscription_error', [
                'type' => 'AuthError', 'error' => 'Presence channel_data missing user_id', 'status' => 400,
            ], $channel->name));
            return;
        }

        $isNewCombined = !$channel->hasUserId($userId);   // new across local + known remote
        $firstLocal = !$channel->hasLocalUserId($userId); // first connection for this user on this node

        $channel->join($connection, $userId, $userInfo);

        // The subscriber sees the full roster (local + known remote members).
        $connection->send(Message::encode('pusher_internal:subscription_succeeded', $channel->presenceData(), $channel->name));

        // Local subscribers hear a member arrived, but only once per user_id.
        if ($isNewCombined) {
            $channel->broadcast(
                Message::encode('pusher_internal:member_added', ['user_id' => $userId, 'user_info' => $userInfo], $channel->name),
                exceptConnectionId: $connection->id(),
            );
        }

        // Peers are told this node now hosts the user (first local occurrence only).
        if ($firstLocal) {
            $this->scaling->publishToPeers(RelayMessage::presence(
                $this->scaling->nodeId(),
                RelayMessage::PRESENCE_ADD,
                $channel->name,
                ['user_id' => $userId, 'user_info' => $userInfo],
            ));
        }
    }

    private function unsubscribe(Client $connection, string $name): void
    {
        if ($name === '') {
            return;
        }
        $this->leave($connection, $name, notify: true);
    }

    private function leave(Client $connection, string $name, bool $notify): void
    {
        $channel = $this->channels->find($name);
        if ($channel === null) {
            return;
        }

        if ($channel instanceof PresenceChannel) {
            $member = $channel->member($connection);
            $channel->unsubscribe($connection);
            if ($member !== null) {
                $userId = $member['user_id'];

                // If this node no longer hosts the user, tell peers to drop it.
                if (!$channel->hasLocalUserId($userId)) {
                    $this->scaling->publishToPeers(RelayMessage::presence(
                        $this->scaling->nodeId(),
                        RelayMessage::PRESENCE_REMOVE,
                        $name,
                        ['user_id' => $userId],
                    ));
                }

                // Local subscribers hear a member left only when fully gone
                // (no local and no remote connection holds the user_id).
                if ($notify && !$channel->hasUserId($userId)) {
                    $channel->broadcast(
                        Message::encode('pusher_internal:member_removed', ['user_id' => $userId], $name),
                        exceptConnectionId: $connection->id(),
                    );
                }
            }
        } else {
            $channel->unsubscribe($connection);
        }

        $this->channels->untrack($connection, $name);
        $this->syncGauges();
    }

    /* ---------------- Client events (whispers) ---------------- */

    private function handleClientEvent(Client $connection, string $event, array $decoded): void
    {
        if (!$this->clientEventsEnabled) {
            $connection->send(Message::error('Client events are disabled'));
            return;
        }

        $name = (string) ($decoded['channel'] ?? '');
        $channel = $this->channels->find($name);

        // Client events are only allowed on private/presence channels the sender
        // is actually subscribed to.
        if ($channel === null || !$channel->requiresAuth() || !$channel->has($connection)) {
            return;
        }

        $data = $decoded['data'] ?? null;

        $channel->broadcast(
            Message::encode($event, $data, $name),
            exceptConnectionId: $connection->id(),
        );

        // Relay to peer nodes so their subscribers see the client event too.
        $this->scaling->publishToPeers(new RelayMessage(
            $this->scaling->nodeId(),
            $name,
            $event,
            $data,
            (string) $connection->getAttribute(self::SOCKET_ID),
        ));
    }

    /* ---------------- Server-side publish (HTTP API / scaling) ---------------- */

    /**
     * Deliver an application event to a channel's subscribers and relay it to
     * peer nodes. Returns the number of local clients the event was sent to.
     */
    public function publish(string $channel, string $event, mixed $data, ?string $exceptSocketId = null): int
    {
        $this->metrics->increment('surge_events_published_total');

        $this->scaling->publishToPeers(new RelayMessage(
            $this->scaling->nodeId(),
            $channel,
            $event,
            $data,
            $exceptSocketId,
        ));

        return $this->publishLocal($channel, $event, $data, $exceptSocketId);
    }

    /** Fan an event out to local subscribers only (no peer relay). */
    public function publishLocal(string $channel, string $event, mixed $data, ?string $exceptSocketId = null): int
    {
        $target = $this->channels->find($channel);
        if ($target === null) {
            return 0;
        }

        $exceptConnectionId = null;
        if ($exceptSocketId !== null && isset($this->socketIndex[$exceptSocketId])) {
            $exceptConnectionId = $this->socketIndex[$exceptSocketId]->id();
        }

        $sent = $target->broadcast(Message::encode($event, $data, $channel), $exceptConnectionId);
        $this->metrics->increment('surge_messages_sent_total', $sent);

        return $sent;
    }

    /** Called each loop tick: apply messages relayed from other nodes. */
    public function tick(): void
    {
        $this->scaling->consume(function (RelayMessage $message): void {
            match ($message->kind) {
                RelayMessage::PRESENCE_ADD    => $this->onRemotePresenceAdd($message),
                RelayMessage::PRESENCE_REMOVE => $this->onRemotePresenceRemove($message),
                RelayMessage::PRESENCE_SYNC   => $this->onPresenceSync($message),
                default                        => $this->publishLocal(
                    $message->channel,
                    (string) $message->event,
                    $message->data,
                    $message->exceptSocketId,
                ),
            };
        });
    }

    private function onRemotePresenceAdd(RelayMessage $message): void
    {
        $channel = $this->channels->find($message->channel);
        if (!$channel instanceof PresenceChannel || !is_array($message->data)) {
            return;
        }
        $userId = (string) ($message->data['user_id'] ?? '');
        if ($userId === '') {
            return;
        }
        $userInfo = $message->data['user_info'] ?? null;

        $wasPresent = $channel->hasUserId($userId);
        $channel->addRemote($message->origin . '|' . $userId, $userId, $userInfo);

        if (!$wasPresent) {
            $channel->broadcast(
                Message::encode('pusher_internal:member_added', ['user_id' => $userId, 'user_info' => $userInfo], $message->channel),
            );
        }
    }

    private function onRemotePresenceRemove(RelayMessage $message): void
    {
        $channel = $this->channels->find($message->channel);
        if (!$channel instanceof PresenceChannel || !is_array($message->data)) {
            return;
        }
        $userId = (string) ($message->data['user_id'] ?? '');
        if ($userId === '') {
            return;
        }

        $channel->removeRemote($message->origin . '|' . $userId);

        if (!$channel->hasUserId($userId)) {
            $channel->broadcast(
                Message::encode('pusher_internal:member_removed', ['user_id' => $userId], $message->channel),
            );
        }
    }

    /** A peer asked for our roster: re-announce our local members. */
    private function onPresenceSync(RelayMessage $message): void
    {
        $channel = $this->channels->find($message->channel);
        if (!$channel instanceof PresenceChannel) {
            return;
        }
        foreach ($channel->localRoster() as $userId => $userInfo) {
            $this->scaling->publishToPeers(RelayMessage::presence(
                $this->scaling->nodeId(),
                RelayMessage::PRESENCE_ADD,
                $message->channel,
                ['user_id' => (string) $userId, 'user_info' => $userInfo],
            ));
        }
    }

    private function generateSocketId(): string
    {
        return sprintf('%d.%d', random_int(1, 1_000_000_000), random_int(1, 1_000_000_000));
    }
}
