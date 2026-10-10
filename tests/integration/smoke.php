#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * End-to-end smoke test: boots a real Surge server, connects a WebSocket
 * client, subscribes, publishes through the signed HTTP trigger API, and
 * asserts the client receives the event. Exits non-zero on failure so CI fails.
 */

$host = '127.0.0.1';
$port = (int) (getenv('SMOKE_PORT') ?: 9191);
$appId = 'surge'; $key = 'surge-key'; $secret = 'surge-secret';

$cmd = sprintf(
    'SURGE_APP_ID=%s SURGE_APP_KEY=%s SURGE_APP_SECRET=%s php %s/../../bin/surge serve --port=%d',
    $appId, $key, $secret, __DIR__, $port,
);
$server = proc_open($cmd, [STDIN, ['pipe', 'w'], ['pipe', 'w']], $pipes);
if (!is_resource($server)) {
    fwrite(STDERR, "could not start server\n");
    exit(1);
}

$fail = static function (string $msg) use ($server): never {
    fwrite(STDERR, "SMOKE FAIL: {$msg}\n");
    proc_terminate($server);
    exit(1);
};

usleep(500000);

$ws = @stream_socket_client("tcp://{$host}:{$port}", $e, $s, 5);
if (!$ws) {
    $fail("connect failed: {$s}");
}

$k = base64_encode(random_bytes(16));
fwrite($ws, "GET /app/{$key} HTTP/1.1\r\nHost:{$host}\r\nUpgrade:websocket\r\nConnection:Upgrade\r\nSec-WebSocket-Key:{$k}\r\nSec-WebSocket-Version:13\r\n\r\n");
$resp = '';
while (!str_contains($resp, "\r\n\r\n")) {
    $b = fread($ws, 1);
    if ($b === '' || $b === false) break;
    $resp .= $b;
}
if (!str_contains($resp, '101')) {
    $fail('handshake did not switch protocols');
}

$send = static function ($fp, string $p): void {
    $m = random_bytes(4); $l = strlen($p); $h = chr(0x81) . chr(0x80 | $l); $x = '';
    for ($i = 0; $i < $l; $i++) $x .= $p[$i] ^ $m[$i & 3];
    fwrite($fp, $h . $m . $x);
};
$read = static function ($fp): ?string {
    $h = fread($fp, 2);
    if (strlen($h) < 2) return null;
    $len = ord($h[1]) & 0x7F;
    if ($len === 126) $len = unpack('n', fread($fp, 2))[1];
    elseif ($len === 127) $len = unpack('J', fread($fp, 8))[1];
    return $len > 0 ? fread($fp, $len) : '';
};

$established = json_decode((string) $read($ws), true);
if (($established['event'] ?? null) !== 'pusher:connection_established') {
    $fail('no connection_established');
}

$send($ws, json_encode(['event' => 'pusher:subscribe', 'data' => ['channel' => 'news']]));
$sub = json_decode((string) $read($ws), true);
if (($sub['event'] ?? null) !== 'pusher_internal:subscription_succeeded') {
    $fail('subscription not acknowledged');
}

$body = json_encode(['name' => 'OrderShipped', 'channels' => ['news'], 'data' => json_encode(['id' => 7])]);
$params = ['auth_key' => $key, 'auth_timestamp' => (string) time(), 'auth_version' => '1.0', 'body_md5' => md5($body)];
ksort($params);
$pairs = []; foreach ($params as $pk => $pv) $pairs[] = "{$pk}={$pv}";
$base = "POST\n/apps/{$appId}/events\n" . implode('&', $pairs);
$params['auth_signature'] = hash_hmac('sha256', $base, $secret);
$q = []; foreach ($params as $pk => $pv) $q[] = "{$pk}={$pv}";

$api = @stream_socket_client("tcp://{$host}:{$port}", $e, $s, 5);
$path = "/apps/{$appId}/events?" . implode('&', $q); $len = strlen($body);
fwrite($api, "POST {$path} HTTP/1.1\r\nHost:{$host}\r\nContent-Length:{$len}\r\n\r\n{$body}");
$apiResp = fread($api, 512);
if (!str_contains($apiResp, '200')) {
    $fail("trigger not accepted: " . substr($apiResp, 0, 40));
}

stream_set_timeout($ws, 3);
$event = json_decode((string) $read($ws), true);
if (($event['event'] ?? null) !== 'OrderShipped' || json_decode($event['data'], true) !== ['id' => 7]) {
    $fail('client did not receive the published event: ' . var_export($event, true));
}

echo "SMOKE PASS: WS subscribe + signed HTTP trigger delivered the event.\n";
proc_terminate($server);
exit(0);
