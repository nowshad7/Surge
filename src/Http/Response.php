<?php

declare(strict_types=1);

namespace Nsd7\Surge\Http;

/**
 * A minimal HTTP/1.1 response. Connections serialise it and close (the trigger
 * API is request/response, not keep-alive).
 */
final class Response
{
    private const REASONS = [
        200 => 'OK',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        413 => 'Payload Too Large',
        422 => 'Unprocessable Entity',
        500 => 'Internal Server Error',
    ];

    public function __construct(
        public readonly int $status,
        public readonly string $body = '',
        public readonly string $contentType = 'application/json',
    ) {
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self($status, json_encode($data, JSON_UNESCAPED_SLASHES), 'application/json');
    }

    public static function error(int $status, string $message): self
    {
        return self::json(['error' => $message], $status);
    }

    public function toRaw(): string
    {
        $reason = self::REASONS[$this->status] ?? 'Status';
        $length = strlen($this->body);

        return "HTTP/1.1 {$this->status} {$reason}\r\n"
            . "Content-Type: {$this->contentType}\r\n"
            . "Content-Length: {$length}\r\n"
            . "Connection: close\r\n\r\n"
            . $this->body;
    }
}
