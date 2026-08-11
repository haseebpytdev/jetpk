<?php

namespace App\Support\AgentPortal;

use App\Models\User;
use App\Services\Ops\OpsInboxService;

/**
 * Agent notification center JSON backed by durable users.meta.ops_inbox.
 */
class AgentPortalNotificationPresenter
{
    public function __construct(
        protected OpsInboxService $inbox,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function presentIndex(User $user, int $page = 1, int $perPage = 20): array
    {
        $result = $this->inbox->listForUser($user, $page, $perPage);

        return [
            'ok' => true,
            'available' => true,
            'transport' => 'EVENT_POLLING',
            'message' => null,
            'unread_count' => $result['unread_count'],
            'notifications' => array_map(fn (array $item): array => $this->presentItem($item), $result['items']),
            'pagination' => $result['pagination'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentUnreadSummary(User $user): array
    {
        return [
            'ok' => true,
            'available' => true,
            'transport' => 'EVENT_POLLING',
            'unread_count' => $this->inbox->unreadCount($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function markRead(User $user, string $notificationId): array
    {
        $ok = $this->inbox->markRead($user, $notificationId);

        return [
            'ok' => $ok,
            'available' => true,
            'unread_count' => $this->inbox->unreadCount($user->fresh() ?? $user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function markAllRead(User $user): array
    {
        $marked = $this->inbox->markAllRead($user);

        return [
            'ok' => true,
            'available' => true,
            'marked' => $marked,
            'unread_count' => $this->inbox->unreadCount($user->fresh() ?? $user),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function presentItem(array $item): array
    {
        if (($item['event_type'] ?? '') === 'support.message_internal'
            || ($item['event_type'] ?? '') === 'booking.note_internal') {
            return [
                'id' => (string) ($item['id'] ?? ''),
                'event_type' => 'ops.redacted',
                'summary' => 'An internal operational update was recorded by JetPakistan operations.',
                'entity_type' => $item['entity_type'] ?? null,
                'entity_ref' => $item['entity_ref'] ?? null,
                'created_at' => $item['created_at'] ?? null,
                'read_at' => $item['read_at'] ?? null,
                'deep_link' => null,
                'unread' => empty($item['read_at']),
            ];
        }

        $deep = (string) ($item['deep_link'] ?? '');
        $href = null;
        if (str_starts_with($deep, 'support')) {
            $href = '/agent/support';
        } elseif (str_starts_with($deep, 'bookings/')) {
            $href = '/agent/bookings/'.substr($deep, strlen('bookings/'));
        }

        return [
            'id' => (string) ($item['id'] ?? ''),
            'event_type' => (string) ($item['event_type'] ?? ''),
            'summary' => (string) ($item['summary'] ?? ''),
            'entity_type' => $item['entity_type'] ?? null,
            'entity_ref' => $item['entity_ref'] ?? null,
            'actor_name' => $item['actor_name'] ?? null,
            'created_at' => $item['created_at'] ?? null,
            'read_at' => $item['read_at'] ?? null,
            'deep_link' => $href,
            'unread' => empty($item['read_at']),
        ];
    }
}
