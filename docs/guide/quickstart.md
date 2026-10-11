# Quickstart

This walks through a realtime round-trip **without a browser**, so you can see
every moving part. For the full Laravel + Echo setup, see
[Laravel & Echo](/guide/laravel).

## 1. Start the server

```bash
SURGE_APP_ID=surge SURGE_APP_KEY=surge-key SURGE_APP_SECRET=surge-secret \
  php bin/surge serve --port=9019
```

## 2. Subscribe from the browser

Open `examples/browser.html` (bundled in the repo), or drop this into any page:

```js
const ws = new WebSocket('ws://127.0.0.1:9019/app/surge-key')

ws.onmessage = (e) => {
  const msg = JSON.parse(e.data)
  if (msg.event === 'pusher:connection_established') {
    ws.send(JSON.stringify({ event: 'pusher:subscribe', data: { channel: 'demo' } }))
  } else {
    console.log(msg.event, msg.data)
  }
}
```

## 3. Publish an event

The bundled `examples/publish.php` sends a **signed** request to the HTTP
trigger API — exactly the way a Laravel backend does:

```bash
php examples/publish.php "Hello from Surge"
```

Your browser logs the `DemoMessage` event on the `demo` channel. That's the
whole loop: a client subscribes over WebSocket, your backend triggers an event
over HTTP, Surge fans it out.

## What just happened

```
browser ──ws subscribe──▶  Surge  ◀──signed HTTP POST── your backend
   ▲                         │
   └──────── event ──────────┘
```

- The WebSocket endpoint is `ws://host:port/app/{app-key}`.
- The HTTP trigger endpoint is `POST /apps/{app-id}/events`, on the **same
  port**, authenticated with a Pusher REST signature.

Next: wire it to [Laravel & Echo](/guide/laravel).
