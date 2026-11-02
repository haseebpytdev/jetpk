<?php

namespace App\Support\Suppliers;

use App\Models\SupplierConnection;

/**
 * One API POS / currency account context (separate from REST/SOAP credentials).
 */
final class OneApiAccountContext
{
    public const STATUS_CONFIRMED = 'CONFIRMED';

    public const STATUS_UNRESOLVED = 'UNRESOLVED';

    public const SEARCH_BLOCKED_MESSAGE = 'OneAPI account POS/currency context has not been confirmed.';

    /**
     * @return array{
     *     agent_preferred_currency: string,
     *     pos_country: string,
     *     pos_station: string,
     *     account_context_status: string,
     *     account_context_confirmed: bool
     * }
     */
    public static function resolve(SupplierConnection $connection): array
    {
        $settings = is_array($connection->settings) ? $connection->settings : [];
        $credentials = is_array($connection->credentials) ? $connection->credentials : [];

        $context = is_array($settings['account_context'] ?? null) ? $settings['account_context'] : [];

        $currency = strtoupper(trim((string) ($context['agent_preferred_currency'] ?? $credentials['agent_preferred_currency'] ?? '')));
        $country = strtoupper(trim((string) ($context['pos_country'] ?? $credentials['pos_country'] ?? '')));
        $station = strtoupper(trim((string) ($context['pos_station'] ?? $credentials['pos_station'] ?? '')));

        $hasExplicitStatus = array_key_exists('account_context_status', $settings)
            || array_key_exists('account_context_status', $credentials);
        $statusRaw = strtoupper(trim((string) ($settings['account_context_status'] ?? $credentials['account_context_status'] ?? self::STATUS_UNRESOLVED)));

        $allFilled = $currency !== '' && $country !== '' && $station !== '';

        // Explicit CONFIRMED requires all three values.
        // Legacy records that already stored POS/currency in credentials (pre-minimization)
        // without an explicit status remain searchable for compatibility.
        $legacyCredentialContext = ! $hasExplicitStatus
            && $context === []
            && self::contextFieldsFilled([
                'agent_preferred_currency' => (string) ($credentials['agent_preferred_currency'] ?? ''),
                'pos_country' => (string) ($credentials['pos_country'] ?? ''),
                'pos_station' => (string) ($credentials['pos_station'] ?? ''),
            ]);

        $confirmed = $allFilled && (
            $statusRaw === self::STATUS_CONFIRMED || $legacyCredentialContext
        );

        return [
            'agent_preferred_currency' => $currency,
            'pos_country' => $country,
            'pos_station' => $station,
            'account_context_status' => $confirmed ? self::STATUS_CONFIRMED : self::STATUS_UNRESOLVED,
            'account_context_confirmed' => $confirmed,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $existingSettings
     * @return array<string, mixed>
     */
    public static function mergeSettings(array $settings, array $existingSettings, bool $confirmChecked): array
    {
        $incoming = is_array($settings['account_context'] ?? null) ? $settings['account_context'] : [];
        $existingContext = is_array($existingSettings['account_context'] ?? null) ? $existingSettings['account_context'] : [];

        $mergedContext = [];
        foreach (['agent_preferred_currency', 'pos_country', 'pos_station'] as $key) {
            $value = trim((string) ($incoming[$key] ?? ''));
            if ($value === '' && array_key_exists($key, $existingContext)) {
                $value = trim((string) $existingContext[$key]);
            }
            if ($value !== '') {
                $mergedContext[$key] = strtoupper($value);
            }
        }

        $settings['account_context'] = $mergedContext;

        $allFilled = self::contextFieldsFilled($mergedContext);
        if ($confirmChecked && $allFilled) {
            $settings['account_context_status'] = self::STATUS_CONFIRMED;
        } else {
            $settings['account_context_status'] = self::STATUS_UNRESOLVED;
        }

        return $settings;
    }

    /**
     * Persist provider-confirmed POS/currency when safely discovered from an authenticated response.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function confirmFromProvider(array $settings, string $currency, string $country, string $station): array
    {
        $currency = strtoupper(trim($currency));
        $country = strtoupper(trim($country));
        $station = strtoupper(trim($station));
        if ($currency === '' || $country === '' || $station === '') {
            return $settings;
        }

        $settings['account_context'] = [
            'agent_preferred_currency' => $currency,
            'pos_country' => $country,
            'pos_station' => $station,
        ];
        $settings['account_context_status'] = self::STATUS_CONFIRMED;

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function contextFieldsFilled(array $context): bool
    {
        foreach (['agent_preferred_currency', 'pos_country', 'pos_station'] as $key) {
            if (trim((string) ($context[$key] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }
}
