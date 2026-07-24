<?php

namespace App\Support\Sabre\Revalidation;

use App\Support\Security\SensitiveDataRedactor;

/**
 * Safe extraction of Sabre GDS revalidation HTTP 4xx/5xx supplier error envelopes.
 * Never returns raw bodies, tokens, credentials, passenger/contact data, or opaque offer identifiers.
 */
final class SabreGdsRevalidationHttpSupplierErrorSanitizer
{
    public const MESSAGE_MAX = 280;

    public const ADDITIONAL_MESSAGE_MAX = 160;

    public const MAX_ADDITIONAL_MESSAGES = 8;

    public const MAX_VALIDATION_PATHS = 12;

    public const MAX_TYPE_LENGTH = 48;

    public const MAX_CODE_LENGTH = 64;

    public const CLASSIFICATION_SCHEMA_REJECTED = 'scenario_revalidation_schema_rejected';

    public const CLASSIFICATION_REQUEST_VALIDATION_FAILED = 'scenario_revalidation_request_validation_failed';

    public const CLASSIFICATION_ENDPOINT_STYLE_MISMATCH = 'scenario_revalidation_endpoint_style_mismatch';

    public const CLASSIFICATION_INVALID_REFERENCE_LINKAGE = 'scenario_revalidation_invalid_reference_linkage';

    public const CLASSIFICATION_UNSUPPORTED_ELEMENT = 'scenario_revalidation_unsupported_element';

    public const CLASSIFICATION_HTTP_REJECTED = 'scenario_revalidation_http_rejected';

