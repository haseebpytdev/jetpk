<?php

namespace App\Support\Support;

use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use Illuminate\Support\Carbon;

/**
 * Derives support ticket progress steps from existing ticket fields (no event history).
 */
class SupportTicketTimelineBuilder
{
    public const AUDIENCE_INTERNAL = 'internal';

    public const AUDIENCE_CUSTOMER = 'customer';

    public const AUDIENCE_AGENT = 'agent';

    /**
     * @return list<array{key: string, label: string, state: string, detail: string|null, at: string|null}>
     */
    public function build(SupportTicket $ticket, string $audience = self::AUDIENCE_INTERNAL): array
    {
        $audience = $this->normalizeAudience($audience);
        $status = $ticket->status instanceof SupportTicketStatus
            ? $ticket->status
            : SupportTicketStatus::tryFrom((string) $ticket->status) ?? SupportTicketStatus::Open;

        $isTerminal = in_array($status, [SupportTicketStatus::Resolved, SupportTicketStatus::Closed], true);
        $isAssigned = $ticket->assigned_to_user_id !== null;
        $isForwarded = $ticket->forwarded_to_agent_id !== null;
        $hasReply = $this->hasReplySignal($ticket);
        $hasActivity = $isAssigned || $isForwarded || $hasReply;

        $steps = [
            $this->submittedStep($ticket, $audience),
            $this->receivedStep($ticket, $audience),
        ];

        if ($isAssigned) {
            $steps[] = $this->assignedStep($ticket, $audience);
        }

        if ($isForwarded) {
            $steps[] = $this->forwardedStep($ticket, $audience);
        }

        $steps[] = $this->inProgressStep($ticket, $audience, $status, $isTerminal, $hasActivity, $hasReply);
        $steps[] = $this->resolvedStep($ticket, $status);
        $steps[] = $this->closedStep($ticket, $status);

        return $steps;
    }

    private function normalizeAudience(string $audience): string
    {
        return in_array($audience, [self::AUDIENCE_INTERNAL, self::AUDIENCE_CUSTOMER, self::AUDIENCE_AGENT], true)
            ? $audience
            : self::AUDIENCE_INTERNAL;
    }

    /**
     * @return array{key: string, label: string, state: string, detail: string|null, at: string|null}
     */
    private function submittedStep(SupportTicket $ticket, string $audience): array
    {
        $detail = match ($audience) {
            self::AUDIENCE_CUSTOMER => $this->customerSubmittedDetail($ticket),
            self::AUDIENCE_AGENT => $this->agentSubmittedDetail($ticket),
            default => $this->internalSubmittedDetail($ticket),
        };

        return [
            'key' => 'submitted',
            'label' => match ($audience) {
                self::AUDIENCE_CUSTOMER => 'Request submitted',
                default => 'Ticket submitted',
            },
            'state' => 'completed',
            'detail' => $detail,
            'at' => $this->formatAt($ticket->created_at),
        ];
    }

    /**
     * @return array{key: string, label: string, state: string, detail: string|null, at: string|null}
     */
    private function receivedStep(SupportTicket $ticket, string $audience): array
    {
        return [
            'key' => 'received',
            'label' => match ($audience) {
                self::AUDIENCE_CUSTOMER => 'Received by our team',
                default => 'Received by support',
            },
            'state' => 'completed',
            'detail' => match ($audience) {
                self::AUDIENCE_CUSTOMER => 'Your request is in the support queue.',
                self::AUDIENCE_AGENT => 'Support team has the ticket on file.',
                default => 'Ticket is in the agency support queue.',
            },
            'at' => $this->formatAt($ticket->created_at),
        ];
    }

    /**
     * @return array{key: string, label: string, state: string, detail: string|null, at: string|null}
     */
    private function assignedStep(SupportTicket $ticket, string $audience): array
    {
        $ticket->loadMissing('assignedTo');

        return [
            'key' => 'assigned',
            'label' => match ($audience) {
                self::AUDIENCE_CUSTOMER => 'Assigned to support',
                default => 'Assigned to staff',
            },
            'state' => 'completed',
            'detail' => match ($audience) {
                self::AUDIENCE_CUSTOMER => 'A support team member is handling your request.',
                self::AUDIENCE_AGENT => 'Assigned to support staff for handling.',
                default => $ticket->assignedTo !== null
                    ? $ticket->assignedTo->name
                    : 'Assigned to support staff.',
            },
            'at' => null,
        ];
    }

    /**
     * @return array{key: string, label: string, state: string, detail: string|null, at: string|null}
     */
    private function forwardedStep(SupportTicket $ticket, string $audience): array
    {
        $ticket->loadMissing('forwardedToAgent.user');

        $agentLabel = $ticket->forwardedToAgent !== null
            ? trim($ticket->forwardedToAgent->code.' — '.($ticket->forwardedToAgent->user?->name ?? 'Agent'))
            : null;

        return [
            'key' => 'forwarded',
            'label' => match ($audience) {
                self::AUDIENCE_CUSTOMER => 'Escalated for review',
                self::AUDIENCE_AGENT => 'Forwarded to your agency',
                default => 'Forwarded to agent',
            },
            'state' => 'completed',
            'detail' => match ($audience) {
                self::AUDIENCE_CUSTOMER => 'Your request was escalated for specialist follow-up.',
                self::AUDIENCE_AGENT => $agentLabel ?? 'Forwarded for agency handling.',
                default => $agentLabel,
            },
            'at' => $this->formatAt($ticket->forwarded_at),
        ];
    }

