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
    public function audiencesFor(string $eventType, ?int $agencyId = null): array
    {
        $resolved = $this->resolve($eventType, $agencyId);

        return ['audiences' => $resolved->audiences(), 'fallback' => $resolved->fallback];
    }

    public function resolve(string $eventType, ?int $agencyId = null, string $channel = 'email'): ResolvedNotificationRouteSet
    {
        if ($agencyId !== null) {
            $agencyRoutes = $this->loadRoutes($eventType, $channel, $agencyId);
            if ($agencyRoutes !== []) {
                return new ResolvedNotificationRouteSet($agencyRoutes, false, 'agency');
            }
        }

        $globalRoutes = $this->loadRoutes($eventType, $channel, null);
        if ($globalRoutes !== []) {
            return new ResolvedNotificationRouteSet($globalRoutes, false, 'global');
        }

        Log::info('notification.route.legacy_fallback', [
            'event_type' => $eventType,
            'agency_id' => $agencyId,
            'channel' => $channel,
        ]);

        $legacy = NotificationRecipientResolver::policyBucketsFor($eventType);
        $queue = NotificationQueueName::forEventType($eventType);
        $routes = [];
        foreach ($legacy as $bucket) {
            if (! in_array($bucket, self::STRATEGY_ALLOWLIST, true)) {
                continue;
            }
            $routes[] = new ResolvedNotificationRoute(
                audience: $bucket,
                recipientStrategy: $bucket,
                queueName: $queue->value,
                priority: $queue->name,
                templateKey: $eventType,
                provider: 'laravel_mail',
                locale: null,
                fromLegacyFallback: true,
            );
        }

        return new ResolvedNotificationRouteSet($routes, true, 'legacy');
    }

    public static function queueFor(string $eventType): string
    {
        if (! (bool) config('notifications.pipeline.async', false)) {
            return (string) config('notifications.pipeline.compat_queue', 'default');
        }

        return NotificationQueueName::forEventType($eventType)->value;
    }

    /**
     * @return list<ResolvedNotificationRoute>
     */
    protected function loadRoutes(string $eventType, string $channel, ?int $agencyId): array
    {
        $query = NotificationRoute::query()
            ->where('event_type', $eventType)
            ->where('channel', $channel)
            ->where('enabled', true);

        if ($agencyId === null) {
            $query->whereNull('agency_id');
        } else {
            $query->where('agency_id', $agencyId);
        }

        $rows = $query->orderBy('id')->get();
        $routes = [];
        foreach ($rows as $row) {
            $strategy = (string) $row->recipient_strategy;
            if (! in_array($strategy, self::STRATEGY_ALLOWLIST, true)) {
                continue;
            }
            $queueName = is_string($row->queue_name) && $row->queue_name !== ''
                ? $row->queue_name
                : NotificationQueueName::forEventType($eventType)->value;
            $routes[] = new ResolvedNotificationRoute(
                audience: is_string($row->audience) && $row->audience !== '' ? $row->audience : $strategy,
                recipientStrategy: $strategy,
                queueName: $queueName,
                priority: is_string($row->priority) && $row->priority !== '' ? $row->priority : NotificationQueueName::forEventType($eventType)->name,
                templateKey: is_string($row->template_key) ? $row->template_key : $eventType,
                provider: is_string($row->provider) && $row->provider !== '' ? $row->provider : 'laravel_mail',
                locale: is_string($row->locale) ? $row->locale : null,
            );
        }

        return $routes;
    }
}
