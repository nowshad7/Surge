<?php

declare(strict_types=1);

namespace Nsd7\Surge\Contract;

/**
 * The surface the protocol and channels need from a connected client,
 * independent of runtime. The lite runtime's Connection implements it directly;
 * the turbo runtime wraps an OpenSwoole file descriptor behind the same
 * interface, so the Pusher protocol is identical on both.
 */
interface Client
{
    /** Stable per-connection identifier, unique for the life of the server. */
    public function id(): int;

    /** Queue an application-level (already JSON-encoded) message to the client. */
    public function send(string $payload): void;

    public function close(int $code = 1000, string $reason = ''): void;

    public function isOpen(): bool;

    public function getAttribute(string $key, mixed $default = null): mixed;

    public function setAttribute(string $key, mixed $value): void;
}