    /**
     * @return array{key: string, label: string, state: string, detail: string|null, at: string|null}
     */
    private function inProgressStep(
        SupportTicket $ticket,
        string $audience,
        SupportTicketStatus $status,
        bool $isTerminal,
        bool $hasActivity,
        bool $hasReply,
    ): array {
        $state = match (true) {
            $isTerminal => 'completed',
            in_array($status, [SupportTicketStatus::Open, SupportTicketStatus::Pending], true) && $hasActivity => 'active',
            default => 'pending',
        };

        [$label, $detail] = match ($audience) {
            self::AUDIENCE_CUSTOMER => [
                $status === SupportTicketStatus::Pending ? 'Awaiting your input' : 'Under review',
                $status === SupportTicketStatus::Pending
                    ? 'We may need more information from you.'
                    : ($hasActivity
                        ? 'Our team is working on your request.'
                        : 'Your request is queued for review.'),
            ],
            self::AUDIENCE_AGENT => [
                $status === SupportTicketStatus::Pending ? 'Pending' : 'In progress',
                $status === SupportTicketStatus::Pending
                    ? 'Awaiting customer or support response.'
                    : ($hasActivity
                        ? 'Ticket is actively being handled.'
                        : 'Waiting for assignment or first response.'),
            ],
            default => [
                $status === SupportTicketStatus::Pending ? 'Pending' : 'In progress',
                $status === SupportTicketStatus::Pending
                    ? 'Awaiting customer, agent, or internal follow-up.'
                    : ($hasActivity
                        ? 'Ticket is actively being worked.'
                        : 'Queued — not yet assigned, forwarded, or replied.'),
            ],
        };

        $at = ($state === 'active' && $hasReply && $ticket->last_reply_at !== null)
            ? $this->formatAt($ticket->last_reply_at)
            : null;

        return [
            'key' => 'in_progress',
            'label' => $label,
            'state' => $state,
            'detail' => $detail,
            'at' => $at,
        ];
    }

    /**
     * @return array{key: string, label: string, state: string, detail: string|null, at: string|null}
     */
    private function resolvedStep(SupportTicket $ticket, SupportTicketStatus $status): array
    {
        $completed = $status === SupportTicketStatus::Resolved;

        return [
            'key' => 'resolved',
            'label' => 'Resolved',
            'state' => $completed ? 'completed' : 'pending',
            'detail' => $completed ? 'Issue marked resolved by support.' : null,
            'at' => $completed ? $this->formatAt($ticket->closed_at) : null,
        ];
    }

    /**
     * @return array{key: string, label: string, state: string, detail: string|null, at: string|null}
     */
    private function closedStep(SupportTicket $ticket, SupportTicketStatus $status): array
    {
        $completed = $status === SupportTicketStatus::Closed;

        return [
            'key' => 'closed',
            'label' => 'Closed',
            'state' => $completed ? 'completed' : 'pending',
            'detail' => $completed ? 'Ticket is closed and no further replies are expected.' : null,
            'at' => $completed ? $this->formatAt($ticket->closed_at) : null,
        ];
    }

    private function internalSubmittedDetail(SupportTicket $ticket): ?string
    {
        $reference = trim((string) ($ticket->ticket_reference ?? ''));
        $source = trim((string) ($ticket->source ?? ''));
        $requester = trim((string) ($ticket->requester_name ?? $ticket->createdBy?->name ?? ''));

        $parts = array_filter([
            $reference !== '' ? 'Ref: '.$reference : null,
            $source !== '' ? 'Source: '.$source : null,
            $requester !== '' ? 'Requester: '.$requester : null,
        ]);

        return $parts !== [] ? implode(' · ', $parts) : null;
    }

    private function customerSubmittedDetail(SupportTicket $ticket): ?string
    {
        $reference = trim((string) ($ticket->ticket_reference ?? ''));

        return $reference !== '' ? 'Reference: '.$reference : null;
    }

    private function agentSubmittedDetail(SupportTicket $ticket): ?string
    {
        $reference = trim((string) ($ticket->ticket_reference ?? ''));

        return $reference !== '' ? 'Ref: '.$reference : 'Submitted from agent portal.';
    }

    private function hasReplySignal(SupportTicket $ticket): bool
    {
        if ($ticket->relationLoaded('messages')) {
            $messages = $ticket->messages;
            if ($messages->count() > 1) {
                return true;
            }

            $creatorId = $ticket->created_by_user_id;

            return $messages->contains(
                static fn ($message): bool => $message->user_id !== null
                    && (int) $message->user_id !== (int) $creatorId,
            );
        }

        if ($ticket->messages()->count() > 1) {
            return true;
        }

        return $ticket->messages()
            ->whereNotNull('user_id')
            ->where('user_id', '!=', $ticket->created_by_user_id)
            ->exists();
    }

    private function formatAt(?Carbon $at): ?string
    {
        return $at?->format('j M Y, H:i');
    }
}
