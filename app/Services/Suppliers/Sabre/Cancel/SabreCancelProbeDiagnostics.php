<?php

namespace App\Services\Suppliers\Sabre\Cancel;

use App\Models\Booking;

/**
 * Sanitized cancelBooking probe diagnostics (no PNR/bookingId/signature/PII in output).
 */
final class SabreCancelProbeDiagnostics
{
    public const NEXT_ACTION_STOP_LIVE_PROBING = 'stop_live_probing_collect_sabre_contract_details';

    public const STOP_LIVE_PROBING_BLOCKED_REASON = 'All unique simple cancel payload bodies have failed or were verified ineffective; stop live probing and collect Sabre contract/PCC details.';

    public const OFFICIAL_AUDIT_OFFICIAL_FULL_CANCEL_SHAPE_CANDIDATE = 'official_full_cancel_shape_candidate';

    public const OFFICIAL_AUDIT_SABRE_CONFIRMED_GDS_FULL_CANCEL = 'sabre_confirmed_gds_full_cancel_shape';

    public const OFFICIAL_AUDIT_VERIFIED_INEFFECTIVE = 'verified_ineffective_for_booking_26';

    /** @var list<string> */
    public const MATRIX_CONFIRMATION_AND_CANCEL_DATA_STYLES = [
        SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR,
        SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_SEGMENT_IDS_CANCEL,
        SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR.'_cancel_data',
        SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_DATA_CANCEL_ALL,
        SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_REQUEST_CONFIRMATION_CANCEL_DATA,
        SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_ALL_ROOT,
        SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_ALL_BOOKING_SOURCE,
    ];

    /** @var list<string> */
    public const MATRIX_BOOKING_ID_CANCEL_ALL_DATA_STYLES = [
        SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_CANCEL_ALL_ROOT,
        SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_CANCEL_DATA,
        SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_SIGNATURE_CANCEL_DATA,
        SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_SIGNATURE_CANCEL_ALL,
    ];

    /**
     * @param  array<string, mixed>  $json
     * @param  array<string, mixed>  $digest
     * @return array<string, mixed>
     */
    public static function enrichDigestFromJson(array $json, array $digest): array
    {
        $details = self::extractSanitizedErrorDetailsFromJson($json);
        $missing = self::validationMissingFieldsSanitized($digest, $details);

        if ($details !== []) {
            $digest['response_error_details_sanitized'] = $details;
        }
        if ($missing !== []) {
            $digest['validation_missing_fields_sanitized'] = $missing;
        }

        return $digest;
    }

    /**
     * @param  array<string, mixed>  $digest
     * @return array<string, mixed>
     */
    public static function cancelProbeSliceFromDigest(array $digest): array
    {
        $details = is_array($digest['response_error_details_sanitized'] ?? null)
            ? $digest['response_error_details_sanitized']
            : self::fallbackErrorDetailsFromDigest($digest);
        $missing = is_array($digest['validation_missing_fields_sanitized'] ?? null)
            ? array_values(array_map('strval', $digest['validation_missing_fields_sanitized']))
            : self::validationMissingFieldsSanitized($digest, $details);

        return [
            'response_error_details_sanitized' => array_slice($details, 0, 12),
            'validation_missing_fields_sanitized' => array_slice($missing, 0, 24),
        ];
    }

