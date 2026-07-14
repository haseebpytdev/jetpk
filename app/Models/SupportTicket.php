<?php

namespace App\Models;

use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'agency_id',
    'booking_id',
    'ticket_reference',
    'source',
    'requester_name',
    'requester_email',
    'created_by_user_id',
    'assigned_to_user_id',
    'forwarded_to_agent_id',
    'forwarded_at',
    'forwarded_by_user_id',
    'subject',
    'category',
    'priority',
    'status',
    'last_reply_at',
    'closed_at',
])]
class SupportTicket extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SupportTicketStatus::class,
            'category' => SupportTicketCategory::class,
            'last_reply_at' => 'datetime',
            'closed_at' => 'datetime',
            'forwarded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /** @return BelongsTo<Agent, $this> */
    public function forwardedToAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'forwarded_to_agent_id');
    }

    /** @return BelongsTo<User, $this> */
    public function forwardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'forwarded_by_user_id');
    }

    public function isForwardedToAgent(?int $agentId): bool
    {
        return $agentId !== null
            && (int) ($this->forwarded_to_agent_id ?? 0) === $agentId;
    }

    /** @return HasMany<SupportTicketMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class)->orderBy('created_at');
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [SupportTicketStatus::Resolved, SupportTicketStatus::Closed], true);
    }

    /**
     * @param  Builder<SupportTicket>  $query
     * @return Builder<SupportTicket>
     */
    public function scopeForAgency(Builder $query, User $user): Builder
    {
        return $query->where('agency_id', $user->current_agency_id);
    }

    /**
     * Open or pending — not resolved/closed.
     *
     * @param  Builder<SupportTicket>  $query
     * @return Builder<SupportTicket>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            SupportTicketStatus::Resolved,
            SupportTicketStatus::Closed,
        ]);
    }

    /**
     * @param  Builder<SupportTicket>  $query
     * @return Builder<SupportTicket>
     */
    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('assigned_to_user_id');
    }

    /**
     * @param  Builder<SupportTicket>  $query
     * @return Builder<SupportTicket>
     */
    public function scopePublicGuest(Builder $query): Builder
    {
        return $query->where('source', 'public');
    }

    /**
     * @param  Builder<SupportTicket>  $query
     * @return Builder<SupportTicket>
     */
    public function scopeAssignedToUser(Builder $query, User $user): Builder
    {
        return $query->where('assigned_to_user_id', $user->id);
    }

    /**
     * @param  Builder<SupportTicket>  $query
     * @return Builder<SupportTicket>
     */
    public function scopeCreatedWithinDays(Builder $query, int $days): Builder
    {
        $days = max(1, min(90, $days));

        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Safe index filters for admin/staff ticket lists (query string only).
     *
     * @param  Builder<SupportTicket>  $query
     * @param  array<string, mixed>  $filters
     */
    public static function applyIndexFilters(Builder $query, array $filters, User $user): void
    {
        $queue = is_string($filters['queue'] ?? null) ? (string) $filters['queue'] : '';
        if ($queue === 'active') {
            $query->active();
        } elseif ($queue === 'open') {
            $query->where('status', SupportTicketStatus::Open);
        }

        if (($filters['assigned'] ?? null) === 'unassigned') {
            $query->unassigned();
        }

        if (filter_var($filters['assigned_to_me'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->assignedToUser($user);
        }

        if (($filters['source'] ?? null) === 'public') {
            $query->publicGuest();
        }

        $recent = (int) ($filters['recent'] ?? 0);
        if ($recent > 0) {
            $query->createdWithinDays($recent)->active();
        }

        $status = is_string($filters['status'] ?? null) ? (string) $filters['status'] : '';
        if ($status !== '' && in_array($status, SupportTicketStatus::values(), true)) {
            $query->where('status', $status);
        }
    }

    /**
     * @param  Builder<SupportTicket>  $query
     * @return Builder<SupportTicket>
     */
    public function scopeForAgentPortalUser(Builder $query, User $user): Builder
    {
        $query->where('agency_id', $user->current_agency_id);

        $agent = $user->agent();
        if ($agent === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isAgentAdmin()) {
            $portalUserIds = $user->ownerAgentPortalUserIds();

            return $query->where(function (Builder $inner) use ($agent, $portalUserIds): void {
                $inner->whereIn('created_by_user_id', $portalUserIds)
                    ->orWhereHas('booking', fn (Builder $booking) => $booking->where('agent_id', $agent->id))
                    ->orWhere('forwarded_to_agent_id', $agent->id);
            });
        }

        return $query->where(function (Builder $inner) use ($user, $agent): void {
            $inner->where('created_by_user_id', $user->id)
                ->orWhere('forwarded_to_agent_id', $agent->id);
        });
    }
}
