
# Realtime Queue

A pure PHP WebSocket server for live queue updates. No dependencies.

## Features
- No external libraries like Ratchet
- WebSocket handshake + messaging fully handled in PHP
- Ideal for broadcasting real-time queue or notification data

## Installation

```bash
composer require nsd7/realtime-queue
```

## Usage

Start server:
```bash
vendor/bin/server.php
```

Broadcast message:
```php
$socket = stream_socket_client("tcp://127.0.0.1:9019", $errno, $errstr);
fwrite($socket, json_encode(['message' => 'Hello!']));
fclose($socket);
```

Connect with frontend:
```js
const ws = new WebSocket("ws://localhost:9019");
ws.onmessage = (event) => {
    console.log("Received:", event.data);
};
```

## License
MIT