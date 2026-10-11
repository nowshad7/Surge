# Configuration reference

Everything is env-driven. In Laravel, publish `config/surge.php` to override
defaults:

```bash
php artisan vendor:publish --tag=surge-config
```

## Options

| Config key | Env var | Default | Description |
| --- | --- | --- | --- |
| `host` | `SURGE_HOST` | `0.0.0.0` | Bind address |
| `port` | `SURGE_PORT` | `9019` | Bind port (WebSocket **and** HTTP API) |
| `app.id` | `SURGE_APP_ID` → `PUSHER_APP_ID` | `surge` | App id in the trigger URL |
| `app.key` | `SURGE_APP_KEY` → `PUSHER_APP_KEY` | `surge-key` | Public key used by Echo and the WS path |
| `app.secret` | `SURGE_APP_SECRET` → `PUSHER_APP_SECRET` | `surge-secret` | Shared secret for signatures |
| `activity_timeout` | `SURGE_ACTIVITY_TIMEOUT` | `120` | Seconds before a client is asked to ping |
| `client_events` | `SURGE_CLIENT_EVENTS` | `true` | Allow `client-*` events on private/presence |
| `limits.max_connections` | `SURGE_MAX_CONNECTIONS` | `10000` | Max concurrent connections (lite) |
| `limits.max_message_bytes` | `SURGE_MAX_MESSAGE_BYTES` | `10485760` | Max single message size (10 MiB) |
| `scaling.enabled` | `SURGE_SCALING_ENABLED` | `false` | Turn on Redis pub/sub scaling |
| `scaling.redis.host` | `SURGE_REDIS_HOST` | `127.0.0.1` | Redis host |
| `scaling.redis.port` | `SURGE_REDIS_PORT` | `6379` | Redis port |
| `scaling.redis.prefix` | `SURGE_REDIS_PREFIX` | `surge` | Key/channel prefix |

The runtime (`lite` / `turbo`) is selected at launch, not in the config file —
see the [CLI reference](/reference/cli) (`--runtime` / `SURGE_RUNTIME`).

## The published config file

```php
return [
    'host' => env('SURGE_HOST', '0.0.0.0'),
    'port' => (int) env('SURGE_PORT', 9019),

    'app' => [
        'id'     => env('SURGE_APP_ID', env('PUSHER_APP_ID', 'surge')),
        'key'    => env('SURGE_APP_KEY', env('PUSHER_APP_KEY', 'surge-key')),
        'secret' => env('SURGE_APP_SECRET', env('PUSHER_APP_SECRET', 'surge-secret')),
    ],

    'activity_timeout' => (int) env('SURGE_ACTIVITY_TIMEOUT', 120),
    'client_events'    => (bool) env('SURGE_CLIENT_EVENTS', true),

    'limits' => [
        'max_connections'   => (int) env('SURGE_MAX_CONNECTIONS', 10000),
        'max_message_bytes' => (int) env('SURGE_MAX_MESSAGE_BYTES', 10 * 1024 * 1024),
    ],

    'scaling' => [
        'enabled' => (bool) env('SURGE_SCALING_ENABLED', false),
        'redis'   => [
            'host'   => env('SURGE_REDIS_HOST', '127.0.0.1'),
            'port'   => (int) env('SURGE_REDIS_PORT', 6379),
            'prefix' => env('SURGE_REDIS_PREFIX', 'surge'),
        ],
    ],
];
```

::: tip Match your credentials
`app.id`, `app.key`, and `app.secret` must match the `pusher` connection in
`config/broadcasting.php` and the Echo config on the frontend.
:::
