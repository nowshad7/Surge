#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * Publish an event to the "demo" channel through Surge's Pusher HTTP trigger
 * API, exactly the way a Laravel backend would. Open examples/browser.html
 * first, then run:
 *
 *   php examples/publish.php "Hello from Surge"
 */

$host = getenv('SURGE_HOST') ?: '127.0.0.1';
$port = (int) (getenv('SURGE_PORT') ?: 9019);
$appId = getenv('SURGE_APP_ID') ?: 'surge';
$key = getenv('SURGE_APP_KEY') ?: 'surge-key';
$secret = getenv('SURGE_APP_SECRET') ?: 'surge-secret';

$message = $argv[1] ?? 'Hello from Surge';

$body = json_encode([
    'name'     => 'DemoMessage',
    'channels' => ['demo'],
    'data'     => json_encode(['message' => $message, 'at' => date('H:i:s')]),
]);

$params = [
    'auth_key'       => $key,
    'auth_timestamp' => (string) time(),
    'auth_version'   => '1.0',
    'body_md5'       => md5($body),
];
ksort($params);
$pairs = [];
foreach ($params as $k => $v) {
    $pairs[] = "{$k}={$v}";
}
$base = "POST\n/apps/{$appId}/events\n" . implode('&', $pairs);
$params['auth_signature'] = hash_hmac('sha256', $base, $secret);

$query = [];
foreach ($params as $k => $v) {
    $query[] = "{$k}={$v}";
}

$socket = stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 5);
if (!$socket) {
    fwrite(STDERR, "Cannot reach Surge at {$host}:{$port}: {$errstr}\n");
    exit(1);
}

$path = "/apps/{$appId}/events?" . implode('&', $query);
$len = strlen($body);
fwrite($socket, "POST {$path} HTTP/1.1\r\nHost: {$host}\r\nContent-Type: application/json\r\nContent-Length: {$len}\r\n\r\n{$body}");

echo trim(fread($socket, 1024)), "\n";
fclose($socket);
