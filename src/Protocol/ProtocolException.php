<?php

declare(strict_types=1);

namespace Nsd7\Surge\Protocol;

/**
 * Thrown when a peer sends data that violates RFC 6455. Carries the WebSocket
 * close code the server should report back before terminating the connection.
 */
final class ProtocolException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $closeCode = 1002)
    {
        parent::__construct($message);
    }
}
