<?php

namespace App\Services\Notifications;

use App\Enums\NotificationQueueName;
use App\Models\NotificationRoute;
use App\Services\Communication\NotificationRecipientResolver;
use Illuminate\Support\Facades\Log;

class NotificationRouteResolver
{
    /** @var list<string> */
    public const STRATEGY_ALLOWLIST = [
        'admin',
        'platform_admin',
        'agency_admin',
        'logged_in_user',
        'customer_party',
        'booking_customer',
        'agent_booking',
        'booking_agent',
        'agent',
        'staff_assigned',
        'assigned_staff',
        'finance',
        'platform_staff',
        'agent_staff_creator',
        'operations_queue',
        'applicant',
        'ticket_creator',
        'ticket_assigned_staff',
        'ticket_forwarded_agent',
        'user',
        'staff',
    ];

    public function __construct(
        protected NotificationRecipientResolver $legacyResolver,
    ) {}

    /**
     * @return array{audiences: list<string>, fallback: bool}
     */
    public function audiencesFor(string $eventType): array
    {
        $rows = NotificationRoute::query()
            ->where('event_type', $eventType)
            ->where('channel', 'email')
            ->where('enabled', true)
            ->whereNull('agency_id')
            ->orderBy('id')
            ->get();

        if ($rows->isNotEmpty()) {
            $audiences = $rows
                ->pluck('recipient_strategy')
                ->filter(fn ($strategy): bool => is_string($strategy) && in_array($strategy, self::STRATEGY_ALLOWLIST, true))
                ->values()
                ->all();

            return ['audiences' => $audiences, 'fallback' => false];
        }

        $legacy = NotificationRecipientResolver::policyBucketsFor($eventType);
        Log::info('notification.route.unresolved', [
            'event_type' => $eventType,
            'legacy_fallback' => true,
            'legacy_count' => count($legacy),
        ]);

        return ['audiences' => $legacy, 'fallback' => true];
    }

    public static function queueFor(string $eventType): string
    {
        if (! (bool) config('notifications.pipeline.async', false)) {
            return (string) config('notifications.pipeline.compat_queue', 'default');
        }

        return NotificationQueueName::forEventType($eventType)->value;
    }
}
