<?php

declare(strict_types=1);

namespace Nsd7\Surge\Contract;

/**
 * An EventHandler that wants to be called periodically by the runtime (e.g. to
 * drain a scaling adapter's inbound peer messages). Keeps the runtime unaware
 * of scaling.
 */
interface Tickable
{
    public function tick(): void;
}
