<?php

declare(strict_types=1);

namespace Nsd7\Surge;

use Nsd7\Surge\Contract\EventHandler;
use Nsd7\Surge\Contract\Runtime;
use Nsd7\Surge\Runtime\LiteRuntime;

/**
 * Top-level entry point. Wires a transport Runtime to an EventHandler and runs
 * the loop. Defaults to the lite runtime; a different runtime (e.g. turbo) can
 * be injected without the handler knowing.
 */
final class Surge
{
    private Runtime $runtime;

    public function __construct(
        private readonly EventHandler $handler,
        ?Runtime $runtime = null,
        /** @var null|callable(string):void */
        $logger = null,
    ) {
        $this->runtime = $runtime ?? new LiteRuntime($handler, logger: $logger);
    }

    public function run(string $host = '0.0.0.0', int $port = 9019): void
    {
        $this->runtime->run($host, $port);
    }

    public function stop(): void
    {
        $this->runtime->stop();
    }

    public function runtimeName(): string
    {
        return $this->runtime->name();
    }
}
