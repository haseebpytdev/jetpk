<?php

namespace App\Services\Support;

use App\Enums\AccountType;
use App\Enums\OtaNotificationEvent;
use App\Enums\SupportTicketMessageVisibility;
use App\Enums\SupportTicketStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\Communication\OtaNotificationService;
use App\Support\Agents\AgentPermission;
use App\Support\References\CompactReferenceGenerator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Support ticket lifecycle: create, reply, status, assign, forward, and E6 notifications.
 */
class SupportTicketService
{
    public function __construct(
        protected OtaNotificationService $notifications,
        protected CompactReferenceGenerator $referenceGenerator,
    ) {}

    /**
     * @param  array{subject: string, category: string, body: string, booking_id?: int|null}  $data
     */
    public function createTicket(User $creator, Agency $agency, array $data, ?Booking $booking = null): SupportTicket
    {
        return DB::transaction(function () use ($creator, $agency, $data, $booking): SupportTicket {
            $ticket = SupportTicket::query()->create([
                'agency_id' => $agency->id,
                'booking_id' => $booking?->id,
                'ticket_reference' => $this->generateUniqueReference(),
                'source' => $this->sourceForPortalUser($creator),
                'requester_name' => $creator->name,
                'requester_email' => $creator->email,
                'created_by_user_id' => $creator->id,
                'subject' => $data['subject'],
                'category' => $data['category'],
                'priority' => 'normal',
                'status' => SupportTicketStatus::Open,
                'last_reply_at' => now(),
            ]);

            SupportTicketMessage::query()->create([
                'support_ticket_id' => $ticket->id,
                'user_id' => $creator->id,
                'visibility' => SupportTicketMessageVisibility::CustomerVisible,
                'body' => $data['body'],
            ]);

            $ticket = $ticket->fresh(['createdBy', 'assignedTo', 'booking']);

            $this->notifyCreated($ticket);

            return $ticket;
        });
    }

    /**
     * @param  array{
     *     subject: string,
     *     category: string,
     *     body: string,
     *     requester_name: string,
     *     requester_email: string
     * }  $data
     */
    public function createPublicTicket(Agency $agency, array $data, ?User $creator = null, ?Booking $booking = null): SupportTicket
    {
        return DB::transaction(function () use ($agency, $data, $creator, $booking): SupportTicket {
            $ticket = SupportTicket::query()->create([
                'agency_id' => $agency->id,
                'booking_id' => $booking?->id,
                'ticket_reference' => $this->generateUniqueReference(),
                'source' => 'public',
                'requester_name' => $data['requester_name'],
                'requester_email' => $data['requester_email'],
                'created_by_user_id' => $creator?->id,
                'subject' => $data['subject'],
                'category' => $data['category'],
                'priority' => 'normal',
                'status' => SupportTicketStatus::Open,
                'last_reply_at' => now(),
            ]);

            SupportTicketMessage::query()->create([
                'support_ticket_id' => $ticket->id,
                'user_id' => $creator?->id,
                'visibility' => SupportTicketMessageVisibility::CustomerVisible,
                'body' => $data['body'],
            ]);

            $ticket = $ticket->fresh(['createdBy', 'assignedTo', 'booking']);

            $this->notifyCreated($ticket);

            return $ticket;
        });
    }

    public function generateUniqueReference(): string
    {
        return $this->referenceGenerator->generateUnique('support_tickets', 'ticket_reference', 8, 'S');
    }

    public function resolveDefaultAgency(): Agency
    {
        $slug = (string) config('ota.default_agency_slug', 'asif-travels');

        return Agency::query()->where('slug', $slug)->firstOrFail();
    }

    public function resolveBookingByReference(Agency $agency, ?string $bookingReference): ?Booking
    {
        $reference = trim((string) $bookingReference);
        if ($reference === '') {
            return null;
        }

        return Booking::query()
            ->where('agency_id', $agency->id)
            ->where('booking_reference', $reference)
            ->first();
    }

    private function sourceForPortalUser(User $creator): string
    {
        if ($creator->isCustomer()) {
            return 'customer';
        }

        if ($creator->isAgentPortalUser()) {
            return 'agent';
        }

        return 'portal';
    }

