<?php

namespace App\Support\Sabre;

use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;

/**
 * V25-CPNR: Safe structured excerpts from Sabre HTTP validation failures (pointer/message/type only).
 */
final class SabrePassengerRecordsHttpValidationExcerptBuilder
{
    /**
     * @param  array<string, mixed>  $json  Decoded Sabre HTTP error body (no raw payload persistence)
     * @return list<array{pointer: ?string, message_excerpt: string, error_type: ?string}>
     */
    public function buildStructuredExcerpts(array $json): array
    {
        $out = [];
        $errors = $json['errors'] ?? null;
        if (is_array($errors)) {
            foreach ($errors as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $structured = $this->structuredRowFromError($row);
                if ($structured !== null) {
                    $out[] = $structured;
                }
                if (count($out) >= 6) {
                    break;
                }
            }
        }

        if ($out === []) {
            $msg = $json['message'] ?? null;
            if (is_string($msg) && trim($msg) !== '') {
                $out[] = [
                    'pointer' => null,
                    'message_excerpt' => substr(trim($msg), 0, 200),
                    'error_type' => is_string($json['type'] ?? null) ? substr((string) $json['type'], 0, 64) : null,
                ];
            }
        }

        return $out;
    }

    /**
     * @param  list<array{pointer: ?string, message_excerpt: string, error_type: ?string}>  $structured
     * @return array{
     *     cpnr_schema_validation_pointer: ?string,
     *     cpnr_schema_validation_message_summary: ?string,
     *     cpnr_schema_validation_stage: ?string
     * }
     */
    public function extractCpnrSchemaValidationSummary(array $structured): array
    {
        foreach ($structured as $row) {
            if (! is_array($row)) {
                continue;
            }
            $pointer = $row['pointer'] ?? null;
            $pointerStr = is_string($pointer) && trim($pointer) !== ''
                ? substr(trim($pointer), 0, 240)
                : null;
            $message = trim((string) ($row['message_excerpt'] ?? ''));
            if ($pointerStr !== null) {
                return [
                    'cpnr_schema_validation_pointer' => $pointerStr,
                    'cpnr_schema_validation_message_summary' => $message !== ''
                        ? substr($message, 0, 240)
                        : 'schema validation failed',
                    'cpnr_schema_validation_stage' => 'post_http',
                ];
            }
            if ($message !== '') {
                return [
                    'cpnr_schema_validation_pointer' => null,
                    'cpnr_schema_validation_message_summary' => substr($message, 0, 240),
                    'cpnr_schema_validation_stage' => 'post_http',
                ];
            }
        }

        return [
            'cpnr_schema_validation_pointer' => null,
            'cpnr_schema_validation_message_summary' => null,
            'cpnr_schema_validation_stage' => null,
        ];
    }

    /**
     * Classify v2.5 AirPrice OptionalQualifiers HTTP 400 schema failures. No auto-retry.
     *
     * @param  list<array{pointer: ?string, message_excerpt: string, error_type: ?string}>  $structured
     */
    public function classifyV25AirPriceOptionalQualifierSchemaReason(array $structured): ?string
    {
        foreach ($structured as $row) {
            if (! is_array($row)) {
                continue;
            }
            $pointer = strtolower(trim((string) ($row['pointer'] ?? '')));
            if ($pointer === '') {
                continue;
            }
            if (str_contains($pointer, 'optionalqualifiers') || str_contains($pointer, 'pricingqualifiers')) {
                return SabreBookingPayloadBuilder::V25_AIRPRICE_OPTIONAL_QUALIFIER_SCHEMA_ERROR;
            }
        }

        return null;
    }

    /**
     * Classify v2.5 Brand host validation failures (required or unknown shape). No auto-retry.
     *
     * @param  list<array{pointer: ?string, message_excerpt: string, error_type: ?string}>  $structured
     */
    public function classifyV25BrandQualifierHostReason(array $structured): ?string
    {
        foreach ($structured as $row) {
            if (! is_array($row)) {
                continue;
            }
            $pointer = strtolower(trim((string) ($row['pointer'] ?? '')));
            if ($pointer === '' || ! str_contains($pointer, '/brand')) {
                continue;
            }

            return SabreBookingPayloadBuilder::V25_BRAND_QUALIFIER_REQUIRED_OR_SHAPE_UNKNOWN;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{pointer: ?string, message_excerpt: string, error_type: ?string}|null
     */
    protected function structuredRowFromError(array $row): ?array
    {
        $pointer = data_get($row, 'source.pointer');
        $pointerStr = is_string($pointer) && trim($pointer) !== ''
            ? substr(trim($pointer), 0, 240)
            : null;
        $message = '';
        foreach (['title', 'detail', 'message'] as $key) {
            $v = $row[$key] ?? null;
            if (is_string($v) && trim($v) !== '') {
                $message = substr(trim($v), 0, 200);
                break;
            }
        }
        if ($message === '' && $pointerStr === null) {
            return null;
        }

        $errorType = null;
        foreach (['code', 'type', 'status'] as $key) {
            $v = $row[$key] ?? null;
            if (is_string($v) && trim($v) !== '') {
                $errorType = substr(trim($v), 0, 64);
                break;
            }
        }

        return [
            'pointer' => $pointerStr,
            'message_excerpt' => $message !== '' ? $message : 'schema validation failed',
            'error_type' => $errorType,
        ];
    }

    /**
     * @param  list<array{pointer: ?string, message_excerpt: string, error_type: ?string}>  $structured
     * @return list<string>
     */
    public function flattenToLegacyStrings(array $structured): array
    {
        $out = [];
        foreach ($structured as $row) {
            $chunk = '';
            if (is_string($row['pointer'] ?? null) && trim((string) $row['pointer']) !== '') {
                $chunk .= 'pointer: '.substr(trim((string) $row['pointer']), 0, 120);
            }
            $msg = trim((string) ($row['message_excerpt'] ?? ''));
            if ($msg !== '') {
                $chunk .= ($chunk !== '' ? ' ' : '').$msg;
            }
            if (is_string($row['error_type'] ?? null) && trim((string) $row['error_type']) !== '') {
                $chunk .= ($chunk !== '' ? ' ' : '').'type: '.substr(trim((string) $row['error_type']), 0, 48);
            }
            if ($chunk !== '') {
                $out[] = substr($chunk, 0, 240);
            }
            if (count($out) >= 6) {
                break;
            }
        }

        return $out;
    }
}
