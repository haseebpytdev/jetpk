<?php

namespace App\Support\Suppliers;

/**
 * Masked credential display and blank-on-edit preservation for supplier API settings forms.
 */
final class SupplierCredentialFormPresenter
{
    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'access_token',
        'client_secret',
        'password',
        'token',
        'api_key',
        'secret',
        'agent_password',
    ];

    /** @var list<string> */
    private const SEMI_SENSITIVE_KEYS = [
        'username',
        'agency_id',
        'mco_invoice_number',
        'sign_in',
        'client_id',
        'client_key',
        'auth_code',
        'organization_id',
        'agent_id',
        'agent_type',
        'agency_name',
    ];

    public static function isSensitive(string $key, array $fieldMeta = []): bool
    {
        if (($fieldMeta['type'] ?? '') === 'password') {
            return true;
        }

        return in_array($key, self::SENSITIVE_KEYS, true);
    }

    public static function isSemiSensitive(string $key): bool
    {
        return in_array($key, self::SEMI_SENSITIVE_KEYS, true);
    }

    public static function isMaskedPlaceholder(string $value): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }

        if (preg_match('/^Saved\b/i', $trimmed) === 1) {
            return true;
        }

        if (str_contains($trimmed, '******')) {
            return true;
        }

        if (preg_match('/^•+$/u', $trimmed) === 1) {
            return true;
        }

        return preg_match('/^•{2,}/u', $trimmed) === 1;
    }

    public static function maskValue(string $value, bool $fullyObscure = false): string
    {
        $text = trim($value);
        if ($text === '') {
            return '';
        }

        if ($fullyObscure) {
            return '••••••••';
        }

        $length = strlen($text);
        if ($length <= 6) {
            return '••••••••';
        }

        return substr($text, 0, 3).'******'.substr($text, -3);
    }

    /**
     * @return list<string>
     */
    public static function configuredFieldKeys(string $provider): array
    {
        $fields = (array) config('supplier_credentials.providers.'.$provider.'.fields', []);

        return array_keys($fields);
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $existingCredentials
     * @param  array<string, string>  $defaults
     * @return array<string, mixed>
     */
    public static function preserveConfiguredCredentials(
        array $credentials,
        array $existingCredentials,
        string $provider,
        array $defaults = [],
    ): array {
        foreach (self::configuredFieldKeys($provider) as $key) {
            $incoming = trim((string) ($credentials[$key] ?? ''));
            if ($incoming !== '' && self::isMaskedPlaceholder($incoming)) {
                $incoming = '';
            }

            if ($incoming === '' && array_key_exists($key, $existingCredentials)) {
                $credentials[$key] = $existingCredentials[$key];

                continue;
            }

            if ($incoming === '' && isset($defaults[$key]) && trim((string) $defaults[$key]) !== '') {
                $existingDefault = trim((string) ($existingCredentials[$key] ?? ''));
                $credentials[$key] = $existingDefault !== '' ? $existingDefault : (string) $defaults[$key];
            }
        }

        return $credentials;
    }

    /**
     * @param  array<string, mixed>  $storedCredentials
     * @param  array<string, mixed>  $oldInput
     * @return array<string, array{
     *     has_saved: bool,
     *     masked_label: ?string,
     *     prefill_value: string,
     *     placeholder: string,
     *     preserve_hint: bool,
     *     is_sensitive: bool
     * }>
     */
    public static function buildFieldStates(
        string $provider,
        array $storedCredentials,
        bool $isEdit,
        array $oldInput = [],
    ): array {
        $providerFields = (array) config('supplier_credentials.providers.'.$provider.'.fields', []);
        $states = [];

        foreach ($providerFields as $key => $fieldMeta) {
            if (! is_array($fieldMeta)) {
                continue;
            }

            $stored = trim((string) ($storedCredentials[$key] ?? ''));
            $hasSaved = $isEdit && $stored !== '';
            $isSensitive = self::isSensitive($key, $fieldMeta);
            $isSemiSensitive = self::isSemiSensitive($key);
            $oldValue = trim((string) ($oldInput[$key] ?? ''));

            $prefillValue = '';
            if ($oldValue !== '') {
                $prefillValue = self::isMaskedPlaceholder($oldValue) ? '' : $oldValue;
            } elseif ($hasSaved && ! $isSensitive && ! $isSemiSensitive) {
                $prefillValue = $stored;
            }

            $maskedLabel = null;
            if ($hasSaved && ($isSensitive || $isSemiSensitive)) {
                $maskedLabel = self::maskValue($stored, $isSensitive);
            }

            $placeholder = (string) ($fieldMeta['placeholder'] ?? '');
            if ($hasSaved && ($isSensitive || $isSemiSensitive)) {
                $placeholder = 'Saved — leave blank to keep existing value.';
            }

            $states[$key] = [
                'has_saved' => $hasSaved,
                'masked_label' => $maskedLabel,
                'prefill_value' => $prefillValue,
                'placeholder' => $placeholder,
                'preserve_hint' => $hasSaved,
                'is_sensitive' => $isSensitive,
            ];
        }

        return $states;
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @param  array<string, mixed>  $existing
     */
    public static function effectiveValue(string $key, array $incoming, array $existing): string
    {
        $incomingValue = trim((string) ($incoming[$key] ?? ''));
        if ($incomingValue !== '' && ! self::isMaskedPlaceholder($incomingValue)) {
            return $incomingValue;
        }

        return trim((string) ($existing[$key] ?? ''));
    }

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    public static function buildFieldStatesByProvider(
        bool $isEdit,
        ?array $storedCredentials = null,
        array $oldInput = [],
        ?string $connectionProvider = null,
    ): array {
        $providers = array_keys((array) config('supplier_credentials.providers', []));
        $byProvider = [];

        foreach ($providers as $provider) {
            $stored = ($isEdit && is_array($storedCredentials) && $connectionProvider === $provider)
                ? $storedCredentials
                : [];
            $byProvider[$provider] = self::buildFieldStates($provider, $stored, $isEdit, $oldInput);
        }

        return $byProvider;
    }
}
