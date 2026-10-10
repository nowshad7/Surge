<?php

declare(strict_types=1);

namespace Nsd7\Surge\Runtime;

use Nsd7\Surge\Contract\EventHandler;
use Nsd7\Surge\Contract\HttpHandler;
use Nsd7\Surge\Contract\Runtime;
use Nsd7\Surge\Contract\Tickable;
use Nsd7\Surge\WebSocket\Connection;

/**
 * Pure-PHP, dependency-free runtime built on a non-blocking stream_select loop.
 *
 * This is the "lite" tier: it runs anywhere PHP runs, with zero extensions
 * beyond the standard build, and is single-process (one core). The "turbo"
 * runtime will trade that for OpenSwoole's multi-core workers behind the same
 * Runtime interface.
 *
 * Fixes over the seed: buffered reads feed a real frame parser, writes honour
 * backpressure (a short fwrite retains the remainder), dead peers are detected
 * via ping/idle timeout, and control/close frames are handled correctly.
 */
final class LiteRuntime implements Runtime
{
    /** @var resource|null */
    private $serverSocket = null;

    /** @var array<int, resource> socket id => stream */
    private array $sockets = [];

    /** @var array<int, Connection> socket id => connection */
    private array $connections = [];

    private bool $running = false;
    private int $nextConnectionId = 1;
    private float $lastHeartbeat = 0.0;

    public function __construct(
        private readonly EventHandler $handler,
        private readonly int $readChunkBytes = 65536,
        private readonly int $idleTimeoutSeconds = 120,
        private readonly int $pingIntervalSeconds = 30,
        private readonly int $maxConnections = 10000,
        /** @var null|callable(string):void */
        private $logger = null,
        private readonly ?HttpHandler $httpHandler = null,
    ) {
    }

    public function name(): string
    {
        return 'lite';
    }

    public function run(string $host, int $port): void
    {
        $context = stream_context_create(['socket' => ['backlog' => 1024, 'so_reuseport' => true]]);
        $this->serverSocket = @stream_socket_server(
            "tcp://{$host}:{$port}",
            $errno,
            $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $context,
        );

        if ($this->serverSocket === false) {
            throw new \RuntimeException("Unable to bind {$host}:{$port}: {$errstr} ({$errno})");
        }

        stream_set_blocking($this->serverSocket, false);
        $this->running = true;
        $this->lastHeartbeat = microtime(true);
        $this->log("Surge lite runtime listening on ws://{$host}:{$port}");

        while ($this->running) {
            $this->tick();
        }

        $this->shutdown();
    }

    public function stop(): void
    {
        $this->running = false;
    }

    private function tick(): void
    {
        $read = [$this->serverSocket];
        $write = [];
        foreach ($this->sockets as $id => $socket) {
            $read[$id] = $socket;
            if ($this->connections[$id]->hasPendingWrite()) {
                $write[$id] = $socket;
            }
        }

        $except = null;
        // 1s cap so the heartbeat runs even when traffic is idle.
        $ready = @stream_select($read, $write, $except, 1, 0);
        if ($ready === false) {
            return; // interrupted (e.g. by a signal); loop again
        }

        if ($ready > 0) {
            foreach ($read as $socket) {
                if ($socket === $this->serverSocket) {
                    $this->accept();
                } else {
                    $this->readFrom($socket);
                }
            }

            foreach ($write as $socket) {
                $this->flush($socket);
            }
        }

        // Let a scaling-aware handler drain inbound peer messages, then flush
        // any sends those produced.
        if ($this->handler instanceof Tickable) {
            $this->handler->tick();
            $this->flushPending();
        }

        $this->heartbeat();
    }

    /** Flush every connection that has buffered output (e.g. after a peer relay). */
    private function flushPending(): void
    {
        foreach ($this->sockets as $id => $socket) {
            if ($this->connections[$id]->hasPendingWrite()) {
                $this->flush($socket);
            }
        }
    }

