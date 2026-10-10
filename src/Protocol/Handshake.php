<?php

declare(strict_types=1);

namespace Nsd7\Surge\Protocol;

/**
 * The opening HTTP/1.1 Upgrade handshake (RFC 6455 §4).
 *
 * Unlike the seed, this validates the request is actually a WebSocket upgrade,
 * parses headers case-insensitively, exposes the requested path and query (used
 * later to carry Pusher app keys and auth), and buffers until the full header
 * block has arrived rather than assuming it lands in a single read.
 */
final class Handshake
{
    private const GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    private string $buffer = '';
    private bool $complete = false;
    private bool $headersParsed = false;
    private int $contentLength = 0;

    /** @var array<string,string> lower-cased header name => value */
    public array $headers = [];
    public string $method = '';
    public string $path = '';
    public string $query = '';
    public string $body = '';

    public function __construct(private readonly int $maxBodyBytes = 10 * 1024 * 1024)
    {
    }

    /**
     * Feed raw bytes. Returns true once the request line, header block, and (for
     * requests carrying a Content-Length) the full body have been received. A
     * WebSocket upgrade has no body and completes as soon as the headers arrive.
     */
    public function feed(string $bytes): bool
    {
        if ($this->headersParsed) {
            $this->body .= $bytes;
            return $this->finishIfBodyComplete();
        }

        $this->buffer .= $bytes;

        $end = strpos($this->buffer, "\r\n\r\n");
        if ($end === false) {
            if (strlen($this->buffer) > 16384) {
                throw new ProtocolException('Handshake headers too large', 1009);
            }
            return false;
        }

        $this->parse(substr($this->buffer, 0, $end));
        $this->headersParsed = true;
        $this->contentLength = max(0, (int) ($this->headers['content-length'] ?? 0));

        if ($this->contentLength > $this->maxBodyBytes) {
            throw new ProtocolException('Request body too large', 1009);
        }

        $this->body = substr($this->buffer, $end + 4);
        $this->buffer = '';

        return $this->finishIfBodyComplete();
    }

    private function finishIfBodyComplete(): bool
    {
        if (strlen($this->body) < $this->contentLength) {
            return false;
        }

        $this->complete = true;
        return true;
    }

    public function isComplete(): bool
    {
        return $this->complete;
    }

    public function isValidUpgrade(): bool
    {
        return $this->method === 'GET'
            && str_contains(strtolower($this->headers['upgrade'] ?? ''), 'websocket')
            && str_contains(strtolower($this->headers['connection'] ?? ''), 'upgrade')
            && ($this->headers['sec-websocket-version'] ?? '') === '13'
            && isset($this->headers['sec-websocket-key']);
    }

    /** Build the 101 Switching Protocols response for a valid request. */
    public function response(): string
    {
        $accept = self::accept($this->headers['sec-websocket-key']);

        return "HTTP/1.1 101 Switching Protocols\r\n"
            . "Upgrade: websocket\r\n"
            . "Connection: Upgrade\r\n"
            . "Sec-WebSocket-Accept: {$accept}\r\n\r\n";
    }

    /** A plain HTTP rejection for a request that is not a valid upgrade. */
    public function rejection(int $status = 400, string $reason = 'Bad Request'): string
    {
        return "HTTP/1.1 {$status} {$reason}\r\n"
            . "Connection: close\r\n"
            . "Content-Length: 0\r\n\r\n";
    }

    public static function accept(string $key): string
    {
        return base64_encode(sha1(trim($key) . self::GUID, true));
    }

    private function parse(string $head): void
    {
        $lines = explode("\r\n", $head);
        $requestLine = array_shift($lines) ?? '';

        if (preg_match('#^(\S+)\s+(\S+)\s+HTTP/1\.[01]$#', $requestLine, $m) === 1) {
            $this->method = strtoupper($m[1]);
            $target = $m[2];
            $qpos = strpos($target, '?');
            if ($qpos === false) {
                $this->path = $target;
            } else {
                $this->path = substr($target, 0, $qpos);
                $this->query = substr($target, $qpos + 1);
            }
        }

        foreach ($lines as $line) {
            $sep = strpos($line, ':');
            if ($sep === false) {
                continue;
            }
            $name = strtolower(trim(substr($line, 0, $sep)));
            $value = trim(substr($line, $sep + 1));
            $this->headers[$name] = $value;
        }
    }
}
