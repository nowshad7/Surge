<?php

declare(strict_types=1);

namespace Nsd7\Surge\Tests\Support;

use Nsd7\Surge\Contract\ScalingAdapter;
use Nsd7\Surge\Scaling\RelayMessage;

/**
 * In-memory scaling adapter for tests: records what was relayed to peers and
 * lets a test inject inbound peer messages to be delivered on the next consume.
 */
final class FakeAdapter implements ScalingAdapter
{
    /** @var list<RelayMessage> */
    public array $relayed = [];
    /** @var list<RelayMessage> */
    public array $inbox = [];

    public function __construct(private readonly string $id = 'node-test')
    {
    }

    public function publishToPeers(RelayMessage $message): void
    {
        $this->relayed[] = $message;
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

    public function deliver(RelayMessage $message): void
    {
        $this->inbox[] = $message;
    }
}
