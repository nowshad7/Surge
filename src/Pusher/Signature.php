<?php

declare(strict_types=1);

namespace Nsd7\Surge\Pusher;

/**
 * HMAC signing compatible with Pusher / Laravel broadcasting auth.
 *
 * Laravel's BroadcastController signs private/presence subscriptions as
 * "{app_key}:{hmac_sha256(payload, app_secret)}" where the payload is
 * "{socket_id}:{channel}" (private) or "{socket_id}:{channel}:{channel_data}"
 * (presence). Surge must verify exactly that so existing Laravel apps and Echo
 * work with no changes.
 */
final class Signature
{
    public function __construct(
        private readonly string $appKey,
        private readonly string $appSecret,
    ) {
    }

    public function channelToken(string $socketId, string $channel, ?string $channelData = null): string
    {
        $payload = $channelData === null
            ? "{$socketId}:{$channel}"
            : "{$socketId}:{$channel}:{$channelData}";

        return $this->appKey . ':' . hash_hmac('sha256', $payload, $this->appSecret);
    }

    /** Constant-time comparison of a client-supplied token against the expected one. */
    public function verifyChannel(string $provided, string $socketId, string $channel, ?string $channelData = null): bool
    {
        return hash_equals($this->channelToken($socketId, $channel, $channelData), $provided);
    }
}
