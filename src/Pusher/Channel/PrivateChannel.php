<?php

declare(strict_types=1);

namespace Nsd7\Surge\Pusher\Channel;

/**
 * A private channel ("private-" / "private-encrypted-" prefix). Subscription
 * requires a valid auth token signed by the Laravel backend.
 */
class PrivateChannel extends Channel
{
    public function requiresAuth(): bool
    {
        return true;
    }
}
