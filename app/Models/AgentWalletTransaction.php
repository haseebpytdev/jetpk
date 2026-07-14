<?php

namespace App\Models;

use App\Enums\AgentWalletTransactionStatus;
use App\Enums\AgentWalletTransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'agency_id',
    'agent_id',
    'user_id',
    'agent_wallet_id',
    'agent_deposit_request_id',
    'type',
    'amount',
    'balance_before',
    'balance_after',
    'status',
    'reference',
    'description',
    'created_by',
    'approved_by',
    'meta',
])]
class AgentWalletTransaction extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'type' => AgentWalletTransactionType::class,
            'status' => AgentWalletTransactionStatus::class,
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<AgentWallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(AgentWallet::class, 'agent_wallet_id');
    }

    /** @return BelongsTo<AgentDepositRequest, $this> */
    public function depositRequest(): BelongsTo
    {
        return $this->belongsTo(AgentDepositRequest::class, 'agent_deposit_request_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
