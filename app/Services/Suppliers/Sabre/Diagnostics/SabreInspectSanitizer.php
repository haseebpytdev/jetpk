<?php

namespace App\Services\Suppliers\Sabre\Diagnostics;

/**
 * Redacts secrets from Sabre shop payload previews and trims API error JSON for console output.
 */
final class SabreInspectSanitizer
{
    /** @var list<string> */
    protected const SENSITIVE_VALUE_KEYS = [
        'authorization',
        'access_token',
        'client_id',
        'client_secret',
        'password',
        'sign_in',
        'username',
        'token',
        'refreshtoken',
        'refresh_token',
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function maskShopPayload(array $payload): array
    {
        return self::maskWalk($payload);
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $node
     * @return array<string, mixed>|list<mixed>|mixed
     */
    protected static function maskWalk(mixed $node): mixed
    {
        if (! is_array($node)) {
            return $node;
        }

        $list = array_is_list($node);
        $out = [];

        foreach ($node as $k => $v) {
            $keyLower = strtolower((string) $k);

            if ($keyLower === 'pseudocitycode' && is_string($v)) {
                $out[$k] = '***PCC***';

                continue;
            }

            if (in_array($keyLower, self::SENSITIVE_VALUE_KEYS, true)) {
                $out[$k] = '***REDACTED***';

                continue;
            }

            $out[$k] = is_array($v) ? self::maskWalk($v) : $v;
        }

        return $list ? array_values($out) : $out;
    }

    /**
     * Safe subset of Sabre / OAuth style JSON error bodies (truncated, no raw dump).
     *
     * @param  array<string, mixed>|null  $json
     * @return array<string, mixed>
     */
    public static function sanitizeErrorBody(?array $json): array
    {
        if ($json === null) {
            return ['detail' => 'empty_or_non_json'];
        }

        $safe = [];

        foreach (['error', 'error_description', 'message', 'provider_message'] as $top) {
            if (! array_key_exists($top, $json)) {
                continue;
            }
            $val = $json[$top];
            if (is_string($val) || is_int($val) || is_float($val)) {
                $safe[$top] = substr((string) $val, 0, 240);
            }
        }

        $errors = $json['errors'] ?? null;
        if (is_array($errors)) {
            $safe['errors'] = [];
            foreach ($errors as $err) {
                if (! is_array($err)) {
                    continue;
                }
                $safe['errors'][] = array_filter([
                    'status' => isset($err['status']) ? substr((string) $err['status'], 0, 16) : null,
                    'code' => isset($err['code']) ? substr((string) $err['code'], 0, 80) : null,
                    'title' => isset($err['title']) ? substr((string) $err['title'], 0, 160) : null,
                    'type' => isset($err['type']) ? substr((string) $err['type'], 0, 160) : null,
                    'detail' => isset($err['detail']) ? substr((string) $err['detail'], 0, 400) : null,
                ], fn ($v) => $v !== null && $v !== '');
            }
            if ($safe['errors'] === []) {
                unset($safe['errors']);
            }
        }

        return $safe;
    }
}
