<?php

declare(strict_types=1);

namespace Nsd7\Surge\Handler;

use Nsd7\Surge\Contract\Client;
use Nsd7\Surge\Contract\EventHandler;

/**
 * A minimal fan-out handler: every message received from any client is relayed
 * to all other connected clients. This is the spiritual successor to the seed's
 * broadcast(), now correct and transport-agnostic. The Pusher protocol handler
 * (Phase 1) supersedes it for Laravel use; this remains handy for simple relays
 * and as a reference EventHandler implementation.
 *
 * @implements EventHandler
 */
final class BroadcastHandler implements EventHandler
{
    /** @var array<int, Client> */
    private array $clients = [];

    public function __construct(
        private readonly bool $echoToSender = false,
    ) {
    }

    public function onOpen(Client $connection): void
    {
        $this->clients[$connection->id()] = $connection;
    }

    public function onMessage(Client $connection, string $message, bool $binary): void
    {
        foreach ($this->clients as $id => $client) {
            if (!$this->echoToSender && $id === $connection->id()) {
                continue;
            }
            $client->send($message);
        }
    }

    public function onClose(Client $connection, int $code, string $reason): void
    {
        unset($this->clients[$connection->id()]);
    }

    public function onError(Client $connection, \Throwable $error): void
    {
        // Relay server tolerates per-connection errors; the connection is torn
        // down by the runtime after this returns.
    }

    public function connectionCount(): int
    {
        return count($this->clients);
    }
}
