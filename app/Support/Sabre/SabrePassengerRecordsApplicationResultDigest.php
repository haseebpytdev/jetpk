<?php

namespace App\Support\Sabre;

use App\Models\Booking;
use App\Support\Bookings\SabreHostErrorClassifier;
use App\Support\Bookings\SupplierBookingAttemptResolution;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Support\Carbon;

/**
 * F9G: Safe normalized digest for Sabre Passenger Records / CreatePassengerNameRecord ApplicationResults (no raw bodies or PII).
 */
final class SabrePassengerRecordsApplicationResultDigest
{
    public const META_DIGEST_KEY = 'sabre_passenger_records_application_digest';

    public const SOURCE_PASSENGER_RECORDS_CREATE = 'passenger_records_create';

    private const MESSAGE_MAX = 220;

    private const ROW_MAX = 10;

    private const KEY_SAMPLE_MAX = 24;

    /** @var list<string> Exact top-level JSON keys to omit from key samples (not substring matches). */
    private const FORBIDDEN_TOP_LEVEL_KEYS = [
        'passengers', 'passenger', 'travelers', 'travellers', 'traveler', 'traveller',
        'contact', 'request_body', 'response_body', 'raw_payload', 'raw_response',
    ];

    /** @var list<string> Substrings that indicate PII/secrets in values or nested keys. */
    private const FORBIDDEN_VALUE_FRAGMENTS = [
        'passport', 'document', 'given_name', 'givenname', 'surname', 'firstname', 'lastname',
        'email', 'phone', 'authorization', 'token', 'secret', 'credential',
    ];

    public function __construct() {}

    /**
     * @param  array<string, mixed>  $decodedResponse
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function digest(array $decodedResponse, array $context = []): array
    {
        $source = (string) ($context['source'] ?? self::SOURCE_PASSENGER_RECORDS_CREATE);
        $existingPnr = trim((string) ($context['existing_booking_pnr'] ?? ''));
        $recordLocator = $this->extractRecordLocator($decodedResponse, $existingPnr);
        $hasLocator = $recordLocator !== '';

        $applicationResults = $this->findApplicationResultsNode($decodedResponse);
        $applicationStatus = $this->stringField($applicationResults, ['status', 'Status'], 64);
        $transactionStatus = $this->stringField($decodedResponse, ['transactionStatus', 'TransactionStatus'], 64)
            ?: $this->stringField($applicationResults, ['transactionStatus', 'TransactionStatus'], 64);

        $errors = $this->collectStructuredRows($applicationResults, ['Error', 'Errors', 'error', 'errors'], 'error');
        $warnings = $this->collectStructuredRows($applicationResults, ['Warning', 'Warnings', 'warning', 'warnings'], 'warning');
        $messages = $this->collectMessageRows($applicationResults);
        $successes = $this->collectStructuredRows($applicationResults, ['Success', 'Successes', 'success', 'successes'], 'success');

        $statusClassifier = $this->deriveStatusClassifier($applicationStatus, $hasLocator);

        $out = [
            'status' => $statusClassifier,
            'application_status' => $applicationStatus,
            'transaction_status' => $transactionStatus !== '' ? $transactionStatus : null,
            'has_record_locator' => $hasLocator,
            'record_locator_present' => $hasLocator,
            'error_count' => count($errors),
            'warning_count' => count($warnings),
            'message_count' => count($messages),
            'success_count' => count($successes),
            'errors' => array_slice($errors, 0, self::ROW_MAX),
            'warnings' => array_slice($warnings, 0, self::ROW_MAX),
            'messages' => array_slice($messages, 0, self::ROW_MAX),
            'successes' => array_slice($successes, 0, self::ROW_MAX),
            'raw_keys_sample' => $this->topLevelKeySample($decodedResponse),
            'application_results_keys_sample' => $applicationResults !== []
                ? $this->topLevelKeySample($applicationResults)
                : [],
            'recorded_at' => Carbon::now()->toIso8601String(),
            'source' => $source,
        ];

        return $this->finalizeApplicationDigest($this->stripForbiddenValues($out));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function finalizeApplicationDigest(array $payload): array
    {
        foreach (['errors', 'warnings', 'messages', 'successes'] as $bucket) {
            $payload[$bucket] = SensitiveDataRedactor::sanitizeApplicationDiagnosticRows($payload[$bucket] ?? []);
        }

        return SensitiveDataRedactor::redact($payload);
    }

    /**
     * @param  array<string, mixed>  $digest
     */
    public function shouldPersistForIncompleteNoLocator(array $digest): bool
    {
        if (($digest['has_record_locator'] ?? false) === true) {
            return false;
        }

        $appStatus = strtolower(trim((string) ($digest['application_status'] ?? '')));

        return in_array($appStatus, ['incomplete', 'notprocessed'], true)
            || ($digest['status'] ?? '') === 'incomplete_no_locator';
    }

