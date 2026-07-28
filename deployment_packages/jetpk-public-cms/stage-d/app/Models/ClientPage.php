<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientPage extends Model
{
    protected $fillable = [
        'client_profile_id',
        'slug',
        'internal_name',
        'public_title',
        'nav_label',
        'enabled',
        'show_header',
        'show_footer',
        'seo_json',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'show_header' => 'boolean',
            'show_footer' => 'boolean',
            'seo_json' => 'array',
        ];
    }

    public function clientProfile(): BelongsTo
    {
        return $this->belongsTo(ClientProfile::class);
    }

    public function pageKey(): string
    {
        return \App\Support\Client\ClientPageKeys::customKey((string) $this->slug);
    }
}
