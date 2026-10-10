<?php

declare(strict_types=1);

namespace Nsd7\Surge\Tests\Protocol;

use Nsd7\Surge\Protocol\FrameParser;
use Nsd7\Surge\Protocol\Opcode;
use Nsd7\Surge\Protocol\ProtocolException;
use Nsd7\Surge\Tests\TestCase;

final class FrameParserTest extends TestCase
{
    public function testDecodesSingleMaskedTextFrame(): void
    {
        $parser = new FrameParser();
        $parser->feed($this->clientFrame('hello'));
        $frames = $parser->drain();

        $this->assertCount(1, $frames);
        $this->assertSame(Opcode::TEXT, $frames[0]->opcode);
        $this->assertSame('hello', $frames[0]->payload);
        $this->assertTrue($frames[0]->fin);
    }

    public function testBuffersPartialFrameUntilComplete(): void
    {
        $frame = $this->clientFrame('partial-delivery-test');
        $parser = new FrameParser();

        // Feed a header-only slice first: nothing should decode yet.
        $parser->feed(substr($frame, 0, 3));
        $this->assertCount(0, $parser->drain());

        // Feed the rest; now the full frame is available.
        $parser->feed(substr($frame, 3));
        $frames = $parser->drain();
        $this->assertCount(1, $frames);
        $this->assertSame('partial-delivery-test', $frames[0]->payload);
    }

    public function testDecodesMultipleFramesFromOneRead(): void
    {
        $parser = new FrameParser();
        $parser->feed($this->clientFrame('one') . $this->clientFrame('two') . $this->clientFrame('three'));
        $frames = $parser->drain();

        $this->assertCount(3, $frames);
        $this->assertSame('one', $frames[0]->payload);
        $this->assertSame('two', $frames[1]->payload);
        $this->assertSame('three', $frames[2]->payload);
    }

    public function testDecodes16BitLengthPayload(): void
    {
        $payload = str_repeat('z', 500);
        $parser = new FrameParser();
        $parser->feed($this->clientFrame($payload));
        $frames = $parser->drain();

        $this->assertCount(1, $frames);
        $this->assertSame($payload, $frames[0]->payload);
    }

    public function testRejectsUnmaskedClientFrame(): void
    {
        $parser = new FrameParser();
        // Server-style (unmasked) frame sent by a client is a protocol violation.
        $parser->feed(chr(0x81) . chr(0x03) . 'abc');

        $this->assertThrows(ProtocolException::class, fn () => $parser->drain());
    }

    public function testRejectsOversizedControlFrame(): void
    {
        $parser = new FrameParser();
        $parser->feed($this->clientFrame(str_repeat('x', 200), Opcode::PING));

        $this->assertThrows(ProtocolException::class, fn () => $parser->drain());
    }

    public function testRejectsFragmentedControlFrame(): void
    {
        $parser = new FrameParser();
        $parser->feed($this->clientFrame('x', Opcode::PING, fin: false));

        $this->assertThrows(ProtocolException::class, fn () => $parser->drain());
    }

    public function testEnforcesMaxPayloadSize(): void
    {
        $parser = new FrameParser(maxPayloadBytes: 10);
        $parser->feed($this->clientFrame('this is definitely longer than ten bytes'));

        $this->assertThrows(ProtocolException::class, fn () => $parser->drain());
    }

    public function testDecodesControlFrames(): void
    {
        $parser = new FrameParser();
        $parser->feed($this->clientFrame('ka', Opcode::PING));
        $parser->feed($this->clientFrame('', Opcode::CLOSE));
        $frames = $parser->drain();

        $this->assertCount(2, $frames);
        $this->assertSame(Opcode::PING, $frames[0]->opcode);
        $this->assertSame('ka', $frames[0]->payload);
        $this->assertSame(Opcode::CLOSE, $frames[1]->opcode);
    }
}
