# Scaling with Redis

One Surge node holds its connections in memory. To run **several nodes** behind
a load balancer — for more connections or redundancy — they need to share
events: an event triggered on node A must reach subscribers connected to node B.
Surge does this with a Redis pub/sub relay.

## How it works

```
         publish                          publish
  node A ─────────▶ Redis channel ◀───────────── node B
    │                   │  │                         │
    │   subscribers     │  └── relayed event ───────▶ subscribers
    └───────────────────┘
```

- Each node **PUBLISHes** every event it fans out to a shared Redis channel.
- Each node holds a **SUBSCRIBE** connection it drains every loop tick, replaying
  peer events to its own local subscribers.
- Every message is stamped with the origin node id, so a node ignores its own
  echoes and replays never re-relay (no loops).

The adapter speaks **RESP over raw sockets** — it does not require `ext-redis`,
keeping the dependency-free promise intact.

## Enabling it

In `config/surge.php` (or via env):

```ini
SURGE_SCALING_ENABLED=true
SURGE_REDIS_HOST=127.0.0.1
SURGE_REDIS_PORT=6379
SURGE_REDIS_PREFIX=surge
```

Then run a node as usual on each host:

```bash
php artisan surge:serve
```

Each node logs its node id on startup when scaling is enabled.

## What is relayed

- Server-published events (your `broadcast()` calls via the HTTP API).
- Client (`client-`) events on private/presence channels.
- **Presence membership** — joins and leaves propagate across nodes, and rosters
  include members from every node.

## Presence across nodes

Presence is **eventually consistent** across the cluster:

- When a member joins or leaves on one node, a `member_added` / `member_removed`
  is relayed so subscribers on **every** node are notified.
- When a node's first subscriber joins a presence channel, it asks peers to
  re-announce their members, so its roster converges to the full cluster roster.

::: tip Convergence window
A brand-new node's very first `subscription_succeeded` roster reflects the
members it knows at that instant; any members it hasn't learned yet arrive
moments later as `member_added` events (so Echo's `.joining()` still fires).
In practice this window is a single relay round-trip.
:::

A load balancer in front of multiple nodes should use **sticky sessions** (or
any stable hashing) so a client's WebSocket stays on one node for its lifetime.

Next: [Deployment](/guide/deployment).
