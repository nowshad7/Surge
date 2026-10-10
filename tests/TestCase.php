<?php

declare(strict_types=1);

namespace Nsd7\Surge\Tests;

use Nsd7\Surge\Protocol\Opcode;

/**
 * Bare-bones assertion base class for the bundled zero-dependency test runner.
 * Keeps the project installable and testable with nothing but `php`.
 */
abstract class TestCase
{
    public int $assertions = 0;

    protected function assertTrue(bool $condition, string $message = ''): void
    {
        $this->assertions++;
        if ($condition !== true) {
            throw new AssertionFailed($message !== '' ? $message : 'Failed asserting that condition is true');
        }
    }

    protected function assertFalse(bool $condition, string $message = ''): void
    {
        $this->assertTrue($condition === false, $message !== '' ? $message : 'Failed asserting that condition is false');
    }

    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertions++;
        if ($expected !== $actual) {
            throw new AssertionFailed($message !== '' ? $message : sprintf(
                'Failed asserting two values are identical. Expected %s, got %s',
                $this->export($expected),
                $this->export($actual),
            ));
        }
    }

    protected function assertCount(int $expected, array|\Countable $actual, string $message = ''): void
    {
        $this->assertSame($expected, count($actual), $message !== '' ? $message : "Failed asserting count is {$expected}");
    }

    protected function assertInstanceOf(string $class, mixed $actual, string $message = ''): void
    {
        $this->assertTrue($actual instanceof $class, $message !== '' ? $message : "Failed asserting instance of {$class}");
    }

    /** @param callable():void $fn */
    protected function assertThrows(string $exceptionClass, callable $fn, string $message = ''): void
    {
        $this->assertions++;
        try {
            $fn();
        } catch (\Throwable $e) {
            if (!$e instanceof $exceptionClass) {
                throw new AssertionFailed("Expected {$exceptionClass}, got " . $e::class . ": {$e->getMessage()}");
            }
            return;
        }
        throw new AssertionFailed($message !== '' ? $message : "Expected {$exceptionClass} to be thrown");
    }

    /**
     * Build a client->server frame exactly as a browser would: masked, with the
     * correct length encoding. Used to drive the parser under test.
     */
    protected function clientFrame(string $payload, int $opcode = Opcode::TEXT, bool $fin = true, ?string $mask = null): string
    {
        $mask ??= random_bytes(4);
        $b0 = ($fin ? 0x80 : 0x00) | ($opcode & 0x0F);
        $length = strlen($payload);

        if ($length <= 125) {
            $header = chr($b0) . chr(0x80 | $length);
        } elseif ($length <= 65535) {
            $header = chr($b0) . chr(0x80 | 126) . pack('n', $length);
        } else {
            $header = chr($b0) . chr(0x80 | 127) . pack('J', $length);
        }

        $masked = '';
        for ($i = 0; $i < $length; $i++) {
            $masked .= $payload[$i] ^ $mask[$i & 3];
        }

        return $header . $mask . $masked;
    }

    private function export(mixed $value): string
    {
        return match (true) {
            is_string($value) => "'" . $value . "'",
            is_bool($value)   => $value ? 'true' : 'false',
            is_null($value)   => 'null',
            is_scalar($value) => (string) $value,
            default           => gettype($value),
        };
    }
}

final class AssertionFailed extends \RuntimeException
{
}
