<?php

declare(strict_types=1);

namespace Nsd7\Surge\Protocol;

/**
 * A single, fully-decoded WebSocket frame (RFC 6455 §5).
 *
 * Frames are the wire unit. One application "message" may be split across a
 * leading data frame plus continuation frames; reassembly lives in the
 * connection layer, not here.
 */
final class Frame
{
    public function __construct(
        public readonly int $opcode,
        public readonly string $payload,
        public readonly bool $fin = true,
    ) {
    }

    /**
     * Encode a server->client frame. Per the spec, the server MUST NOT mask
     * frames it sends, so no masking key is applied here.
     */
    public static function encode(string $payload, int $opcode = Opcode::TEXT, bool $fin = true): string
    {
        $b0 = ($fin ? 0x80 : 0x00) | ($opcode & 0x0F);
        $length = strlen($payload);

        if ($length <= 125) {
            $header = chr($b0) . chr($length);
        } elseif ($length <= 65535) {
            $header = chr($b0) . chr(126) . pack('n', $length);
        } else {
            // 64-bit length. pack('J') needs PHP with 64-bit ints, which is
            // our baseline; guard against the theoretical 63-bit overflow.
            $header = chr($b0) . chr(127) . pack('J', $length);
        }

        return $header . $payload;
    }

    /** Convenience builders for the control frames the server originates. */
    public static function close(int $code = 1000, string $reason = ''): string
    {
        return self::encode(pack('n', $code) . $reason, Opcode::CLOSE);
    }

    public static function ping(string $payload = ''): string
    {
        return self::encode($payload, Opcode::PING);
    }

    public static function pong(string $payload = ''): string
    {
        return self::encode($payload, Opcode::PONG);
    }
}
