<?php

declare(strict_types=1);

namespace Nsd7\Surge\Scaling;

use Nsd7\Surge\Contract\ScalingAdapter;

/**
 * Single-node adapter: there are no peers, so publishing and consuming are
 * no-ops. This is the default, keeping the common case dependency-free.
 */
final class LocalAdapter implements ScalingAdapter
{
    public function publishToPeers(RelayMessage $message): void
    {
    }

    public function consume(callable $handler): void
    {
    }

    public function nodeId(): string
    {
        return 'local';
    }
}
