<?php

declare(strict_types=1);

namespace Nsd7\Surge\Support;

/**
 * A small leveled logger with two output formats: human-readable "text" (the
 * default, for a terminal) and structured "json" (one JSON object per line, for
 * log aggregation). Dependency-free.
 *
 * It is also usable as the runtime's plain `callable(string)` logger via
 * asCallable(), so existing call sites keep working.
 */
final class Logger
{
    public const TEXT = 'text';
    public const JSON = 'json';

    /** @var callable(string):void */
    private $sink;

    public function __construct(
        private readonly string $format = self::TEXT,
        ?callable $sink = null,
    ) {
        $this->sink = $sink ?? static function (string $line): void {
            fwrite(STDOUT, $line . "\n");
        };
    }

    /** @param array<string,mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public function log(string $level, string $message, array $context = []): void
    {
        ($this->sink)($this->format($level, $message, $context));
    }

    /** A plain callable(string) that logs at info level — for the runtime. */
    public function asCallable(): callable
    {
        return fn (string $message) => $this->info($message);
    }

    /** @param array<string,mixed> $context */
    private function format(string $level, string $message, array $context): string
    {
        if ($this->format === self::JSON) {
            return json_encode([
                'ts'      => date('c'),
                'level'   => $level,
                'message' => $message,
                'context' => (object) $context,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $suffix = $context === [] ? '' : ' ' . json_encode($context, JSON_UNESCAPED_SLASHES);

        return sprintf('[%s] %s: %s%s', date('Y-m-d H:i:s'), strtoupper($level), $message, $suffix);
    }
}
