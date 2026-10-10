<?php

declare(strict_types=1);

namespace Nsd7\Surge\Tests\Runtime;

use Nsd7\Surge\Runtime\SwooleRuntime;
use Nsd7\Surge\Tests\Support\RecordingHandler;
use Nsd7\Surge\Tests\TestCase;

final class SwooleRuntimeTest extends TestCase
{
    public function testIsNamedTurbo(): void
    {
        $runtime = new SwooleRuntime(new RecordingHandler());
        $this->assertSame('turbo', $runtime->name());
    }

    public function testFailsClearlyWithoutOpenSwoole(): void
    {
        if (extension_loaded('openswoole')) {
            // On a host that actually has OpenSwoole this guard does not fire;
            // the runtime is exercised by integration tests there instead.
            $this->assertTrue(true);
            return;
        }

        $runtime = new SwooleRuntime(new RecordingHandler());
        $this->assertThrows(
            \RuntimeException::class,
            fn () => $runtime->run('127.0.0.1', 0),
        );
    }
}
