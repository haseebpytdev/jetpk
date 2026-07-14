<?php

namespace App\Models;

use App\Enums\AgentDepositRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'agency_id',
    'agent_id',
    'user_id',
    'agent_wallet_id',
    'amount',
    'currency',
    'payment_method',
    'reference',
    'proof_path',
    'agent_note',
    'status',
    'admin_note',
    'reviewed_by',
    'reviewed_at',
])]
class AgentDepositRequest extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => AgentDepositRequestStatus::class,
            'reviewed_at' => 'datetime',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<AgentWallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(AgentWallet::class, 'agent_wallet_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return HasMany<AgentWalletTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(AgentWalletTransaction::class);
    }
}
