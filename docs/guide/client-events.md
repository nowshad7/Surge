# Client events

Client events let one subscriber send a message directly to the others on a
channel without a round-trip through your backend — ideal for ephemeral signals
like "user is typing".

## Rules

Surge enforces the same constraints as Pusher:

- The event name **must start with `client-`**.
- They are only allowed on **private** and **presence** channels (never public).
- The sender must be **subscribed** to the channel.
- They are **not echoed back** to the sender.

If client events are disabled (see below) or the rules aren't met, Surge drops
the event — a `client-` event is never delivered to an unauthorized channel.

## Sending

With Echo:

```js
const channel = Echo.private('chat.1')

channel.whisper('typing', { user: 'Ann' })

channel.listenForWhisper('typing', (e) => {
  console.log(`${e.user} is typing…`)
})
```

## Fan-out across nodes

When [Redis scaling](/guide/scaling) is enabled, client events are relayed to
peer nodes too, so subscribers connected to a different node also receive them.

## Disabling

Set `client_events` to `false` in `config/surge.php` (or `SURGE_CLIENT_EVENTS=false`)
to reject all `client-` events. A rejected event returns a `pusher:error` to the
sender.

Next: [Broadcasting events from your backend](/guide/broadcasting).