    /**
     * @param  array<string, mixed>|null  $decodedJson
     * @param  array<string, mixed>  $errorDigest
     * @return array<string, mixed>
     */
    public function extract(
        ?int $httpStatus,
        ?array $decodedJson,
        ?string $rawBody = null,
        array $errorDigest = [],
    ): array {
        $jsonValid = $decodedJson !== null;
        $json = $decodedJson ?? [];

        $supplierType = $this->safeBoundedType($this->firstScalar($json, ['type']));
        $supplierCode = $this->safeBoundedCode($this->firstScalar($json, ['errorCode', 'code', 'error_code']));
        $supplierMessage = $this->sanitizeMessage($this->firstScalar($json, ['message', 'error.message', 'error_description']));

        if ($supplierCode === null && $errorDigest !== []) {
            $codes = is_array($errorDigest['response_error_codes'] ?? null) ? $errorDigest['response_error_codes'] : [];
            $supplierCode = $this->safeBoundedCode(is_string($codes[0] ?? null) ? (string) $codes[0] : null);
        }

        if ($supplierMessage === null && $errorDigest !== []) {
            $messages = is_array($errorDigest['response_error_messages'] ?? null) ? $errorDigest['response_error_messages'] : [];
            $supplierMessage = $this->sanitizeMessage(is_string($messages[0] ?? null) ? (string) $messages[0] : null);
        }

        $additional = $this->extractAdditionalMessages($json, $errorDigest);
        $validationPaths = $this->extractValidationPaths($json, $errorDigest);

        if ($supplierType === null) {
            $supplierType = $this->inferTypeFromAdditional($additional['codes']);
        }

        $classification = $this->classify(
            $httpStatus,
            $jsonValid,
            $supplierType,
            $supplierCode,
            $supplierMessage,
            $additional['codes'],
            $validationPaths,
        );

        $baseErrorCount = ($supplierCode !== null || $supplierMessage !== null) ? 1 : 0;
        $errorCountTotal = $baseErrorCount + $additional['error_count'];

        return array_filter([
            'supplier_response_received' => $httpStatus !== null,
            'response_json_valid' => $jsonValid,
            'supplier_error_type' => $supplierType,
            'supplier_error_code' => $supplierCode,
            'supplier_error_message_safe' => $supplierMessage,
            'supplier_additional_messages_summary' => $additional['summary'] !== '' ? $additional['summary'] : null,
            'supplier_additional_message_codes' => $additional['codes'] !== [] ? $additional['codes'] : null,
            'supplier_validation_paths' => $validationPaths !== [] ? $validationPaths : null,
            'supplier_error_count' => $errorCountTotal > 0 ? $errorCountTotal : 0,
            'supplier_warning_count' => $additional['warning_count'],
            'failure_category' => 'http_rejected',
            'reason_code' => 'sabre_revalidation_failed',
            'supplier_http_failure_classification' => $classification,
            'automatic_retry_allowed' => false,
            'same_payload_retry_recommended' => false,
        ], static fn ($value) => $value !== null && $value !== [] && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $logContext
     */
    public function emitCorrelatedLog(array $logContext): void
    {
        $allowed = [
            'run_id',
            'search_correlation_id',
            'revalidation_correlation_id',
            'endpoint_path',
            'payload_style',
            'revalidation_style',
            'http_status',
            'supplier_error_type',
            'supplier_error_code',
            'supplier_error_message_safe',
            'supplier_http_failure_classification',
            'failure_category',
            'reason_code',
            'response_candidate_count',
            'supplier_revalidation_call_count',
            'provider',
            'connection_id',
            'duration_ms',
        ];

        $payload = [];
        foreach ($allowed as $key) {
            if (! array_key_exists($key, $logContext)) {
                continue;
            }
            $value = $logContext[$key];
            if (is_string($value)) {
                $payload[$key] = SensitiveDataRedactor::sanitizeErrorMessage($value);
            } elseif (is_scalar($value) || $value === null) {
                $payload[$key] = $value;
            }
        }

        if ($payload === []) {
            return;
        }

        \Illuminate\Support\Facades\Log::notice('sabre.revalidate.http_supplier_error', $payload);
    }

    /**
     * @param  array<string, mixed>  $json
     * @param  array<string, mixed>  $errorDigest
     * @return array{summary: string, codes: list<string>, error_count: int, warning_count: int}
     */
    protected function extractAdditionalMessages(array $json, array $errorDigest): array
    {
        $codes = [];
        $messages = [];
        $errorCount = 0;
        $warningCount = 0;

        foreach ($this->additionalMessageRows($json) as $row) {
            $rowType = strtolower(trim((string) ($row['type'] ?? 'error')));
            $code = $this->safeBoundedCode($this->firstScalar($row, ['errorCode', 'code', 'type']));
            $message = $this->sanitizeMessage($this->firstScalar($row, ['message', 'detail', 'description', 'title']));

            if ($code !== null) {
                $codes[] = $code;
            }
            if ($message !== null) {
                $messages[] = $message;
            }

            if ($rowType === 'warning') {
                $warningCount++;
            } else {
                $errorCount++;
            }
        }

        $digestCodes = is_array($errorDigest['response_error_codes'] ?? null) ? $errorDigest['response_error_codes'] : [];
        foreach ($digestCodes as $digestCode) {
            if (! is_string($digestCode)) {
                continue;
            }
            $safe = $this->safeBoundedCode($digestCode);
            if ($safe !== null && ! in_array($safe, $codes, true)) {
                $codes[] = $safe;
            }
        }

        $codes = array_slice(array_values(array_unique($codes)), 0, self::MAX_ADDITIONAL_MESSAGES);
        $messages = array_slice(array_values(array_unique(array_filter($messages))), 0, self::MAX_ADDITIONAL_MESSAGES);
        $summary = $messages !== [] ? implode(' | ', $messages) : '';

        if ($summary !== '' && mb_strlen($summary) > self::MESSAGE_MAX) {
            $summary = mb_substr($summary, 0, self::MESSAGE_MAX - 3).'...';
        }

        return [
            'summary' => $summary,
            'codes' => $codes,
            'error_count' => $errorCount,
            'warning_count' => $warningCount,
        ];
    }

    /**
     * @param  array<string, mixed>  $json
     * @param  array<string, mixed>  $errorDigest
     * @return list<string>
     */
    protected function extractValidationPaths(array $json, array $errorDigest): array
    {
        $paths = [];

        $digestPaths = is_array($errorDigest['response_validation_paths'] ?? null) ? $errorDigest['response_validation_paths'] : [];
        foreach ($digestPaths as $path) {
            if (! is_string($path)) {
                continue;
            }
            $safe = $this->safeValidationPath($path);
            if ($safe !== null) {
                $paths[] = $safe;
            }
        }

        $missing = is_array($errorDigest['response_missing_fields'] ?? null) ? $errorDigest['response_missing_fields'] : [];
        foreach ($missing as $field) {
            if (! is_string($field)) {
                continue;
            }
            $safe = $this->safeValidationPath($field);
            if ($safe !== null) {
                $paths[] = $safe;
            }
        }

        foreach ($this->errorShapeRows($json) as $row) {
            foreach (['path', 'field', 'source.pointer', 'validationPath'] as $key) {
                $path = data_get($row, $key);
                if (! is_string($path) || trim($path) === '') {
                    continue;
                }
                $safe = $this->safeValidationPath($path);
                if ($safe !== null) {
                    $paths[] = $safe;
                }
            }
        }

        return array_slice(array_values(array_unique($paths)), 0, self::MAX_VALIDATION_PATHS);
    }

    /**
     * @param  list<string>  $additionalCodes
     * @param  list<string>  $validationPaths
     */
    protected function classify(
        ?int $httpStatus,
        bool $jsonValid,
        ?string $supplierType,
        ?string $supplierCode,
        ?string $supplierMessage,
        array $additionalCodes,
        array $validationPaths,
    ): string {
        if (! $jsonValid && $httpStatus !== null && $httpStatus >= 400) {
            return self::CLASSIFICATION_HTTP_REJECTED;
        }

        $haystack = strtolower(implode(' ', array_filter([
            $supplierType,
            $supplierCode,
            $supplierMessage,
            implode(' ', $additionalCodes),
            implode(' ', $validationPaths),
        ])));

        if ($validationPaths !== []
            || str_contains($haystack, 'validation')
            || str_contains($haystack, 'bad_request')
            || str_contains($haystack, 'invalid request')
            || str_contains($haystack, 'missingfield')
            || str_contains($haystack, 'required field')) {
            return self::CLASSIFICATION_REQUEST_VALIDATION_FAILED;
        }

        if (str_contains($haystack, 'schema')
            || str_contains($haystack, 'malformed')
            || str_contains($haystack, 'json parse')
            || str_contains($haystack, 'could not parse')) {
            return self::CLASSIFICATION_SCHEMA_REJECTED;
        }

        if (str_contains($haystack, 'endpoint')
            || str_contains($haystack, 'not found')
            || str_contains($haystack, 'unsupported media')
            || str_contains($haystack, 'method not allowed')
            || str_contains($haystack, 'payload style')
            || str_contains($haystack, 'wrong path')) {
            return self::CLASSIFICATION_ENDPOINT_STYLE_MISMATCH;
        }

        if (preg_match('/\b27131\b|linkage|offer\s+ref|itinerary\s+ref|pricinginformation|fare\s+component\s+ref/i', $haystack) === 1) {
            return self::CLASSIFICATION_INVALID_REFERENCE_LINKAGE;
        }

        if (str_contains($haystack, 'unsupported')
            || str_contains($haystack, 'not supported')
            || str_contains($haystack, 'not enabled')) {
            return self::CLASSIFICATION_UNSUPPORTED_ELEMENT;
        }

        return self::CLASSIFICATION_HTTP_REJECTED;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return list<array<string, mixed>>
     */
    protected function additionalMessageRows(array $json): array
    {
        $rows = [];
        $additional = $json['additionalMessages'] ?? null;
        if (is_array($additional)) {
            foreach ($additional as $item) {
                if (is_array($item)) {
                    $rows[] = $item;
                } elseif (is_string($item) && trim($item) !== '') {
                    $rows[] = ['message' => $item];
                }
            }
        }

        return array_slice($rows, 0, self::MAX_ADDITIONAL_MESSAGES);
    }

    /**
     * @param  array<string, mixed>  $json
     * @return list<array<string, mixed>>
     */
    protected function errorShapeRows(array $json): array
    {
        $rows = [];
        foreach (['errors', 'error.errors', 'error', 'Error'] as $path) {
            $value = data_get($json, $path);
            if (is_array($value) && array_is_list($value)) {
                foreach ($value as $row) {
                    if (is_array($row)) {
                        $rows[] = $row;
                    }
                }
            } elseif (is_array($value)) {
                $rows[] = $value;
            }
        }

        if ($rows === [] && $json !== []) {
            $rows[] = $json;
        }

        return array_slice($rows, 0, self::MAX_ADDITIONAL_MESSAGES);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $paths
     */
    protected function firstScalar(array $row, array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = str_contains($path, '.') ? data_get($row, $path) : ($row[$path] ?? null);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $codes
     */
    protected function inferTypeFromAdditional(array $codes): ?string
    {
        foreach ($codes as $code) {
            if (str_contains(strtoupper($code), 'WARN')) {
                return 'Warning';
            }
        }

        return null;
    }

    protected function safeBoundedType(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);
        if (! $this->looksLikeSafeClassificationToken($value)) {
            return null;
        }

        return mb_substr($value, 0, self::MAX_TYPE_LENGTH);
    }

    protected function safeBoundedCode(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        if ($this->looksLikeOpaqueIdentifier($value)) {
            return 'opaque_supplier_code';
        }

        if (! $this->looksLikeSafeClassificationToken($value)) {
            return null;
        }

        return mb_substr($value, 0, self::MAX_CODE_LENGTH);
    }

    protected function sanitizeMessage(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $redacted = SensitiveDataRedactor::sanitizeErrorMessage($this->redactOpaqueIdentifiers(trim($value)));
        if ($redacted === null || $redacted === '') {
            return null;
        }

        if (mb_strlen($redacted) > self::MESSAGE_MAX) {
            return mb_substr($redacted, 0, self::MESSAGE_MAX - 3).'...';
        }

        return $redacted;
    }

    protected function safeValidationPath(string $path): ?string
    {
        $path = trim($path);
        if ($path === '' || str_starts_with($path, '/passengers') || str_contains(strtolower($path), 'passport')) {
            return null;
        }

        if ($this->looksLikeOpaqueIdentifier($path)) {
            return null;
        }

        $safe = SensitiveDataRedactor::sanitizeErrorMessage($path);

        return $safe !== null && $safe !== '' ? mb_substr($safe, 0, 160) : null;
    }

    protected function looksLikeSafeClassificationToken(string $value): bool
    {
        if (mb_strlen($value) > self::MAX_CODE_LENGTH) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9._\-\/]{0,127}$/', $value);
    }

    protected function looksLikeOpaqueIdentifier(string $value): bool
    {
        if (preg_match('/^[0-9a-f]{32,}$/i', $value) === 1) {
            return true;
        }

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1) {
            return true;
        }

        return mb_strlen($value) >= 40 && preg_match('/^[A-Za-z0-9+\/=_-]+$/', $value) === 1;
    }

    protected function redactOpaqueIdentifiers(string $value): string
    {
        $redacted = preg_replace('/Bearer\s+[A-Za-z0-9\-._~+\/]+=*/i', 'Bearer [REDACTED]', $value) ?? $value;
        $redacted = preg_replace('/client_secret=\S+/i', 'client_secret=[REDACTED]', $redacted) ?? $redacted;
        $redacted = preg_replace('/\b[0-9a-f]{32,}\b/i', '[REDACTED_ID]', $redacted) ?? $redacted;
        $redacted = preg_replace('/\b[A-Z]{2}[0-9]{6,9}\b/', '[REDACTED_DOC]', $redacted) ?? $redacted;
        $redacted = preg_replace('/\b\d{3}-\d{2}-\d{4}\b/', '[REDACTED_ID]', $redacted) ?? $redacted;

        return preg_replace('/\b(?:Mr|Mrs|Ms|Miss|Dr)\.?\s+[A-Z][a-z]+(?:\s+[A-Z][a-z]+)+\b/', '[REDACTED_NAME]', $redacted) ?? $redacted;
    }
}
