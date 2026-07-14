<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'client_profile_id',
    'supplier_key',
    'enabled',
    'mode',
    'credentials',
    'config',
])]
#[Hidden(['credentials'])]
class ClientProfileSupplier extends Model
{
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'credentials' => 'encrypted:array',
            'config' => 'array',
        ];
    }

    /** @return BelongsTo<ClientProfile, $this> */
    public function clientProfile(): BelongsTo
    {
        return $this->belongsTo(ClientProfile::class);
    }

    /**
     * @return array<string, string>
     */
    public function maskedCredentials(): array
    {
        $credentials = is_array($this->credentials) ? $this->credentials : [];
        $masked = [];

        foreach ($credentials as $key => $value) {
            if (! is_string($key)) {
                continue;
            }
            $masked[$key] = is_string($value) && $value !== '' ? '••••••••' : '';
        }

        return $masked;
    }
}
