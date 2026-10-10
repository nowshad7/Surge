<?php

declare(strict_types=1);

namespace Nsd7\Surge\Pusher;

/**
 * Builds Pusher-protocol wire messages.
 *
 * On the wire the envelope is { "event": ..., "channel"?: ..., "data": <string> }
 * where `data` is itself a JSON-encoded string (Pusher double-encodes it, and
 * Laravel Echo expects exactly that). These helpers keep that contract in one
 * place.
 */
final class Message
{
    public static function encode(string $event, mixed $data = null, ?string $channel = null): string
    {
        $envelope = ['event' => $event];
        if ($channel !== null) {
            $envelope['channel'] = $channel;
        }
        // Pass through an already-encoded string as-is; otherwise JSON-encode.
        $envelope['data'] = is_string($data) ? $data : json_encode($data === null ? (object) [] : $data);

        return json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function connectionEstablished(string $socketId, int $activityTimeout): string
    {
        return self::encode('pusher:connection_established', [
            'socket_id'        => $socketId,
            'activity_timeout' => $activityTimeout,
        ]);
    }

    public static function error(string $message, ?int $code = null): string
    {
        $data = ['message' => $message];
        if ($code !== null) {
            $data['code'] = $code;
        }

        return self::encode('pusher:error', $data);
    }

    public static function pong(): string
    {
        return self::encode('pusher:pong');
    }
}
