<?php

declare(strict_types=1);

namespace Nsd7\Surge\Scaling;

/**
 * An event relayed between Surge nodes so a publish on one node reaches
 * subscribers connected to another. `origin` is the publishing node's id, used
 * to drop a node's own echoes.
 */
final class RelayMessage
{
    public function __construct(
        public readonly string $origin,
        public readonly string $channel,
        public readonly string $event,
        public readonly mixed $data,
        public readonly ?string $exceptSocketId = null,
    ) {
    }

    public function toJson(): string
    {
        return json_encode([
            'origin'  => $this->origin,
            'channel' => $this->channel,
            'event'   => $this->event,
            'data'    => $this->data,
            'except'  => $this->exceptSocketId,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function fromJson(string $json): ?self
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded) || !isset($decoded['channel'], $decoded['event'], $decoded['origin'])) {
            return null;
        }

        return new self(
            (string) $decoded['origin'],
            (string) $decoded['channel'],
            (string) $decoded['event'],
            $decoded['data'] ?? null,
            isset($decoded['except']) ? (string) $decoded['except'] : null,
        );
    }
}
