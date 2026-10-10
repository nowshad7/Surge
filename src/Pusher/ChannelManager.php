<?php

declare(strict_types=1);

namespace Nsd7\Surge\Pusher;

use Nsd7\Surge\Pusher\Channel\Channel;
use Nsd7\Surge\Pusher\Channel\PresenceChannel;
use Nsd7\Surge\Pusher\Channel\PrivateChannel;
use Nsd7\Surge\WebSocket\Connection;

/**
 * Owns the set of live channels for a single node and the reverse index of
 * which channels each connection is in (so a disconnect cleans up in O(k)).
 */
final class ChannelManager
{
    /** @var array<string, Channel> */
    private array $channels = [];

    /** @var array<int, array<string, true>> connection id => set of channel names */
    private array $connectionChannels = [];

    public function findOrCreate(string $name): Channel
    {
        if (!isset($this->channels[$name])) {
            $this->channels[$name] = match (Channel::classify($name)) {
                'presence'                   => new PresenceChannel($name),
                'private', 'private-encrypted' => new PrivateChannel($name),
                default                      => new Channel($name),
            };
        }

        return $this->channels[$name];
    }

    public function find(string $name): ?Channel
    {
        return $this->channels[$name] ?? null;
    }

    public function track(Connection $connection, string $channel): void
    {
        $this->connectionChannels[$connection->id][$channel] = true;
    }

    public function untrack(Connection $connection, string $channel): void
    {
        unset($this->connectionChannels[$connection->id][$channel]);
        $this->pruneIfEmpty($channel);
    }

    /** @return list<string> channel names the connection was subscribed to */
    public function channelsFor(Connection $connection): array
    {
        return array_keys($this->connectionChannels[$connection->id] ?? []);
    }

    public function forget(Connection $connection): void
    {
        unset($this->connectionChannels[$connection->id]);
    }

    public function pruneIfEmpty(string $name): void
    {
        $channel = $this->channels[$name] ?? null;
        if ($channel !== null && $channel->isEmpty()) {
            unset($this->channels[$name]);
        }
    }

    public function totalChannels(): int
    {
        return count($this->channels);
    }
}
