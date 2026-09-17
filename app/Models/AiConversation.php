<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AiConversation extends Model
{
    public const STATE_AI_ACTIVE = 'AI_ACTIVE';

    public const STATE_WAITING_FOR_HUMAN = 'WAITING_FOR_HUMAN';

    public const STATE_HUMAN_ACTIVE = 'HUMAN_ACTIVE';

    public const STATE_CLOSED = 'CLOSED';

    protected $fillable = [
        'public_id',
        'channel',
        'visitor_token_hash',
        'user_id',
        'state',
        'taken_over_by_user_id',
        'taken_over_at',
        'last_message_at',
        'shopping_state',
    ];

    protected static function booted(): void
    {
        static::creating(static function (AiConversation $c): void {
            if (! filled($c->public_id)) {
                $c->public_id = (string) Str::uuid();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'taken_over_at' => 'datetime',
            'last_message_at' => 'datetime',
            'shopping_state' => 'array',
        ];
    }

    /** @return HasMany<AiMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function takenOverBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'taken_over_by_user_id');
    }

    public function aiMayReply(): bool
    {
        return $this->state === self::STATE_AI_ACTIVE;
    }
}
