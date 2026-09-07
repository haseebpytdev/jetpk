<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationRoute extends Model
{
    protected $fillable = [
        'route_key',
        'agency_id',
        'event_type',
        'channel',
        'audience',
        'recipient_strategy',
        'provider',
        'template_key',
        'priority',
        'queue_name',
        'locale',
        'conditions',
        'enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'enabled' => 'boolean',
        ];
    }
}
