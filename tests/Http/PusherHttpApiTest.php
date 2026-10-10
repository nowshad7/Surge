<?php

declare(strict_types=1);

namespace Nsd7\Surge\Tests\Http;

use Nsd7\Surge\Http\PusherHttpApi;
use Nsd7\Surge\Http\Request;
use Nsd7\Surge\Pusher\PusherProtocol;
use Nsd7\Surge\Pusher\Signature;
use Nsd7\Surge\Tests\TestCase;
use Nsd7\Surge\WebSocket\Connection;

final class PusherHttpApiTest extends TestCase
{
    private const APP_ID = '12345';
    private const KEY = 'app-key';
    private const SECRET = 'app-secret';

    private function api(PusherProtocol $protocol): PusherHttpApi
    {
        return new PusherHttpApi(new Signature(self::KEY, self::SECRET), $protocol, self::APP_ID);
    }

    private function protocol(): PusherProtocol
    {
        return new PusherProtocol(new Signature(self::KEY, self::SECRET));
    }

    public function testHealthCheck(): void
    {
        $response = $this->api($this->protocol())->handle(new Request('GET', '/health', '', [], ''));

        $this->assertSame(200, $response->status);
        $this->assertSame('ok', json_decode($response->body, true)['status']);
    }

    public function testUnknownRouteIs404(): void
    {
        $response = $this->api($this->protocol())->handle(new Request('GET', '/nope', '', [], ''));
        $this->assertSame(404, $response->status);
    }

    public function testRejectsInvalidSignature(): void
    {
        $path = '/apps/' . self::APP_ID . '/events';
        $request = new Request('POST', $path, 'auth_key=app-key&auth_signature=wrong', [], '{"name":"X","channel":"news"}');

        $response = $this->api($this->protocol())->handle($request);
        $this->assertSame(401, $response->status);
    }

    public function testTriggerPublishesToSubscribers(): void
    {
        $protocol = $this->protocol();
        $api = $this->api($protocol);

        // A subscriber on the public "news" channel.
        $subscriber = new Connection(1);
        $subscriber->ingest($this->wsHandshake(), $protocol);
        $subscriber->ingest($this->clientFrame(json_encode(['event' => 'pusher:subscribe', 'data' => ['channel' => 'news']])), $protocol);
        $this->drainServerMessages($subscriber);

        $body = json_encode(['name' => 'NewsPublished', 'channels' => ['news'], 'data' => json_encode(['headline' => 'Hi'])]);
        $request = $this->signedTrigger($body);

        $response = $api->handle($request);
        $this->assertSame(200, $response->status);

        $messages = $this->drainServerMessages($subscriber);
        $this->assertCount(1, $messages);
        $this->assertSame('NewsPublished', $messages[0]['event']);
        $this->assertSame('news', $messages[0]['channel']);
        $this->assertSame(['headline' => 'Hi'], json_decode($messages[0]['data'], true));
    }

    public function testTriggerRoutesThroughFullConnection(): void
    {
        $protocol = $this->protocol();
        $api = $this->api($protocol);

        $subscriber = new Connection(1);
        $subscriber->ingest($this->wsHandshake(), $protocol);
        $subscriber->ingest($this->clientFrame(json_encode(['event' => 'pusher:subscribe', 'data' => ['channel' => 'news']])), $protocol);
        $this->drainServerMessages($subscriber);

        // Feed a raw HTTP POST to a connection that has the API as its HTTP handler.
        $body = json_encode(['name' => 'Evt', 'channel' => 'news', 'data' => '{}']);
        $raw = $this->signedTriggerRaw($body);

        $apiConnection = new Connection(2, httpHandler: $api);
        $apiConnection->ingest($raw, $protocol);

        // The API connection should have a 200 response queued...
        $this->assertTrue(str_contains($apiConnection->outgoingBuffer(), 'HTTP/1.1 200'));
        // ...and the subscriber should have received the event.
        $messages = $this->drainServerMessages($subscriber);
        $this->assertSame('Evt', $messages[0]['event']);
    }

    private function signedTrigger(string $body): Request
    {
        [$query] = $this->sign($body);
        return new Request('POST', '/apps/' . self::APP_ID . '/events', $query, [], $body);
    }

    private function signedTriggerRaw(string $body): string
    {
        [$query] = $this->sign($body);
        $path = '/apps/' . self::APP_ID . '/events?' . $query;
        $len = strlen($body);

        return "POST {$path} HTTP/1.1\r\n"
            . "Host: localhost\r\n"
            . "Content-Type: application/json\r\n"
            . "Content-Length: {$len}\r\n\r\n"
            . $body;
    }

    /**
     * Sign a request exactly as pusher-php-server does, proving round-trip
     * compatibility with the verifier.
     *
     * @return array{0:string} the query string
     */
    private function sign(string $body): array
    {
        $params = [
            'auth_key'       => self::KEY,
            'auth_timestamp' => (string) time(),
            'auth_version'   => '1.0',
            'body_md5'       => md5($body),
        ];
        ksort($params);
        $pairs = [];
        foreach ($params as $k => $v) {
            $pairs[] = "{$k}={$v}";
        }
        $base = "POST\n/apps/" . self::APP_ID . "/events\n" . implode('&', $pairs);
        $params['auth_signature'] = hash_hmac('sha256', $base, self::SECRET);

        $query = [];
        foreach ($params as $k => $v) {
            $query[] = "{$k}={$v}";
        }

        return [implode('&', $query)];
    }

    private function wsHandshake(): string
    {
        return "GET /app/app-key HTTP/1.1\r\n"
            . "Host: localhost\r\n"
            . "Upgrade: websocket\r\n"
            . "Connection: Upgrade\r\n"
            . "Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==\r\n"
            . "Sec-WebSocket-Version: 13\r\n\r\n";
    }
}
