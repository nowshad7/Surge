<?php

declare(strict_types=1);

namespace Nsd7\Surge\Contract;

/**
 * A transport/event-loop backend. The "lite" runtime is pure PHP (stream_select);
 * the future "turbo" runtime uses OpenSwoole. Both drive the same EventHandler
 * and the same Connection state machine, so swapping them changes nothing above.
 */
interface Runtime
{
    /** Block, accepting and servicing connections, until stop() is called. */
    public function run(string $host, int $port): void;

    public function stop(): void;

    /** Human-readable name for logs/metrics, e.g. "lite" or "turbo". */
    public function name(): string;
}
