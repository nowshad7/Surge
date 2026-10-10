<?php

declare(strict_types=1);

namespace Nsd7\Surge\Tests\Support;

use Nsd7\Surge\Contract\EventHandler;
use Nsd7\Surge\WebSocket\Connection;

/**
 * Test double that records every lifecycle callback it receives.
 */
final class RecordingHandler implements EventHandler
{
    /** @var list<Connection> */
    public array $opened = [];
    /** @var list<array{Connection, string, bool}> */
    public array $messages = [];
    /** @var list<array{Connection, int, string}> */
    public array $closed = [];
    /** @var list<array{Connection, \Throwable}> */
    public array $errors = [];

    public function onOpen(Connection $connection): void
    {
        $this->opened[] = $connection;
    }

    public function onMessage(Connection $connection, string $message, bool $binary): void
    {
        $this->messages[] = [$connection, $message, $binary];
    }

    public function onClose(Connection $connection, int $code, string $reason): void
    {
        $this->closed[] = [$connection, $code, $reason];
    }

    public function onError(Connection $connection, \Throwable $error): void
    {
        $this->errors[] = [$connection, $error];
    }
}