    private function accept(): void
    {
        $client = @stream_socket_accept($this->serverSocket, 0);
        if ($client === false) {
            return;
        }

        if (count($this->sockets) >= $this->maxConnections) {
            @fclose($client);
            $this->log('Rejected connection: max connections reached');
            return;
        }

        stream_set_blocking($client, false);
        $id = (int) $client;
        $this->sockets[$id] = $client;
        $this->connections[$id] = new Connection(
            $this->nextConnectionId++,
            httpHandler: $this->httpHandler,
        );
    }

    private function readFrom($socket): void
    {
        $id = (int) $socket;
        if (!isset($this->connections[$id])) {
            return;
        }

        $data = @fread($socket, $this->readChunkBytes);
        if ($data === '' || $data === false) {
            // Distinguish a real EOF/error from a transient empty read.
            if (feof($socket) || $data === false) {
                $this->disconnect($socket, 1006, 'connection reset');
            }
            return;
        }

        $connection = $this->connections[$id];
        $connection->ingest($data, $this->handler);

        // ingest() may have queued a handshake response, pong, or close frame.
        if ($connection->hasPendingWrite()) {
            $this->flush($socket);
        }
        if ($connection->shouldDisconnect() && !$connection->hasPendingWrite()) {
            $this->disconnect($socket, 1000, 'closed');
        }
    }

    private function flush($socket): void
    {
        $id = (int) $socket;
        if (!isset($this->connections[$id])) {
            return;
        }

        $connection = $this->connections[$id];
        $buffer = $connection->outgoingBuffer();
        if ($buffer === '') {
            return;
        }

        $written = @fwrite($socket, $buffer);
        if ($written === false) {
            $this->disconnect($socket, 1006, 'write failed');
            return;
        }

        $connection->consumeOutgoing($written);

        if (!$connection->hasPendingWrite() && $connection->shouldDisconnect()) {
            $this->disconnect($socket, 1000, 'closed');
        }
    }

    private function heartbeat(): void
    {
        $now = microtime(true);
        if (($now - $this->lastHeartbeat) < 1.0) {
            return;
        }
        $this->lastHeartbeat = $now;

        foreach ($this->sockets as $id => $socket) {
            $connection = $this->connections[$id];
            if (!$connection->isOpen()) {
                continue;
            }

            $idle = $now - $connection->lastActivityAt;
            if ($idle >= $this->idleTimeoutSeconds) {
                $connection->close(1001, 'idle timeout');
                $this->flush($socket);
                $this->disconnect($socket, 1001, 'idle timeout');
            } elseif ($idle >= $this->pingIntervalSeconds && !$connection->awaitingPong) {
                $connection->ping();
                $this->flush($socket);
            }
        }
    }

    private function disconnect($socket, int $code, string $reason): void
    {
        $id = (int) $socket;
        // Idempotent: flush() and readFrom() can both decide to tear a
        // connection down within one tick; only the first call does the work.
        if (!isset($this->sockets[$id]) && !isset($this->connections[$id])) {
            return;
        }

        if (isset($this->connections[$id])) {
            $connection = $this->connections[$id];
            if ($connection->state() !== Connection::CLOSED) {
                $this->handler->onClose($connection, $code, $reason);
            }
            $connection->markClosed();
        }

        if (is_resource($socket)) {
            @fclose($socket);
        }
        unset($this->sockets[$id], $this->connections[$id]);
    }

    private function shutdown(): void
    {
        foreach ($this->sockets as $socket) {
            @fclose($socket);
        }
        if (is_resource($this->serverSocket)) {
            @fclose($this->serverSocket);
        }
        $this->sockets = [];
        $this->connections = [];
        $this->log('Surge lite runtime stopped');
    }

    private function log(string $message): void
    {
        if ($this->logger !== null) {
            ($this->logger)($message);
        }
    }
}
