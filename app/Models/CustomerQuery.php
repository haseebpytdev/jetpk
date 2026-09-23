<?php

namespace App\Models;

use App\Enums\CustomerQueryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CustomerQuery extends Model
{
    protected $fillable = [
        'query_reference',
        'visitor_token_hash',
        'user_id',
        'ai_conversation_id',
        'ai_embed_tenant_id',
        'name',
        'email',
        'email_verified',
        'phone_raw',
        'phone_e164',
        'phone_country',
        'phone_verified',
        'contact_consent',
        'consent_timestamp',
        'consent_source',
        'source',
        'intent',
        'origin',
        'destination',
        'departure_date',
        'return_date',
        'trip_type',
        'adult_count',
        'child_count',
        'infant_count',
        'status',
        'priority',
        'callback_required',
        'assigned_to_user_id',
        'ai_summary',
        'travel_state',
        'ip_country_hint',
        'internal_notes',
        'last_activity_at',
        'closed_at',
    ];

    protected static function booted(): void
    {
        static::creating(static function (CustomerQuery $query): void {
            if (! filled($query->query_reference)) {
                $query->query_reference = 'CQ-'.strtoupper(Str::random(8));
            }
            if ($query->last_activity_at === null) {
                $query->last_activity_at = now();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CustomerQueryStatus::class,
            'email_verified' => 'boolean',
            'phone_verified' => 'boolean',
            'contact_consent' => 'boolean',
            'callback_required' => 'boolean',
            'consent_timestamp' => 'datetime',
            'departure_date' => 'date',
            'return_date' => 'date',
            'travel_state' => 'array',
            'last_activity_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<AiConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, [
            CustomerQueryStatus::Closed,
            CustomerQueryStatus::Invalid,
            CustomerQueryStatus::Converted,
        ], true);
    }
}