    /**
     * @param  array<string, mixed>  $digest
     * @return list<array<string, mixed>>
     */
    public static function fallbackErrorDetailsFromDigest(array $digest): array
    {
        $out = [];
        $codes = is_array($digest['response_error_codes'] ?? null) ? $digest['response_error_codes'] : [];
        $messages = is_array($digest['response_error_messages'] ?? null) ? $digest['response_error_messages'] : [];
        $paths = is_array($digest['response_error_paths'] ?? null) ? $digest['response_error_paths'] : [];
        $count = max(count($codes), count($messages), count($paths), 1);

        for ($i = 0; $i < min($count, 12); $i++) {
            $row = [];
            if (isset($codes[$i]) && is_string($codes[$i]) && trim($codes[$i]) !== '') {
                $row['code'] = self::sanitizeScalarToken($codes[$i]);
            }
            if (isset($messages[$i]) && is_string($messages[$i]) && trim($messages[$i]) !== '') {
                $row['message'] = self::sanitizeScalarToken($messages[$i]);
            }
            if (isset($paths[$i]) && is_string($paths[$i]) && trim($paths[$i]) !== '') {
                $row['source'] = ['pointer' => self::sanitizePathToken($paths[$i])];
            }
            if ($row !== []) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return list<array<string, mixed>>
     */
    public static function extractSanitizedErrorDetailsFromJson(array $json): array
    {
        $out = [];
        $errors = $json['errors'] ?? null;
        if (is_array($errors)) {
            foreach (array_slice($errors, 0, 12) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $sanitized = self::sanitizeErrorRow($row);
                if ($sanitized !== null) {
                    $out[] = $sanitized;
                }
            }
        }

        $single = $json['error'] ?? null;
        if (is_array($single)) {
            $sanitized = self::sanitizeErrorRow($single);
            if ($sanitized !== null) {
                $out[] = $sanitized;
            }
            if (isset($single['validationErrors']) && is_array($single['validationErrors'])) {
                foreach (array_slice($single['validationErrors'], 0, 12) as $ve) {
                    if (is_array($ve)) {
                        $nested = self::sanitizeErrorRow($ve);
                        if ($nested !== null) {
                            $out[] = $nested;
                        }
                    }
                }
            }
        }

        if (isset($json['validationErrors']) && is_array($json['validationErrors'])) {
            foreach (array_slice($json['validationErrors'], 0, 12) as $ve) {
                if (is_array($ve)) {
                    $nested = self::sanitizeErrorRow($ve);
                    if ($nested !== null) {
                        $out[] = $nested;
                    }
                }
            }
        }

        return self::uniqueErrorDetails($out);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    public static function sanitizeErrorRow(array $row): ?array
    {
        $out = [];
        foreach (['code', 'type', 'status'] as $k) {
            if (! isset($row[$k])) {
                continue;
            }
            $v = self::sanitizeScalarToken((string) $row[$k]);
            if ($v !== '') {
                $out[$k] = $v;
            }
        }
        foreach (['message', 'title', 'detail', 'description', 'developerMessage'] as $k) {
            if (! isset($row[$k]) || ! is_string($row[$k]) || trim($row[$k]) === '') {
                continue;
            }
            $v = self::sanitizeScalarToken(trim($row[$k]));
            if ($v !== '') {
                $out[$k] = $v;
            }
        }

        $source = [];
        if (isset($row['source']) && is_array($row['source'])) {
            foreach (['pointer', 'parameter', 'field'] as $sk) {
                if (! isset($row['source'][$sk]) || ! is_string($row['source'][$sk]) || trim($row['source'][$sk]) === '') {
                    continue;
                }
                $token = self::sanitizePathToken(trim($row['source'][$sk]));
                if ($token !== '') {
                    $source[$sk] = $token;
                }
            }
        }
        foreach (['pointer', 'parameter', 'field', 'path', 'invalidField', 'invalid_field', 'property'] as $fk) {
            if (! isset($row[$fk]) || ! is_string($row[$fk]) || trim($row[$fk]) === '') {
                continue;
            }
            $token = self::sanitizePathToken(trim($row[$fk]));
            if ($token === '') {
                continue;
            }
            if ($fk === 'pointer' || $fk === 'path') {
                $source['pointer'] ??= $token;
            } elseif ($fk === 'parameter') {
                $source['parameter'] ??= $token;
            } else {
                $source['field'] ??= $token;
            }
        }

        if ($source !== []) {
            $out['source'] = $source;
        }

        if (isset($row['validationErrors']) && is_array($row['validationErrors'])) {
            $nestedPaths = [];
            foreach (array_slice($row['validationErrors'], 0, 8) as $ve) {
                if (! is_array($ve)) {
                    continue;
                }
                $nested = self::sanitizeErrorRow($ve);
                if ($nested === null) {
                    continue;
                }
                $ptr = is_array($nested['source'] ?? null) ? ($nested['source']['pointer'] ?? null) : null;
                if (is_string($ptr) && $ptr !== '') {
                    $nestedPaths[] = $ptr;
                }
            }
            if ($nestedPaths !== []) {
                $out['validation_error_pointers'] = array_values(array_unique(array_slice($nestedPaths, 0, 8)));
            }
        }

        return $out !== [] ? $out : null;
    }

    /**
     * @param  array<string, mixed>  $digest
     * @param  list<array<string, mixed>>  $errorDetails
     * @return list<string>
     */
    public static function validationMissingFieldsSanitized(array $digest, array $errorDetails): array
    {
        $tokens = [];
        foreach (['response_missing_fields', 'response_error_paths', 'response_error_fields'] as $k) {
            if (! is_array($digest[$k] ?? null)) {
                continue;
            }
            foreach ($digest[$k] as $item) {
                if (! is_string($item) || trim($item) === '') {
                    continue;
                }
                $token = self::sanitizePathToken(trim($item));
                if ($token !== '') {
                    $tokens[] = $token;
                }
            }
        }

        foreach ($errorDetails as $row) {
            if (! is_array($row)) {
                continue;
            }
            $source = is_array($row['source'] ?? null) ? $row['source'] : [];
            foreach (['pointer', 'parameter', 'field'] as $sk) {
                if (isset($source[$sk]) && is_string($source[$sk]) && $source[$sk] !== '') {
                    $tokens[] = $source[$sk];
                }
            }
            foreach (['validation_error_pointers'] as $vk) {
                if (! is_array($row[$vk] ?? null)) {
                    continue;
                }
                foreach ($row[$vk] as $ptr) {
                    if (is_string($ptr) && $ptr !== '') {
                        $tokens[] = $ptr;
                    }
                }
            }
            foreach (['message', 'detail', 'title'] as $mk) {
                if (! isset($row[$mk]) || ! is_string($row[$mk])) {
                    continue;
                }
                $tokens = array_merge($tokens, self::extractNullFieldHintsFromText($row[$mk]));
            }
        }

        $messages = is_array($digest['response_error_messages'] ?? null) ? $digest['response_error_messages'] : [];
        foreach ($messages as $msg) {
            if (is_string($msg)) {
                $tokens = array_merge($tokens, self::extractNullFieldHintsFromText($msg));
            }
        }

        return array_values(array_unique(array_slice($tokens, 0, 24)));
    }

    /**
     * @return list<string>
     */
    public static function extractNullFieldHintsFromText(string $text): array
    {
        $hints = [];
        $text = self::sanitizeScalarToken($text);
        if ($text === '') {
            return [];
        }

        if (stripos($text, 'must not be null') !== false || stripos($text, 'must not be empty') !== false) {
            if (preg_match_all('/\b([a-zA-Z_][\w\[\].]*)\s+(?:must not be null|must not be empty)/i', $text, $mm)) {
                foreach ($mm[1] as $field) {
                    $token = self::sanitizePathToken((string) $field);
                    if ($token !== '') {
                        $hints[] = $token;
                    }
                }
            }
            if (preg_match_all('#(/[\w\[\]./-]+)\s+(?:must not be null|must not be empty)#i', $text, $pm)) {
                foreach ($pm[1] as $path) {
                    $token = self::sanitizePathToken((string) $path);
                    if ($token !== '') {
                        $hints[] = $token;
                    }
                }
            }
        }

        return $hints;
    }

    public static function sanitizeScalarToken(string $value, int $max = 240): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $value = preg_replace('/\b[A-Z0-9]{6}\b/', '[REDACTED]', $value) ?? $value;
        $value = preg_replace('/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i', '[REDACTED]', $value) ?? $value;
        foreach (['confirmationid', 'bookingid', 'bookingsignature', 'recordlocator', 'pnr', 'orderid'] as $needle) {
            if (stripos($value, $needle) !== false && preg_match('/'.$needle.'\s*[:=]\s*\S+/i', $value)) {
                $value = preg_replace('/'.$needle.'\s*[:=]\s*\S+/i', $needle.'=[REDACTED]', $value) ?? $value;
            }
        }
        if (strlen($value) > $max) {
            return substr($value, 0, $max);
        }

        return $value;
    }

    public static function sanitizePathToken(string $value, int $max = 200): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^[A-Z0-9]{6}$/', $value)) {
            return '';
        }
        if (preg_match('/\b[A-Z0-9]{6}\b/', $value) && str_contains($value, '/')) {
            $value = preg_replace('/\b[A-Z0-9]{6}\b/', '[segment]', $value) ?? $value;
        }
        if (strlen($value) > $max) {
            return substr($value, 0, $max);
        }

        return $value;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected static function uniqueErrorDetails(array $rows): array
    {
        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $key = json_encode($row);
            if (! is_string($key) || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $row;
            if (count($out) >= 12) {
                break;
            }
        }

        return $out;
    }

    public static function officialShapeAuditForStyle(string $style, SabreCancelBookingContext $context): ?array
    {
        if ($style === SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_ALL_BOOKING_SOURCE) {
            return [
                'label' => self::OFFICIAL_AUDIT_SABRE_CONFIRMED_GDS_FULL_CANCEL,
                'notes' => 'Sabre-confirmed GDS full cancel: flat root confirmationId + cancelAll=true + bookingSource=SABRE + receivedFrom.',
                'do_not_auto_recommend' => true,
            ];
        }

        if ($style !== SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_ALL_ROOT) {
            return null;
        }

        $audit = [
            'label' => self::OFFICIAL_AUDIT_OFFICIAL_FULL_CANCEL_SHAPE_CANDIDATE,
            'notes' => 'Sabre public docs: confirmationId + cancelAll=true at request root (legacy 2-key probe; superseded by bookingSource shape for GDS).',
        ];

        if ($context->stylePreviouslyIneffective($style)) {
            $audit['verified_ineffective'] = true;
            $audit['verified_ineffective_reason'] = $context->previouslyIneffectiveReason($style)
                ?? self::OFFICIAL_AUDIT_VERIFIED_INEFFECTIVE;
            $audit['do_not_auto_recommend'] = true;
        }

        return $audit;
    }

    /** @var list<string> */
    private const SIMPLE_BODY_ROOT_KEYS = [
        'confirmationId',
        'recordLocator',
        'bookingId',
        'bookingSignature',
        'cancelAll',
        'cancelData',
        'bookingSource',
        'receivedFrom',
    ];

    /** @var list<string> */
    private const WRAPPER_ROOT_KEYS = [
        'cancelBookingRequest',
        'request',
        'CancelBookingRQ',
        'CancelBookingRequest',
    ];

    /** @var list<string> */
    public const WRAPPER_PROBE_STYLE_CONSTANTS = [
        SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CANCEL_REQUEST_CONFIRMATION,
        SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CANCEL_REQUEST_ROOT,
        SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_REQUEST_CONFIRMATION_CANCEL_DATA,
        SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_REQUEST_WRAPPED,
        SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_REQUEST_WRAPPED_CANCEL_DATA,
        SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CANCEL_BOOKING_REQUEST_BOOKING_ID_SIGNATURE_CANCEL_ALL,
        SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CANCEL_BOOKING_REQUEST_BOOKING_ID_SIGNATURE_CANCEL_DATA,
        SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CANCELBOOKINGREQUEST_BOOKING_ID_SIGNATURE_CANCEL_ALL,
        SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CANCELBOOKINGRQ_BOOKING_ID_SIGNATURE_CANCEL_ALL,
    ];

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @return array{
     *   duplicate_payload_styles: list<array{style: string, duplicate_of_style: string, duplicate_of_failed_style: bool}>,
     *   unique_simple_body_fingerprint_count: int,
     *   unique_payload_bodies_tested_count: int,
     *   unique_payload_bodies_failed_or_ineffective_count: int,
     *   recommended_style_blocked_reason: ?string
     * }
     */
    public static function enrichCandidatesWithDuplicateSemantics(
        array &$candidates,
        SabreCancelBookingContext $context,
    ): array {
        $canonicalByFingerprint = [];
        $duplicatePayloadStyles = [];

        foreach ($candidates as &$row) {
            $style = (string) ($row['style'] ?? '');
            $body = is_array($row['body'] ?? null) ? $row['body'] : [];
            if ($style === '' || ! self::isSimpleCancelBody($body)) {
                $row['duplicate_of_style'] = null;
                $row['duplicate_of_failed_style'] = false;

                continue;
            }

            $fingerprint = self::semanticBodyFingerprint($body);
            if (! isset($canonicalByFingerprint[$fingerprint])) {
                $canonicalByFingerprint[$fingerprint] = $style;
                $row['duplicate_of_style'] = null;
                $row['duplicate_of_failed_style'] = false;

                continue;
            }

            $canonical = $canonicalByFingerprint[$fingerprint];
            $duplicateFailed = self::fingerprintHasFailedOrIneffectiveEquivalent($context, $fingerprint, $candidates);
            $row['duplicate_of_style'] = $canonical;
            $row['duplicate_of_failed_style'] = $duplicateFailed;
            $duplicatePayloadStyles[] = [
                'style' => $style,
                'duplicate_of_style' => $canonical,
                'duplicate_of_failed_style' => $duplicateFailed,
            ];
        }
        unset($row);

        $simpleFingerprints = [];
        foreach ($candidates as $row) {
            $body = is_array($row['body'] ?? null) ? $row['body'] : [];
            if (! self::isSimpleCancelBody($body)) {
                continue;
            }
            $simpleFingerprints[self::semanticBodyFingerprint($body)] = true;
        }

        $tested = 0;
        $settled = 0;
        foreach (array_keys($simpleFingerprints) as $fingerprint) {
            if (! self::fingerprintHasAttempt($context, $fingerprint, $candidates)) {
                continue;
            }
            $tested++;
            if (self::fingerprintSettledForStop($context, $fingerprint, $candidates)) {
                $settled++;
            }
        }

        return [
            'duplicate_payload_styles' => $duplicatePayloadStyles,
            'unique_simple_body_fingerprint_count' => count($simpleFingerprints),
            'unique_payload_bodies_tested_count' => $tested,
            'unique_payload_bodies_failed_or_ineffective_count' => $settled,
            'recommended_style_blocked_reason' => null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @param  array<string, mixed>  $analysis
     * @return array<string, mixed>
     */
    public static function finalizeEquivalenceAnalysis(
        array $candidates,
        array $analysis,
        ?SabreCancelBookingContext $context = null,
    ): array {
        if ($context !== null
            && self::shouldStopLiveProbing($context, $analysis, $candidates, self::firstRecommendedStyleAmongCandidates($candidates))) {
            $analysis['recommended_style_blocked_reason'] = self::STOP_LIVE_PROBING_BLOCKED_REASON;
        } else {
            $analysis['recommended_style_blocked_reason'] = self::resolveRecommendedStyleBlockedReason($candidates);
        }

        return $analysis;
    }

    /**
     * @param  array<string, mixed>|null  $equivalenceAnalysis
     * @param  list<array<string, mixed>>|null  $candidates
     */
    public static function shouldStopLiveProbing(
        SabreCancelBookingContext $context,
        ?array $equivalenceAnalysis,
        ?array $candidates = null,
        ?string $prospectiveRecommendedStyle = null,
    ): bool {
        if ($context->tripOrderContext->isCancelable !== true || ! is_array($equivalenceAnalysis)) {
            return false;
        }

        $tested = (int) ($equivalenceAnalysis['unique_payload_bodies_tested_count'] ?? 0);
        $settled = (int) ($equivalenceAnalysis['unique_payload_bodies_failed_or_ineffective_count'] ?? 0);
        if ($tested <= 0 || $tested !== $settled) {
            return false;
        }

        $style = $prospectiveRecommendedStyle ?? self::firstRecommendedStyleAmongCandidates($candidates ?? []);
        if ($style === null || $style === '') {
            return true;
        }

        $body = self::bodyForCandidateStyle($candidates ?? [], $style);

        return $body === [] || ! self::isSimpleCancelBody($body);
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     */
    protected static function firstRecommendedStyleAmongCandidates(array $candidates): ?string
    {
        foreach ($candidates as $row) {
            if (($row['recommended'] ?? false) === true) {
                $style = is_string($row['style'] ?? null) ? trim((string) $row['style']) : '';

                return $style !== '' ? $style : null;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @return array<string, mixed>
     */
    protected static function bodyForCandidateStyle(array $candidates, string $style): array
    {
        foreach ($candidates as $row) {
            if (($row['style'] ?? '') !== $style) {
                continue;
            }

            return is_array($row['body'] ?? null) ? $row['body'] : [];
        }

        return [];
    }

    public static function isWrapperProbeStyle(string $style): bool
    {
        $style = trim($style);
        if ($style === '') {
            return false;
        }

        if (in_array($style, self::WRAPPER_PROBE_STYLE_CONSTANTS, true)) {
            return true;
        }

        return str_ends_with($style, '_request_wrapped');
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     */
    public static function applyStopLiveProbingToCandidates(array &$candidates): void
    {
        foreach ($candidates as &$row) {
            $row['recommended'] = false;
            if (self::isWrapperProbeStyle((string) ($row['style'] ?? ''))) {
                $row['recommendation_suppressed_reason'] = self::NEXT_ACTION_STOP_LIVE_PROBING;
            }
        }
        unset($row);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public static function isSimpleCancelBody(array $body): bool
    {
        if ($body === []) {
            return false;
        }

        foreach (self::WRAPPER_ROOT_KEYS as $wrapper) {
            if (array_key_exists($wrapper, $body)) {
                return false;
            }
        }

        if (isset($body['orderId']) || isset($body['orderItemIds']) || isset($body['serviceItemIds'])) {
            return false;
        }

        $cancelData = $body['cancelData'] ?? null;
        if (is_array($cancelData)
            && (array_key_exists('segmentIds', $cancelData) || array_key_exists('segmentId', $cancelData))) {
            return false;
        }

        foreach (array_keys($body) as $key) {
            if (! is_string($key)) {
                return false;
            }
            if (! in_array($key, self::SIMPLE_BODY_ROOT_KEYS, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public static function semanticBodyFingerprint(array $body): string
    {
        $normalized = self::normalizeBodyForSemanticCompare($body);

        return hash('xxh128', json_encode($normalized, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public static function semanticBodyShapeForDisplay(array $body): array
    {
        $normalized = self::normalizeBodyForSemanticCompare($body);
        if (! is_array($normalized)) {
            return [];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>|list<mixed>|string|int|float|bool|null
     */
    protected static function normalizeBodyForSemanticCompare(mixed $value, ?string $parentKey = null): mixed
    {
        if (is_array($value)) {
            $out = [];
            ksort($value);
            foreach ($value as $k => $v) {
                $key = is_string($k) ? $k : (string) $k;
                $out[$key] = self::normalizeBodyForSemanticCompare($v, $key);
            }

            return $out;
        }

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            if (in_array($parentKey, ['confirmationId', 'recordLocator', 'bookingId', 'bookingSignature', 'orderId'], true)) {
                return '*';
            }

            return '*';
        }

        return '*';
    }

    public static function resolveNextActionRecommendation(
        SabreCancelBookingContext $context,
        ?string $recommendedStyle,
        ?array $equivalenceAnalysis = null,
    ): ?string {
        if ($context->tripOrderContext->isCancelable !== true) {
            return null;
        }

        if (self::shouldStopLiveProbing($context, $equivalenceAnalysis, null, $recommendedStyle)) {
            return self::NEXT_ACTION_STOP_LIVE_PROBING;
        }

        if (! self::matrixExhausted($context)) {
            return null;
        }

        return self::NEXT_ACTION_STOP_LIVE_PROBING;
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     */
    protected static function fingerprintHasFailedOrIneffectiveEquivalent(
        SabreCancelBookingContext $context,
        string $fingerprint,
        array $candidates,
    ): bool {
        foreach ($candidates as $row) {
            $style = (string) ($row['style'] ?? '');
            $body = is_array($row['body'] ?? null) ? $row['body'] : [];
            if ($style === '' || self::semanticBodyFingerprint($body) !== $fingerprint) {
                continue;
            }
            if ($context->stylePreviouslyFailed($style) || $context->stylePreviouslyIneffective($style)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     */
    protected static function fingerprintHasAttempt(
        SabreCancelBookingContext $context,
        string $fingerprint,
        array $candidates,
    ): bool {
        foreach ($candidates as $row) {
            $style = (string) ($row['style'] ?? '');
            $body = is_array($row['body'] ?? null) ? $row['body'] : [];
            if ($style === '' || self::semanticBodyFingerprint($body) !== $fingerprint) {
                continue;
            }
            if ($context->stylePreviouslyFailed($style) || $context->stylePreviouslyIneffective($style)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     */
    protected static function fingerprintSettledForStop(
        SabreCancelBookingContext $context,
        string $fingerprint,
        array $candidates,
    ): bool {
        foreach ($candidates as $row) {
            $style = (string) ($row['style'] ?? '');
            $body = is_array($row['body'] ?? null) ? $row['body'] : [];
            if ($style === '' || self::semanticBodyFingerprint($body) !== $fingerprint) {
                continue;
            }
            if (self::styleOutcomeSettledForStop($context, $style)) {
                return true;
            }
        }

        return false;
    }

    protected static function styleOutcomeSettledForStop(SabreCancelBookingContext $context, string $style): bool
    {
        if ($context->stylePreviouslyIneffective($style)) {
            return true;
        }

        $reason = $context->previouslyFailedReason($style);
        if ($reason === null) {
            return false;
        }

        if ($reason === 'CANCEL_DATA_MISSING') {
            return true;
        }

        return $reason === 'HTTP_400' || str_starts_with($reason, 'HTTP_400');
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     */
    protected static function resolveRecommendedStyleBlockedReason(array $candidates): ?string
    {
        foreach ($candidates as $row) {
            if (($row['recommended'] ?? false) !== true) {
                continue;
            }
            $duplicateOf = is_string($row['duplicate_of_style'] ?? null) ? (string) $row['duplicate_of_style'] : '';
            if ($duplicateOf !== '' && ($row['duplicate_of_failed_style'] ?? false) === true) {
                return 'Style '.($row['style'] ?? '').' suppressed: semantically equivalent to failed or ineffective style '.$duplicateOf.'.';
            }
        }

        $priorityStyles = [
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR_CANCEL_DATA_CANCEL_ALL,
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CONFIRMATION_PNR.'_cancel_data',
        ];
        foreach ($priorityStyles as $wanted) {
            foreach ($candidates as $row) {
                if (($row['style'] ?? '') !== $wanted) {
                    continue;
                }
                if (($row['duplicate_of_failed_style'] ?? false) !== true) {
                    continue;
                }
                $duplicateOf = is_string($row['duplicate_of_style'] ?? null) ? (string) $row['duplicate_of_style'] : '';
                if ($duplicateOf === '') {
                    continue;
                }

                return 'Style '.$wanted.' not recommended: semantically equivalent to failed or ineffective style '.$duplicateOf.'.';
            }
        }

        return null;
    }

    protected static function matrixExhausted(SabreCancelBookingContext $context): bool
    {
        foreach (self::MATRIX_CONFIRMATION_AND_CANCEL_DATA_STYLES as $style) {
            if (! self::styleMatrixSettled($context, $style)) {
                return false;
            }
        }
        foreach (self::MATRIX_BOOKING_ID_CANCEL_ALL_DATA_STYLES as $style) {
            if (! self::styleMatrixSettled($context, $style)) {
                return false;
            }
        }

        return true;
    }

    protected static function styleMatrixSettled(SabreCancelBookingContext $context, string $style): bool
    {
        if ($context->stylePreviouslyFailed($style)) {
            return true;
        }
        if ($context->stylePreviouslyIneffective($style)) {
            return true;
        }

        return false;
    }

    public static function resolveHostTypeLabel(?string $endpointHost, bool $certConfirmed, bool $productionConfirmed): string
    {
        if ($certConfirmed) {
            return 'CERT';
        }
        if ($productionConfirmed) {
            return 'PROD';
        }
        $host = strtolower(trim((string) $endpointHost));
        if ($host !== '' && (str_contains($host, '.cert.') || str_contains($host, 'api-crt'))) {
            return 'CERT';
        }
        if ($host === SabreCancelBookingInspectProbe::PRODUCTION_BASE_URL_HOST) {
            return 'PROD';
        }

        return 'UNKNOWN';
    }

    /**
     * @param  array<string, mixed>|null  $getBookingInventory
     * @return list<string>
     */
    public static function inferCancelSchemaGapDiagnosis(
        SabreCancelBookingContext $cancelContext,
        ?array $getBookingInventory = null,
    ): array {
        $notes = [];
        $trip = $cancelContext->tripOrderContext;
        $inventoryPresence = is_array($getBookingInventory['cancel_related_presence'] ?? null)
            ? $getBookingInventory['cancel_related_presence']
            : [];

        if ($trip->isCancelable === true && $trip->hasBookingId() && $trip->hasBookingSignature()) {
            $bookingIdStylesFailed = false;
            foreach (self::MATRIX_BOOKING_ID_CANCEL_ALL_DATA_STYLES as $style) {
                if ($cancelContext->stylePreviouslyFailed($style)) {
                    $bookingIdStylesFailed = true;
                    break;
                }
            }
            if ($bookingIdStylesFailed) {
                $notes[] = 'getBooking reports isCancelable=true with bookingId and bookingSignature present, but bookingId-based cancelBooking probes returned HTTP 400 INVALID_VALUE (must not be null).';
            }
        }

        $wrapperTested = false;
        foreach ([
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_CANCEL_BOOKING_REQUEST_BOOKING_ID_SIGNATURE_CANCEL_ALL,
            SabreCancelPayloadBuilder::STYLE_TRIP_ORDERS_BOOKING_ID_REQUEST_WRAPPED,
        ] as $wrapperStyle) {
            if ($cancelContext->stylePreviouslyFailed($wrapperStyle)) {
                $wrapperTested = true;
                break;
            }
        }
        if ($wrapperTested) {
            $notes[] = 'Sabre validation pointers referenced cancelBookingRequest/bookingSignature; wrapper payloads with both fields still failed — likely missing undocumented required field(s), wrong HTTP verb, or PCC/API entitlement gap (not a absent bookingSignature in getBooking).';
        }

        if (($inventoryPresence['cancel_data_present'] ?? false) !== true) {
            $notes[] = 'getBooking response did not expose cancelData (or cancel_data) paths; cancel may require server-side cancelData not returned by getBooking.';
        }
        if (($inventoryPresence['order_id_present'] ?? false) !== true
            && ($inventoryPresence['order_item_ids_path_count'] ?? 0) === 0) {
            $notes[] = 'getBooking did not expose orderId/orderItemIds paths; order-scoped cancel payloads cannot be built from retrieve alone.';
        }
        if (($inventoryPresence['links_or_actions_present'] ?? false) !== true) {
            $notes[] = 'getBooking did not expose links/actions/href/method templates that might document the canonical cancelBooking body.';
        }

        $notes[] = 'OTA inspect uses HTTP POST to /v1/trip/orders/cancelBooking; legacy Iati integration uses HTTP DELETE with confirmationId only — confirm contracted method and schema with Sabre.';

        if ($notes === []) {
            $notes[] = 'Insufficient probe history or getBooking inventory to infer a specific schema gap; collect support packet after refresh-trip-order-context dry-run.';
        }

        return array_values(array_unique(array_slice($notes, 0, 8)));
    }

    /**
     * @param  array<string, mixed>  $packetContext  host_type, endpoint_path, style_outcomes, gap_diagnosis lines
     * @return array{subject: string, body_lines: list<string>}
     */
    public static function buildSabreEscalationNoteTemplate(array $packetContext): array
    {
        $hostType = (string) ($packetContext['host_type'] ?? 'UNKNOWN');
        $endpointPath = (string) ($packetContext['endpoint_path'] ?? '/v1/trip/orders/cancelBooking');

        $bodyLines = [
            'Sabre Trip Orders cancelBooking — schema/entitlement clarification request (OTA integration).',
            '',
            'Environment: '.$hostType.' (api.cert.platform.sabre.com or api.platform.sabre.com — no credentials attached).',
            'Endpoint: POST '.$endpointPath.' (please confirm whether DELETE is required instead).',
            'Supplier connection id: [REDACTED — provide internally].',
            'PCC / EPR: [REDACTED — provide internally].',
            '',
            'getBooking (/v1/trip/orders/getBooking) succeeds for a disposable CERT hold PNR.',
            'Observed safe flags: bookingId present, bookingSignature present, isCancelable=true, isTicketed=false, ticket_numbers_present=false, segments HK/HK.',
            'Post-cancel retrieve: PNR remains active; isCancelable unchanged after failed cancel attempts.',
            '',
            'cancelBooking attempts (all HTTP 400, booking not cancelled):',
            '- trip_orders_booking_id_cancel_all_root',
            '- trip_orders_booking_id_signature_cancel_all',
            '- trip_orders_booking_id_signature_cancel_data',
            '- trip_orders_booking_id_request_wrapped',
            '- trip_orders_cancel_booking_request_booking_id_signature_cancel_all',
            '',
            'Representative error: INVALID_VALUE — Validation Failed: must not be null.',
            'Validation pointer (sanitized): /cancelBookingRequest/bookingSignature',
            '',
            'Questions for Sabre:',
            '1. What is the exact required JSON schema for cancelBooking on Trip Orders (root keys vs cancelBookingRequest wrapper)?',
            '2. Are bookingId + bookingSignature from getBooking sufficient, or are additional cancelData / orderItemIds / segmentIds required?',
            '3. Is cancelBooking entitled for our CERT PCC on connection [id]? getBooking works; cancel returns 400.',
            '4. Should the HTTP method be POST or DELETE for this endpoint?',
            '5. Does getBooking expose any cancel-specific fields we should copy into the cancel request?',
            '',
            'Internal gap diagnosis (automated, no PII):',
        ];

        $gapLines = is_array($packetContext['gap_diagnosis'] ?? null) ? $packetContext['gap_diagnosis'] : [];
        foreach (array_slice($gapLines, 0, 6) as $line) {
            if (is_string($line) && trim($line) !== '') {
                $bodyLines[] = '- '.trim($line);
            }
        }

        return [
            'subject' => 'Sabre CERT cancelBooking HTTP 400 — schema/entitlement for Trip Orders (OTA)',
            'body_lines' => $bodyLines,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $getBookingInventory
     * @return array<string, mixed>
     */
    public static function buildSupportPacket(
        Booking $booking,
        SabreCancelBookingContext $cancelContext,
        ?string $endpointHost,
        ?string $endpointPath,
        ?array $equivalenceAnalysis = null,
        ?string $recommendedStyle = null,
        ?array $getBookingInventory = null,
        bool $certBaseConfirmed = false,
        bool $productionHostConfirmed = false,
    ): array {
        $attempts = [];
        foreach ($cancelContext->lastTwoCancelAttemptsSummary as $row) {
            if (! is_array($row)) {
                continue;
            }
            $attempts[] = [
                'payload_style' => is_string($row['payload_style'] ?? null) ? (string) $row['payload_style'] : null,
                'http_status' => $row['http_status'] ?? null,
                'response_error_codes' => is_array($row['response_error_codes'] ?? null)
                    ? array_slice(array_map('strval', $row['response_error_codes']), 0, 8)
                    : [],
                'response_error_details_sanitized' => is_array($row['response_error_details_sanitized'] ?? null)
                    ? array_slice($row['response_error_details_sanitized'], 0, 4)
                    : [],
                'validation_missing_fields_sanitized' => is_array($row['validation_missing_fields_sanitized'] ?? null)
                    ? array_slice(array_map('strval', $row['validation_missing_fields_sanitized']), 0, 12)
                    : [],
            ];
        }

        $styleOutcomes = [];
        foreach ($cancelContext->failedCancelPayloadStyles as $style => $reason) {
            $styleOutcomes[] = [
                'style' => $style,
                'outcome' => 'failed',
                'reason' => $reason,
            ];
        }
        foreach ($cancelContext->ineffectiveCancelPayloadStyles as $style => $reason) {
            $styleOutcomes[] = [
                'style' => $style,
                'outcome' => 'ineffective',
                'reason' => $reason,
            ];
        }

        $stopLiveProbing = self::shouldStopLiveProbing(
            $cancelContext,
            $equivalenceAnalysis,
            null,
            $recommendedStyle,
        );
        if ($stopLiveProbing) {
            $recommendedStyle = null;
        }

        $resolvedPath = $endpointPath ?? (string) config('suppliers.sabre.cancel_endpoint_path', '/v1/trip/orders/cancelBooking');
        $hostType = self::resolveHostTypeLabel($endpointHost, $certBaseConfirmed, $productionHostConfirmed);
        $gapDiagnosis = self::inferCancelSchemaGapDiagnosis($cancelContext, $getBookingInventory);
        $escalation = self::buildSabreEscalationNoteTemplate([
            'host_type' => $hostType,
            'endpoint_path' => $resolvedPath,
            'gap_diagnosis' => $gapDiagnosis,
        ]);

        $inventorySummary = null;
        if (is_array($getBookingInventory)) {
            $inventorySummary = [
                'top_level_keys_sanitized' => $getBookingInventory['top_level_keys_sanitized'] ?? [],
                'cancel_safety_flags' => $getBookingInventory['cancel_safety_flags'] ?? null,
                'cancel_related_presence' => $getBookingInventory['cancel_related_presence'] ?? null,
                'possible_cancel_related_paths' => array_slice(
                    is_array($getBookingInventory['possible_cancel_related_paths'] ?? null)
                        ? $getBookingInventory['possible_cancel_related_paths']
                        : [],
                    0,
                    16,
                ),
                'possible_order_item_paths' => array_slice(
                    is_array($getBookingInventory['possible_order_item_paths'] ?? null)
                        ? $getBookingInventory['possible_order_item_paths']
                        : [],
                    0,
                    8,
                ),
                'possible_segment_paths' => array_slice(
                    is_array($getBookingInventory['possible_segment_paths'] ?? null)
                        ? $getBookingInventory['possible_segment_paths']
                        : [],
                    0,
                    8,
                ),
            ];
        }

        return [
            'mode' => 'support_packet',
            'booking_id' => $booking->id,
            'endpoint' => [
                'base_url_host' => $endpointHost ?? 'unknown',
                'host_type' => $hostType,
                'endpoint_path' => $resolvedPath,
                'cancel_http_method_ota' => 'POST',
                'cancel_http_method_legacy_iati' => 'DELETE',
            ],
            'payload_style_outcomes' => $styleOutcomes,
            'recent_cancel_attempts' => $attempts,
            'get_booking_summary' => [
                'trip_order_booking_id_present' => $cancelContext->tripOrderContext->hasBookingId(),
                'trip_order_booking_signature_present' => $cancelContext->tripOrderContext->hasBookingSignature(),
                'trip_order_is_cancelable' => $cancelContext->tripOrderContext->isCancelable,
                'trip_order_is_ticketed' => $cancelContext->tripOrderContext->isTicketed,
                'trip_order_context_source' => $cancelContext->tripOrderContext->contextSource,
                'ticket_numbers_present' => is_array($getBookingInventory['cancel_safety_flags'] ?? null)
                    ? ($getBookingInventory['cancel_safety_flags']['ticket_numbers_present'] ?? null)
                    : null,
            ],
            'get_booking_cancel_schema_inventory' => $inventorySummary,
            'cancel_schema_gap_diagnosis' => $gapDiagnosis,
            'sabre_escalation_note_template' => $escalation,
            'next_action_recommendation' => self::resolveNextActionRecommendation(
                $cancelContext,
                $recommendedStyle,
                $equivalenceAnalysis,
            ),
            'recommended_payload_style' => $recommendedStyle,
            'unique_payload_bodies_tested_count' => is_array($equivalenceAnalysis)
                ? (int) ($equivalenceAnalysis['unique_payload_bodies_tested_count'] ?? 0)
                : null,
            'unique_payload_bodies_failed_or_ineffective_count' => is_array($equivalenceAnalysis)
                ? (int) ($equivalenceAnalysis['unique_payload_bodies_failed_or_ineffective_count'] ?? 0)
                : null,
            'duplicate_payload_styles' => is_array($equivalenceAnalysis)
                ? ($equivalenceAnalysis['duplicate_payload_styles'] ?? [])
                : [],
            'recommended_style_blocked_reason' => $stopLiveProbing
                ? self::STOP_LIVE_PROBING_BLOCKED_REASON
                : (is_array($equivalenceAnalysis)
                    ? ($equivalenceAnalysis['recommended_style_blocked_reason'] ?? null)
                    : null),
            'ticketing_disabled' => true,
            'booking_status_updated' => false,
        ];
    }
}
