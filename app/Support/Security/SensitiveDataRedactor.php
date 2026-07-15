<?php

namespace App\Support\Security;

use Illuminate\Support\Facades\Log;

class SensitiveDataRedactor
{
    protected const SENSITIVE_KEYS = [
        'password',
        'smtp_password',
        'whatsapp_access_token',
        'whatsapp_webhook_verify_token',
        'token',
        'access_token',
        'refresh_token',
        'client_secret',
        'secret',
        'api_key',
        'authorization',
        'bearer',
        'credentials',
        'sign_in',
        'username',
        'client_id',
        'private_key',
        'session_id',
        'cvv',
        'cvc',
        'pan',
        'card_number',
        'cardholder',
        'payment_method',
        'epr',
        'encoded_credentials',
        'pseudocitycode',
        'pcc',
    ];

    protected const PII_KEYS = [
        'email',
        'email_address',
        'phone',
        'mobile',
        'phone_number',
        'passport',
        'passport_number',
        'document_number',
        'identity_document',
        'date_of_birth',
        'dob',
        'birth_date',
        'passenger_name',
        'given_name',
        'surname',
        'family_name',
        'first_name',
        'last_name',
        'middle_name',
        'full_name',
        'name',
        'contact',
        'passengers',
        'traveler',
        'travelers',
        'passenger',
    ];

    /** @var list<string> */
    protected const SUMMARY_FORBIDDEN_KEYS = [
        'request_payload',
        'response_payload',
        'raw_payload',
        'raw_body',
        'raw_request',
        'raw_response',
        'request_body',
        'response_body',
        'body',
        'headers',
        'authorization',
        'wire_request_body',
        'redacted_wire_request_body',
        'CreatePassengerNameRecordRQ',
        'passengers',
        'contact',
        'payment',
        'card',
        'card_number',
        'cvv',
        'cvc',
        'pan',
    ];

    /** @var list<string> */
    protected const SAFE_CONTEXT_KEYS = [
        'booking_id',
        'user_id',
        'provider',
        'supplier_connection_id',
        'action',
        'source',
        'status',
        'reason',
        'reason_code',
        'http_status',
        'error_code',
        'attempt_id',
        'route',
        'context',
        'endpoint_path',
        'endpoint_host',
        'endpoint',
        'module_key',
        'booking_schema',
        'payload_schema',
        'segment_count',
        'passenger_count',
        'live_call_attempted',
        'live_supplier_call_attempted',
        'token_present',
        'pcc_present',
        'domain_present',
        'encoding_style',
        'profile',
        'auth_host',
        'auth_path',
    ];

    /** @var list<string> */
    protected const APPLICATION_DIAGNOSTIC_SUMMARY_KEYS = [
        'safe_application_errors',
        'safe_application_warnings',
        'safe_application_successes',
        'safe_validation_excerpts_structured',
        'safe_errors',
        'safe_warnings',
        'safe_messages',
    ];

    /** @var list<string> */
    protected const APPLICATION_DIAGNOSTIC_ROW_KEYS = [
        'type',
        'code',
        'message',
        'severity',
        'element',
        'path',
        'source',
    ];

    public const MAX_APPLICATION_DIAGNOSTIC_TEXT_LENGTH = 220;

    protected const MAX_SUMMARY_STRING_LENGTH = 240;

    protected const MAX_ERROR_MESSAGE_LENGTH = 240;

    public static function redact(mixed $value): mixed
    {
        try {
            if (is_array($value)) {
                $redacted = [];
                foreach ($value as $key => $inner) {
                    $normalizedKey = is_string($key) ? strtolower($key) : '';
                    if (is_string($key) && self::isSensitiveKey($normalizedKey)) {
                        $redacted[$key] = '[REDACTED]';

                        continue;
                    }
                    if (is_string($key) && self::isPiiKey($normalizedKey)) {
                        $redacted[$key] = '[REDACTED]';

                        continue;
                    }
                    $redacted[$key] = self::redact($inner);
                }

                return $redacted;
            }

            if (is_string($value)) {
                return self::redactString($value);
            }

            return $value;
        } catch (\Throwable $e) {
            Log::warning('sensitive_data_redactor_failed', [
                'exception' => $e::class,
            ]);

            return '[redaction_failed]';
        }
    }