    public function reply(
        SupportTicket $ticket,
        User $author,
        string $body,
        SupportTicketMessageVisibility $visibility = SupportTicketMessageVisibility::CustomerVisible,
    ): SupportTicketMessage {
        if ($ticket->isClosed()) {
            throw new InvalidArgumentException('Cannot reply to a closed ticket.');
        }

        $message = DB::transaction(function () use ($ticket, $author, $body, $visibility): SupportTicketMessage {
            $message = SupportTicketMessage::query()->create([
                'support_ticket_id' => $ticket->id,
                'user_id' => $author->id,
                'visibility' => $visibility,
                'body' => $body,
            ]);

            $updates = ['last_reply_at' => now()];

            if ($author->isCustomer() || $author->isAgent()) {
                $updates['status'] = SupportTicketStatus::Pending;
            } elseif ($visibility === SupportTicketMessageVisibility::CustomerVisible) {
                $updates['status'] = SupportTicketStatus::Open;
            }

            $ticket->forceFill($updates)->save();

            return $message->fresh(['author']);
        });

        if ($visibility === SupportTicketMessageVisibility::CustomerVisible) {
            $this->notifyReplied($ticket->fresh(['createdBy', 'assignedTo', 'booking']), $author);
        }

        return $message;
    }

    public function updateStatus(SupportTicket $ticket, SupportTicketStatus $status, User $actor): SupportTicket
    {
        $previous = $ticket->status;
        $ticket->forceFill([
            'status' => $status,
            'closed_at' => in_array($status, [SupportTicketStatus::Resolved, SupportTicketStatus::Closed], true)
                ? now()
                : null,
        ])->save();

        if ($previous !== $status) {
            $this->notifyStatusChanged($ticket->fresh(['createdBy', 'assignedTo', 'booking']), $actor, $status);
        }

        return $ticket;
    }

    public function assign(SupportTicket $ticket, ?User $assignee, ?User $actor = null): SupportTicket
    {
        $previousAssigneeId = $ticket->assigned_to_user_id;

        $ticket->forceFill(['assigned_to_user_id' => $assignee?->id])->save();

        $fresh = $ticket->fresh(['assignedTo', 'createdBy', 'booking']);

        if (
            $assignee !== null
            && $assignee->isStaff()
            && (int) $previousAssigneeId !== (int) $assignee->id
        ) {
            $this->notifyAssigned($fresh, $actor);
        }

        return $fresh;
    }

    public function forward(SupportTicket $ticket, ?Agent $agent, User $actor): SupportTicket
    {
        $previousAgentId = $ticket->forwarded_to_agent_id;

        if ($agent === null) {
            $ticket->forceFill([
                'forwarded_to_agent_id' => null,
                'forwarded_at' => null,
                'forwarded_by_user_id' => null,
            ])->save();
        } else {
            $ticket->forceFill([
                'forwarded_to_agent_id' => $agent->id,
                'forwarded_at' => now(),
                'forwarded_by_user_id' => $actor->id,
            ])->save();
        }

        $fresh = $ticket->fresh(['forwardedToAgent.user', 'createdBy', 'assignedTo', 'booking']);

        if ($agent !== null && (int) $previousAgentId !== (int) $agent->id) {
            $this->notifyForwarded($fresh, $actor);
        }

        return $fresh;
    }

    public function closeByCustomer(SupportTicket $ticket, User $customer): SupportTicket
    {
        return $this->updateStatus($ticket, SupportTicketStatus::Closed, $customer);
    }

    private function notifyCreated(SupportTicket $ticket): void
    {
        $agency = Agency::query()->findOrFail($ticket->agency_id);

        $reference = (string) ($ticket->ticket_reference ?? '');

        $this->notifications->send(
            $agency,
            OtaNotificationEvent::SupportTicketCreated->value,
            $this->safePayload($ticket),
            $ticket->booking,
            $ticket->createdBy,
            'Support ticket '.$reference.' opened',
            'A new support ticket was submitted.',
            $this->templateVars($ticket),
            [],
            $this->recipientContext($ticket, [
                'notify_buckets' => ['admin', 'ticket_assigned_staff', 'ticket_creator'],
            ]),
        );
    }

    private function notifyReplied(SupportTicket $ticket, User $author): void
    {
        $agency = Agency::query()->findOrFail($ticket->agency_id);

        $buckets = $author->isCustomer() || $author->isAgent()
            ? ['admin', 'ticket_assigned_staff']
            : ['ticket_creator'];

        $this->notifications->send(
            $agency,
            OtaNotificationEvent::SupportTicketReplied->value,
            $this->safePayload($ticket, ['replied_by' => $author->account_type?->value ?? 'user']),
            $ticket->booking,
            $author,
            'Reply on support ticket #'.$ticket->id,
            'There is a new reply on your support ticket.',
            $this->templateVars($ticket),
            [],
            $this->recipientContext($ticket, ['notify_buckets' => $buckets]),
        );
    }

