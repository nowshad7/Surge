<?php

declare(strict_types=1);

namespace Nsd7\Surge\Scaling;

/**
 * A message relayed between Surge nodes. `origin` is the publishing node's id,
 * used to drop a node's own echoes.
 *
 * `kind` distinguishes what the message carries:
 *   - "event"           — an application event to fan out (channel/event/data)
 *   - "presence_add"    — a presence member joined on the origin node
 *   - "presence_remove" — a presence member left the origin node
 *   - "presence_sync"   — a node asks peers to re-announce their members
 */
final class RelayMessage
{
    public const EVENT = 'event';
    public const PRESENCE_ADD = 'presence_add';
    public const PRESENCE_REMOVE = 'presence_remove';
    public const PRESENCE_SYNC = 'presence_sync';

    public function __construct(
        public readonly string $origin,
        public readonly string $channel,
        public readonly ?string $event,
        public readonly mixed $data = null,
        public readonly ?string $exceptSocketId = null,
        public readonly string $kind = self::EVENT,
    ) {
    }

    public static function event(string $origin, string $channel, string $event, mixed $data, ?string $exceptSocketId = null): self
    {
        return new self($origin, $channel, $event, $data, $exceptSocketId, self::EVENT);
    }

    public static function presence(string $origin, string $kind, string $channel, mixed $data = null): self
    {
        return new self($origin, $channel, null, $data, null, $kind);
    }

    public function toJson(): string
    {
        return json_encode([
            'origin'  => $this->origin,
            'kind'    => $this->kind,
            'channel' => $this->channel,
            'event'   => $this->event,
            'data'    => $this->data,
            'except'  => $this->exceptSocketId,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function fromJson(string $json): ?self
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded) || !isset($decoded['channel'], $decoded['origin'])) {
            return null;
        }

        return new self(
            (string) $decoded['origin'],
            (string) $decoded['channel'],
            isset($decoded['event']) ? (string) $decoded['event'] : null,
            $decoded['data'] ?? null,
            isset($decoded['except']) ? (string) $decoded['except'] : null,
            isset($decoded['kind']) ? (string) $decoded['kind'] : self::EVENT,
        );
    }
}
