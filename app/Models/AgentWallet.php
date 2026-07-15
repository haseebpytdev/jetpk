<?php

namespace App\Models;

use App\Enums\AgentWalletStatus;
use Database\Factories\AgentWalletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'agency_id',
    'agent_id',
    'user_id',
    'balance',
    'credit_limit',
    'currency',
    'status',
])]
class AgentWallet extends Model
{
    /** @use HasFactory<AgentWalletFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'credit_limit' => 'decimal:2',
            'status' => AgentWalletStatus::class,
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

    /** @return HasMany<AgentWalletTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(AgentWalletTransaction::class);
    }

    /** @return HasMany<AgentDepositRequest, $this> */
    public function depositRequests(): HasMany
    {
        return $this->hasMany(AgentDepositRequest::class);
    }
}
