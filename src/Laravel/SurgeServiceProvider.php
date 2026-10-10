<?php

declare(strict_types=1);

namespace Nsd7\Surge\Laravel;

use Illuminate\Support\ServiceProvider;

/**
 * Laravel integration for Surge. Auto-discovered via composer extra.laravel.
 *
 * Requires illuminate/* at runtime, which only exists inside a Laravel app, so
 * this class is never loaded by the dependency-free core or its tests.
 */
final class SurgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/surge.php', 'surge');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/surge.php' => $this->app->configPath('surge.php'),
            ], 'surge-config');

            $this->commands([SurgeServeCommand::class]);
        }
    }
}
