<?php

declare(strict_types=1);

namespace Nsd7\Surge\Pusher\Channel;

use Nsd7\Surge\Contract\Client;

/**
 * A presence channel ("presence-" prefix): a private channel that also tracks
 * who is subscribed.
 *
 * Membership has two parts:
 *   - **local** members — connections subscribed to this node (keyed by conn id)
 *   - **remote** members — members on other nodes, learned via the scaling
 *     adapter (keyed by "origin|user_id")
 *
 * The roster and member-added/removed decisions use the *combined* set, so
 * presence is consistent across a multi-node cluster.
 */
final class PresenceChannel extends PrivateChannel
{
    /** @var array<int, array{user_id: string, user_info: mixed}> conn id => member */
    private array $members = [];

    /** @var array<string, array{user_id: string, user_info: mixed}> "origin|user_id" => member */
    private array $remoteMembers = [];

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

    /** Record a member present on another node. Returns true if newly tracked. */
    public function addRemote(string $key, string $userId, mixed $userInfo): void
    {
        $this->remoteMembers[$key] = ['user_id' => $userId, 'user_info' => $userInfo];
    }

    public function removeRemote(string $key): void
    {
        unset($this->remoteMembers[$key]);
    }

    /** Unique user_ids among this node's local members. */
    public function hasLocalUserId(string $userId): bool
    {
        foreach ($this->members as $member) {
            if ($member['user_id'] === $userId) {
                return true;
            }
        }

        return false;
    }

    /** True if any local OR remote member has this user_id (combined view). */
    public function hasUserId(string $userId): bool
    {
        if ($this->hasLocalUserId($userId)) {
            return true;
        }
        foreach ($this->remoteMembers as $member) {
            if ($member['user_id'] === $userId) {
                return true;
            }
        }

        return false;
    }

    /** Local members only, deduplicated by user_id — used to answer a sync. */
    public function localRoster(): array
    {
        $roster = [];
        foreach ($this->members as $member) {
            $roster[$member['user_id']] = $member['user_info'];
        }

        return $roster;
    }

    /**
     * The presence payload Pusher sends inside subscription_succeeded, over the
     * combined local + remote membership:
     * { "presence": { "ids": [...], "hash": { id: info }, "count": n } }.
     */
    public function presenceData(): array
    {
        $hash = [];
        foreach ([...array_values($this->members), ...array_values($this->remoteMembers)] as $member) {
            $hash[$member['user_id']] = $member['user_info'];
        }
        $ids = array_keys($hash);

        return [
            'presence' => [
                'ids'   => $ids,
                'hash'  => (object) $hash,
                'count' => count($ids),
            ],
        ];
    }
}
