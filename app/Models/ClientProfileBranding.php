<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'client_profile_id',
    'company_name',
    'logo_path',
    'favicon_path',
    'primary_color',
    'secondary_color',
    'accent_color',
    'phone',
    'email',
    'address',
    'footer_text',
    'config',
])]
class ClientProfileBranding extends Model
{
    protected $table = 'client_profile_branding';

    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }

    /** @return BelongsTo<ClientProfile, $this> */
    public function clientProfile(): BelongsTo
    {
        return $this->belongsTo(ClientProfile::class);
    }
}
