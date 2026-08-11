<?php

namespace App\Services\Dashboard\Api;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Ops\OpsInboxService;
use App\Support\Dashboard\DashboardPermissionResolver;
use Illuminate\Http\Request;

class DashboardOpsReadService
{
    public function __construct(
        protected OpsInboxService $inbox,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function inbox(User $user, Request $request): array
    {
        $this->assertDashboardUser($user);

        $page = max(1, (int) $request->query('page', 1));
        $pageSize = max(5, min(50, (int) $request->query('pageSize', 20)));
        $unreadOnly = filter_var($request->query('unreadOnly', false), FILTER_VALIDATE_BOOLEAN);

        $result = $this->inbox->listForUser($user, $page, $pageSize, $unreadOnly);

        return [
            'transport' => 'EVENT_POLLING',
            'available' => true,
            'unreadCount' => $result['unread_count'],
            'items' => array_map(fn (array $item): array => $this->presentInboxItem($item, $user), $result['items']),
            'pagination' => [
                'page' => $result['pagination']['current_page'],
                'pageSize' => $result['pagination']['per_page'],
                'total' => $result['pagination']['total'],
                'pageCount' => $result['pagination']['last_page'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function unreadSummary(User $user): array
    {
        $this->assertDashboardUser($user);

        return [
            'transport' => 'EVENT_POLLING',
            'available' => true,
            'unreadCount' => $this->inbox->unreadCount($user),
        ];
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, mixed>
     */
    public function markRead(User $user, array $ids): array
    {
        $this->assertDashboardUser($user);
        $changed = $this->inbox->markManyRead($user, $ids);

        return [
            'available' => true,
            'marked' => $changed,
            'unreadCount' => $this->inbox->unreadCount($user->fresh()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function markAllRead(User $user): array
    {
        $this->assertDashboardUser($user);
        $changed = $this->inbox->markAllRead($user);

        return [
            'available' => true,
            'marked' => $changed,
            'unreadCount' => $this->inbox->unreadCount($user->fresh()),
        ];
    }

    /**
     * Live activity delta over audit_logs.
     *
     * @return array<string, mixed>
     */
    public function events(User $user, Request $request): array
    {
        DashboardPermissionResolver::assertPermission($user, 'audit.view');

        $sinceId = max(0, (int) $request->query('since_id', $request->query('sinceId', 0)));
        $limit = max(1, min(100, (int) $request->query('limit', 50)));

        $query = AuditLog::query()->with('user')->orderBy('id');
        if (! $user->isPlatformAdmin()) {
            $query->where('agency_id', $user->current_agency_id);
        }
        if ($sinceId > 0) {
            $query->where('id', '>', $sinceId);
        } else {
            $query->orderByDesc('id')->limit($limit);
        }

        $rows = $sinceId > 0
            ? $query->limit($limit)->get()
            : $query->get()->sortBy('id')->values();

        $items = $rows->map(function (AuditLog $log) use ($user): array {
            $ref = data_get($log->properties, 'entity_ref')
                ?? data_get($log->properties, 'new_values.booking_reference')
                ?? (string) ($log->auditable_id ?? '');

            return [
                'id' => (int) $log->id,
                'publicId' => 'JP-AUD-'.$log->id,
                'occurredAt' => $log->created_at?->toIso8601String(),
                'eventType' => (string) $log->action,
                'actorId' => $log->user_id,
                'actorName' => $log->user?->name ?? 'System',
                'actorRole' => $this->inbox->actorRoleLabel($log->user),
                'entityType' => class_basename((string) ($log->auditable_type ?? 'unknown')),
                'entityId' => $log->auditable_id,
                'entityRef' => $ref !== '' ? (string) $ref : null,
                'summary' => (string) (data_get($log->properties, 'summary') ?: $log->action),
                'deepLink' => $this->deepLinkForAudit($log, $user),
                'agencyId' => $log->agency_id,
            ];
        })->values()->all();

        $cursor = $items === []
            ? $sinceId
            : (int) max(array_column($items, 'id'));

        return [
            'transport' => 'EVENT_POLLING',
            'sinceId' => $sinceId,
            'cursor' => $cursor,
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function workQueue(User $user, Request $request): array
    {
        $this->assertDashboardUser($user);
        DashboardPermissionResolver::assertPermission($user, 'bookings.view');

        $limit = max(1, min(50, (int) $request->query('limit', 25)));
        $bookings = Booking::query()
            ->when(! $user->isPlatformAdmin(), fn ($q) => $q->where('agency_id', $user->current_agency_id))
            ->when($user->isStaff(), fn ($q) => $q->where('assigned_staff_id', $user->id))
            ->when($user->isPlatformAdmin() && $request->boolean('assignedToMe'), fn ($q) => $q->where('assigned_staff_id', $user->id))
            ->orderByDesc('assigned_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'booking_reference', 'status', 'assigned_staff_id', 'assigned_at', 'agency_id', 'updated_at']);

        $tickets = [];
        $keys = DashboardPermissionResolver::effectivePermissionKeys($user);
        if (in_array('support.view', $keys, true)) {
            $tickets = SupportTicket::query()
                ->when(! $user->isPlatformAdmin(), fn ($q) => $q->where('agency_id', $user->current_agency_id))
                ->when($user->isStaff(), fn ($q) => $q->where('assigned_to_user_id', $user->id))
                ->orderByDesc('updated_at')
                ->limit($limit)
                ->get(['id', 'ticket_reference', 'subject', 'status', 'assigned_to_user_id', 'agency_id', 'updated_at', 'priority'])
                ->all();
        }

        return [
            'transport' => 'EVENT_POLLING',
            'bookings' => $bookings->map(static fn (Booking $b): array => [
                'id' => $b->id,
                'reference' => $b->booking_reference,
                'status' => $b->status?->value ?? (string) $b->status,
                'assignedStaffId' => $b->assigned_staff_id,
                'assignedAt' => $b->assigned_at?->toIso8601String(),
                'updatedAt' => $b->updated_at?->toIso8601String(),
                'deepLink' => 'bookings/'.rawurlencode((string) ($b->booking_reference ?? $b->id)),
                'entityType' => 'booking',
            ])->values()->all(),
            'supportTickets' => collect($tickets)->map(static fn (SupportTicket $t): array => [
                'id' => $t->id,
                'reference' => $t->ticket_reference,
                'subject' => $t->subject,
                'status' => $t->status?->value ?? (string) $t->status,
                'assignedToUserId' => $t->assigned_to_user_id,
                'priority' => $t->priority,
                'updatedAt' => $t->updated_at?->toIso8601String(),
                'deepLink' => 'support?ticket='.rawurlencode((string) ($t->ticket_reference ?? $t->id)),
                'entityType' => 'support_ticket',
            ])->values()->all(),
        ];
    }

    protected function assertDashboardUser(User $user): void
    {
        if (! $user->isPlatformAdmin() && ! $user->isStaff()) {
            abort(403, 'Ops inbox is limited to Admin/Staff dashboards.');
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function presentInboxItem(array $item, User $user): array
    {
        $portal = $user->isPlatformAdmin() ? 'admin' : 'staff';
        $deep = (string) ($item['deep_link'] ?? '');
        $href = $deep !== ''
            ? '/'.$portal.'/dashboard/'.ltrim($deep, '/')
            : null;

        return [
            'id' => (string) ($item['id'] ?? ''),
            'eventType' => (string) ($item['event_type'] ?? ''),
            'entityType' => (string) ($item['entity_type'] ?? ''),
            'entityId' => $item['entity_id'] ?? null,
            'entityRef' => $item['entity_ref'] ?? null,
            'summary' => (string) ($item['summary'] ?? ''),
            'actorId' => $item['actor_id'] ?? null,
            'actorName' => $item['actor_name'] ?? null,
            'actorRole' => $item['actor_role'] ?? null,
            'category' => $item['category'] ?? 'operations',
            'createdAt' => $item['created_at'] ?? null,
            'readAt' => $item['read_at'] ?? null,
            'unread' => empty($item['read_at']),
            'deepLink' => $href,
            'agencyId' => $item['agency_id'] ?? null,
        ];
    }

    protected function deepLinkForAudit(AuditLog $log, User $user): ?string
    {
        $portal = $user->isPlatformAdmin() ? 'admin' : 'staff';
        $type = (string) ($log->auditable_type ?? '');
        $explicit = data_get($log->properties, 'deep_link');
        if (is_string($explicit) && $explicit !== '') {
            return '/'.$portal.'/dashboard/'.ltrim($explicit, '/');
        }
        if (str_contains($type, 'Booking')) {
            $ref = (string) (data_get($log->properties, 'entity_ref') ?? $log->auditable_id ?? '');
            if ($ref !== '') {
                return '/'.$portal.'/dashboard/bookings/'.rawurlencode($ref);
            }
        }
        if (str_contains($type, 'SupportTicket')) {
            $ref = (string) (data_get($log->properties, 'entity_ref') ?? $log->auditable_id ?? '');
            if ($ref !== '') {
                return '/'.$portal.'/dashboard/support?ticket='.rawurlencode($ref);
            }
        }

        return '/'.$portal.'/dashboard/audit';
    }
}
