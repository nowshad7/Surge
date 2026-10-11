# Laravel & Echo

Surge is a Laravel package. The service provider auto-registers and adds a
`surge:serve` command. Because Surge speaks the Pusher protocol, you use
Laravel's **built-in `pusher` broadcaster** and the standard Echo client — no
custom driver.

## 1. Publish the config (optional)

```bash
php artisan vendor:publish --tag=surge-config
```

This writes `config/surge.php`. See the [Configuration reference](/reference/configuration).

## 2. Set your credentials

In `.env`:

```ini
SURGE_APP_ID=my-app
SURGE_APP_KEY=my-key
SURGE_APP_SECRET=my-secret
SURGE_HOST=127.0.0.1
SURGE_PORT=9019

BROADCAST_CONNECTION=pusher
```

## 3. Point the broadcaster at Surge

In `config/broadcasting.php`, use the `pusher` driver with Surge's host/port in
`options`:

```php
'pusher' => [
    'driver' => 'pusher',
    'key'    => env('SURGE_APP_KEY'),
    'secret' => env('SURGE_APP_SECRET'),
    'app_id' => env('SURGE_APP_ID'),
    'options' => [
        'host'   => env('SURGE_HOST', '127.0.0.1'),
        'port'   => env('SURGE_PORT', 9019),
        'scheme' => 'http',
        'useTLS' => false,
    ],
],
```

This is what `pusher-php-server` uses to send your `broadcast()` events to
Surge's [HTTP trigger API](/guide/broadcasting).

## 4. Point Echo at Surge

In `resources/js/echo.js`:

```js
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher
window.Echo = new Echo({
  broadcaster: 'pusher',
  key: import.meta.env.VITE_SURGE_APP_KEY,
  wsHost: import.meta.env.VITE_SURGE_HOST ?? '127.0.0.1',
  wsPort: import.meta.env.VITE_SURGE_PORT ?? 9019,
  forceTLS: false,
  enabledTransports: ['ws'],
})
```

Expose the vars to Vite in `.env`:

```ini
VITE_SURGE_APP_KEY="${SURGE_APP_KEY}"
VITE_SURGE_HOST="${SURGE_HOST}"
VITE_SURGE_PORT="${SURGE_PORT}"
```

## 5. Run the server and broadcast

```bash
php artisan surge:serve
```

```php
// app/Events/OrderShipped.php implements ShouldBroadcast
broadcast(new OrderShipped($order));
```

On the frontend:

```js
Echo.channel('orders').listen('OrderShipped', (e) => {
  console.log('shipped', e.order)
})
```

That's it — the same broadcasting code you'd write for Pusher, now served by
Surge.

## Private & presence channels

These authorize through your normal `routes/channels.php` and the
`/broadcasting/auth` endpoint. Surge verifies the **same HMAC signature**
Laravel generates, so no extra setup is needed. See [Channels](/guide/channels).
