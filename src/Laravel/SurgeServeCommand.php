<?php

declare(strict_types=1);

namespace Nsd7\Surge\Laravel;

use Illuminate\Console\Command;
use Nsd7\Surge\Http\PusherHttpApi;
use Nsd7\Surge\Pusher\ChannelManager;
use Nsd7\Surge\Pusher\PusherProtocol;
use Nsd7\Surge\Pusher\Signature;
use Nsd7\Surge\Scaling\LocalAdapter;
use Nsd7\Surge\Scaling\RedisAdapter;
use Nsd7\Surge\Surge;

/**
 * Boots the Surge server from the Laravel app's configuration.
 *
 *   php artisan surge:serve
 */
final class SurgeServeCommand extends Command
{
    protected $signature = 'surge:serve {--host=} {--port=}';

    protected $description = 'Start the Surge realtime broadcasting server';

    public function handle(): int
    {
        $config = $this->laravel['config']->get('surge');

        $host = (string) ($this->option('host') ?: $config['host']);
        $port = (int) ($this->option('port') ?: $config['port']);

        $scaling = ($config['scaling']['enabled'] ?? false)
            ? new RedisAdapter(
                $config['scaling']['redis']['host'],
                (int) $config['scaling']['redis']['port'],
                $config['scaling']['redis']['prefix'] . ':events',
            )
            : new LocalAdapter();

        $signature = new Signature($config['app']['key'], $config['app']['secret']);
        $protocol = new PusherProtocol(
            $signature,
            new ChannelManager(),
            (int) $config['activity_timeout'],
            (bool) $config['client_events'],
            $scaling,
        );

        if (!$scaling instanceof LocalAdapter) {
            $this->info('Horizontal scaling enabled via Redis (node ' . $scaling->nodeId() . ').');
        }
        $httpApi = new PusherHttpApi($signature, $protocol, (string) $config['app']['id']);

        $logger = fn (string $line) => $this->line('<fg=gray>[' . date('H:i:s') . ']</> ' . $line);
        $server = new Surge($protocol, logger: $logger, httpHandler: $httpApi);

        $this->info("Surge listening on ws://{$host}:{$port} (app: {$config['app']['id']})");

        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, fn () => $server->stop());
            pcntl_signal(SIGTERM, fn () => $server->stop());
        }

        $server->run($host, $port);

        return self::SUCCESS;
    }
}
