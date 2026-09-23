<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiEmbedAuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_public_id',
        'event_type',
        'success',
        'context',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
