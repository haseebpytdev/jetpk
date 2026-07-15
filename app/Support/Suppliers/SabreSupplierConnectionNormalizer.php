<?php

namespace App\Support\Suppliers;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Core\SabreClient;

/**
 * Canonical Sabre API settings mapping for admin forms — preserves legacy credential keys
 * consumed by {@see SabreClient}.
 */
final class SabreSupplierConnectionNormalizer
{
    public const CERT_BASE_URL = 'https://api.cert.platform.sabre.com';

    public const LIVE_BASE_URL = 'https://api.platform.sabre.com';

    public static function baseUrlForEnvironment(string $environment): string
    {
        return strtolower(trim($environment)) === 'live'
            ? self::LIVE_BASE_URL
            : self::CERT_BASE_URL;
    }

    /**
     * @return array{sign_in: string, password: string, pcc: string}
     */
    public static function canonicalCredentialsFromConnection(SupplierConnection $connection): array
    {
        $credentials = is_array($connection->credentials) ? $connection->credentials : [];
        $settings = is_array($connection->settings) ? $connection->settings : [];

        return [
            'sign_in' => self::firstValue($credentials, $settings, ['sign_in', 'username', 'client_id']),
            'password' => self::firstValue($credentials, $settings, ['password', 'client_secret']),
            'pcc' => self::firstValue($credentials, $settings, ['pcc', 'PCC', 'pseudo_city_code', 'pseudoCityCode']),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function normalizePayload(array $payload, ?SupplierConnection $existing = null): array
    {
        if (($payload['provider'] ?? '') !== SupplierProvider::Sabre->value) {
            return $payload;
        }

        $environment = (string) ($payload['environment'] ?? 'sandbox');
        $overrideBaseUrl = (bool) ($payload['advanced_base_url_override'] ?? false);
        $incomingBaseUrl = trim((string) ($payload['base_url'] ?? ''));

        if ($overrideBaseUrl && $incomingBaseUrl !== '') {
            $payload['base_url'] = $incomingBaseUrl;
        } else {
            $payload['base_url'] = self::baseUrlForEnvironment($environment);
        }

        $credentials = is_array($payload['credentials'] ?? null) ? $payload['credentials'] : [];
        $existingCredentials = ($existing !== null && is_array($existing->credentials)) ? $existing->credentials : [];
        $existingCanonical = $existing !== null
            ? self::canonicalCredentialsFromConnection($existing)
            : ['sign_in' => '', 'password' => '', 'pcc' => ''];

        $signIn = trim((string) ($credentials['sign_in'] ?? ''));
        $password = trim((string) ($credentials['password'] ?? ''));
        $pcc = trim((string) ($credentials['pcc'] ?? ''));

        if ($signIn === '') {
            $signIn = $existingCanonical['sign_in'];
        }
        if ($password === '') {
            $password = $existingCanonical['password'];
        }
        if ($pcc === '') {
            $pcc = $existingCanonical['pcc'];
        }

        $merged = $existingCredentials;
        if ($signIn !== '') {
            $merged['sign_in'] = $signIn;
            $merged['client_id'] = $signIn;
        }
        if ($password !== '') {
            $merged['password'] = $password;
            $merged['client_secret'] = $password;
        }
        if ($pcc !== '') {
            $merged['pcc'] = $pcc;
        }

        $payload['credentials'] = $merged;

        $settings = is_array($payload['settings'] ?? null) ? $payload['settings'] : [];
        if ($pcc !== '') {
            unset($settings['pcc'], $settings['PCC'], $settings['pseudo_city_code'], $settings['pseudoCityCode']);
        }

        $existingSettings = ($existing !== null && is_array($existing->settings)) ? $existing->settings : [];
        $gdsEnabled = array_key_exists('sabre_gds_enabled', $payload)
            ? (bool) $payload['sabre_gds_enabled']
            : SabreSupplierChannelConfig::readBoolSetting($existingSettings, SabreSupplierChannelConfig::SETTING_GDS, true);
        $ndcEnabled = array_key_exists('sabre_ndc_enabled', $payload)
            ? (bool) $payload['sabre_ndc_enabled']
            : SabreSupplierChannelConfig::readBoolSetting($existingSettings, SabreSupplierChannelConfig::SETTING_NDC, false);
        $payload['settings'] = SabreSupplierChannelConfig::mergeIntoSettings($settings, $gdsEnabled, $ndcEnabled);

        unset($payload['sabre_gds_enabled'], $payload['sabre_ndc_enabled']);

        return $payload;
    }

    /**
     * @return array<string, string>
     */
    public static function maskedSummary(SupplierConnection $connection): array
    {
        $masked = $connection->maskedCredentials();
        $canonical = self::canonicalCredentialsFromConnection($connection);
        $summary = [];

        if ($canonical['sign_in'] !== '') {
            $summary['Sign in / Client ID'] = $masked['sign_in'] ?? $masked['client_id'] ?? '****';
        }
        if ($canonical['password'] !== '') {
            $summary['Secret / Password'] = $masked['password'] ?? $masked['client_secret'] ?? '****';
        }
        if ($canonical['pcc'] !== '') {
            $summary['PCC'] = $masked['pcc'] ?? '****';
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $settings
     * @param  list<string>  $keys
     */
    private static function firstValue(array $credentials, array $settings, array $keys): string
    {
        foreach ($keys as $key) {
            $fromCredentials = trim((string) ($credentials[$key] ?? ''));
            if ($fromCredentials !== '') {
                return $fromCredentials;
            }
            $fromSettings = trim((string) ($settings[$key] ?? ''));
            if ($fromSettings !== '') {
                return $fromSettings;
            }
        }

        return '';
    }
}
