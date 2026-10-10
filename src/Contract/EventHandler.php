<?php

declare(strict_types=1);

namespace Nsd7\Surge\Contract;

/**
 * High-level connection lifecycle, independent of transport. The Pusher
 * protocol layer (and tests) implement this; runtimes invoke it. The client
 * argument is any Client, so the same handler serves the lite and turbo
 * runtimes.
 */
interface EventHandler
{
    public function onOpen(Client $client): void;

    public function onMessage(Client $client, string $message, bool $binary): void;

    public function onClose(Client $client, int $code, string $reason): void;

    public function onError(Client $client, \Throwable $error): void;
}
