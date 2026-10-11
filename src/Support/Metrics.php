<?php

declare(strict_types=1);

namespace Nsd7\Surge\Support;

/**
 * A tiny in-process metrics registry. Counters only ever increase; gauges hold
 * a current value. `toPrometheus()` renders the standard text exposition format
 * served at GET /metrics.
 *
 * Dependency-free and deliberately minimal — just the handful of series an
 * operator actually watches for a realtime server.
 */
final class Metrics
{
    /** name => [type, help] */
    private const META = [
        'surge_connections_total'       => ['counter', 'Total WebSocket connections accepted'],
        'surge_connections_current'     => ['gauge',   'Currently open WebSocket connections'],
        'surge_channels_current'        => ['gauge',   'Currently active channels'],
        'surge_messages_received_total' => ['counter', 'Total messages received from clients'],
        'surge_messages_sent_total'     => ['counter', 'Total messages sent to clients'],
        'surge_events_published_total'  => ['counter', 'Total events published to channels'],
    ];

    /** @var array<string, int> */
    private array $values;

    public function __construct()
    {
        $this->values = array_fill_keys(array_keys(self::META), 0);
    }

    public function increment(string $name, int $by = 1): void
    {
        if (array_key_exists($name, $this->values)) {
            $this->values[$name] += $by;
        }
    }

    public function setGauge(string $name, int $value): void
    {
        if (array_key_exists($name, $this->values) && self::META[$name][0] === 'gauge') {
            $this->values[$name] = $value;
        }
    }

    /** @return array<string, int> */
    public function snapshot(): array
    {
        return $this->values;
    }

    /** Render the Prometheus text exposition format (version 0.0.4). */
    public function toPrometheus(): string
    {
        $lines = [];
        foreach (self::META as $name => [$type, $help]) {
            $lines[] = "# HELP {$name} {$help}";
            $lines[] = "# TYPE {$name} {$type}";
            $lines[] = "{$name} {$this->values[$name]}";
        }

        return implode("\n", $lines) . "\n";
    }
}
