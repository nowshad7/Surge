# Introduction

**Surge** is a lightweight, self-hosted, **Pusher-compatible** realtime
broadcasting server for Laravel. It speaks the Pusher WebSocket protocol, so it
drops straight into an app that already uses Laravel broadcasting and
[Laravel Echo](https://laravel.com/docs/broadcasting) — you change the host and
port in your config and your existing private/presence channels, events, and
client events keep working, now on infrastructure you own.

The core has **zero required dependencies**: the WebSocket layer, the Pusher
protocol, the HTTP trigger API, and even the Redis scaling client are written
against PHP's standard library.

## Why it exists

Hosted realtime (Pusher) is easy but metered and third-party. Self-hosted
options exist, but Surge aims for a specific combination:

- **Drop-in.** Keep Laravel Echo, keep your channels, keep your `broadcast()`
  calls — just repoint them.
- **Light.** The default *lite* runtime is one pure-PHP process. Nothing to
  compile, runs anywhere PHP runs.
- **Honest.** It fails closed on malformed input and never claims a capability
  it doesn't have.

## What's real today

Surge is young. Here is the current, honest status:

<p>
  <span class="surge-badge surge-badge--ok">stable</span>
  RFC 6455 WebSocket server · lite (pure-PHP) runtime
</p>
<p>
  <span class="surge-badge surge-badge--ok">stable</span>
  Pusher protocol — public / private / presence channels, client events, ping/pong
</p>
<p>
  <span class="surge-badge surge-badge--ok">stable</span>
  Pusher HTTP trigger API with REST signature verification
</p>
<p>
  <span class="surge-badge surge-badge--ok">stable</span>
  Laravel package (service provider, config, <code>surge:serve</code>)
</p>
<p>
  <span class="surge-badge surge-badge--ok">stable</span>
  Redis pub/sub event relay across nodes
</p>
<p>
  <span class="surge-badge surge-badge--exp">experimental</span>
  Multi-core <em>turbo</em> runtime (OpenSwoole) — not yet load-tested
</p>

## Honest caveats

- The pure-PHP *lite* runtime is single-process (one core). It is **light, not
  the fastest**. The multi-core [*turbo* runtime](/guide/runtimes#turbo) is
  included but experimental.
- Cross-node **presence rosters** are per-node in the current
  [scaling adapter](/guide/scaling).
- There is **no TLS termination** built in — run Surge behind a TLS-terminating
  proxy. See [Deployment](/guide/deployment).

## Next steps

- [Installation](/guide/installation)
- [Quickstart](/guide/quickstart)
- [Laravel & Echo setup](/guide/laravel)
