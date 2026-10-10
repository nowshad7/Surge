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

    public function appKey(): string
    {
        return $this->appKey;
    }

    /**
     * Verify a Pusher REST API request signature, matching pusher-php-server
     * (what Laravel's broadcaster uses). The signed string is
     * "{METHOD}\n{path}\n{sorted auth params}", and the body is bound via the
     * body_md5 param.
     *
     * @param array<string,string> $queryParams
     */
    public function verifyRequest(string $method, string $path, array $queryParams, string $body): bool
    {
        $provided = (string) ($queryParams['auth_signature'] ?? '');
        if ($provided === '') {
            return false;
        }
        unset($queryParams['auth_signature']);

        if (!hash_equals($this->appKey, (string) ($queryParams['auth_key'] ?? ''))) {
            return false;
        }

        if ($body !== '' && isset($queryParams['body_md5'])
            && !hash_equals(md5($body), (string) $queryParams['body_md5'])) {
            return false;
        }

        ksort($queryParams);
        $pairs = [];
        foreach ($queryParams as $key => $value) {
            $pairs[] = $key . '=' . $value;
        }
        $base = strtoupper($method) . "\n" . $path . "\n" . implode('&', $pairs);

        return hash_equals(hash_hmac('sha256', $base, $this->appSecret), $provided);
    }
}
