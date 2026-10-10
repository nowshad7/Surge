<?php

declare(strict_types=1);

namespace Nsd7\Surge\Pusher\Channel;

use Nsd7\Surge\Contract\Client;

/**
 * A public channel: a named set of subscribed connections. Private and presence
 * channels extend this with authorization and membership semantics.
 */
class Channel
{
    /** @var array<int, Client> connection id => client */
    protected array $subscribers = [];

    public function __construct(public readonly string $name)
    {
    }

    public function subscribe(Client $connection): void
    {
        $this->subscribers[$connection->id()] = $connection;
    }

    public function unsubscribe(Client $connection): void
    {
        unset($this->subscribers[$connection->id()]);
    }

    public function has(Client $connection): bool
    {
        return isset($this->subscribers[$connection->id()]);
    }

    public function isEmpty(): bool
    {
        return $this->subscribers === [];
    }

    public function count(): int
    {
        return count($this->subscribers);
    }

    /** True for private- and presence- channels, which require authorization. */
    public function requiresAuth(): bool
    {
        return false;
    }

    /**
     * Fan a raw, pre-encoded message out to every subscriber, optionally
     * skipping one connection (used so a publisher does not echo to itself).
     */
    public function broadcast(string $encodedMessage, ?int $exceptConnectionId = null): int
    {
        $sent = 0;
        foreach ($this->subscribers as $id => $connection) {
            if ($id === $exceptConnectionId) {
                continue;
            }
            $connection->send($encodedMessage);
            $sent++;
        }

        return $sent;
    }

    public static function classify(string $name): string
    {
        return match (true) {
            str_starts_with($name, 'private-encrypted-') => 'private-encrypted',
            str_starts_with($name, 'private-')           => 'private',
            str_starts_with($name, 'presence-')          => 'presence',
            default                                       => 'public',
        };
    }
}
