<?php

declare(strict_types=1);

namespace Nsd7\Surge\Contract;

use Nsd7\Surge\Scaling\RelayMessage;

/**
 * Relays published events between Surge nodes so the server scales horizontally.
 * The default LocalAdapter is a no-op (single node); RedisAdapter uses Redis
 * pub/sub.
 */
interface ScalingAdapter
{
    /** Broadcast an event to the other nodes in the cluster. */
    public function publishToPeers(RelayMessage $message): void;

    /**
     * Non-blocking: invoke the handler for each peer message that has arrived
     * since the last call. Called once per event-loop tick.
     *
     * @param callable(RelayMessage):void $handler
     */
    public function consume(callable $handler): void;

    /** This node's unique id, stamped on outgoing messages to drop self-echoes. */
    public function nodeId(): string;
}
