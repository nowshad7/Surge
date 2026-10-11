# Channels

Surge implements the three Pusher channel types. Which one you get is decided by
the channel name prefix — exactly as Pusher and Echo expect.

| Prefix | Type | Authorized? | Membership |
| --- | --- | --- | --- |
| *(none)* | Public | No | — |
| `private-` | Private | Yes | — |
| `private-encrypted-` | Private | Yes | — |
| `presence-` | Presence | Yes | Yes |

## Public channels

No authorization. Anyone who can reach the server can subscribe.

```js
Echo.channel('orders').listen('OrderShipped', (e) => { /* ... */ })
```

Under the hood the client sends `pusher:subscribe` with the channel name, and
Surge replies `pusher_internal:subscription_succeeded`.

## Private channels

Private channels require a signed token. With Laravel + Echo you don't write any
of this by hand — `Echo.private('orders.1')` calls your `/broadcasting/auth`
endpoint, Laravel signs the subscription, and Surge verifies it.

Authorize the channel in `routes/channels.php` as usual:

```php
Broadcast::channel('orders.{orderId}', function ($user, $orderId) {
    return $user->id === Order::find($orderId)->user_id;
});
```

```js
Echo.private('orders.1').listen('OrderShipped', (e) => { /* ... */ })
```

Surge verifies the token (`{app_key}:{hmac_sha256("{socket_id}:{channel}", app_secret)}`)
in constant time. A bad signature gets a `pusher:subscription_error`, never a
silent pass.

## Presence channels

Presence channels are private channels that also track **who** is subscribed.
The signed `channel_data` carries a `user_id` and arbitrary `user_info`.

```php
Broadcast::channel('room.{roomId}', function ($user, $roomId) {
    // Returning an array joins the presence set.
    return ['id' => $user->id, 'name' => $user->name];
});
```

```js
Echo.join('room.42')
  .here((members) => console.log('present:', members))
  .joining((member) => console.log('joined:', member))
  .leaving((member) => console.log('left:', member))
```

Surge sends:

- `pusher_internal:subscription_succeeded` to the joining client, with the full
  roster: `{ presence: { ids, hash, count } }`.
- `pusher_internal:member_added` to everyone else — **once per `user_id`**, so a
  user with two tabs doesn't appear twice.
- `pusher_internal:member_removed` when a user's **last** connection leaves.

::: tip Cross-node presence
With [Redis scaling](/guide/scaling) enabled, presence works across nodes:
joins/leaves propagate to subscribers on every node and rosters include members
from the whole cluster. It's **eventually consistent** — see
[Presence across nodes](/guide/scaling#presence-across-nodes).
:::

Next: [Client events](/guide/client-events).
