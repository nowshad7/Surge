# CLI reference

Surge can be launched two ways: the standalone `surge` binary, or the Laravel
Artisan command. Both start the same server.

## Standalone: `surge serve`

```bash
vendor/bin/surge serve [options]
# from a clone:
php bin/surge serve [options]
```

| Option | Env var | Default | Description |
| --- | --- | --- | --- |
| `--host=` | `SURGE_HOST` | `0.0.0.0` | Bind address |
| `--port=` | `SURGE_PORT` | `9019` | Bind port |
| `--handler=` | `SURGE_HANDLER` | `pusher` | `pusher` (Echo-compatible) or `broadcast` (relay-all) |
| `--runtime=` | `SURGE_RUNTIME` | `lite` | `lite` (pure PHP) or `turbo` (OpenSwoole) |
| `--app-id=` | `SURGE_APP_ID` | `surge` | App id (pusher handler) |
| `--key=` | `SURGE_APP_KEY` | `surge-key` | App key (pusher handler) |
| `--secret=` | `SURGE_APP_SECRET` | `surge-secret` | App secret (pusher handler) |

Examples:

```bash
# Pusher-compatible server (default)
SURGE_APP_SECRET=s3cr3t php bin/surge serve --app-id=my-app --key=my-key --port=9019

# Simple broadcast relay (every message echoed to all other clients)
php bin/surge serve --handler=broadcast

# Experimental multi-core runtime
SURGE_RUNTIME=turbo php bin/surge serve
```

## Laravel: `php artisan surge:serve`

Reads everything from `config/surge.php`, so you usually pass no options.

```bash
php artisan surge:serve [--host=] [--port=] [--runtime=lite|turbo]
```

| Option | Default | Description |
| --- | --- | --- |
| `--host=` | config `host` | Override the bind address |
| `--port=` | config `port` | Override the bind port |
| `--runtime=` | `lite` | `lite` or `turbo` |

```bash
php artisan surge:serve
php artisan surge:serve --port=9020
php artisan surge:serve --runtime=turbo
```

Both entry points handle `SIGINT`/`SIGTERM` for a clean shutdown, so they behave
well under systemd or Supervisor — see [Deployment](/guide/deployment).

## The `broadcast` handler

`--handler=broadcast` runs a dependency-free relay: every message a client sends
is forwarded to all other connected clients, with no Pusher protocol, channels,
or auth. It's useful for quick demos and as a reference `EventHandler`; for
Laravel, use the default `pusher` handler.
