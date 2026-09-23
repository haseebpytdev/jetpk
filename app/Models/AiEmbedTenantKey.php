<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiEmbedTenantKey extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'ai_embed_tenant_id',
        'key_hash',
        'key_prefix',
        'status',
        'activated_at',
        'revoked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AiEmbedTenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(AiEmbedTenant::class, 'ai_embed_tenant_id');
    }
}
