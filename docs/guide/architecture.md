# Architecture

Surge is layered so the socket loop, the protocol, and the transport are
independent. The protocol depends only on a small `Client` interface, which both
runtimes implement — that's what makes the lite/turbo split clean.

```
          ┌──────────────────────────────────────────────┐
  HTTP ──▶ │  Pusher HTTP API   │   Pusher WS protocol      │ ◀── WebSocket
  trigger  │  POST /apps/../events  subscribe · presence ·  │     (Laravel Echo)
           │                        client events           │
           └───────────────┬──────────────┬─────────────────┘
                           │              │
                   ┌───────┴──────┐  ┌────┴───────────────┐
                   │ Runtime (WS) │  │ Scaling (optional) │
                   │ lite: pure   │  │ Redis pub/sub over │
                   │ PHP select   │  │ raw RESP sockets   │
                   │ turbo: swoole│  │                    │
                   └──────────────┘  └────────────────────┘
```

## Layers

**Protocol core — `src/Protocol`, `src/WebSocket`**
RFC 6455 implementation: a buffering `FrameParser` (partial reads, batched
frames, 16/64-bit lengths, masking), the `Handshake`, and a per-connection state
machine (`Connection`) that reassembles fragmented messages, answers
ping/pong/close, and buffers outgoing bytes for backpressure. It rejects
unmasked, oversized, or malformed frames rather than mangling them.

**Pusher protocol — `src/Pusher`**
`PusherProtocol` implements the transport-agnostic `EventHandler`:
`connection_established`, subscribe/unsubscribe, channels
(`Channel`/`PrivateChannel`/`PresenceChannel` + `ChannelManager`), `pusher:ping`,
`client-*` events, and a `publish()` entry point for server-side events.
`Signature` handles both channel auth and REST-request auth.

**HTTP API — `src/Http`**
`PusherHttpApi` serves the trigger endpoint and health checks. Any non-upgrade
request on the Surge port is routed here.

**Runtimes — `src/Runtime`**
`LiteRuntime` (pure-PHP `stream_select`) and the experimental `SwooleRuntime`
(OpenSwoole), both implementing `Runtime`. A connection is a `Client`:
`Connection` for lite, `SwooleConnection` for turbo.

**Scaling — `src/Scaling`**
`ScalingAdapter` with a no-op `LocalAdapter` (default) and a `RedisAdapter` that
speaks RESP over raw sockets (`Resp`). `RelayMessage` is the wire format.

**Laravel — `src/Laravel`**
The service provider and `surge:serve` command. This is the only part that
depends on `illuminate/*`; the core stays dependency-free.

## Design principles

- **Fail closed.** Missing, malformed, or unauthenticated input is never treated
  as valid.
- **Dependency-free core.** Everything in `src/` except the Laravel integration
  uses only PHP's standard library.
- **Honest.** Capabilities that aren't verified (the turbo runtime, cross-node
  presence) are documented as such, never claimed complete.

See the [source on GitHub](https://github.com/nowshad7/Surge).
