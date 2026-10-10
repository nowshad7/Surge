<?php

declare(strict_types=1);

namespace Nsd7\Surge\Contract;

use Nsd7\Surge\WebSocket\Connection;

/**
 * High-level connection lifecycle, independent of transport. The Pusher
 * protocol layer (and tests) implement this; runtimes invoke it.
 */
interface EventHandler
{
    public function onOpen(Connection $connection): void;

    public function onMessage(Connection $connection, string $message, bool $binary): void;

    public function onClose(Connection $connection, int $code, string $reason): void;

    public function onError(Connection $connection, \Throwable $error): void;
}
