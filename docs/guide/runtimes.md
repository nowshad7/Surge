# Runtimes

Surge separates *what* it does (the Pusher protocol) from *how* it moves bytes
(the runtime). Both runtimes drive the exact same protocol and channel logic
through a single `Runtime` interface, so switching them changes nothing about
behavior.

| Runtime | Backing | Processes | Dependencies | Status |
| --- | --- | --- | --- | --- |
| **lite** (default) | pure-PHP `stream_select` | single process, one core | none | <span class="surge-badge surge-badge--ok">stable</span> |
| **turbo** | OpenSwoole | worker pool, all cores | `ext-openswoole` | <span class="surge-badge surge-badge--exp">experimental</span> |

## Lite (default)

A non-blocking `stream_select` event loop in a single PHP process. It runs
anywhere PHP runs, with no extensions beyond a standard build, and handles
buffered reads, backpressure, keepalive pings, and idle timeouts itself.

It is **light, not the fastest** — one process uses one core. For most apps
that's plenty; when you need more, run several nodes behind a load balancer with
[Redis scaling](/guide/scaling), or reach for turbo.

Select it explicitly (it's already the default):

```bash
php artisan surge:serve --runtime=lite
# or
SURGE_RUNTIME=lite php bin/surge serve
```

## Turbo {#turbo}

::: warning Experimental — not yet load-tested
The turbo runtime is written and shares all protocol tests with lite, but it was
developed without `ext-openswoole` available and has **not been load-tested or
benchmarked**. Treat "scales across cores / faster" as the design goal, not a
measured result, and keep lite for anything you depend on.
:::

Where lite is one process on one core, OpenSwoole runs a pool of worker
processes across all cores. It drives the identical `EventHandler` and Pusher
protocol — OpenSwoole just owns the socket transport (and performs the WebSocket
framing itself, so Surge hands it already-encoded application messages).

Install the extension, then select the runtime:

```bash
pecl install openswoole

SURGE_RUNTIME=turbo php bin/surge serve
# or
php artisan surge:serve --runtime=turbo
```

If `ext-openswoole` is missing, Surge fails fast with a clear message rather
than pretending to start.

### Known turbo limitations

- Not yet verified end-to-end on a real OpenSwoole host.
- No published benchmark yet (methodology will ship with the numbers).
- OpenSwoole workers don't share memory, so presence currently assumes a single
  worker owns a channel.

Next: [Scaling with Redis](/guide/scaling).
