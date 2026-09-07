<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationOutbox extends Model
{
    protected $table = 'notification_outbox';

    protected $fillable = [
        'event_id',
        'event_type',
        'schema_version',
        'agency_id',
        'aggregate_type',
        'aggregate_id',
        'actor_user_id',
        'payload',
        'status',
        'attempt_count',
        'available_at',
        'locked_at',
        'processed_at',
        'last_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'available_at' => 'datetime',
            'locked_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
