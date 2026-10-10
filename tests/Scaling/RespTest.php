<?php

declare(strict_types=1);

namespace Nsd7\Surge\Tests\Scaling;

use Nsd7\Surge\Scaling\Resp;
use Nsd7\Surge\Tests\TestCase;

final class RespTest extends TestCase
{
    public function testEncodesCommandAsBulkStringArray(): void
    {
        $this->assertSame(
            "*2\r\n\$9\r\nSUBSCRIBE\r\n\$5\r\nhello\r\n",
            Resp::command('SUBSCRIBE', 'hello'),
        );
    }

    public function testParsesSimpleStringAndInteger(): void
    {
        $resp = new Resp();
        $resp->feed("+OK\r\n:42\r\n");
        $this->assertSame(['OK', 42], $resp->drain());
    }

    public function testParsesBulkStringAndNull(): void
    {
        $resp = new Resp();
        $resp->feed("\$5\r\nhello\r\n\$-1\r\n");
        $this->assertSame(['hello', null], $resp->drain());
    }

    public function testParsesPubSubMessageFrame(): void
    {
        $resp = new Resp();
        $resp->feed("*3\r\n\$7\r\nmessage\r\n\$12\r\nsurge:events\r\n\$2\r\nhi\r\n");
        $result = $resp->drain();

        $this->assertCount(1, $result);
        $this->assertSame(['message', 'surge:events', 'hi'], $result[0]);
    }

    public function testBuffersPartialFrame(): void
    {
        $resp = new Resp();
        $frame = "*2\r\n\$5\r\nhello\r\n\$5\r\nworld\r\n";

        $resp->feed(substr($frame, 0, 10));
        $this->assertSame([], $resp->drain()); // incomplete

        $resp->feed(substr($frame, 10));
        $this->assertSame([['hello', 'world']], $resp->drain());
    }
}
