<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'master_enabled',
    'lab_adapter_enabled',
    'rag_enabled',
    'human_handoff_enabled',
    'learning_queue_enabled',
    'internal_canary_enabled',
    'flight_search_read_only_enabled',
    'audience_mode',
    'updated_by_user_id',
])]
class AiAssistantSetting extends Model
{
    public const AUDIENCE_OFF = 'off';

    public const AUDIENCE_INTERNAL_CANARY = 'internal_canary';

    public const AUDIENCE_PUBLIC = 'public';

    protected function casts(): array
    {
        return [
            'master_enabled' => 'boolean',
            'lab_adapter_enabled' => 'boolean',
            'rag_enabled' => 'boolean',
            'human_handoff_enabled' => 'boolean',
            'learning_queue_enabled' => 'boolean',
            'internal_canary_enabled' => 'boolean',
            'flight_search_read_only_enabled' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
