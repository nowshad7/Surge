<?php
namespace Nsd7\RealtimeQueue;

class Server
{
    private array $clients = [];
    private array $handshakes = [];
    private $serverSocket;

    public function start(string $host = '0.0.0.0', int $port = 9019): void
    {
        $this->serverSocket = stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr);
        if (!$this->serverSocket) {
            throw new \RuntimeException("Unable to create socket: $errstr ($errno)");
        }

        stream_set_blocking($this->serverSocket, false);
        echo "WebSocket server running at ws://{$host}:{$port}\n";

        while (true) {
            $readSockets = array_merge([$this->serverSocket], $this->clients);
            $write = $except = null;

            if (@stream_select($readSockets, $write, $except, null) === false) {
                continue;
            }

            foreach ($readSockets as $socket) {
                if ($socket === $this->serverSocket) {
                    $client = @stream_socket_accept($this->serverSocket);
                    if ($client) {
                        stream_set_blocking($client, false);
                        $this->clients[] = $client;
                    }
                } else {
                    $data = @fread($socket, 1024);
                    if (!$data) {
                        $this->disconnect($socket);
                        continue;
                    }

                    if (!isset($this->handshakes[(int) $socket])) {
                        $this->performHandshake($socket, $data);
                    } else {
                        $msg = $this->unmask($data);
                        $this->broadcast($msg);
                    }
                }
            }
        }
    }

    private function performHandshake($conn, string $headers): void
    {
        if (preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $headers, $matches)) {
            $key = trim($matches[1]);
            $accept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

            $response = "HTTP/1.1 101 Switching Protocols\r\n" .
                        "Upgrade: websocket\r\n" .
                        "Connection: Upgrade\r\n" .
                        "Sec-WebSocket-Accept: {$accept}\r\n\r\n";

            fwrite($conn, $response);
            $this->handshakes[(int) $conn] = true;
        } else {
            fclose($conn);
        }
    }

    private function unmask(string $payload): string
    {
        $length = ord($payload[1]) & 127;

        if ($length === 126) {
            $masks = substr($payload, 4, 4);
            $data = substr($payload, 8);
        } elseif ($length === 127) {
            $masks = substr($payload, 10, 4);
            $data = substr($payload, 14);
        } else {
            $masks = substr($payload, 2, 4);
            $data = substr($payload, 6);
        }

        $unmasked = '';
        for ($i = 0, $len = strlen($data); $i < $len; ++$i) {
            $unmasked .= $data[$i] ^ $masks[$i % 4];
        }

        return $unmasked;
    }

    private function encode(string $msg): string
    {
        $len = strlen($msg);
        $frameHead = chr(129);

        if ($len <= 125) {
            return $frameHead . chr($len) . $msg;
        } elseif ($len <= 65535) {
            return $frameHead . chr(126) . pack('n', $len) . $msg;
        }

        return $frameHead . chr(127) . pack('J', $len) . $msg;
    }

    private function broadcast(string $msg): void
    {
        $encoded = $this->encode($msg);
        foreach ($this->clients as $client) {
            if (isset($this->handshakes[(int) $client])) {
                @fwrite($client, $encoded);
            }
        }

        echo "📤 Broadcasted: $msg\n";
    }

    private function disconnect($conn): void
    {
        fclose($conn);
        unset($this->clients[array_search($conn, $this->clients, true)]);
        unset($this->handshakes[(int) $conn]);
    }
}
