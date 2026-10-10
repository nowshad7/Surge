<?php

declare(strict_types=1);

namespace Nsd7\Surge\Tests\Support;

use Nsd7\Surge\Contract\Client;
use Nsd7\Surge\Contract\EventHandler;

/**
 * Test double that records every lifecycle callback it receives.
 */
final class RecordingHandler implements EventHandler
{
    /** @var list<Client> */
    public array $opened = [];
    /** @var list<array{Client, string, bool}> */
    public array $messages = [];
    /** @var list<array{Client, int, string}> */
    public array $closed = [];
    /** @var list<array{Client, \Throwable}> */
    public array $errors = [];

    public function onOpen(Client $connection): void
    {
        $this->opened[] = $connection;
    }

    public function onMessage(Client $connection, string $message, bool $binary): void
    {
        $this->messages[] = [$connection, $message, $binary];
    }

    public function onClose(Client $connection, int $code, string $reason): void
    {
        $this->closed[] = [$connection, $code, $reason];
    }

    public function onError(Client $connection, \Throwable $error): void
    {
        $this->errors[] = [$connection, $error];
    }
}
