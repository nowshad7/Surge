#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * Honest local throughput benchmark for the Surge lite runtime.
 *
 * Opens N WebSocket clients subscribed to one channel, triggers M events via
 * the HTTP API, and measures how long the server takes to fan every event out
 * to every client (N * M total deliveries). Reports deliveries/sec.
 *
 * This measures a single-process lite-tier server with clients on the same
 * machine; it is a relative sanity check, not a datacenter benchmark. Run:
 *
 *   php bench/throughput.php [clients] [events] [port]
 */

$clients = (int) ($argv[1] ?? 100);
$events  = (int) ($argv[2] ?? 500);
$port    = (int) ($argv[3] ?? 9099);
$host    = '127.0.0.1';
$appId = 'surge'; $key = 'surge-key'; $secret = 'surge-secret';

require __DIR__ . '/../autoload.php';

// Boot a server in a child process.
$cmd = sprintf(
    'SURGE_APP_ID=%s SURGE_APP_KEY=%s SURGE_APP_SECRET=%s php %s/../bin/surge serve --port=%d',
    $appId, $key, $secret, __DIR__, $port,
);
$server = proc_open($cmd, [STDIN, ['pipe', 'w'], ['pipe', 'w']], $pipes);
usleep(400000);

function wsConnect(string $host, int $port, string $path): mixed {
    $fp = stream_socket_client("tcp://{$host}:{$port}", $e, $s, 5);
    $k = base64_encode(random_bytes(16));
    fwrite($fp, "GET {$path} HTTP/1.1\r\nHost:{$host}\r\nUpgrade:websocket\r\nConnection:Upgrade\r\nSec-WebSocket-Key:{$k}\r\nSec-WebSocket-Version:13\r\n\r\n");
    $r = ''; while (!str_contains($r, "\r\n\r\n")) { $b = fread($fp, 1); if ($b === '' || $b === false) break; $r .= $b; }
    return $fp;
}
function wsSend($fp, string $p): void {
    $m = random_bytes(4); $l = strlen($p); $h = chr(0x81) . chr(0x80 | $l); $x = '';
    for ($i = 0; $i < $l; $i++) $x .= $p[$i] ^ $m[$i & 3];
    fwrite($fp, $h . $m . $x);
}

echo "Connecting {$clients} clients...\n";
$conns = [];
for ($i = 0; $i < $clients; $i++) {
    $fp = wsConnect($host, $port, "/app/{$key}");
    stream_set_blocking($fp, false);
    wsSend($fp, json_encode(['event' => 'pusher:subscribe', 'data' => ['channel' => 'bench']]));
    $conns[] = $fp;
}
usleep(300000);
foreach ($conns as $fp) { fread($fp, 65536); } // drain established + subscribed

// Fire M events through the HTTP API.
echo "Publishing {$events} events...\n";
$start = microtime(true);
for ($n = 0; $n < $events; $n++) {
    $body = json_encode(['name' => 'Bench', 'channels' => ['bench'], 'data' => json_encode(['n' => $n])]);
    $params = ['auth_key' => $key, 'auth_timestamp' => (string) time(), 'auth_version' => '1.0', 'body_md5' => md5($body)];
    ksort($params); $pairs = []; foreach ($params as $k => $v) $pairs[] = "{$k}={$v}";
    $base = "POST\n/apps/{$appId}/events\n" . implode('&', $pairs);
    $params['auth_signature'] = hash_hmac('sha256', $base, $secret);
    $q = []; foreach ($params as $k => $v) $q[] = "{$k}={$v}";
    $api = stream_socket_client("tcp://{$host}:{$port}", $e, $s, 5);
    $path = "/apps/{$appId}/events?" . implode('&', $q); $len = strlen($body);
    fwrite($api, "POST {$path} HTTP/1.1\r\nHost:{$host}\r\nContent-Length:{$len}\r\n\r\n{$body}");
    fread($api, 256); fclose($api);
}

// Count deliveries received across all clients.
$expected = $clients * $events;
$received = 0; $deadline = microtime(true) + 20;
while ($received < $expected && microtime(true) < $deadline) {
    foreach ($conns as $fp) {
        $data = @fread($fp, 1 << 20);
        if ($data !== '' && $data !== false) {
            $received += substr_count($data, '"event":"Bench"');
        }
    }
    usleep(1000);
}
$elapsed = microtime(true) - $start;

printf("\nClients: %d  Events: %d  Expected deliveries: %d\n", $clients, $events, $expected);
printf("Received: %d (%.1f%%)  in %.2fs\n", $received, 100 * $received / $expected, $elapsed);
printf("Throughput: %s deliveries/sec\n", number_format($received / max($elapsed, 0.001), 0));

foreach ($conns as $fp) { @fclose($fp); }
proc_terminate($server);
