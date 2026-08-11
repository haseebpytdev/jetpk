<?php

namespace App\Services\Ops;

use App\Enums\AccountType;
use App\Models\AgentDepositRequest;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin orchestration helper: write audit (when needed) + fan-out durable inbox items.
 * Does not own business mutations — callers remain authoritative.
 */
class OpsEventDispatcher
{
    public function __construct(
        protected OpsInboxService $inbox,
    ) {}

    /**
     * @param  list<User|int>  $recipients
     * @param  array<string, mixed>  $properties
     */
    public function dispatch(
        array $recipients,
        string $eventType,
        string $entityType,
        int|string|null $entityId,
        ?string $entityRef,
        string $summary,
        ?User $actor = null,
        ?int $agencyId = null,
        ?string $deepLink = null,
        ?string $category = null,
        ?string $eventKey = null,
        array $properties = [],
        bool $writeAudit = false,
        ?string $auditableType = null,
    ): void {
        try {
            $key = $eventKey ?: sprintf(
                '%s:%s:%s:%s',
                $eventType,
                (string) $entityType,
                (string) ($entityId ?? '0'),
                (string) ($properties['dedupe_token'] ?? now()->format('YmdHis')),
            );

            if ($writeAudit) {
                AuditLog::query()->create([
                    'agency_id' => $agencyId,
                    'user_id' => $actor?->id,
                    'action' => $eventType,
                    'auditable_type' => $auditableType,
                    'auditable_id' => is_numeric($entityId) ? (int) $entityId : null,
                    'properties' => array_merge([
                        'summary' => $summary,
                        'entity_ref' => $entityRef,
                        'deep_link' => $deepLink,
                    ], $properties),
                ]);
            }

            $uniqueRecipients = [];
            foreach ($recipients as $recipient) {
                $id = $recipient instanceof User ? (int) $recipient->id : (int) $recipient;
                if ($id <= 0) {
                    continue;
                }
                // Never notify the actor about their own action unless sole recipient intent is explicit.
                if ($actor !== null && $id === (int) $actor->id) {
                    continue;
                }
                $uniqueRecipients[$id] = $id;
            }

            if ($uniqueRecipients === []) {
                return;
            }

            $this->inbox->fanOut(array_values($uniqueRecipients), [
                'event_key' => $key,
                'event_type' => $eventType,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'entity_ref' => $entityRef,
                'summary' => $summary,
                'actor_id' => $actor?->id,
                'actor_name' => $actor?->name,
                'actor_role' => $this->inbox->actorRoleLabel($actor),
                'agency_id' => $agencyId,
                'deep_link' => $deepLink,
                'category' => $category ?? $entityType,
            ]);
        } catch (Throwable $e) {
            Log::warning('ops.event_dispatch_failed', [
                'event_type' => $eventType,
                'entity_type' => $entityType,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function bookingAssigned(Booking $booking, User $actor, ?User $assignee): void
    {
        if ($assignee === null) {
            return;
        }

        $ref = (string) ($booking->booking_reference ?? $booking->id);
        $this->dispatch(
            recipients: [$assignee],
            eventType: 'booking.staff_assigned',
            entityType: 'booking',
            entityId: $booking->id,
            entityRef: $ref,
            summary: 'Booking '.$ref.' was assigned to you',
            actor: $actor,
            agencyId: $booking->agency_id,
            deepLink: 'bookings/'.rawurlencode($ref),
            category: 'bookings',
            eventKey: 'booking.staff_assigned:'.$booking->id.':'.$assignee->id,
        );
    }

    public function bookingNoteAdded(Booking $booking, User $actor, bool $customerVisible): void
    {
        $ref = (string) ($booking->booking_reference ?? $booking->id);
        $recipients = [];

        if ($customerVisible && $booking->customer_id) {
            $recipients[] = (int) $booking->customer_id;
        }

        // Internal notes notify platform admins / assigned staff watchers via activity; inbox to admins in agency.
        if (! $customerVisible) {
            foreach ($this->agencyOpsRecipients((int) $booking->agency_id) as $opsId) {
                $recipients[] = $opsId;
            }
            if ($booking->assigned_staff_id) {
                $recipients[] = (int) $booking->assigned_staff_id;
            }
        }

        $this->dispatch(
            recipients: $recipients,
            eventType: $customerVisible ? 'booking.note_customer_visible' : 'booking.note_internal',
            entityType: 'booking',
            entityId: $booking->id,
            entityRef: $ref,
            summary: $customerVisible
                ? 'A note was added on booking '.$ref
                : 'Internal note added on booking '.$ref,
            actor: $actor,
            agencyId: $booking->agency_id,
            deepLink: 'bookings/'.rawurlencode($ref),
            category: 'bookings',
            eventKey: 'booking.note:'.$booking->id.':'.$actor->id.':'.now()->timestamp,
        );
    }

    public function supportTicketCreated(SupportTicket $ticket, ?User $actor = null): void
    {
        $ref = (string) ($ticket->ticket_reference ?? $ticket->id);
        $recipients = $this->agencyOpsRecipients((int) $ticket->agency_id);

        $this->dispatch(
            recipients: $recipients,
            eventType: 'support.ticket_created',
            entityType: 'support_ticket',
            entityId: $ticket->id,
            entityRef: $ref,
            summary: 'Support ticket '.$ref.' opened: '.((string) $ticket->subject),
            actor: $actor ?? $ticket->createdBy,
            agencyId: $ticket->agency_id,
            deepLink: 'support?ticket='.rawurlencode($ref),
            category: 'support',
            eventKey: 'support.ticket_created:'.$ticket->id,
            writeAudit: true,
            auditableType: SupportTicket::class,
            properties: [
                'dedupe_token' => 'created-'.$ticket->id,
                'status' => (string) ($ticket->status?->value ?? $ticket->status),
            ],
        );
    }

    public function supportTicketAssigned(SupportTicket $ticket, ?User $assignee, ?User $actor): void
    {
        if ($assignee === null) {
            return;
        }

        $ref = (string) ($ticket->ticket_reference ?? $ticket->id);
        $this->dispatch(
            recipients: [$assignee],
            eventType: 'support.ticket_assigned',
            entityType: 'support_ticket',
            entityId: $ticket->id,
            entityRef: $ref,
            summary: 'Support ticket '.$ref.' was assigned to you',
            actor: $actor,
            agencyId: $ticket->agency_id,
            deepLink: 'support?ticket='.rawurlencode($ref),
            category: 'support',
            eventKey: 'support.ticket_assigned:'.$ticket->id.':'.$assignee->id,
            writeAudit: true,
            auditableType: SupportTicket::class,
            properties: [
                'dedupe_token' => 'assigned-'.$ticket->id.'-'.$assignee->id,
                'assigned_to_user_id' => $assignee->id,
            ],
        );
    }

    public function supportMessagePosted(SupportTicket $ticket, User $author, bool $customerVisible): void
    {
        $ref = (string) ($ticket->ticket_reference ?? $ticket->id);
        $recipients = [];

        if ($customerVisible) {
            if ($author->isCustomer() || $author->isAgentPortalUser()) {
                // Customer/agent reply → staff assignee + ops
                if ($ticket->assigned_to_user_id) {
                    $recipients[] = (int) $ticket->assigned_to_user_id;
                }
                foreach ($this->agencyOpsRecipients((int) $ticket->agency_id) as $opsId) {
                    $recipients[] = $opsId;
                }
            } else {
                // Staff reply visible to customer/creator
                if ($ticket->created_by_user_id) {
                    $recipients[] = (int) $ticket->created_by_user_id;
                }
            }
        } else {
            // Internal-only: never fan-out to customer/agent
            foreach ($this->agencyOpsRecipients((int) $ticket->agency_id) as $opsId) {
                $recipients[] = $opsId;
            }
            if ($ticket->assigned_to_user_id) {
                $recipients[] = (int) $ticket->assigned_to_user_id;
            }
        }

        $this->dispatch(
            recipients: $recipients,
            eventType: $customerVisible ? 'support.message_posted' : 'support.message_internal',
            entityType: 'support_ticket',
            entityId: $ticket->id,
            entityRef: $ref,
            summary: $customerVisible
                ? 'New message on support ticket '.$ref
                : 'Internal note on support ticket '.$ref,
            actor: $author,
            agencyId: $ticket->agency_id,
            deepLink: 'support?ticket='.rawurlencode($ref),
            category: 'support',
            eventKey: 'support.message:'.$ticket->id.':'.$author->id.':'.now()->timestamp.':'.($customerVisible ? 'pub' : 'int'),
            writeAudit: true,
            auditableType: SupportTicket::class,
            properties: [
                'dedupe_token' => 'msg-'.$ticket->id.'-'.$author->id.'-'.now()->timestamp,
                'customer_visible' => $customerVisible,
            ],
        );
    }

    /**
     * @return list<int>
     */
    protected function agencyOpsRecipients(int $agencyId): array
    {
        return User::query()
            ->where(function ($query) use ($agencyId): void {
                $query->where('account_type', AccountType::PlatformAdmin->value)
                    ->orWhere(function ($inner) use ($agencyId): void {
                        $inner->where('account_type', AccountType::Staff->value)
                            ->where('current_agency_id', $agencyId);
                    });
            })
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    public function agentDepositSubmitted(AgentDepositRequest $deposit, User $actor): void
    {
        $ref = (string) ($deposit->reference ?? $deposit->id);
        $recipients = $this->agencyOpsRecipients((int) $deposit->agency_id);

        $this->dispatch(
            recipients: $recipients,
            eventType: 'agent.deposit_submitted',
            entityType: 'agent_deposit',
            entityId: $deposit->id,
            entityRef: $ref,
            summary: 'Agent deposit request '.$ref.' submitted for review',
            actor: $actor,
            agencyId: $deposit->agency_id,
            deepLink: 'agents/deposits',
            category: 'payments',
            eventKey: 'agent.deposit_submitted:'.$deposit->id,
            writeAudit: true,
            auditableType: AgentDepositRequest::class,
            properties: [
                'dedupe_token' => 'deposit-'.$deposit->id,
                'amount' => (string) $deposit->amount,
                'currency' => (string) $deposit->currency,
            ],
        );
    }
}
