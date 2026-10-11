<?php

declare(strict_types=1);

namespace Nsd7\Surge\Tests\Support;

use Nsd7\Surge\Contract\ScalingAdapter;
use Nsd7\Surge\Scaling\RelayMessage;

/**
 * A ScalingAdapter backed by an in-memory TestBus — one per simulated node.
 * Publishing fans out to every node on the bus; consume() drains this node's
 * inbox. Messages from this node's own origin are dropped on receipt, matching
 * the real RedisAdapter.
 */
final class BusAdapter implements ScalingAdapter
{
    /** @var list<RelayMessage> */
    private array $inbox = [];

    public function __construct(
        private readonly TestBus $bus,
        private readonly string $id,
    ) {
        $bus->register($this);
    }

    public function publishToPeers(RelayMessage $message): void
    {
        $this->bus->dispatch($message);
    }

    public function consume(callable $handler): void
    {
        while ($this->inbox !== []) {
            $handler(array_shift($this->inbox));
        }
    }

    public function nodeId(): string
    {
        return $this->id;
    }

    public function receive(RelayMessage $message): void
    {
        if ($message->origin !== $this->id) {
            $this->inbox[] = $message;
        }
    }
}
