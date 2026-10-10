<?php

declare(strict_types=1);

namespace Nsd7\Surge\Contract;

use Nsd7\Surge\Http\Request;
use Nsd7\Surge\Http\Response;

/**
 * Handles plain HTTP requests that arrive on the Surge port (i.e. not a
 * WebSocket upgrade): the Pusher trigger API and health checks.
 */
interface HttpHandler
{
    public function handle(Request $request): Response;
}
