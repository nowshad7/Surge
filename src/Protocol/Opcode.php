<?php

declare(strict_types=1);

namespace Nsd7\Surge\Protocol;

/**
 * RFC 6455 frame opcodes.
 */
final class Opcode
{
    public const CONTINUATION = 0x0;
    public const TEXT         = 0x1;
    public const BINARY       = 0x2;
    public const CLOSE        = 0x8;
    public const PING         = 0x9;
    public const PONG         = 0xA;

    public static function isControl(int $opcode): bool
    {
        return ($opcode & 0x8) !== 0;
    }

    public static function isData(int $opcode): bool
    {
        return $opcode === self::TEXT
            || $opcode === self::BINARY
            || $opcode === self::CONTINUATION;
    }
}
