<?php

declare(strict_types=1);

namespace Nsd7\Surge\Http;

/**
 * An immutable view of a non-upgrade HTTP request received on the Surge port
 * (the Pusher trigger API and health checks arrive this way).
 */
final class Request
{
    /** @param array<string,string> $headers lower-cased name => value */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly string $query,
        public readonly array $headers,
        public readonly string $body,
    ) {
    }

    /** @return array<string,string> parsed query-string parameters */
    public function queryParams(): array
    {
        parse_str($this->query, $params);
        /** @var array<string,string> $params */
        return $params;
    }

    public function json(): mixed
    {
        return json_decode($this->body, true);
    }
}
