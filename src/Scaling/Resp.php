<?php

declare(strict_types=1);

namespace Nsd7\Surge\Scaling;

/**
 * A minimal RESP2 (Redis serialization protocol) codec — enough for pub/sub,
 * implemented over raw sockets so the Redis scaling adapter needs no extension,
 * matching Surge's dependency-free ethos. The parser is incremental so it can
 * be driven from a non-blocking event loop.
 */
final class Resp
{
    private string $buffer = '';

    /** Encode a command as a RESP array of bulk strings. */
    public static function command(string ...$args): string
    {
        $out = '*' . count($args) . "\r\n";
        foreach ($args as $arg) {
            $out .= '$' . strlen($arg) . "\r\n" . $arg . "\r\n";
        }

        return $out;
    }

    public function feed(string $bytes): void
    {
        $this->buffer .= $bytes;
    }

    /**
     * Pull every complete top-level reply currently buffered.
     *
     * @return list<mixed> each a string|int|array|null
     */
    public function drain(): array
    {
        $results = [];
        while (true) {
            $consumed = 0;
            $value = $this->parse($this->buffer, $consumed);
            if ($consumed === 0) {
                break; // incomplete; wait for more bytes
            }
            $this->buffer = substr($this->buffer, $consumed);
            $results[] = $value;
        }

        return $results;
    }

    /**
     * Parse one value starting at the front of $data. Sets $consumed to the
     * number of bytes used (0 if the value is not yet complete).
     */
    private function parse(string $data, int &$consumed): mixed
    {
        if ($data === '') {
            $consumed = 0;
            return null;
        }

        $lineEnd = strpos($data, "\r\n");
        if ($lineEnd === false) {
            $consumed = 0;
            return null;
        }

        $type = $data[0];
        $line = substr($data, 1, $lineEnd - 1);
        $headerLen = $lineEnd + 2;

        switch ($type) {
            case '+': // simple string
            case '-': // error
                $consumed = $headerLen;
                return $line;

            case ':': // integer
                $consumed = $headerLen;
                return (int) $line;

            case '$': // bulk string
                $length = (int) $line;
                if ($length === -1) {
                    $consumed = $headerLen;
                    return null;
                }
                if (strlen($data) < $headerLen + $length + 2) {
                    $consumed = 0;
                    return null;
                }
                $consumed = $headerLen + $length + 2;
                return substr($data, $headerLen, $length);

            case '*': // array
                $count = (int) $line;
                if ($count === -1) {
                    $consumed = $headerLen;
                    return null;
                }
                $items = [];
                $offset = $headerLen;
                for ($i = 0; $i < $count; $i++) {
                    $itemConsumed = 0;
                    $item = $this->parse(substr($data, $offset), $itemConsumed);
                    if ($itemConsumed === 0) {
                        $consumed = 0; // array not fully arrived
                        return null;
                    }
                    $items[] = $item;
                    $offset += $itemConsumed;
                }
                $consumed = $offset;
                return $items;

            default:
                // Unknown type byte: skip the line to stay in sync.
                $consumed = $headerLen;
                return null;
        }
    }
}
