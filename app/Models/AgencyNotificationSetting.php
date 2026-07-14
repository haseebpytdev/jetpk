<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'agency_id',
    'event_key',
    'channel',
    'enabled',
    'recipient_scope',
    'recipient_emails',
    'cc_emails',
    'bcc_emails',
    'digest_mode',
    'meta',
])]
class AgencyNotificationSetting extends Model
{
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'recipient_emails' => 'array',
            'cc_emails' => 'array',
            'bcc_emails' => 'array',
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
