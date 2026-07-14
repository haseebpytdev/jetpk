<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Agency-scoped online payment gateway credentials (AbhiPay, etc.).
 * Merchant secrets are encrypted at rest; never log or expose raw values.
 */
#[Fillable([
    'agency_id',
    'code',
    'name',
    'environment',
    'is_active',
    'merchant_id',
    'merchant_secret_key',
    'base_url',
    'callback_url',
    'success_url',
    'cancel_url',
    'decline_url',
    'config_json',
])]
#[Hidden(['merchant_id', 'merchant_secret_key', 'config_json'])]
class PaymentGateway extends Model
{
    public const CODE_ABHIPAY = 'abhipay';

    public const DEFAULT_BASE_URL = 'https://api.abhipay.com.pk/api/v3';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'merchant_id' => 'encrypted',
            'merchant_secret_key' => 'encrypted',
            'config_json' => 'encrypted:array',
        ];
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function isConfigured(): bool
    {
        return filled($this->merchant_id) && filled($this->merchant_secret_key);
    }

    public function isAvailableForCheckout(): bool
    {
        return $this->is_active && $this->isConfigured();
    }

    public function maskedMerchantId(): ?string
    {
        $value = $this->merchant_id;
        if (! filled($value)) {
            return null;
        }

        return self::maskSecret((string) $value, 4);
    }

    public function hasMerchantSecretKey(): bool
    {
        return filled($this->merchant_secret_key);
    }

    public static function maskSecret(string $value, int $visibleTail = 4): string
    {
        $length = strlen($value);
        if ($length <= $visibleTail) {
            return str_repeat('•', max(0, $length - 1)).substr($value, -1);
        }

        return str_repeat('•', min(12, $length - $visibleTail)).substr($value, -$visibleTail);
    }
}
