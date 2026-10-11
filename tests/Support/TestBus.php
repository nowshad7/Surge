<?php

declare(strict_types=1);

namespace Nsd7\Surge\Tests\Support;

use Nsd7\Surge\Scaling\RelayMessage;

/**
 * An in-memory relay bus connecting several BusAdapters, so multi-node behavior
 * can be tested deterministically without a real Redis. Mirrors pub/sub: a
 * published message is offered to every node, and each node drops its own
 * origin (as the real RedisAdapter does on consume).
 */
final class TestBus
{
    /** @var list<BusAdapter> */
    private array $nodes = [];

    public function register(BusAdapter $node): void
    {
        $this->nodes[] = $node;
    }

    public function dispatch(RelayMessage $message): void
    {
        foreach ($this->nodes as $node) {
            $node->receive($message);
        }
    }
}