    /**
     * @param  array<string, mixed>  $digest
     * @return array<string, mixed>
     */
    public function shouldPersistForApplicationFailure(array $digest): bool
    {
        if (($digest['has_record_locator'] ?? false) === true) {
            return false;
        }

        return $this->shouldPersistForIncompleteNoLocator($digest)
            || (int) ($digest['error_count'] ?? 0) > 0
            || (int) ($digest['warning_count'] ?? 0) > 0
            || (int) ($digest['message_count'] ?? 0) > 0
            || trim((string) ($digest['application_status'] ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $digest
     * @return array<string, mixed>
     */
    public function convenienceMetaFromDigest(array $digest): array
    {
        $firstError = is_array($digest['errors'][0] ?? null) ? $digest['errors'][0] : [];
        $firstWarning = is_array($digest['warnings'][0] ?? null) ? $digest['warnings'][0] : [];
        $errorMessages = $this->pluckMessages($digest['errors'] ?? []);
        $warningMessages = $this->pluckMessages($digest['warnings'] ?? []);
        $infoMessages = $this->pluckMessages($digest['messages'] ?? []);

        $appStatus = trim((string) ($digest['application_status'] ?? ''));
        $firstErrorCode = trim((string) ($firstError['code'] ?? ''));
        $firstErrorMessage = trim((string) ($firstError['message'] ?? ''));

        return array_filter([
            'sabre_last_create_status' => $appStatus !== '' ? substr($appStatus, 0, 64) : null,
            'sabre_last_create_error_code' => $firstErrorCode !== '' ? substr($firstErrorCode, 0, 120) : null,
            'sabre_last_create_error_message' => $firstErrorMessage !== '' ? substr($firstErrorMessage, 0, self::MESSAGE_MAX) : null,
            'sabre_last_create_messages' => $infoMessages !== [] ? array_slice($infoMessages, 0, 8) : null,
            'sabre_last_create_warnings' => $warningMessages !== [] ? array_slice($warningMessages, 0, 8) : null,
            'sabre_booking_application_status' => $appStatus !== '' ? substr($appStatus, 0, 64) : null,
            'sabre_booking_application_error_code' => $firstErrorCode !== '' ? substr($firstErrorCode, 0, 120) : null,
            'sabre_booking_application_error' => $firstErrorMessage !== '' ? substr($firstErrorMessage, 0, self::MESSAGE_MAX) : null,
            'sabre_booking_application_messages' => $infoMessages !== [] ? array_slice($infoMessages, 0, 8) : null,
            'sabre_booking_application_warnings' => $warningMessages !== [] ? array_slice($warningMessages, 0, 8) : null,
            'supplier_booking_status' => 'manual_review',
            'supplier_booking_error_code' => 'sabre_booking_application_error',
            'supplier_booking_error_message' => $firstErrorMessage !== ''
                ? substr($firstErrorMessage, 0, self::MESSAGE_MAX)
                : 'Sabre Passenger Records application error without PNR locator.',
        ], static fn ($v) => $v !== null && $v !== [] && $v !== '');
    }

    /**
     * @param  array<string, mixed>|null  $digest
     * @return array<string, mixed>
     */
    public function commandSummaryFromDigest(?array $digest): array
    {
        if ($digest === null || $digest === []) {
            return [
                'sabre_application_status' => null,
                'sabre_application_error_count' => 0,
                'sabre_application_warning_count' => 0,
                'sabre_application_message_count' => 0,
                'sabre_application_first_error_code' => null,
                'sabre_application_first_error_message' => null,
                'application_error_digest_available' => false,
            ];
        }

        $firstError = is_array($digest['errors'][0] ?? null) ? $digest['errors'][0] : [];

        return [
            'sabre_application_status' => $digest['application_status'] ?? null,
            'sabre_application_error_count' => (int) ($digest['error_count'] ?? 0),
            'sabre_application_warning_count' => (int) ($digest['warning_count'] ?? 0),
            'sabre_application_message_count' => (int) ($digest['message_count'] ?? 0),
            'sabre_application_first_error_code' => $firstError['code'] ?? null,
            'sabre_application_first_error_message' => isset($firstError['message'])
                ? substr((string) $firstError['message'], 0, self::MESSAGE_MAX)
                : null,
            'application_error_digest_available' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $digest
     * @return list<array<string, mixed>>
     */
    public function safeValidationExcerptsStructuredFromDigest(array $digest): array
    {
        $out = [];
        foreach (['errors', 'warnings', 'messages', 'successes'] as $bucket) {
            foreach ((array) ($digest[$bucket] ?? []) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $excerpt = array_filter([
                    'type' => $row['type'] ?? $bucket,
                    'code' => isset($row['code']) ? substr((string) $row['code'], 0, 120) : null,
                    'message' => isset($row['message']) ? substr((string) $row['message'], 0, self::MESSAGE_MAX) : null,
                    'severity' => isset($row['severity']) ? substr((string) $row['severity'], 0, 64) : null,
                    'element' => isset($row['element']) ? substr((string) $row['element'], 0, 200) : null,
                    'source' => isset($row['source']) ? substr((string) $row['source'], 0, 80) : null,
                ], static fn ($v) => $v !== null && $v !== '');
                if ($excerpt !== []) {
                    $out[] = $excerpt;
                }
            }
        }

        return array_slice($out, 0, self::ROW_MAX);
    }

    /**
     * @param  array<string, mixed>  $digest
     * @return list<string>
     */
    public function safeValidationExcerptStringsFromDigest(array $digest): array
    {
        $lines = [];
        foreach ($this->safeValidationExcerptsStructuredFromDigest($digest) as $row) {
            $parts = array_filter([
                isset($row['type']) ? (string) $row['type'] : null,
                isset($row['code']) ? (string) $row['code'] : null,
                isset($row['message']) ? (string) $row['message'] : null,
            ]);
            if ($parts !== []) {
                $lines[] = substr(implode(': ', $parts), 0, self::MESSAGE_MAX);
            }
        }

        return array_values(array_unique($lines));
    }

    /**
     * @param  array<string, mixed>  $digest
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function hostClassificationContextFromDigest(array $digest, array $extra = []): array
    {
        $messages = array_merge(
            $this->pluckMessages($digest['errors'] ?? []),
            $this->pluckMessages($digest['warnings'] ?? []),
            $this->pluckMessages($digest['messages'] ?? []),
        );

        return array_merge($extra, array_filter([
            'application_status' => $digest['application_status'] ?? null,
            'application_digest_status' => $digest['status'] ?? null,
            'response_error_messages' => $messages !== [] ? $messages : null,
        ], static fn ($v) => $v !== null && $v !== [] && $v !== ''));
    }

    /**
     * Safe subset for supplier_booking_attempts.safe_summary (no full nested digest).
     *
     * @param  array<string, mixed>  $digest
     * @return array<string, mixed>
     */
    public function attemptSafeSummarySlice(array $digest): array
    {
        $structured = $this->safeValidationExcerptsStructuredFromDigest($digest);
        $legacyExcerpts = $this->safeValidationExcerptStringsFromDigest($digest);

        return array_merge(
            $this->commandSummaryFromDigest($digest),
            [
                'passenger_records_application_digest_status' => $digest['status'] ?? null,
                'passenger_records_application_status' => $digest['application_status'] ?? null,
                'safe_application_status' => $digest['application_status'] ?? null,
                'safe_application_errors' => array_slice((array) ($digest['errors'] ?? []), 0, self::ROW_MAX),
                'safe_application_warnings' => array_slice((array) ($digest['warnings'] ?? []), 0, self::ROW_MAX),
                'safe_application_successes' => array_slice((array) ($digest['successes'] ?? []), 0, self::ROW_MAX),
                'safe_validation_excerpts_structured' => $structured !== [] ? $structured : null,
                'safe_validation_excerpts' => $legacyExcerpts !== [] ? $legacyExcerpts : null,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function inspectBooking(Booking $booking): array
    {
        $booking->loadMissing('supplierBookingAttempts');
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $digest = is_array($meta[self::META_DIGEST_KEY] ?? null) ? $meta[self::META_DIGEST_KEY] : null;
        $digestSource = $digest !== null && $digest !== [] ? 'booking_meta' : null;

        $attempt = SupplierBookingAttemptResolution::resolveLatestMeaningfulCreateAttempt(
            $booking->supplierBookingAttempts,
        );
        $attemptSafe = is_array($attempt?->safe_summary) ? $attempt->safe_summary : [];

        if ($digest === null || $digest === []) {
            $fallback = $this->fallbackFromLatestAttempt($booking);
            if ($fallback !== null) {
                $digest = $fallback['digest'];
                $digestSource = 'attempt_safe_summary_fallback';
            }
        }

        $summary = $this->commandSummaryFromDigest($digest);
        $recommended = $this->recommendedNextAction($booking, $digest, $digestSource);
        $safeErrors = $this->resolveInspectableApplicationRows($attemptSafe, $digest, 'errors');
        $safeWarnings = $this->resolveInspectableApplicationRows($attemptSafe, $digest, 'warnings');
        $safeMessages = $this->resolveInspectableApplicationRows($attemptSafe, $digest, 'messages');
        $retroactive = $this->retroactiveEnrichmentReport($booking, $attempt, $digest, $digestSource, $attemptSafe);
        $hostSlice = $this->resolveHostClassificationSlice($digest, $attemptSafe, $safeErrors, $safeWarnings);
        $mixedProof = is_array($meta['mixed_carrier_preflight_proof'] ?? null)
            ? $meta['mixed_carrier_preflight_proof']
            : array_intersect_key($attemptSafe, array_flip($this->mixedPreflightInspectKeys()));

        return array_merge([
            'booking_id' => $booking->id,
            'booking_reference' => (string) ($booking->reference_code ?? ''),
            'attempt_id' => $attempt?->id,
            'attempt_status' => $attempt?->status,
            'pnr_present' => trim((string) ($booking->pnr ?? '')) !== '',
            'supplier_reference_present' => trim((string) ($booking->supplier_reference ?? '')) !== '',
            'digest_status' => is_array($digest) ? ($digest['status'] ?? null) : null,
            'digest_source' => $digestSource,
            'sabre_last_create_status' => $meta['sabre_last_create_status'] ?? ($digest['application_status'] ?? null),
            'sabre_last_create_error_code' => $meta['sabre_last_create_error_code'] ?? ($summary['sabre_application_first_error_code'] ?? null),
            'sabre_last_create_error_message' => $meta['sabre_last_create_error_message'] ?? ($summary['sabre_application_first_error_message'] ?? null),
            'safe_errors' => $safeErrors,
            'safe_warnings' => $safeWarnings,
            'safe_messages' => $safeMessages,
            'safe_application_errors' => $safeErrors,
            'safe_application_warnings' => $safeWarnings,
            'recommended_next_action' => $recommended,
            'live_supplier_call_attempted' => ($attemptSafe['live_call_attempted'] ?? false) === true,
            'pnr_create_attempted' => ($attemptSafe['pnr_attempted'] ?? $attemptSafe['live_call_attempted'] ?? false) === true,
            'ticketing_attempted' => false,
            'cancellation_attempted' => false,
        ], $summary, $hostSlice, $mixedProof, $retroactive);
    }

    /**
     * @param  array<string, mixed>  $attemptSafe
     * @param  array<string, mixed>|null  $digest
     * @return list<array<string, mixed>>
     */
    public function resolveInspectableApplicationRows(array $attemptSafe, ?array $digest, string $bucket): array
    {
        $attemptKey = match ($bucket) {
            'errors' => 'safe_application_errors',
            'warnings' => 'safe_application_warnings',
            default => null,
        };
        $attemptRows = ($attemptKey !== null && is_array($attemptSafe[$attemptKey] ?? null))
            ? $attemptSafe[$attemptKey]
            : [];
        $digestRows = is_array($digest) ? (array) ($digest[$bucket] ?? []) : [];

        if (! $this->applicationRowsAreRedactedPlaceholders($attemptRows)) {
            return array_slice(
                SensitiveDataRedactor::sanitizeApplicationDiagnosticRows($attemptRows),
                0,
                self::ROW_MAX,
            );
        }
        if ($digestRows !== []) {
            return array_slice(
                SensitiveDataRedactor::sanitizeApplicationDiagnosticRows($digestRows),
                0,
                self::ROW_MAX,
            );
        }

        return array_slice($this->rehydrateApplicationRowsFromAttemptSafeSummary($attemptSafe, $bucket), 0, self::ROW_MAX);
    }

    /**
     * @param  list<mixed>  $rows
     */
    protected function applicationRowsAreRedactedPlaceholders(array $rows): bool
    {
        if ($rows === []) {
            return false;
        }

        foreach ($rows as $row) {
            if (is_string($row) && ! in_array($row, ['[redacted]', '[REDACTED]'], true)) {
                return false;
            }
            if (! is_array($row)) {
                continue;
            }
            $code = trim((string) ($row['code'] ?? ''));
            $message = trim((string) ($row['message'] ?? ''));
            if ($code !== '' && ! in_array($code, ['[redacted]', '[REDACTED]'], true)) {
                return false;
            }
            if ($message !== '' && ! in_array($message, ['[redacted]', '[REDACTED]'], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $attemptSafe
     * @return list<array<string, mixed>>
     */
    public function rehydrateApplicationRowsFromAttemptSafeSummary(array $attemptSafe, string $bucket): array
    {
        $structured = is_array($attemptSafe['safe_validation_excerpts_structured'] ?? null)
            ? $attemptSafe['safe_validation_excerpts_structured']
            : [];
        $rows = [];
        foreach ($structured as $row) {
            if (! is_array($row)) {
                continue;
            }
            $type = strtolower(trim((string) ($row['type'] ?? '')));
            if ($bucket === 'errors' && ! in_array($type, ['error', 'errors'], true)) {
                continue;
            }
            if ($bucket === 'warnings' && ! in_array($type, ['warning', 'warnings'], true)) {
                continue;
            }
            if ($bucket === 'messages' && ! in_array($type, ['message', 'messages', 'success', 'successes'], true)) {
                continue;
            }
            $rows[] = SensitiveDataRedactor::sanitizeApplicationDiagnosticRow($row);
        }
        if ($rows !== []) {
            return $rows;
        }

        $codes = is_array($attemptSafe['response_error_codes'] ?? null) ? $attemptSafe['response_error_codes'] : [];
        $messages = is_array($attemptSafe['response_error_messages'] ?? null) ? $attemptSafe['response_error_messages'] : [];
        if ($bucket !== 'errors' && $bucket !== 'warnings') {
            $messages = is_array($attemptSafe['safe_validation_excerpts'] ?? null)
                ? $attemptSafe['safe_validation_excerpts']
                : $messages;
        }
        foreach (array_slice($messages, 0, self::ROW_MAX) as $i => $message) {
            if (! is_string($message) || trim($message) === '') {
                continue;
            }
            $code = isset($codes[$i]) && is_scalar($codes[$i]) ? (string) $codes[$i] : '';
            $rows[] = SensitiveDataRedactor::sanitizeApplicationDiagnosticRow(array_filter([
                'type' => $bucket === 'warnings' ? 'warning' : ($bucket === 'messages' ? 'message' : 'error'),
                'code' => $code !== '' ? $code : null,
                'message' => $message,
            ]));
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>|null  $digest
     * @param  array<string, mixed>  $attemptSafe
     * @param  list<array<string, mixed>>  $safeErrors
     * @param  list<array<string, mixed>>  $safeWarnings
     * @return array<string, mixed>
     */
    protected function resolveHostClassificationSlice(
        ?array $digest,
        array $attemptSafe,
        array $safeErrors,
        array $safeWarnings,
    ): array {
        $messages = array_merge(
            $this->pluckMessages($safeErrors),
            $this->pluckMessages($safeWarnings),
            is_array($digest) ? $this->pluckMessages((array) ($digest['errors'] ?? [])) : [],
            is_array($digest) ? $this->pluckMessages((array) ($digest['warnings'] ?? [])) : [],
        );
        $context = $this->hostClassificationContextFromDigest(is_array($digest) ? $digest : [], array_merge(
            array_intersect_key($attemptSafe, array_flip(['error_code', 'reason_code', 'http_status', 'application_status'])),
            [
                'error_code' => (string) ($attemptSafe['error_code'] ?? 'sabre_booking_application_error'),
                'response_error_messages' => $messages !== [] ? array_values(array_unique($messages)) : null,
            ],
        ));
        $classified = SabreHostErrorClassifier::buildPersistedSlice(
            $context,
            array_intersect_key($attemptSafe, array_flip([
                'live_call_attempted', 'booking_schema', 'payload_schema', 'segment_count', 'passenger_count',
            ])),
        );

        $hostSlice = array_intersect_key($classified, array_flip([
            'safe_reason_code',
            'host_error_family',
            'retry_policy',
            'recommended_admin_action',
            'admin_summary',
            'manual_review_required',
        ]));
        $hostSlice['safe_host_error_family'] = $hostSlice['host_error_family'] ?? null;
        $hostSlice['host_classification_reclassified_from_digest'] = ($classified['safe_reason_code'] ?? null)
            !== ($attemptSafe['safe_reason_code'] ?? null);

        return $hostSlice;
    }

    /**
     * @param  array<string, mixed>|null  $digest
     * @param  array<string, mixed>  $attemptSafe
     * @return array<string, mixed>
     */
    protected function retroactiveEnrichmentReport(
        Booking $booking,
        mixed $attempt,
        ?array $digest,
        ?string $digestSource,
        array $attemptSafe,
    ): array {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $hasMetaDigest = is_array($meta[self::META_DIGEST_KEY] ?? null) && $meta[self::META_DIGEST_KEY] !== [];
        $attemptWarnings = is_array($attemptSafe['safe_application_warnings'] ?? null)
            ? $attemptSafe['safe_application_warnings']
            : [];
        $redactedAttempt = $this->applicationRowsAreRedactedPlaceholders($attemptWarnings);
        $rehydrated = $this->rehydrateApplicationRowsFromAttemptSafeSummary($attemptSafe, 'warnings');

        if ($hasMetaDigest && $digestSource === 'booking_meta') {
            return [
                'retroactive_enrichment_available' => true,
                'retroactive_enrichment_source' => 'booking_meta_application_digest',
                'retroactive_enrichment_note' => null,
            ];
        }
        if ($rehydrated !== [] && $redactedAttempt) {
            return [
                'retroactive_enrichment_available' => true,
                'retroactive_enrichment_source' => 'attempt_safe_summary_messages',
                'retroactive_enrichment_note' => null,
            ];
        }
        if (! $redactedAttempt && $attemptWarnings !== []) {
            return [
                'retroactive_enrichment_available' => true,
                'retroactive_enrichment_source' => 'attempt_safe_summary_rows',
                'retroactive_enrichment_note' => null,
            ];
        }

        return [
            'retroactive_enrichment_available' => false,
            'retroactive_enrichment_source' => null,
            'retroactive_enrichment_note' => 'Attempt #'.($attempt?->id ?? '?').' cannot be enriched retroactively: no booking-meta application digest and no recoverable safe warning text in attempt safe_summary. A future controlled create would persist a fresh digest; do not auto-retry mixed live create from this phase.',
        ];
    }

    /**
     * @return list<string>
     */
    protected function mixedPreflightInspectKeys(): array
    {
        return [
            'mixed_mapping_comparison_result',
            'command_pricing_schema_valid',
            'command_pricing_allowed_shape',
            'command_pricing_rejected_keys',
            'payload_preflight_status',
            'mixed_fare_carrier_mapping_complete',
            'no_fares_rbd_carrier_preflight_risk',
            'segment_marketing_carriers',
            'command_pricing_carriers',
            'command_pricing_segmentselect_pairing_complete',
            'segment_select_rph_values',
            'command_pricing_rph_values',
            'brand_present',
            'brand_code',
            'brand_rph_present',
            'brand_rph_type',
            'brand_rph_values',
            'brand_rph_values_raw',
            'brand_rph_values_normalized',
            'brand_rph_schema_valid',
            'brand_segmentselect_pairing_required',
            'brand_segmentselect_pairing_complete',
            'brand_segmentselect_pairing_values_match_normalized',
            'brand_segmentselect_missing_rph',
            'brand_schema_valid',
            'brand_schema_rejected_pointer',
            'brand_schema_rejected_message',
            'brand_wire_shape',
            'brand_omitted_for_mixed_v24_segmentselect',
            'brand_omission_reason',
            'selected_payload_style',
        ];
    }

    /**
     * @param  array<string, mixed>  $decodedResponse
     */
    protected function extractRecordLocator(array $decodedResponse, string $existingBookingPnr = ''): string
    {
        if ($existingBookingPnr !== '') {
            return strtoupper(substr($existingBookingPnr, 0, 32));
        }

        foreach (['CreatePassengerNameRecordRS', 'createPassengerNameRecordRS'] as $rk) {
            $rs = $decodedResponse[$rk] ?? null;
            if (! is_array($rs)) {
                continue;
            }
            foreach ([
                'ItineraryRef.ID', 'ItineraryRef.Id', 'ItineraryRef.id',
                'itineraryRef.ID', 'itineraryRef.Id', 'itineraryRef.id',
            ] as $p) {
                $v = data_get($rs, $p);
                if (is_string($v) && trim($v) !== '') {
                    return strtoupper(substr(trim($v), 0, 32));
                }
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    protected function findApplicationResultsNode(array $json): array
    {
        $candidates = [
            $json,
            is_array($json['CreatePassengerNameRecordRS'] ?? null) ? $json['CreatePassengerNameRecordRS'] : null,
            is_array($json['createPassengerNameRecordRS'] ?? null) ? $json['createPassengerNameRecordRS'] : null,
        ];
        foreach ($candidates as $node) {
            if (! is_array($node)) {
                continue;
            }
            foreach (['ApplicationResults', 'applicationResults'] as $arKey) {
                $ar = $node[$arKey] ?? null;
                if (is_array($ar)) {
                    return $ar;
                }
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $applicationResults
     * @param  list<string>  $keys
     * @return list<array<string, mixed>>
     */
    protected function collectStructuredRows(array $applicationResults, array $keys, string $type): array
    {
        $rows = [];
        foreach ($keys as $ek) {
            $raw = $applicationResults[$ek] ?? null;
            $list = match (true) {
                $raw === null => [],
                is_array($raw) && array_is_list($raw) => $raw,
                is_array($raw) => [$raw],
                default => [],
            };
            foreach (array_slice($list, 0, 24) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $structured = $this->structureApplicationRow($row, $type);
                if ($structured !== null) {
                    $rows[] = $structured;
                }
                $ssRaw = $row['SystemSpecificResults'] ?? $row['systemSpecificResults'] ?? null;
                $ssList = match (true) {
                    $ssRaw === null => [],
                    is_array($ssRaw) && array_is_list($ssRaw) => $ssRaw,
                    is_array($ssRaw) => [$ssRaw],
                    default => [],
                };
                foreach (array_slice($ssList, 0, 16) as $ssr) {
                    if (! is_array($ssr)) {
                        continue;
                    }
                    $nested = $this->structureSystemSpecificRow($ssr, $type);
                    if ($nested !== null) {
                        $rows[] = $nested;
                    }
                    $msgRaw = $ssr['Message'] ?? $ssr['message'] ?? null;
                    foreach ($this->normalizeMessageList($msgRaw) as $mrow) {
                        $msgStructured = $this->structureMessageRow($mrow, $type);
                        if ($msgStructured !== null) {
                            $rows[] = $msgStructured;
                        }
                    }
                }
            }
        }

        return $this->dedupeRows($rows);
    }

    /**
     * @param  array<string, mixed>  $applicationResults
     * @return list<array<string, mixed>>
     */
    protected function collectMessageRows(array $applicationResults): array
    {
        $rows = [];
        foreach (['Message', 'Messages', 'message', 'messages'] as $mk) {
            $raw = $applicationResults[$mk] ?? null;
            foreach ($this->normalizeMessageList($raw) as $mrow) {
                $structured = $this->structureMessageRow($mrow, 'message');
                if ($structured !== null) {
                    $rows[] = $structured;
                }
            }
        }

        return $this->dedupeRows($rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function normalizeMessageList(mixed $raw): array
    {
        return match (true) {
            $raw === null => [],
            is_array($raw) && array_is_list($raw) => $raw,
            is_array($raw) => [$raw],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    protected function structureApplicationRow(array $row, string $type): ?array
    {
        $code = $this->stringField($row, ['code', 'Code', 'errorCode', 'ErrorCode'], 120);
        $message = $this->extractRowMessage($row);
        $severity = $this->stringField($row, ['severity', 'Severity', 'status', 'Status'], 64);
        $element = $this->pathHintFromRow($row);

        if ($code === '' && $message === '' && $element === '') {
            return null;
        }

        return $this->safeRow($type, $code, $message, $severity, $element, $this->sourceFromRow($row));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    protected function structureSystemSpecificRow(array $row, string $type): ?array
    {
        $message = $this->extractRowMessage($row);
        $element = $this->pathHintFromRow($row);
        foreach (['ShortText', 'shortText', 'Element', 'element'] as $sk) {
            if (isset($row[$sk]) && is_string($row[$sk]) && trim($row[$sk]) !== '') {
                $element = $element !== '' ? $element : substr(trim($row[$sk]), 0, 200);
            }
        }

        if ($message === '' && $element === '') {
            return null;
        }

        return $this->safeRow($type, '', $message, '', $element, $this->sourceFromRow($row));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    protected function structureMessageRow(array $row, string $type): ?array
    {
        $code = $this->stringField($row, ['code', 'Code'], 120);
        $message = $this->extractRowMessage($row);

        if ($code === '' && $message === '') {
            return null;
        }

        return $this->safeRow($type, $code, $message, '', '', null);
    }

    protected function safeRow(
        string $type,
        string $code,
        string $message,
        string $severity,
        string $element,
        ?string $source,
    ): array {
        $row = array_filter([
            'type' => $type,
            'code' => $code !== '' ? SensitiveDataRedactor::sanitizeApplicationDiagnosticText(substr($code, 0, 120)) : null,
            'message' => $message !== '' ? SensitiveDataRedactor::sanitizeApplicationDiagnosticText(substr($message, 0, self::MESSAGE_MAX)) : null,
            'severity' => $severity !== '' ? SensitiveDataRedactor::sanitizeApplicationDiagnosticText(substr($severity, 0, 64)) : null,
            'element' => $element !== '' ? SensitiveDataRedactor::sanitizeApplicationDiagnosticText(substr($element, 0, 200)) : null,
            'path' => $element !== '' ? SensitiveDataRedactor::sanitizeApplicationDiagnosticText(substr($element, 0, 200)) : null,
            'source' => $source,
        ], static fn ($v) => $v !== null && $v !== '' && $v !== '[redacted]');

        if ($row === []) {
            return ['type' => $type];
        }

        return $row;
    }

    /**
     * @deprecated Retained for compatibility; row fields are sanitized individually in {@see safeRow()}.
     *
     * @param  array<string, mixed>  $row
     */
    protected function rowLooksSafe(array $row): bool
    {
        return $row !== [];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function extractRowMessage(array $row): string
    {
        foreach (['content', 'Content', 'value', 'Value', '_', 'text', 'Text', 'message', 'Message', 'ShortText', 'shortText'] as $tk) {
            if (! isset($row[$tk])) {
                continue;
            }
            $v = $row[$tk];
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function pathHintFromRow(array $row): string
    {
        foreach (['propertyPath', 'jsonPath', 'field', 'path', 'parameter', 'invalidField', 'invalid_field', 'Element', 'element'] as $k) {
            if (! isset($row[$k]) || ! is_string($row[$k]) || trim($row[$k]) === '') {
                continue;
            }

            return trim($row[$k]);
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function sourceFromRow(array $row): ?string
    {
        $host = data_get($row, 'HostCommand');
        if (is_string($host) && trim($host) !== '') {
            return '[host_command_redacted]';
        }
        $system = data_get($row, 'SystemSpecificResults.0.host');
        if (is_string($system) && trim($system) !== '') {
            return substr(trim($system), 0, 80);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $node
     * @param  list<string>  $keys
     */
    protected function stringField(?array $node, array $keys, int $max): string
    {
        if ($node === null) {
            return '';
        }
        foreach ($keys as $k) {
            if (! isset($node[$k]) || ! is_string($node[$k]) || trim($node[$k]) === '') {
                continue;
            }

            return substr(trim($node[$k]), 0, $max);
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $json
     * @return list<string>
     */
    protected function topLevelKeySample(array $json): array
    {
        $keys = array_keys($json);
        $safe = [];
        foreach (array_slice($keys, 0, self::KEY_SAMPLE_MAX) as $k) {
            if (! is_string($k)) {
                continue;
            }
            $lower = strtolower($k);
            if (in_array($lower, self::FORBIDDEN_TOP_LEVEL_KEYS, true)) {
                continue;
            }
            $blocked = false;
            foreach (self::FORBIDDEN_VALUE_FRAGMENTS as $frag) {
                if ($lower === $frag || str_starts_with($lower, $frag.'_')) {
                    $blocked = true;
                    break;
                }
            }
            if (! $blocked) {
                $safe[] = $k;
            }
        }

        return $safe;
    }

    protected function deriveStatusClassifier(string $applicationStatus, bool $hasLocator): string
    {
        $lower = strtolower(trim($applicationStatus));
        if (! $hasLocator && in_array($lower, ['incomplete', 'notprocessed'], true)) {
            return 'incomplete_no_locator';
        }
        if ($hasLocator) {
            return 'record_locator_present';
        }
        if ($lower === 'complete') {
            return 'complete_no_locator';
        }

        return $lower !== '' ? 'application_'.$lower : 'unknown_application_response';
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function dedupeRows(array $rows): array
    {
        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $key = json_encode($row);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    protected function pluckMessages(array $rows): array
    {
        $msgs = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $m = trim((string) ($row['message'] ?? ''));
            if ($m !== '') {
                $msgs[] = substr($m, 0, self::MESSAGE_MAX);
            }
        }

        return array_values(array_unique($msgs));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function stripForbiddenValues(array $payload): array
    {
        array_walk_recursive($payload, function (&$value, $key): void {
            if (! is_string($value)) {
                return;
            }
            $keyLower = is_string($key) ? strtolower($key) : '';
            if (in_array($keyLower, self::FORBIDDEN_TOP_LEVEL_KEYS, true)) {
                $value = '[redacted]';

                return;
            }
            foreach (self::FORBIDDEN_VALUE_FRAGMENTS as $frag) {
                if (str_contains($keyLower, $frag)) {
                    $value = '[redacted]';

                    return;
                }
            }
            if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $value) === 1) {
                $value = '[redacted]';
            }
        });

        return $payload;
    }

    /**
     * @return array{digest: array<string, mixed>}|null
     */
    protected function fallbackFromLatestAttempt(Booking $booking): ?array
    {
        $attempt = SupplierBookingAttemptResolution::resolveLatestMeaningfulCreateAttempt(
            $booking->supplierBookingAttempts,
        );
        if ($attempt === null) {
            return null;
        }
        $safe = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $structured = is_array($safe['safe_validation_excerpts_structured'] ?? null)
            ? $safe['safe_validation_excerpts_structured']
            : [];
        if ($structured !== []) {
            $errors = [];
            foreach ($structured as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $errors[] = array_filter([
                    'type' => $row['type'] ?? 'error',
                    'code' => isset($row['code']) ? substr((string) $row['code'], 0, 120) : null,
                    'message' => isset($row['message']) ? substr((string) $row['message'], 0, self::MESSAGE_MAX) : null,
                ]);
            }

            return [
                'digest' => [
                    'status' => in_array(strtolower((string) ($safe['safe_application_status'] ?? $safe['sabre_application_status'] ?? '')), ['incomplete', 'notprocessed'], true)
                        ? 'incomplete_no_locator'
                        : 'attempt_fallback',
                    'application_status' => $safe['safe_application_status'] ?? $safe['sabre_application_status'] ?? null,
                    'has_record_locator' => false,
                    'record_locator_present' => false,
                    'error_count' => count($errors),
                    'warning_count' => is_array($safe['safe_application_warnings'] ?? null) ? count($safe['safe_application_warnings']) : 0,
                    'message_count' => 0,
                    'errors' => $errors,
                    'warnings' => is_array($safe['safe_application_warnings'] ?? null) ? array_slice($safe['safe_application_warnings'], 0, self::ROW_MAX) : [],
                    'messages' => [],
                    'source' => 'attempt_safe_summary_fallback',
                    'recorded_at' => optional($attempt->attempted_at)->toIso8601String(),
                ],
            ];
        }

        $appStatus = trim((string) ($safe['application_results_status'] ?? $safe['safe_application_status'] ?? $safe['sabre_application_status'] ?? ''));
        $codes = is_array($safe['response_error_codes'] ?? null) ? $safe['response_error_codes'] : [];
        $messages = is_array($safe['response_error_messages'] ?? null) ? $safe['response_error_messages'] : [];
        if ($appStatus === '' && $codes === [] && $messages === []) {
            return null;
        }

        $errors = [];
        foreach (array_slice($codes, 0, self::ROW_MAX) as $i => $code) {
            $msg = isset($messages[$i]) ? (string) $messages[$i] : '';
            $errors[] = array_filter([
                'type' => 'error',
                'code' => substr((string) $code, 0, 120),
                'message' => $msg !== '' ? substr($msg, 0, self::MESSAGE_MAX) : null,
            ]);
        }

        return [
            'digest' => [
                'status' => in_array(strtolower($appStatus), ['incomplete', 'notprocessed'], true)
                    ? 'incomplete_no_locator'
                    : 'attempt_fallback',
                'application_status' => $appStatus !== '' ? $appStatus : null,
                'has_record_locator' => false,
                'record_locator_present' => false,
                'error_count' => count($errors),
                'warning_count' => 0,
                'message_count' => 0,
                'errors' => $errors,
                'warnings' => [],
                'messages' => [],
                'source' => 'attempt_safe_summary_fallback',
                'recorded_at' => optional($attempt->attempted_at)->toIso8601String(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $digest
     */
    protected function recommendedNextAction(Booking $booking, ?array $digest, ?string $digestSource): string
    {
        if ($digest === null || $digest === []) {
            return 'Deploy F9G if needed, then run sabre:inspect-controlled-pnr-application-error; if digest still missing, one controlled create with exact confirm may capture ApplicationResults (do not retry until digest reviewed).';
        }

        if (trim((string) ($booking->pnr ?? '')) !== '') {
            return 'PNR already present — review ticketing readiness; no automatic create retry.';
        }

        $status = strtolower(trim((string) ($digest['application_status'] ?? '')));
        if (in_array($status, ['incomplete', 'notprocessed'], true)) {
            return 'Staff review required: Sabre returned Incomplete/NotProcessed without locator. Review safe errors/warnings before any retry; do not bypass failure gates.';
        }

        if ($digestSource === 'attempt_safe_summary_fallback') {
            return 'Partial digest from prior attempt safe_summary only — consider one post-F9G controlled create to persist full ApplicationResults digest before retry decisions.';
        }

        return 'Review persisted ApplicationResults digest and host/application messages before controlled retry.';
    }
}
