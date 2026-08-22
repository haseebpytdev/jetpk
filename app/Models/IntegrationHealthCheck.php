<?php

namespace App\Models;

use App\Enums\IntegrationHealthStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sanitized integration health / connection-test history (never stores secrets or PII).
 */
class IntegrationHealthCheck extends Model
{
    protected $fillable = [
        'provider',
        'test_type',
        'status',
        'latency_ms',
        'http_status',
        'environment',
        'tested_at',
        'tested_by',
        'sanitized_error_code',
        'sanitized_message',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'status' => IntegrationHealthStatus::class,
            'latency_ms' => 'integer',
            'http_status' => 'integer',
            'tested_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function tester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tested_by');
    }
}
