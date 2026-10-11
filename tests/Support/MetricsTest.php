<?php

declare(strict_types=1);

namespace Nsd7\Surge\Tests\Support;

use Nsd7\Surge\Support\Metrics;
use Nsd7\Surge\Tests\TestCase;

final class MetricsTest extends TestCase
{
    public function testIncrementsCounters(): void
    {
        $m = new Metrics();
        $m->increment('surge_connections_total');
        $m->increment('surge_connections_total', 2);

        $this->assertSame(3, $m->snapshot()['surge_connections_total']);
    }

    public function testUnknownMetricIsIgnored(): void
    {
        $m = new Metrics();
        $m->increment('surge_not_a_metric'); // must not error or appear
        $this->assertFalse(array_key_exists('surge_not_a_metric', $m->snapshot()));
    }

    public function testSetGaugeOnlyAppliesToGauges(): void
    {
        $m = new Metrics();
        $m->setGauge('surge_connections_current', 5);
        $m->setGauge('surge_connections_total', 99); // a counter — ignored

        $this->assertSame(5, $m->snapshot()['surge_connections_current']);
        $this->assertSame(0, $m->snapshot()['surge_connections_total']);
    }

    public function testRendersPrometheusFormat(): void
    {
        $m = new Metrics();
        $m->increment('surge_connections_total', 7);
        $out = $m->toPrometheus();

        $this->assertTrue(str_contains($out, '# HELP surge_connections_total'));
        $this->assertTrue(str_contains($out, '# TYPE surge_connections_total counter'));
        $this->assertTrue(str_contains($out, "surge_connections_total 7\n"));
        $this->assertTrue(str_contains($out, '# TYPE surge_connections_current gauge'));
    }
}
