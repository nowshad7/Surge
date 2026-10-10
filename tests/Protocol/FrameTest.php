<?php

declare(strict_types=1);

namespace Nsd7\Surge\Tests\Protocol;

use Nsd7\Surge\Protocol\Frame;
use Nsd7\Surge\Protocol\Opcode;
use Nsd7\Surge\Tests\TestCase;

final class FrameTest extends TestCase
{
    public function testEncodesShortPayloadWithSingleByteLength(): void
    {
        $encoded = Frame::encode('hi');
        $this->assertSame(0x81, ord($encoded[0]));   // FIN + text opcode
        $this->assertSame(2, ord($encoded[1]));       // length, no mask bit
        $this->assertSame('hi', substr($encoded, 2));
    }

    public function testEncodesMediumPayloadWith16BitLength(): void
    {
        $payload = str_repeat('x', 200);
        $encoded = Frame::encode($payload);
        $this->assertSame(126, ord($encoded[1]));
        $this->assertSame(200, unpack('n', substr($encoded, 2, 2))[1]);
        $this->assertSame($payload, substr($encoded, 4));
    }

    public function testEncodesLargePayloadWith64BitLength(): void
    {
        $payload = str_repeat('y', 70000);
        $encoded = Frame::encode($payload);
        $this->assertSame(127, ord($encoded[1]));
        $this->assertSame(70000, unpack('J', substr($encoded, 2, 8))[1]);
    }

    public function testServerFramesAreNeverMasked(): void
    {
        $encoded = Frame::encode('data');
        // The mask bit (0x80 of byte 1) must be clear on server frames.
        $this->assertSame(0, ord($encoded[1]) & 0x80);
    }

    public function testCloseFrameCarriesStatusCode(): void
    {
        $encoded = Frame::close(1001, 'bye');
        $this->assertSame(Opcode::CLOSE, ord($encoded[0]) & 0x0F);
        $len = ord($encoded[1]) & 0x7F;
        $this->assertSame(5, $len); // 2-byte code + "bye"
        $this->assertSame(1001, unpack('n', substr($encoded, 2, 2))[1]);
    }
}
