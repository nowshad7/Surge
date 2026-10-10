<?php

declare(strict_types=1);

namespace Nsd7\Surge\Runtime;

use Nsd7\Surge\Contract\EventHandler;
use Nsd7\Surge\Contract\HttpHandler;
use Nsd7\Surge\Contract\Runtime;
use Nsd7\Surge\Contract\Tickable;
use Nsd7\Surge\Http\Request;
use Nsd7\Surge\WebSocket\SwooleConnection;

/**
 * Multi-core "turbo" runtime backed by OpenSwoole.
 *
 * Where the lite runtime is one PHP process on one core, OpenSwoole runs a pool
 * of worker processes across all cores, which is where Surge can outperform a
 * single-threaded event loop under load. It drives the exact same EventHandler
 * and Pusher protocol as the lite runtime — only the transport differs.
 *
 * EXPERIMENTAL / UNVERIFIED: this requires the ext-openswoole extension, which
 * is not present in every environment, and it has NOT yet been load-tested or
 * benchmarked in this build. The lite runtime remains the supported default.
 * Treat the performance characteristics as a goal, not a measured result, until
 * docs/turbo.md says otherwise.
 */
final class SwooleRuntime implements Runtime
{
    private mixed $server = null;

    /** @var array<int, SwooleConnection> fd => client */
    private array $connections = [];

    public function __construct(
        private readonly EventHandler $handler,
        private readonly ?HttpHandler $httpHandler = null,
        private readonly int $workerCount = 0, // 0 => OpenSwoole default (CPU count)
        /** @var null|callable(string):void */
        private $logger = null,
    ) {
    }

    public function name(): string
    {
        return 'turbo';
    }

    public function run(string $host, int $port): void
    {
        if (!extension_loaded('openswoole')) {
            throw new \RuntimeException(
                'The turbo runtime requires ext-openswoole. Install it, or use the lite runtime (the default).',
            );
        }

        $server = new \OpenSwoole\WebSocket\Server($host, $port);
        $this->server = $server;

        $settings = ['open_websocket_close_frame' => true];
        if ($this->workerCount > 0) {
            $settings['worker_num'] = $this->workerCount;
        }
        $server->set($settings);

        $server->on('open', function ($server, $request): void {
            $client = new SwooleConnection($server, $request->fd);
            $this->connections[$request->fd] = $client;
            $this->handler->onOpen($client);
        });

        $server->on('message', function ($server, $frame): void {
            $client = $this->connections[$frame->fd] ?? null;
            if ($client === null) {
                return;
            }
            $binary = ($frame->opcode ?? 1) === 2;
            $this->handler->onMessage($client, (string) $frame->data, $binary);
        });

        $server->on('close', function ($server, int $fd): void {
            $client = $this->connections[$fd] ?? null;
            if ($client !== null) {
                $this->handler->onClose($client, 1000, '');
                unset($this->connections[$fd]);
            }
        });

        // Non-upgrade HTTP (the Pusher trigger API, health checks).
        $server->on('request', function ($request, $response): void {
            if ($this->httpHandler === null) {
                $response->status(404);
                $response->end('');
                return;
            }

            $surgeRequest = new Request(
                (string) ($request->server['request_method'] ?? 'GET'),
                (string) parse_url((string) ($request->server['request_uri'] ?? '/'), PHP_URL_PATH),
                (string) ($request->server['query_string'] ?? ''),
                array_change_key_case((array) ($request->header ?? []), CASE_LOWER),
                (string) $request->rawContent(),
            );

            $result = $this->httpHandler->handle($surgeRequest);
            $response->status($result->status);
            $response->header('Content-Type', $result->contentType);
            $response->end($result->body);
        });

        // Drive a scaling-aware handler's tick() from a Swoole timer.
        if ($this->handler instanceof Tickable) {
            $server->on('workerStart', function ($server, int $workerId): void {
                if ($workerId === 0) {
                    $server->tick(50, fn () => $this->handler->tick());
                }
            });
        }

        $this->log("Surge turbo runtime listening on ws://{$host}:{$port}");
        $server->start();
    }

    public function stop(): void
    {
        if ($this->server !== null) {
            $this->server->shutdown();
        }
    }

    private function log(string $message): void
    {
        if ($this->logger !== null) {
            ($this->logger)($message);
        }
    }
}
