<?php

declare(strict_types=1);

namespace Nsd7\Surge\Scaling;

use Nsd7\Surge\Contract\ScalingAdapter;

/**
 * Redis pub/sub scaling adapter over raw sockets (no extension required).
 *
 * Each node PUBLISHes events to a shared channel on one connection and, each
 * loop tick, drains any messages its SUBSCRIBE connection has received and
 * replays them locally. A node drops its own messages via the origin id.
 *
 * The subscriber socket is non-blocking, so consume() never stalls the event
 * loop: it reads whatever bytes are available and the RESP parser yields only
 * complete frames.
 *
 * NOTE: this relays *events*. Presence membership is tracked per node in this
 * version; cross-node presence rosters are future work and documented as such,
 * never reported as complete.
 */
final class RedisAdapter implements ScalingAdapter
{
    /** @var resource */
    private $publisher;
    /** @var resource */
    private $subscriber;
    private readonly string $nodeId;
    private readonly Resp $parser;

    public function __construct(
        private readonly string $host = '127.0.0.1',
        private readonly int $port = 6379,
        private readonly string $channel = 'surge:events',
        ?string $nodeId = null,
        ?string $password = null,
    ) {
        $this->nodeId = $nodeId ?? bin2hex(random_bytes(8));
        $this->parser = new Resp();

        $this->publisher = $this->connect(blocking: true);
        $this->subscriber = $this->connect(blocking: true);

        if ($password !== null) {
            $this->send($this->publisher, Resp::command('AUTH', $password));
            $this->send($this->subscriber, Resp::command('AUTH', $password));
        }

        // Enter subscribe mode, then switch the socket to non-blocking so the
        // event loop can poll it.
        $this->send($this->subscriber, Resp::command('SUBSCRIBE', $this->channel));
        stream_set_blocking($this->subscriber, false);
    }

    public function publishToPeers(RelayMessage $message): void
    {
        $this->send($this->publisher, Resp::command('PUBLISH', $this->channel, $message->toJson()));
    }

    public function consume(callable $handler): void
    {
        $bytes = @fread($this->subscriber, 65536);
        if ($bytes === '' || $bytes === false) {
            return;
        }

        $this->parser->feed($bytes);
        foreach ($this->parser->drain() as $frame) {
            // A pub/sub delivery is ["message", <channel>, <payload>].
            if (!is_array($frame) || ($frame[0] ?? null) !== 'message') {
                continue;
            }
            $message = RelayMessage::fromJson((string) ($frame[2] ?? ''));
            if ($message !== null && $message->origin !== $this->nodeId) {
                $handler($message);
            }
        }
    }

    public function nodeId(): string
    {
        return $this->nodeId;
    }

    public function close(): void
    {
        foreach ([$this->publisher, $this->subscriber] as $socket) {
            if (is_resource($socket)) {
                @fclose($socket);
            }
        }
    }

    /** @return resource */
    private function connect(bool $blocking)
    {
        $socket = @stream_socket_client("tcp://{$this->host}:{$this->port}", $errno, $errstr, 5);
        if ($socket === false) {
            throw new \RuntimeException("Surge: cannot reach Redis at {$this->host}:{$this->port}: {$errstr} ({$errno})");
        }
        stream_set_blocking($socket, $blocking);

        return $socket;
    }

    /** @param resource $socket */
    private function send($socket, string $command): void
    {
        @fwrite($socket, $command);
    }
}
