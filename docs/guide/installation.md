# Installation

## Requirements

- **PHP 8.1+**
- Extensions: `ext-json`, `ext-sockets` (both are in a standard PHP build)

Optional:

- `ext-redis` is **not** required — Surge's Redis scaling adapter speaks RESP
  over raw sockets.
- `ext-openswoole` only if you want the experimental [turbo runtime](/guide/runtimes#turbo).

## Install with Composer

```bash
composer require nsd7/surge
```

In a Laravel app the service provider is auto-discovered — there's nothing else
to register.

## Without Composer

Surge ships a standalone autoloader, so it runs from a clone with just PHP:

```bash
git clone https://github.com/nowshad7/Surge.git
cd Surge
php bin/surge serve
```

## Verify it runs

Start the server in Pusher-compatible mode (the default):

```bash
SURGE_APP_ID=my-app SURGE_APP_KEY=my-key SURGE_APP_SECRET=my-secret \
  php bin/surge serve --port=9019
```

You should see:

```
[2026-01-01 12:00:00] Starting Surge (Pusher-compatible, lite runtime) app 'my-app' on ws://0.0.0.0:9019
```

Health check from another terminal:

```bash
curl http://127.0.0.1:9019/health
# {"status":"ok","channels":0}
```

## Run the tests

The library ships a zero-dependency test runner — no PHPUnit needed:

```bash
php tests/run.php                 # unit suite
php tests/integration/smoke.php   # boots a server, end-to-end check
```

Next: [Quickstart](/guide/quickstart).