    private function notifyAssigned(SupportTicket $ticket, ?User $actor): void
    {
        $agency = Agency::query()->findOrFail($ticket->agency_id);
        $reference = (string) ($ticket->ticket_reference ?? (string) $ticket->id);

        $this->notifications->send(
            $agency,
            OtaNotificationEvent::SupportTicketAssigned->value,
            $this->safePayload($ticket),
            $ticket->booking,
            $actor,
            'Support ticket '.$reference.' assigned to you',
            'A support ticket has been assigned to you.',
            $this->templateVars($ticket),
            [],
            $this->recipientContext($ticket, [
                'notify_buckets' => ['ticket_assigned_staff'],
            ]),
        );
    }

    private function notifyForwarded(SupportTicket $ticket, User $actor): void
    {
        $agency = Agency::query()->findOrFail($ticket->agency_id);
        $reference = (string) ($ticket->ticket_reference ?? (string) $ticket->id);

        $this->notifications->send(
            $agency,
            OtaNotificationEvent::SupportTicketForwarded->value,
            $this->safePayload($ticket),
            $ticket->booking,
            $actor,
            'Support ticket '.$reference.' forwarded to your agency',
            'A support ticket has been forwarded for your agency to handle.',
            $this->templateVars($ticket),
            [],
            $this->recipientContext($ticket, [
                'notify_buckets' => ['ticket_forwarded_agent'],
                'ticket_forwarded_agent_emails' => $this->forwardedAgentRecipientEmails($ticket),
            ]),
        );
    }

    private function notifyStatusChanged(SupportTicket $ticket, User $actor, SupportTicketStatus $status): void
    {
        $agency = Agency::query()->findOrFail($ticket->agency_id);

        $this->notifications->send(
            $agency,
            OtaNotificationEvent::SupportTicketStatusChanged->value,
            $this->safePayload($ticket, ['new_status' => $status->value]),
            $ticket->booking,
            $actor,
            'Support ticket #'.$ticket->id.' status updated',
            'Your support ticket status is now: '.$status->value.'.',
            array_merge($this->templateVars($ticket), ['ticket_status' => $status->value]),
            [],
            $this->recipientContext($ticket, ['notify_buckets' => ['ticket_creator']]),
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function safePayload(SupportTicket $ticket, array $extra = []): array
    {
        return array_merge([
            'ticket_id' => $ticket->id,
            'ticket_reference' => (string) ($ticket->ticket_reference ?? ''),
            'ticket_subject' => $ticket->subject,
            'ticket_category' => $ticket->category->value,
            'ticket_status' => $ticket->status->value,
            'requester_name' => (string) ($ticket->requester_name ?? ''),
            'requester_email' => (string) ($ticket->requester_email ?? ''),
            'booking_reference' => $ticket->booking?->booking_reference ?? '',
        ], $extra);
    }

    /**
     * @return array<string, string>
     */
    private function templateVars(SupportTicket $ticket): array
    {
        return [
            'ticket_id' => (string) $ticket->id,
            'ticket_reference' => (string) ($ticket->ticket_reference ?? ''),
            'ticket_subject' => $ticket->subject,
            'ticket_status' => $ticket->status->value,
            'requester_name' => (string) ($ticket->requester_name ?? ''),
            'requester_email' => (string) ($ticket->requester_email ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function recipientContext(SupportTicket $ticket, array $extra = []): array
    {
        return array_merge([
            'ticket_creator_email' => $ticket->createdBy?->email ?? $ticket->requester_email,
            'ticket_assigned_staff_email' => $ticket->assignedTo?->email,
        ], $extra);
    }

    /**
     * Agent owner plus agent staff with SupportManage under the forwarded agent.
     *
     * @return list<string|null>
     */
    private function forwardedAgentRecipientEmails(SupportTicket $ticket): array
    {
        $agentId = $ticket->forwarded_to_agent_id;
        if ($agentId === null) {
            return [];
        }

        $agent = $ticket->relationLoaded('forwardedToAgent')
            ? $ticket->forwardedToAgent
            : Agent::query()->with('user')->find($agentId);

        if ($agent === null) {
            return [];
        }

        $emails = [$agent->user?->email];

        $staffEmails = User::query()
            ->where('account_type', AccountType::AgentStaff)
            ->where('current_agency_id', $agent->agency_id)
            ->where('meta->owner_agent_id', $agent->id)
            ->get()
            ->filter(static fn (User $user): bool => $user->hasAgentPermission(AgentPermission::SupportManage))
            ->pluck('email')
            ->all();

        return array_merge($emails, $staffEmails);
    }
}
