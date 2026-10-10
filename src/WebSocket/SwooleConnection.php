<?php

declare(strict_types=1);

namespace Nsd7\Surge\WebSocket;

use Nsd7\Surge\Contract\Client;

/**
 * A Client backed by an OpenSwoole WebSocket file descriptor.
 *
 * OpenSwoole performs the WebSocket handshake and framing itself, so unlike the
 * lite Connection this class does no parsing — send() hands an already-encoded
 * application message to the server, which frames and flushes it across the
 * worker's own buffers. Because the protocol layer only ever touches the Client
 * interface, it is identical on both runtimes.
 *
 * EXPERIMENTAL: requires ext-openswoole and has not been load-tested in this
 * build. See docs/turbo.md.
 */
final class SwooleConnection implements Client
{
    /** @var array<string,mixed> */
    private array $attributes = [];

    public function __construct(
        private readonly \OpenSwoole\WebSocket\Server $server,
        private readonly int $fd,
    ) {
    }

    public function id(): int
    {
        return $this->fd;
    }

    public function send(string $payload): void
    {
        if ($this->server->isEstablished($this->fd)) {
            $this->server->push($this->fd, $payload);
        }
    }

    public function close(int $code = 1000, string $reason = ''): void
    {
        $this->server->disconnect($this->fd, $code, $reason);
    }

    public function isOpen(): bool
    {
        return $this->server->isEstablished($this->fd);
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }
}
