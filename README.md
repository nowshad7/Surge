<h1 align="center">⚡ Surge</h1>

<p align="center">
  <strong>A lightweight, self-hosted, Pusher-compatible realtime server for Laravel.</strong><br>
  Point Laravel Echo at your own server — no code changes, no per-message bills.
</p>

<p align="center">
  <a href="#quickstart">Quickstart</a> ·
  <a href="#use-it-with-laravel">Laravel</a> ·
  <a href="#how-it-works">How it works</a> ·
  <a href="#roadmap">Roadmap</a>
</p>

---

**Surge** speaks the Pusher WebSocket protocol, so it drops straight into an app
that already uses Laravel broadcasting and [Laravel Echo](https://laravel.com/docs/broadcasting).
Change the host and port in your config and your existing private/presence
channels, events, and client events keep working — now on infrastructure you own.

The core has **zero required dependencies**: the WebSocket layer, the Pusher
protocol, the HTTP trigger API, and even the Redis scaling client are written
against PHP's standard library.

## Why

- **Drop-in.** Pusher-protocol compatible. Keep Laravel Echo, keep your channels,
  keep your `broadcast()` calls. Just repoint them.
- **Self-hosted.** No usage caps, no per-message pricing, no third-party seeing
  your traffic.
- **Light.** The default *lite* runtime is pure PHP — one file to run, nothing to
  compile, runs anywhere PHP runs.
- **Honest.** Fails closed on malformed frames, verifies every signature in
  constant time, and never claims a capability it doesn't have (see
  [Status](#status)).

## Quickstart

```bash
composer require nsd7/surge
```

Run the server (Pusher-compatible mode is the default):

```bash
SURGE_APP_ID=my-app SURGE_APP_KEY=my-key SURGE_APP_SECRET=my-secret \
  vendor/bin/surge serve --port=9019
```

Connect from the browser with a plain WebSocket, or — more usefully — with
Laravel Echo (below).

## Use it with Laravel

Surge is a Laravel package: the service provider auto-registers and adds a
`surge:serve` command. Publish the config if you want to tweak it:

```bash
php artisan vendor:publish --tag=surge-config
```

**1. Point the broadcaster at Surge** in `config/broadcasting.php` (the built-in
`pusher` driver — no custom driver needed):

```php
'pusher' => [
    'driver' => 'pusher',
    'key'    => env('SURGE_APP_KEY'),
    'secret' => env('SURGE_APP_SECRET'),
    'app_id' => env('SURGE_APP_ID'),
    'options' => [
        'host'   => '127.0.0.1',
        'port'   => 9019,
        'scheme' => 'http',
        'useTLS' => false,
    ],
],
```

**2. Point Echo at Surge** in `resources/js/echo.js`:

```js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_SURGE_APP_KEY,
    wsHost: '127.0.0.1',
    wsPort: 9019,
    forceTLS: false,
    enabledTransports: ['ws'],
});
```

**3. Run the server and broadcast as usual:**

```bash
php artisan surge:serve
```

```php
broadcast(new OrderShipped($order)); // delivered over Surge
```

Private and presence channels authorize through your normal
`routes/channels.php` + `/broadcasting/auth` endpoint — Surge verifies the same
HMAC signature Laravel generates.

## How it works

Surge is layered so the socket loop, the protocol, and the transport are
independent:

```
          ┌──────────────────────────────────────────────┐
  HTTP ───▶│  Pusher HTTP API   │   Pusher WS protocol      │◀── WebSocket
  trigger  │  POST /apps/../events  subscribe · presence ·  │     (Laravel Echo)
           │                        client events           │
           └───────────────┬──────────────┬─────────────────┘
                           │              │
                   ┌───────┴──────┐  ┌────┴───────────────┐
                   │ Runtime (WS) │  │ Scaling (optional) │
                   │ lite: pure   │  │ Redis pub/sub over │
                   │ PHP select   │  │ raw RESP sockets   │
                   └──────────────┘  └────────────────────┘
```

- **RFC 6455 core** — buffered frame parser (partial reads, batched frames,
  16/64-bit lengths, masking), fragmented-message reassembly, ping/pong, close
  handling, and send backpressure. Rejects unmasked, oversized, or malformed
  frames instead of mangling them.
- **Pusher protocol** — `connection_established`, public/private/presence
  channels, `pusher:ping`/`pong`, and `client-*` events.
- **HTTP trigger API** — `POST /apps/{id}/events` on the same port, with Pusher
  REST signature verification matching `pusher-php-server`.
- **Scaling** — optional Redis pub/sub so multiple nodes relay events to each
  other.

## Configuration

Everything is env-driven (see `config/surge.php`):

| Variable | Default | Purpose |
| --- | --- | --- |
| `SURGE_HOST` / `SURGE_PORT` | `0.0.0.0` / `9019` | Bind address |
| `SURGE_APP_ID` / `_KEY` / `_SECRET` | — | App credentials (match broadcasting + Echo) |
| `SURGE_ACTIVITY_TIMEOUT` | `120` | Seconds before a client is asked to ping |
| `SURGE_CLIENT_EVENTS` | `true` | Allow `client-*` events on private/presence |
| `SURGE_SCALING_ENABLED` | `false` | Turn on Redis pub/sub scaling |

## Testing & benchmarks

The library ships a zero-dependency test runner — no PHPUnit needed:

```bash
php tests/run.php          # 50 tests
php bench/throughput.php   # local fan-out sanity check
```

The benchmark reliably fans events out to 100 concurrent clients on a single
machine; it's a correctness/sanity harness, **not** a tuned datacenter
benchmark (real throughput depends on your hardware and the test harness is
itself single-threaded).

## Status

Surge is young. What's real today, and what isn't:

**Working & tested**
- RFC 6455 WebSocket server (lite, pure-PHP runtime)
- Pusher protocol: public/private/presence channels, client events, ping/pong
- Pusher HTTP trigger API with REST signature verification
- Laravel package (service provider, config, `surge:serve`)
- Redis pub/sub event relay across nodes

**Honest caveats**
- The pure-PHP *lite* runtime is single-process (one core). It is **light, not
  the fastest** — see the roadmap for the planned multi-core *turbo* runtime.
- Cross-node **presence rosters** are per-node in the current scaling adapter;
  shared presence state is future work.
- There is **no TLS termination** built in — run Surge behind a TLS-terminating
  proxy (nginx, Caddy) for `wss://`.

## Roadmap

- [x] Phase 0 — Correct RFC 6455 core behind a runtime interface
- [x] Phase 1 — Pusher protocol (channels, presence, client events, auth)
- [x] Phase 2 — Pusher HTTP trigger API (same port)
- [x] Phase 3 — Laravel package (`surge:serve`, config, Echo compatibility)
- [x] Phase 4 — Redis pub/sub horizontal scaling
- [ ] Phase 5 — Multi-core *turbo* runtime (OpenSwoole), shared presence state,
      metrics endpoint, reproducible benchmarks vs Reverb
- [ ] TLS, connection metrics, structured logging, Prometheus export

## License

MIT © nowshad7
