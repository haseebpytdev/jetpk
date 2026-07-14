<?php

namespace App\Models;

use App\Enums\AgentCommissionEntryStatus;
use App\Enums\AgentCommissionEntryType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'agency_id',
    'agent_id',
    'booking_id',
    'booking_ticket_id',
    'type',
    'status',
    'calculation_basis',
    'rate',
    'base_amount',
    'commission_amount',
    'currency',
    'description',
    'approved_by',
    'approved_at',
    'paid_by',
    'paid_at',
    'meta',
])]
class AgentCommissionEntry extends Model
{
    protected function casts(): array
    {
        return [
            'type' => AgentCommissionEntryType::class,
            'status' => AgentCommissionEntryStatus::class,
            'rate' => 'decimal:4',
            'base_amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return BelongsTo<Agent, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<BookingTicket, $this> */
    public function bookingTicket(): BelongsTo
    {
        return $this->belongsTo(BookingTicket::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /** @return BelongsToMany<AgentCommissionStatement, $this> */
    public function statements(): BelongsToMany
    {
        return $this->belongsToMany(AgentCommissionStatement::class, 'agent_commission_entry_statement', 'entry_id', 'statement_id')
            ->withTimestamps();
    }
}
