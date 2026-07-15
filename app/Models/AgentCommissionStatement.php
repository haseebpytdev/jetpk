<?php

namespace App\Models;

use App\Enums\AgentCommissionStatementStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'agency_id',
    'agent_id',
    'statement_number',
    'period_start',
    'period_end',
    'status',
    'opening_balance',
    'earned_total',
    'adjustment_total',
    'payout_total',
    'closing_balance',
    'issued_by',
    'issued_at',
    'meta',
])]
class AgentCommissionStatement extends Model
{
    protected function casts(): array
    {
        return [
            'status' => AgentCommissionStatementStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'opening_balance' => 'decimal:2',
            'earned_total' => 'decimal:2',
            'adjustment_total' => 'decimal:2',
            'payout_total' => 'decimal:2',
            'closing_balance' => 'decimal:2',
            'issued_at' => 'datetime',
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

    /** @return BelongsTo<User, $this> */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /** @return BelongsToMany<AgentCommissionEntry, $this> */
    public function entries(): BelongsToMany
    {
        return $this->belongsToMany(AgentCommissionEntry::class, 'agent_commission_entry_statement', 'statement_id', 'entry_id')
            ->withTimestamps();
    }
}
