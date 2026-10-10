<?php

declare(strict_types=1);

namespace Nsd7\Surge\Protocol;

/**
 * Incremental, buffering decoder for client->server frames.
 *
 * The original Surge seed read a fixed 1024 bytes and assumed exactly one whole
 * frame arrived each time. Real sockets split a frame across reads, coalesce
 * several frames into one read, and interleave control frames; browsers also
 * send close/ping frames. This parser accumulates bytes and only yields frames
 * once they are fully present, so partial and batched reads are both handled.
 */
final class FrameParser
{
    private string $buffer = '';

    public function __construct(
        /** Hard ceiling on a single frame's payload, to bound memory. */
        private readonly int $maxPayloadBytes = 10 * 1024 * 1024,
    ) {
    }

    public function feed(string $bytes): void
    {
        $this->buffer .= $bytes;
    }

    /**
     * Pull every complete frame currently buffered. Incomplete trailing bytes
     * remain buffered for the next read.
     *
     * @return list<Frame>
     *
     * @throws ProtocolException on a malformed or disallowed frame.
     */
    public function drain(): array
    {
        $frames = [];

        while (($frame = $this->next()) !== null) {
            $frames[] = $frame;
        }

        return $frames;
    }

    private function next(): ?Frame
    {
        $available = strlen($this->buffer);
        if ($available < 2) {
            return null; // need at least the two header bytes
        }

        $b0 = ord($this->buffer[0]);
        $b1 = ord($this->buffer[1]);

        $fin    = ($b0 & 0x80) !== 0;
        $rsv    = $b0 & 0x70;
        $opcode = $b0 & 0x0F;
        $masked = ($b1 & 0x80) !== 0;
        $len7   = $b1 & 0x7F;

        if ($rsv !== 0) {
            throw new ProtocolException('RSV bits set without a negotiated extension');
        }

        if (!$this->isKnownOpcode($opcode)) {
            throw new ProtocolException(sprintf('Unknown opcode 0x%X', $opcode));
        }

        if (Opcode::isControl($opcode)) {
            // Control frames must not be fragmented and carry at most 125 bytes.
            if (!$fin) {
                throw new ProtocolException('Fragmented control frame');
            }
            if ($len7 > 125) {
                throw new ProtocolException('Control frame payload exceeds 125 bytes');
            }
        }

        $offset = 2;
        if ($len7 === 126) {
            if ($available < $offset + 2) {
                return null;
            }
            $payloadLen = unpack('n', substr($this->buffer, $offset, 2))[1];
            $offset += 2;
        } elseif ($len7 === 127) {
            if ($available < $offset + 8) {
                return null;
            }
            $payloadLen = unpack('J', substr($this->buffer, $offset, 8))[1];
            // A negative value here means the 64th bit was set, which the spec
            // forbids (most significant bit MUST be 0).
            if ($payloadLen < 0) {
                throw new ProtocolException('Frame payload length has the high bit set');
            }
            $offset += 8;
        } else {
            $payloadLen = $len7;
        }

        if ($payloadLen > $this->maxPayloadBytes) {
            throw new ProtocolException('Frame payload exceeds configured maximum', 1009);
        }

        // RFC 6455 §5.1: every client->server frame MUST be masked.
        if (!$masked) {
            throw new ProtocolException('Client frame is not masked');
        }

        if ($available < $offset + 4) {
            return null;
        }
        $mask = substr($this->buffer, $offset, 4);
        $offset += 4;

        if ($available < $offset + $payloadLen) {
            return null; // payload not fully arrived yet
        }

        $masked = substr($this->buffer, $offset, $payloadLen);
        $offset += $payloadLen;

        $payload = $this->applyMask($masked, $mask);

        // Consume exactly what we parsed, leaving any trailing frame bytes.
        $this->buffer = substr($this->buffer, $offset);

        return new Frame($opcode, $payload, $fin);
    }

    private function applyMask(string $data, string $mask): string
    {
        $out = '';
        for ($i = 0, $len = strlen($data); $i < $len; ++$i) {
            $out .= $data[$i] ^ $mask[$i & 3];
        }

        return $out;
    }

    private function isKnownOpcode(int $opcode): bool
    {
        return in_array($opcode, [
            Opcode::CONTINUATION,
            Opcode::TEXT,
            Opcode::BINARY,
            Opcode::CLOSE,
            Opcode::PING,
            Opcode::PONG,
        ], true);
    }
}
