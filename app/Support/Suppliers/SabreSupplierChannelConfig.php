<?php

namespace App\Support\Suppliers;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;

/**
 * Per-connection Sabre GDS / NDC channel toggles stored in {@see SupplierConnection::$settings}.
 */
final class SabreSupplierChannelConfig
{
    public const SETTING_GDS = 'sabre_gds_enabled';

    public const SETTING_NDC = 'sabre_ndc_enabled';

    public function __construct(
        public bool $gdsEnabled,
        public bool $ndcEnabled,
    ) {}

    public static function fromConnection(SupplierConnection $connection): self
    {
        $settings = is_array($connection->settings) ? $connection->settings : [];

        return new self(
            gdsEnabled: self::readBool($settings, self::SETTING_GDS, true),
            ndcEnabled: self::readBool($settings, self::SETTING_NDC, false),
        );
    }

    public static function gdsEnabled(SupplierConnection $connection): bool
    {
        return self::fromConnection($connection)->gdsEnabled;
    }

    public static function ndcEnabled(SupplierConnection $connection): bool
    {
        return self::fromConnection($connection)->ndcEnabled;
    }

    public static function anyChannelEnabled(SupplierConnection $connection): bool
    {
        $config = self::fromConnection($connection);

        return $config->gdsEnabled || $config->ndcEnabled;
    }

    public static function bothChannelsDisabled(SupplierConnection $connection): bool
    {
        return ! self::anyChannelEnabled($connection);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function mergeIntoSettings(array $settings, bool $gdsEnabled, bool $ndcEnabled): array
    {
        $settings[self::SETTING_GDS] = $gdsEnabled;
        $settings[self::SETTING_NDC] = $ndcEnabled;

        return $settings;
    }

    public static function connectionAdminLabel(SupplierConnection $connection): string
    {
        $config = self::fromConnection($connection);

        if ($config->gdsEnabled && $config->ndcEnabled) {
            return 'Sabre';
        }
        if ($config->gdsEnabled) {
            return 'Sabre GDS';
        }
        if ($config->ndcEnabled) {
            return 'Sabre NDC';
        }

        return 'Sabre (channels off)';
    }

    public static function offerLabel(
        ?string $provider,
        ?string $sourceType = null,
        ?string $providerChannel = null,
        ?SupplierConnection $connection = null,
    ): string {
        if (strtolower(trim((string) $provider)) !== SupplierProvider::Sabre->value) {
            return SupplierSourcePresenter::label($provider);
        }

        $resolvedChannel = self::resolveOfferChannel($sourceType, $providerChannel);
        if ($resolvedChannel === 'gds') {
            return 'Sabre GDS';
        }
        if ($resolvedChannel === 'ndc') {
            return 'Sabre NDC';
        }

        if ($connection !== null) {
            return self::connectionAdminLabel($connection);
        }

        return 'Sabre';
    }

    public static function resolveOfferChannel(?string $sourceType, ?string $providerChannel): ?string
    {
        foreach ([$sourceType, $providerChannel] as $value) {
            $normalized = strtolower(trim((string) $value));
            if ($normalized === '') {
                continue;
            }
            if (in_array($normalized, ['gds', 'atpco'], true) || str_contains($normalized, 'gds')) {
                return 'gds';
            }
            if (in_array($normalized, ['ndc', 'ndc_connector'], true) || str_contains($normalized, 'ndc')) {
                return 'ndc';
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function readBoolSetting(array $settings, string $key, bool $default): bool
    {
        return self::readBool($settings, $key, $default);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private static function readBool(array $settings, string $key, bool $default): bool
    {
        if (! array_key_exists($key, $settings)) {
            return $default;
        }

        $value = $settings[$key];
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (bool) $value;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return $default;
    }
}
