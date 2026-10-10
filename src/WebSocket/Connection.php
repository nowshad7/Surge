<?php

declare(strict_types=1);

namespace Nsd7\Surge\WebSocket;

use Nsd7\Surge\Contract\Client;
use Nsd7\Surge\Contract\EventHandler;
use Nsd7\Surge\Contract\HttpHandler;
use Nsd7\Surge\Http\Request;
use Nsd7\Surge\Protocol\Frame;
use Nsd7\Surge\Protocol\FrameParser;
use Nsd7\Surge\Protocol\Handshake;
use Nsd7\Surge\Protocol\Opcode;
use Nsd7\Surge\Protocol\ProtocolException;

/**
 * One client connection: owns its handshake, frame reassembly, and an outgoing
 * byte buffer. The runtime drives it (feeds inbound bytes, flushes the outgoing
 * buffer) but all protocol state lives here, so every runtime behaves the same.
 */
final class Connection implements Client
{
    public const CONNECTING = 0;
    public const OPEN       = 1;
    public const CLOSING    = 2;
    public const CLOSED     = 3;

    private int $state = self::CONNECTING;
    private readonly Handshake $handshake;
    private readonly FrameParser $parser;

    private string $outgoing = '';

    /** Reassembly state for fragmented data messages. */
    private ?int $fragmentOpcode = null;
    private string $fragmentBuffer = '';

    /** Application-level scratch space (subscribed channels, user info, ...). */
    public array $attributes = [];

    public bool $awaitingPong = false;
    public float $lastActivityAt;

    public function __construct(
        public readonly int $id,
        private readonly int $maxMessageBytes = 10 * 1024 * 1024,
        private readonly ?HttpHandler $httpHandler = null,
    ) {
        $this->handshake = new Handshake();
        $this->parser = new FrameParser($this->maxMessageBytes);
        $this->lastActivityAt = microtime(true);
    }

