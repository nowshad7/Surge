# Contributing to Surge

Thanks for helping build a lightweight, honest realtime server. 💙

## Ground rules

- **The core stays dependency-free.** The WebSocket layer, Pusher protocol,
  HTTP API, and Redis client all use PHP's standard library. New runtime
  dependencies in `src/` are rejected; the Laravel integration (which needs
  `illuminate/*`) is the one allowed exception and is isolated under
  `src/Laravel/`.
- **Fail closed.** Malformed, oversized, duplicate, or unauthenticated input
  must never be treated as valid. If you touch the frame parser, protocol, or
  signature code, add tests for the bad-input cases.
- **Be honest in docs and messages.** Don't describe the pure-PHP runtime as
  multi-core, call the local server a sandbox, or publish benchmark numbers you
  didn't measure. If something is future work, say so.

## Developing

```sh
php tests/run.php                 # unit suite (no dependencies)
php tests/integration/smoke.php   # boots a server, end-to-end check
php bench/throughput.php          # local fan-out sanity harness
```

Every PHP file must pass `php -l`. Keep code `declare(strict_types=1)` and match
the surrounding style.

## Layout

| Path | Responsibility |
| --- | --- |
| `src/Protocol` | RFC 6455 frames, parser, handshake |
| `src/WebSocket` | Per-connection state machine |
| `src/Runtime` | Event-loop backends (lite; turbo is planned) |
| `src/Pusher` | Pusher protocol, channels, signatures |
| `src/Http` | HTTP trigger API |
| `src/Scaling` | Multi-node relay (Redis over raw RESP) |
| `src/Laravel` | Laravel service provider + `surge:serve` |

## Pull requests

- One focused change per PR, with tests.
- Describe what you changed and why; note anything you couldn't verify.
- A change isn't done until `php tests/run.php` and the smoke test pass.
