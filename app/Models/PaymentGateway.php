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

    public function isBaseUrlV3(): bool
    {
        $base = strtolower(rtrim((string) $this->base_url, '/'));

        return str_contains($base, '/api/v3');
    }

    public function hasCallbackConfigured(): bool
    {
        return filled($this->callback_url);
    }

    /**
     * Public Review "Pay by Card" readiness flags (admin Integrations + checkout).
     *
     * @return array{
     *   ABHIPAY_RECORD_PRESENT: string,
     *   ABHIPAY_ACTIVE: string,
     *   ABHIPAY_CONFIGURED: string,
     *   ABHIPAY_CHECKOUT_AVAILABLE: string,
     *   ABHIPAY_BASE_URL_IS_V3: string,
     *   ABHIPAY_CALLBACK_CONFIGURED: string
     * }
     */
    public function checkoutReadinessFlags(): array
    {
        $configured = $this->isConfigured();
        $active = (bool) $this->is_active;
        $v3 = $this->isBaseUrlV3();
        $callback = $this->hasCallbackConfigured();
        $checkout = $active && $configured && $v3 && $callback;

        return [
            'ABHIPAY_RECORD_PRESENT' => $this->exists ? 'YES' : 'NO',
            'ABHIPAY_ACTIVE' => $active ? 'YES' : 'NO',
            'ABHIPAY_CONFIGURED' => $configured ? 'YES' : 'NO',
            'ABHIPAY_CHECKOUT_AVAILABLE' => $checkout ? 'YES' : 'NO',
            'ABHIPAY_BASE_URL_IS_V3' => $v3 ? 'YES' : 'NO',
            'ABHIPAY_CALLBACK_CONFIGURED' => $callback ? 'YES' : 'NO',
        ];
    }

    public function isAvailableForCheckout(): bool
    {
        return $this->is_active
            && $this->isConfigured()
            && $this->isBaseUrlV3()
            && $this->hasCallbackConfigured();
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