    public function state(): int
    {
        return $this->state;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function isOpen(): bool
    {
        return $this->state === self::OPEN;
    }

    public function path(): string
    {
        return $this->handshake->path;
    }

    public function query(): string
    {
        return $this->handshake->query;
    }

    /** Header lookup (lower-cased name), available once the handshake completed. */
    public function header(string $name): ?string
    {
        return $this->handshake->headers[strtolower($name)] ?? null;
    }

    /**
     * Process inbound bytes from the socket, driving the connection forward and
     * invoking the handler for high-level events.
     */
    public function ingest(string $bytes, EventHandler $handler): void
    {
        $this->lastActivityAt = microtime(true);

        try {
            if ($this->state === self::CONNECTING) {
                $this->progressHandshake($bytes, $handler);
                return;
            }

            if ($this->state !== self::OPEN) {
                return; // ignore anything after we've started closing
            }

            $this->parser->feed($bytes);
            foreach ($this->parser->drain() as $frame) {
                $this->handleFrame($frame, $handler);
                if ($this->state !== self::OPEN) {
                    break;
                }
            }
        } catch (ProtocolException $e) {
            $this->fail($e->closeCode, $handler, $e);
        }
    }

    private function progressHandshake(string $bytes, EventHandler $handler): void
    {
        if (!$this->handshake->feed($bytes)) {
            return; // still waiting for the rest of the header block
        }

        if (!$this->handshake->isValidUpgrade()) {
            $this->serveHttp();
            return;
        }

        $this->enqueue($this->handshake->response());
        $this->state = self::OPEN;
        $handler->onOpen($this);
    }

    /**
     * A completed request that is not a WebSocket upgrade is handled as plain
     * HTTP (the Pusher trigger API, health checks). Without a handler, reject.
     */
    private function serveHttp(): void
    {
        if ($this->httpHandler === null) {
            $this->enqueue($this->handshake->rejection());
            $this->state = self::CLOSING;
            return;
        }

        $request = new Request(
            $this->handshake->method,
            $this->handshake->path,
            $this->handshake->query,
            $this->handshake->headers,
            $this->handshake->body,
        );

        $response = $this->httpHandler->handle($request);
        $this->enqueue($response->toRaw());
        $this->state = self::CLOSING;
    }

    private function handleFrame(Frame $frame, EventHandler $handler): void
    {
        switch ($frame->opcode) {
            case Opcode::PING:
                $this->enqueue(Frame::pong($frame->payload));
                break;

            case Opcode::PONG:
                $this->awaitingPong = false;
                break;

            case Opcode::CLOSE:
                $this->handleClose($frame->payload, $handler);
                break;

            case Opcode::TEXT:
            case Opcode::BINARY:
                if ($this->fragmentOpcode !== null) {
                    throw new ProtocolException('New data frame while a fragmented message is open');
                }
                if ($frame->fin) {
                    $this->deliver($frame->opcode, $frame->payload, $handler);
                } else {
                    $this->fragmentOpcode = $frame->opcode;
                    $this->fragmentBuffer = $frame->payload;
                }
                break;

            case Opcode::CONTINUATION:
                if ($this->fragmentOpcode === null) {
                    throw new ProtocolException('Continuation frame with no message in progress');
                }
                $this->fragmentBuffer .= $frame->payload;
                if (strlen($this->fragmentBuffer) > $this->maxMessageBytes) {
                    throw new ProtocolException('Reassembled message exceeds maximum', 1009);
                }
                if ($frame->fin) {
                    $opcode = $this->fragmentOpcode;
                    $payload = $this->fragmentBuffer;
                    $this->fragmentOpcode = null;
                    $this->fragmentBuffer = '';
                    $this->deliver($opcode, $payload, $handler);
                }
                break;
        }
    }

    private function deliver(int $opcode, string $payload, EventHandler $handler): void
    {
        if ($opcode === Opcode::TEXT && !mb_check_encoding($payload, 'UTF-8')) {
            throw new ProtocolException('Text message is not valid UTF-8', 1007);
        }

        $handler->onMessage($this, $payload, $opcode === Opcode::BINARY);
    }

    private function handleClose(string $payload, EventHandler $handler): void
    {
        $code = 1005;
        $reason = '';
        if (strlen($payload) >= 2) {
            $code = unpack('n', substr($payload, 0, 2))[1];
            $reason = substr($payload, 2);
        }

        if ($this->state === self::OPEN) {
            $this->enqueue(Frame::close($code === 1005 ? 1000 : $code));
        }

        $this->state = self::CLOSED;
        $handler->onClose($this, $code, $reason);
    }

    private function fail(int $code, EventHandler $handler, \Throwable $cause): void
    {
        if ($this->state === self::OPEN) {
            $this->enqueue(Frame::close($code));
        }
        $this->state = self::CLOSING;
        $handler->onError($this, $cause);
    }

    /* ---- Outbound API used by the handler / protocol layer ---- */

    public function send(string $payload, int $opcode = Opcode::TEXT): void
    {
        if ($this->state === self::OPEN) {
            $this->enqueue(Frame::encode($payload, $opcode));
        }
    }

    public function ping(string $payload = ''): void
    {
        if ($this->state === self::OPEN) {
            $this->awaitingPong = true;
            $this->enqueue(Frame::ping($payload));
        }
    }

    public function close(int $code = 1000, string $reason = ''): void
    {
        if ($this->state === self::OPEN) {
            $this->enqueue(Frame::close($code, $reason));
        }
        $this->state = self::CLOSING;
    }

    /* ---- Buffer plumbing used by the runtime ---- */

    public function enqueue(string $bytes): void
    {
        $this->outgoing .= $bytes;
    }

    public function hasPendingWrite(): bool
    {
        return $this->outgoing !== '';
    }

    public function outgoingBuffer(): string
    {
        return $this->outgoing;
    }

    public function consumeOutgoing(int $bytes): void
    {
        $this->outgoing = $bytes >= strlen($this->outgoing)
            ? ''
            : substr($this->outgoing, $bytes);
    }

    public function markClosed(): void
    {
        $this->state = self::CLOSED;
    }

    /** True once we've flushed everything and the connection is winding down. */
    public function shouldDisconnect(): bool
    {
        return ($this->state === self::CLOSING && $this->outgoing === '')
            || $this->state === self::CLOSED;
    }
}
