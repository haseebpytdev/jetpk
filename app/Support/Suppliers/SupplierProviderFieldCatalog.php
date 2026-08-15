<?php

namespace App\Support\Suppliers;

/**
 * Safe UI metadata for supplier credential/advanced fields. Never includes secret defaults.
 */
final class SupplierProviderFieldCatalog
{
    /** @var list<string> */
    private const ADVANCED_KEYS = [
        'api_channel',
        'auth_mode',
        'currency',
        'language_code',
        'owner_code',
        'carrier_code',
        'api_version',
        'payment_type',
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public static function fieldsFor(string $provider): array
    {
        $rows = [];
        foreach ((array) data_get(config('supplier_credentials.providers'), $provider.'.fields', []) as $key => $meta) {
            $rows[] = self::presentField((string) $key, is_array($meta) ? $meta : []);
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public static function presentField(string $key, array $meta): array
    {
        $type = (string) ($meta['type'] ?? 'text');
        $row = [
            'key' => $key,
            'label' => (string) ($meta['label'] ?? $key),
            'type' => $type,
            'required' => (bool) ($meta['required'] ?? false),
            'group' => self::groupFor($key, $type),
        ];

        foreach (['placeholder', 'help', 'channel'] as $copy) {
            if (! array_key_exists($copy, $meta) || $meta[$copy] === null || $meta[$copy] === '') {
                continue;
            }
            $row[$copy] = is_scalar($meta[$copy]) ? (string) $meta[$copy] : $meta[$copy];
        }

        if ($type !== 'password' && array_key_exists('default', $meta) && $meta['default'] !== null && $meta['default'] !== '') {
            $row['default'] = is_scalar($meta['default']) ? (string) $meta['default'] : $meta['default'];
        }

        if (isset($meta['options']) && is_array($meta['options'])) {
            $options = [];
            foreach ($meta['options'] as $value => $label) {
                $options[] = [
                    'value' => (string) $value,
                    'label' => (string) $label,
                ];
            }
            $row['options'] = $options;
        }

        return $row;
    }

    public static function isAdvancedKey(string $key): bool
    {
        return in_array($key, self::ADVANCED_KEYS, true);
    }

    private static function groupFor(string $key, string $type): string
    {
        if ($key === 'api_channel' || $key === 'auth_mode') {
            return 'channel';
        }
        if ($type === 'password') {
            return 'credentials';
        }

        return self::isAdvancedKey($key) ? 'advanced' : 'credentials';
    }
}
