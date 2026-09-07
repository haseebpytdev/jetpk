<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationDelivery extends Model
{
    protected $table = 'notification_deliveries';

    protected $fillable = [
        'event_id',
        'event_type',
        'agency_id',
        'channel',
        'audience',
        'recipient',
        'recipient_user_id',
        'provider',
        'template_key',
        'template_version',
        'queue_name',
        'priority',
        'idempotency_key',
        'status',
        'attempt_count',
        'provider_message_id',
        'last_error',
        'queued_at',
        'processing_at',
        'sent_at',
        'delivered_at',
        'failed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'queued_at' => 'datetime',
            'processing_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
