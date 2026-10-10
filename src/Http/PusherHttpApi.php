<?php

declare(strict_types=1);

namespace Nsd7\Surge\Http;

use Nsd7\Surge\Contract\HttpHandler;
use Nsd7\Surge\Pusher\PusherProtocol;
use Nsd7\Surge\Pusher\Signature;

/**
 * The Pusher-compatible HTTP REST API. Laravel's broadcaster (pusher-php-server)
 * publishes events by POSTing to /apps/{app_id}/events with a signed request;
 * this handler verifies the signature and fans the event out to subscribers via
 * the protocol's publish().
 *
 * Served on the same port as the WebSocket endpoint: non-upgrade requests land
 * here.
 *
 * @implements HttpHandler
 */
final class PusherHttpApi implements HttpHandler
{
    public function __construct(
        private readonly Signature $signature,
        private readonly PusherProtocol $protocol,
        private readonly string $appId,
    ) {
    }

    public function handle(Request $request): Response
    {
        // Health check for load balancers / orchestrators.
        if ($request->method === 'GET' && in_array($request->path, ['/', '/up', '/health'], true)) {
            return Response::json([
                'status'   => 'ok',
                'channels' => $this->protocol->channels()->totalChannels(),
            ]);
        }

        if ($request->method === 'POST' && $request->path === "/apps/{$this->appId}/events") {
            return $this->triggerEvent($request);
        }

        return Response::error(404, 'Not found');
    }

    private function triggerEvent(Request $request): Response
    {
        if (!$this->signature->verifyRequest($request->method, $request->path, $request->queryParams(), $request->body)) {
            return Response::error(401, 'Invalid signature');
        }

        $payload = $request->json();
        if (!is_array($payload) || !isset($payload['name'])) {
            return Response::error(422, 'Missing event name');
        }

        $channels = $this->resolveChannels($payload);
        if ($channels === []) {
            return Response::error(422, 'No channels specified');
        }

        $event = (string) $payload['name'];
        $data = $payload['data'] ?? null; // already a JSON string from the client
        $exceptSocketId = isset($payload['socket_id']) ? (string) $payload['socket_id'] : null;

        foreach ($channels as $channel) {
            $this->protocol->publish($channel, $event, $data, $exceptSocketId);
        }

        return Response::json((object) []);
    }

    /** @return list<string> */
    private function resolveChannels(array $payload): array
    {
        if (isset($payload['channels']) && is_array($payload['channels'])) {
            return array_values(array_map('strval', $payload['channels']));
        }
        if (isset($payload['channel'])) {
            return [(string) $payload['channel']];
        }

        return [];
    }
}
