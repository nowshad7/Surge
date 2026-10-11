<?php

declare(strict_types=1);

namespace Nsd7\Surge\Tests\Support;

use Nsd7\Surge\Support\Logger;
use Nsd7\Surge\Tests\TestCase;

final class LoggerTest extends TestCase
{
    public function testJsonFormatEmitsStructuredLine(): void
    {
        $lines = [];
        $log = new Logger(Logger::JSON, function (string $l) use (&$lines) { $lines[] = $l; });

        $log->info('server started', ['port' => 9019]);

        $this->assertCount(1, $lines);
        $decoded = json_decode($lines[0], true);
        $this->assertSame('info', $decoded['level']);
        $this->assertSame('server started', $decoded['message']);
        $this->assertSame(9019, $decoded['context']['port']);
        $this->assertTrue(isset($decoded['ts']));
    }

    public function testTextFormatIsHumanReadable(): void
    {
        $lines = [];
        $log = new Logger(Logger::TEXT, function (string $l) use (&$lines) { $lines[] = $l; });

        $log->warning('slow consumer');

        $this->assertTrue(str_contains($lines[0], 'WARNING'));
        $this->assertTrue(str_contains($lines[0], 'slow consumer'));
    }

    public function testAsCallableLogsAtInfo(): void
    {
        $lines = [];
        $log = new Logger(Logger::JSON, function (string $l) use (&$lines) { $lines[] = $l; });

        ($log->asCallable())('via callable');

        $this->assertSame('info', json_decode($lines[0], true)['level']);
        $this->assertSame('via callable', json_decode($lines[0], true)['message']);
    }
}
