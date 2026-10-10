<?php

declare(strict_types=1);

namespace Nsd7\Surge\Pusher\Channel;

use Nsd7\Surge\Contract\Client;

/**
 * A presence channel ("presence-" prefix): a private channel that also tracks
 * who is subscribed. Each member contributes a user_id and arbitrary user_info,
 * supplied in the signed channel_data at subscribe time.
 */
final class PresenceChannel extends PrivateChannel
{
    /** @var array<int, array{user_id: string, user_info: mixed}> connection id => member */
    private array $members = [];

    public function join(Client $connection, string $userId, mixed $userInfo): void
    {
        $this->subscribe($connection);
        $this->members[$connection->id()] = ['user_id' => $userId, 'user_info' => $userInfo];
    }

    public function unsubscribe(Client $connection): void
    {
        parent::unsubscribe($connection);
        unset($this->members[$connection->id()]);
    }

    public function member(Client $connection): ?array
    {
        return $this->members[$connection->id()] ?? null;
    }

    /** True if this user_id already has at least one other connection present. */
    public function hasOtherWithUserId(string $userId, int $exceptConnectionId): bool
    {
        foreach ($this->members as $id => $member) {
            if ($id !== $exceptConnectionId && $member['user_id'] === $userId) {
                return true;
            }
        }

        return false;
    }

    /**
     * The presence payload Pusher sends inside subscription_succeeded:
     * { "presence": { "ids": [...], "hash": { id: info }, "count": n } }.
     */
    public function presenceData(): array
    {
        $ids = [];
        $hash = [];
        foreach ($this->members as $member) {
            $ids[] = $member['user_id'];
            $hash[$member['user_id']] = $member['user_info'];
        }
        $ids = array_values(array_unique($ids));

        return [
            'presence' => [
                'ids'   => $ids,
                'hash'  => (object) $hash,
                'count' => count($ids),
            ],
        ];
    }
}
