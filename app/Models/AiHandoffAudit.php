<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiHandoffAudit extends Model
{
    protected $fillable = [
        'ai_conversation_id',
        'staff_user_id',
        'from_state',
        'to_state',
        'reason',
    ];

    /** @return BelongsTo<AiConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }
}