    /**
     * @param  array<string, mixed>|null  $summary
     * @return array<string, mixed>|null
     */
    public static function sanitizeSupplierSummary(?array $summary): ?array
    {
        if ($summary === null || $summary === []) {
            return $summary;
        }

        $before = json_encode($summary);
        $sanitized = [];

        foreach ($summary as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            if (self::isSummaryForbiddenKey($normalizedKey)) {
                continue;
            }
            if (self::isSensitiveKey($normalizedKey) || self::isPiiKey($normalizedKey)) {
                $sanitized[$key] = '[REDACTED]';

                continue;
            }
            if (in_array($normalizedKey, self::APPLICATION_DIAGNOSTIC_SUMMARY_KEYS, true)) {
                $sanitized[$key] = self::sanitizeApplicationDiagnosticRows($value);

                continue;
            }
            $sanitized[$key] = self::sanitizeSummaryValue($value);
        }

        $after = json_encode($sanitized);
        if (is_string($before) && is_string($after) && $before !== $after) {
            Log::notice('supplier_diagnostics.unsafe_summary_sanitized', [
                'keys_removed' => count($summary) - count($sanitized),
            ]);
        }

        return $sanitized;
    }

    public static function sanitizeErrorMessage(?string $message): ?string
    {
        if ($message === null || $message === '') {
            return $message;
        }

        try {
            $redacted = self::redactString($message);
            if (strlen($redacted) > self::MAX_ERROR_MESSAGE_LENGTH) {
                $redacted = substr($redacted, 0, self::MAX_ERROR_MESSAGE_LENGTH - 3).'...';
            }

            return $redacted;
        } catch (\Throwable $e) {
            Log::warning('sensitive_data_redactor_sanitize_message_failed', [
                'exception' => $e::class,
            ]);

            return '[redaction_failed]';
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function supplierSafeContext(array $context): array
    {
        $safe = [];
        foreach ($context as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            if (! in_array($normalizedKey, self::SAFE_CONTEXT_KEYS, true)) {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $safe[$key] = is_string($value) ? self::redactString($value) : $value;

                continue;
            }
            if (is_array($value)) {
                $safe[$key] = self::redact($value);
            }
        }

        return $safe;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    public static function redactSupplierPayload(?array $payload): ?array
    {
        if ($payload === null || $payload === []) {
            return $payload;
        }

        $redacted = self::redact($payload);
        if (! is_array($redacted)) {
            return null;
        }

        $encoded = json_encode($redacted);
        if (is_string($encoded) && self::containsUnsafePatterns($encoded)) {
            Log::notice('supplier_diagnostics.redacted_payload_detected', [
                'payload_key_count' => count($redacted),
            ]);
        }

        return $redacted;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function prepareSupplierAttemptAttributes(array $attributes): array
    {
        $status = strtolower((string) ($attributes['status'] ?? ''));
        $failedLike = in_array($status, ['failed', 'blocked', 'manual_review', 'needs_review', 'skipped'], true);

        if (array_key_exists('safe_summary', $attributes)) {
            $summary = is_array($attributes['safe_summary']) ? $attributes['safe_summary'] : null;
            $attributes['safe_summary'] = self::sanitizeSupplierSummary($summary);
        }

        if (array_key_exists('error_message', $attributes)) {
            $attributes['error_message'] = self::sanitizeErrorMessage(
                is_string($attributes['error_message']) ? $attributes['error_message'] : null
            );
        }

        if ($failedLike) {
            foreach (['request_payload', 'response_payload'] as $payloadKey) {
                if (! array_key_exists($payloadKey, $attributes)) {
                    continue;
                }

                $payload = is_array($attributes[$payloadKey]) ? $attributes[$payloadKey] : null;
                if ($payload === null) {
                    $attributes[$payloadKey] = null;

                    continue;
                }

                // Keep sanitized summary payloads on failure; strip raw supplier XML only.
                $attributes[$payloadKey] = array_key_exists('xml', $payload)
                    ? null
                    : self::redactSupplierPayload($payload);
            }
        } else {
            if (array_key_exists('request_payload', $attributes)) {
                $request = is_array($attributes['request_payload']) ? $attributes['request_payload'] : null;
                $attributes['request_payload'] = self::redactSupplierPayload($request);
            }
            if (array_key_exists('response_payload', $attributes)) {
                $response = is_array($attributes['response_payload']) ? $attributes['response_payload'] : null;
                $attributes['response_payload'] = self::redactSupplierPayload($response);
            }
        }

        return $attributes;
    }

    protected static function sanitizeSummaryValue(mixed $value): mixed
    {
        if (is_string($value)) {
            $redacted = self::redactString($value);
            if (strlen($redacted) > self::MAX_SUMMARY_STRING_LENGTH) {
                return substr($redacted, 0, self::MAX_SUMMARY_STRING_LENGTH - 3).'...';
            }

            return $redacted;
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                return array_map(
                    fn (mixed $item): mixed => is_array($item)
                        ? self::sanitizeApplicationDiagnosticRow($item)
                        : self::sanitizeSummaryValue($item),
                    array_slice($value, 0, 24),
                );
            }

            return self::sanitizeSupplierSummary($value) ?? [];
        }

        return is_scalar($value) || $value === null ? $value : '[redacted]';
    }

    /**
     * @return list<array<string, mixed>>|array<string, mixed>|null
     */
    public static function sanitizeApplicationDiagnosticRows(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            return self::sanitizeApplicationDiagnosticRow($value);
        }

        $out = [];
        foreach (array_slice($value, 0, 24) as $item) {
            if (is_array($item)) {
                $row = self::sanitizeApplicationDiagnosticRow($item);
                if ($row !== []) {
                    $out[] = $row;
                }
            } elseif (is_string($item)) {
                $text = self::sanitizeApplicationDiagnosticText($item);
                if ($text !== '') {
                    $out[] = ['message' => $text];
                }
            } elseif (is_scalar($item)) {
                $out[] = ['message' => (string) $item];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function sanitizeApplicationDiagnosticRow(array $row): array
    {
        $out = [];
        foreach ($row as $key => $value) {
            if (! is_string($key) || ! in_array($key, self::APPLICATION_DIAGNOSTIC_ROW_KEYS, true) || ! is_string($value)) {
                continue;
            }
            $text = self::sanitizeApplicationDiagnosticText($value);
            if ($text !== '' && $text !== '[redacted]') {
                $out[$key] = $text;
            }
        }

        return $out;
    }

    public static function sanitizeApplicationDiagnosticText(string $text): string
    {
        $text = trim($text);
        if ($text === '' || in_array($text, ['[redacted]', '[REDACTED]', '[redaction_failed]'], true)) {
            return $text;
        }

        if (preg_match('/^(ERR|WARN|INFO)(\.[A-Z0-9_.-]+)+$/i', $text) === 1) {
            return substr($text, 0, 120);
        }

        $redacted = self::redactString($text);
        $redacted = preg_replace('/\bbearer[-_\s]?[^\s]+/i', '[REDACTED_TOKEN]', $redacted) ?? $redacted;
        $redacted = preg_replace('/\b[A-Z0-9]{6}\b/', '[LOCATOR_REDACTED]', $redacted) ?? $redacted;
        $redacted = preg_replace('/\+?\d[\d\s().-]{7,}\d/', '[REDACTED_PHONE]', $redacted) ?? $redacted;
        $redacted = preg_replace('/\b(?:PCC|TARGETCITY)\s*[:=]?\s*[A-Z0-9]{2,4}\b/i', '[REDACTED_PCC]', $redacted) ?? $redacted;

        if (strlen($redacted) > self::MAX_APPLICATION_DIAGNOSTIC_TEXT_LENGTH) {
            $redacted = substr($redacted, 0, self::MAX_APPLICATION_DIAGNOSTIC_TEXT_LENGTH - 3).'...';
        }

        return $redacted;
    }

    protected static function redactString(string $value): string
    {
        $redacted = preg_replace('/\$2y\$\d{2}\$[A-Za-z0-9.\/]{50,}/', '[REDACTED_HASH]', $value) ?? $value;
        $redacted = preg_replace('/Bearer\s+[A-Za-z0-9\-._~+\/]+=*/i', 'Bearer [REDACTED]', $redacted) ?? $redacted;
        $redacted = preg_replace('/Basic\s+[A-Za-z0-9+\/=]+/i', 'Basic [REDACTED]', $redacted) ?? $redacted;
        $redacted = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[REDACTED_EMAIL]', $redacted) ?? $redacted;

        return $redacted;
    }

    protected static function containsUnsafePatterns(string $encoded): bool
    {
        return (bool) preg_match('/Bearer\s+[A-Za-z0-9\-._~+\/]+=*/i', $encoded)
            || str_contains($encoded, '@')
            || str_contains(strtolower($encoded), 'client_secret');
    }

    protected static function isSensitiveKey(string $key): bool
    {
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if ($key === $sensitive || str_contains($key, $sensitive)) {
                return true;
            }
        }

        return false;
    }

    protected static function isPiiKey(string $key): bool
    {
        $exactOnly = ['name', 'contact'];

        foreach (self::PII_KEYS as $pii) {
            if (in_array($pii, $exactOnly, true)) {
                if ($key === $pii) {
                    return true;
                }

                continue;
            }
            if ($key === $pii || str_contains($key, $pii)) {
                return true;
            }
        }

        return false;
    }

    protected static function isSummaryForbiddenKey(string $key): bool
    {
        foreach (self::SUMMARY_FORBIDDEN_KEYS as $forbidden) {
            if ($key === $forbidden || str_contains($key, $forbidden)) {
                return true;
            }
        }

        return false;
    }
}
