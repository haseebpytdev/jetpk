<?php

namespace App\Services\Ops;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Durable per-user operational inbox stored in users.meta.ops_inbox (no migration).
 *
 * Authoritative business state remains on domain tables + audit_logs.
 * This service only fans out recipient-scoped notification rows with read state.
 */
class OpsInboxService
{
    public const META_KEY = 'ops_inbox';

    public const MAX_ITEMS = 100;

    /**
     * @param  list<int|User>  $recipients
     * @param  array{
     *     event_key: string,
     *     event_type: string,
     *     entity_type: string,
     *     entity_id?: int|string|null,
     *     entity_ref?: string|null,
     *     summary: string,
     *     actor_id?: int|null,
     *     actor_name?: string|null,
     *     actor_role?: string|null,
     *     agency_id?: int|null,
     *     deep_link?: string|null,
     *     category?: string|null,
     *     created_at?: string|null
     * }  $payload
     * @return list<string> notification ids created or already present
     */
    public function fanOut(array $recipients, array $payload): array
    {
        $eventKey = trim((string) ($payload['event_key'] ?? ''));
        if ($eventKey === '') {
            return [];
        }

        $ids = [];
        foreach ($recipients as $recipient) {
            $user = $recipient instanceof User ? $recipient : User::query()->find($recipient);
            if ($user === null) {
                continue;
            }

            $id = $this->appendForUser($user, $payload);
            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @param  array{
     *     event_key: string,
     *     event_type: string,
     *     entity_type: string,
     *     entity_id?: int|string|null,
     *     entity_ref?: string|null,
     *     summary: string,
     *     actor_id?: int|null,
     *     actor_name?: string|null,
     *     actor_role?: string|null,
     *     agency_id?: int|null,
     *     deep_link?: string|null,
     *     category?: string|null,
     *     created_at?: string|null
     * }  $payload
     */
    public function appendForUser(User $user, array $payload): ?string
    {
        $eventKey = trim((string) ($payload['event_key'] ?? ''));
        if ($eventKey === '') {
            return null;
        }

        return DB::transaction(function () use ($user, $payload, $eventKey): ?string {
            /** @var User $locked */
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->first();
            if ($locked === null) {
                return null;
            }

            $meta = is_array($locked->meta) ? $locked->meta : [];
            $inbox = $this->normalizeInbox($meta[self::META_KEY] ?? []);

            foreach ($inbox as $item) {
                if (($item['event_key'] ?? '') === $eventKey) {
                    return (string) ($item['id'] ?? '');
                }
            }

            $id = 'ops-'.substr(hash('sha256', $eventKey.'|'.$locked->id), 0, 24);
            $item = [
                'id' => $id,
                'event_key' => $eventKey,
                'event_type' => (string) ($payload['event_type'] ?? 'ops.event'),
                'entity_type' => (string) ($payload['entity_type'] ?? 'unknown'),
                'entity_id' => $payload['entity_id'] ?? null,
                'entity_ref' => $payload['entity_ref'] ?? null,
                'summary' => Str::limit((string) ($payload['summary'] ?? 'Operational update'), 240),
                'actor_id' => $payload['actor_id'] ?? null,
                'actor_name' => $payload['actor_name'] ?? null,
                'actor_role' => $payload['actor_role'] ?? null,
                'agency_id' => $payload['agency_id'] ?? $locked->current_agency_id,
                'deep_link' => $payload['deep_link'] ?? null,
                'category' => $payload['category'] ?? 'operations',
                'created_at' => $payload['created_at'] ?? now()->toIso8601String(),
                'read_at' => null,
            ];

            array_unshift($inbox, $item);
            if (count($inbox) > self::MAX_ITEMS) {
                $inbox = array_slice($inbox, 0, self::MAX_ITEMS);
            }

            $meta[self::META_KEY] = $inbox;
            $locked->forceFill(['meta' => $meta])->save();

            return $id;
        });
    }

    /**
     * @return array{items: list<array<string, mixed>>, unread_count: int, available: bool}
     */
    public function listForUser(User $user, int $page = 1, int $perPage = 20, bool $unreadOnly = false): array
    {
        $inbox = $this->inboxItems($user);
        if ($unreadOnly) {
            $inbox = array_values(array_filter(
                $inbox,
                static fn (array $item): bool => empty($item['read_at']),
            ));
        }

        $total = count($inbox);
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($inbox, $offset, $perPage);

        return [
            'available' => true,
            'items' => $slice,
            'unread_count' => $this->unreadCount($user),
            'pagination' => [
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'per_page' => $perPage,
                'total' => $total,
                'from' => $total === 0 ? null : $offset + 1,
                'to' => $total === 0 ? null : min($offset + $perPage, $total),
            ],
        ];
    }

    public function unreadCount(User $user): int
    {
        return count(array_filter(
            $this->inboxItems($user),
            static fn (array $item): bool => empty($item['read_at']),
        ));
    }

    public function markRead(User $user, string $notificationId): bool
    {
        return $this->markManyRead($user, [$notificationId]) > 0;
    }

    /**
     * @param  list<string>  $notificationIds
     */
    public function markManyRead(User $user, array $notificationIds): int
    {
        $wanted = array_values(array_filter(array_map('strval', $notificationIds)));
        if ($wanted === []) {
            return 0;
        }

        return (int) DB::transaction(function () use ($user, $wanted): int {
            /** @var User $locked */
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->first();
            if ($locked === null) {
                return 0;
            }

            $meta = is_array($locked->meta) ? $locked->meta : [];
            $inbox = $this->normalizeInbox($meta[self::META_KEY] ?? []);
            $changed = 0;
            $now = now()->toIso8601String();

            foreach ($inbox as $index => $item) {
                $id = (string) ($item['id'] ?? '');
                if ($id === '' || ! in_array($id, $wanted, true)) {
                    continue;
                }
                if (! empty($item['read_at'])) {
                    continue;
                }
                $inbox[$index]['read_at'] = $now;
                $changed++;
            }

            if ($changed > 0) {
                $meta[self::META_KEY] = $inbox;
                $locked->forceFill(['meta' => $meta])->save();
            }

            return $changed;
        });
    }

    public function markAllRead(User $user): int
    {
        return (int) DB::transaction(function () use ($user): int {
            /** @var User $locked */
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->first();
            if ($locked === null) {
                return 0;
            }

            $meta = is_array($locked->meta) ? $locked->meta : [];
            $inbox = $this->normalizeInbox($meta[self::META_KEY] ?? []);
            $changed = 0;
            $now = now()->toIso8601String();

            foreach ($inbox as $index => $item) {
                if (! empty($item['read_at'])) {
                    continue;
                }
                $inbox[$index]['read_at'] = $now;
                $changed++;
            }

            if ($changed > 0) {
                $meta[self::META_KEY] = $inbox;
                $locked->forceFill(['meta' => $meta])->save();
            }

            return $changed;
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function inboxItems(User $user): array
    {
        $meta = is_array($user->meta) ? $user->meta : [];

        return $this->normalizeInbox($meta[self::META_KEY] ?? []);
    }

    public function actorRoleLabel(?User $actor): ?string
    {
        if ($actor === null) {
            return null;
        }
        if ($actor->isPlatformAdmin()) {
            return 'admin';
        }
        if ($actor->isStaff()) {
            return 'staff';
        }
        if ($actor->isAgentPortalUser()) {
            return 'agent';
        }
        if ($actor->isCustomer()) {
            return 'customer';
        }

        return 'user';
    }

    /**
     * @param  mixed  $raw
     * @return list<array<string, mixed>>
     */
    protected function normalizeInbox(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $row) {
            if (! is_array($row) || empty($row['id']) || empty($row['event_key'])) {
                continue;
            }
            $items[] = $row;
        }

        return $items;
    }
}
