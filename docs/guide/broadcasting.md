# Broadcasting events

Your backend publishes events to Surge over HTTP — the same **Pusher REST API**
that `pusher-php-server` (and therefore Laravel's `pusher` broadcaster) already
speaks. Surge serves this endpoint on the **same port** as the WebSocket server;
any request that isn't a WebSocket upgrade is handled as HTTP.

## From Laravel

Once [configured](/guide/laravel), you broadcast exactly as you would with
Pusher:

```php
broadcast(new OrderShipped($order));
broadcast(new OrderShipped($order))->toOthers(); // exclude the sender's socket
```

Nothing Surge-specific appears in your application code.

## The trigger endpoint

```
POST /apps/{app_id}/events
```

Request body (JSON):

```json
{
  "name": "OrderShipped",
  "channels": ["orders", "private-orders.1"],
  "data": "{\"id\":42}",
  "socket_id": "12345.67890"
}
```

- `name` — the event name clients listen for.
- `channels` (array) or `channel` (string) — where to publish.
- `data` — a **string** (Pusher double-encodes the payload); Laravel sends it
  pre-encoded.
- `socket_id` — optional; excludes one connection (this is how `->toOthers()`
  works).

A successful trigger returns `200 {}`.

## Authentication

Every trigger request is signed with a Pusher REST signature and Surge verifies
it in constant time before doing anything:

- `auth_key` must match the app key.
- `body_md5` must match `md5(body)`.
- `auth_signature` must equal
  `hmac_sha256("{METHOD}\n{path}\n{sorted auth params}", app_secret)`.

An invalid signature returns `401` — a missing-evidence request is never
treated as a valid publish.

## Publishing without Laravel

Any Pusher-compatible server library works, or you can sign a request yourself —
see `examples/publish.php` in the repo for a dependency-free reference
implementation.

## Health check

```
GET /health   →   {"status":"ok","channels":<n>}
```

`GET /`, `/up`, and `/health` all return the same payload — handy for load
balancers and orchestrators.

Next: choose a [runtime](/guide/runtimes) or [scale out](/guide/scaling).
