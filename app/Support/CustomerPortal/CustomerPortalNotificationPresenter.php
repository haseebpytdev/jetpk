<?php

namespace App\Support\CustomerPortal;

use App\Models\User;

/**
 * Customer notification center JSON — honest empty state until inbox backend exists.
 */
class CustomerPortalNotificationPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function presentIndex(User $user, int $page = 1, int $perPage = 20): array
    {
        return [
            'ok' => true,
            'available' => false,
            'message' => 'In-app notifications are not available yet. Booking updates are sent to your registered email address.',
            'unread_count' => 0,
            'notifications' => [],
            'pagination' => [
                'current_page' => $page,
                'last_page' => 1,
                'per_page' => $perPage,
                'total' => 0,
                'from' => null,
                'to' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentUnreadSummary(User $user): array
    {
        return [
            'ok' => true,
            'available' => false,
            'unread_count' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function markReadUnavailable(): array
    {
        return [
            'ok' => false,
            'available' => false,
            'message' => 'In-app notification read state is not available yet.',
        ];
    }
}
