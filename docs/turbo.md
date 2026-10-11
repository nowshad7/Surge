# The turbo runtime (OpenSwoole)

Surge ships two interchangeable runtimes behind one `Runtime` interface:

| Runtime | Backing | Processes | Dependencies | Status |
| --- | --- | --- | --- | --- |
| **lite** (default) | pure-PHP `stream_select` | single process, one core | none | supported |
| **turbo** | OpenSwoole | worker pool across all cores | `ext-openswoole` | **experimental / unverified** |

Both drive the **same** Pusher protocol and channel logic — only the socket
transport differs — because the protocol depends only on the `Client` interface,
which both `Connection` (lite) and `SwooleConnection` (turbo) implement.

## Honest status

The turbo runtime is **written but not yet load-tested or benchmarked**. It was
developed in an environment without `ext-openswoole`, so while it is covered by
unit tests for its guards and shares all protocol tests with the lite runtime,
its end-to-end behaviour on a real OpenSwoole host has not been verified. Until
this page says otherwise:

- Treat "scales across cores / faster than a single event loop" as the **design
  goal**, not a measured result.
- Use the **lite** runtime for anything you depend on.

## Running it

Install the extension (see the [OpenSwoole docs](https://openswoole.com/)):

```sh
pecl install openswoole
```

Then select the runtime:

```sh
# standalone
SURGE_RUNTIME=turbo vendor/bin/surge serve

# Laravel
php artisan surge:serve --runtime=turbo
```

If the extension is missing, Surge fails fast with a clear message rather than
pretending to start.

## What still needs doing before turbo is "supported"

- [ ] End-to-end verification on an OpenSwoole host (handshake, channels,
      presence, client events, HTTP trigger).
- [ ] A reproducible benchmark comparing lite vs turbo vs Laravel Reverb, with
      the methodology published alongside the numbers.
- [ ] Worker-shared state for presence across workers (OpenSwoole workers do not
      share memory; presence currently assumes a single worker owns a channel).
