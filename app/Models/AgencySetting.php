<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'agency_id',
    'display_name',
    'legal_name',
    'tagline',
    'support_phone',
    'support_whatsapp',
    'support_email',
    'office_address',
    'city',
    'country',
    'website_url',
    'timezone',
    'currency',
    'primary_color',
    'secondary_color',
    'accent_color',
    'logo_path',
    'favicon_path',
    'hero_image_path',
    'footer_logo_path',
    'header_cta_label',
    'header_cta_url',
    'footer_about',
    'footer_copyright',
    'social_links',
    'meta',
])]
class AgencySetting extends Model
{
    protected function casts(): array
    {
        return [
            'social_links' => 'array',
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
