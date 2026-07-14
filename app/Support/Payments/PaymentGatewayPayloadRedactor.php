<?php

namespace App\Support\Payments;

use App\Support\Security\SensitiveDataRedactor;

/**
 * Redacts AbhiPay/gateway payloads before encrypted persistence or logging.
 */
class PaymentGatewayPayloadRedactor
{
    /** @var list<string> */
    protected const EXTRA_SENSITIVE_KEYS = [
        'merchant_secret_key',
        'merchantSecretKey',
        'merchant_id',
        'merchantId',
        'merchant_number',
        'merchantNumber',
        'authorization',
        'cardpan',
        'card_pan',
        'cardPan',
        'cardholdername',
        'card_holder_name',
        'cardHolderName',
        'cvv',
        'cvc',
        'otp',
        'password',
        'email',
    ];

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    public static function redact(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        $redacted = SensitiveDataRedactor::redact($payload);

        return self::redactRecursive($redacted);
    }

    protected static function redactRecursive(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $out = [];
        foreach ($value as $key => $item) {
            $normalized = strtolower((string) $key);
            if (in_array($normalized, self::EXTRA_SENSITIVE_KEYS, true)) {
                $out[$key] = '[REDACTED]';

                continue;
            }

            if (is_string($item) && self::looksLikePan($item)) {
                $out[$key] = self::maskPan($item);

                continue;
            }

            $out[$key] = is_array($item) ? self::redactRecursive($item) : $item;
        }

        return $out;
    }

    protected static function looksLikePan(string $value): bool
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return strlen($digits) >= 13 && strlen($digits) <= 19;
    }

    protected static function maskPan(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (strlen($digits) < 4) {
            return '****';
        }

        return str_repeat('*', max(4, strlen($digits) - 4)).substr($digits, -4);
    }
}
