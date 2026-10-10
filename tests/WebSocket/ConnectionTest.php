<?php

declare(strict_types=1);

namespace Nsd7\Surge\Tests\WebSocket;

use Nsd7\Surge\Protocol\Opcode;
use Nsd7\Surge\Tests\Support\RecordingHandler;
use Nsd7\Surge\Tests\TestCase;
use Nsd7\Surge\WebSocket\Connection;

final class ConnectionTest extends TestCase
{
    public function testCompletesHandshakeAndOpens(): void
    {
        $handler = new RecordingHandler();
        $connection = new Connection(1);

        $connection->ingest($this->request(), $handler);

        $this->assertCount(1, $handler->opened);
        $this->assertTrue($connection->isOpen());
        $this->assertTrue(str_starts_with($connection->outgoingBuffer(), 'HTTP/1.1 101'));
        $this->assertSame('/app/key', $connection->path());
    }

    public function testRejectsInvalidUpgrade(): void
    {
        $handler = new RecordingHandler();
        $connection = new Connection(1);

        $connection->ingest("GET / HTTP/1.1\r\nHost: x\r\n\r\n", $handler);

        $this->assertCount(0, $handler->opened);
        $this->assertFalse($connection->isOpen());
        $this->assertTrue(str_starts_with($connection->outgoingBuffer(), 'HTTP/1.1 400'));
    }

    public function testDeliversTextMessageAfterHandshake(): void
    {
        $handler = new RecordingHandler();
        $connection = new Connection(1);
        $connection->ingest($this->request(), $handler);
        $connection->consumeOutgoing(strlen($connection->outgoingBuffer())); // drop handshake response

        $connection->ingest($this->clientFrame('{"event":"ping"}'), $handler);

        $this->assertCount(1, $handler->messages);
        $this->assertSame('{"event":"ping"}', $handler->messages[0][1]);
        $this->assertFalse($handler->messages[0][2]); // not binary
    }

    public function testReassemblesFragmentedMessage(): void
    {
        $handler = new RecordingHandler();
        $connection = new Connection(1);
        $connection->ingest($this->request(), $handler);

        $connection->ingest($this->clientFrame('Hel', Opcode::TEXT, fin: false), $handler);
        $connection->ingest($this->clientFrame('lo!', Opcode::CONTINUATION, fin: true), $handler);

        $this->assertCount(1, $handler->messages);
        $this->assertSame('Hello!', $handler->messages[0][1]);
    }

    public function testRespondsToPingWithPong(): void
    {
        $handler = new RecordingHandler();
        $connection = new Connection(1);
        $connection->ingest($this->request(), $handler);
        $connection->consumeOutgoing(strlen($connection->outgoingBuffer()));

        $connection->ingest($this->clientFrame('beat', Opcode::PING), $handler);

        $out = $connection->outgoingBuffer();
        $this->assertSame(Opcode::PONG, ord($out[0]) & 0x0F);
        $this->assertSame('beat', substr($out, 2));
    }

    public function testHandlesClientCloseFrame(): void
    {
        $handler = new RecordingHandler();
        $connection = new Connection(1);
        $connection->ingest($this->request(), $handler);
        $connection->consumeOutgoing(strlen($connection->outgoingBuffer()));

        $connection->ingest($this->clientFrame(pack('n', 1000), Opcode::CLOSE), $handler);

        $this->assertCount(1, $handler->closed);
        $this->assertSame(1000, $handler->closed[0][1]);
        $this->assertSame(Opcode::CLOSE, ord($connection->outgoingBuffer()[0]) & 0x0F);
    }

    public function testInvalidUtf8TextIsRejected(): void
    {
        $handler = new RecordingHandler();
        $connection = new Connection(1);
        $connection->ingest($this->request(), $handler);

        $connection->ingest($this->clientFrame("\xC0\xC0", Opcode::TEXT), $handler);

        $this->assertCount(0, $handler->messages);
        $this->assertCount(1, $handler->errors);
    }

    private function request(): string
    {
        return "GET /app/key HTTP/1.1\r\n"
            . "Host: localhost\r\n"
            . "Upgrade: websocket\r\n"
            . "Connection: Upgrade\r\n"
            . "Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==\r\n"
            . "Sec-WebSocket-Version: 13\r\n\r\n";
    }
}
