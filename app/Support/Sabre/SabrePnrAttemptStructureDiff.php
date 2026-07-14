<?php

namespace App\Support\Sabre;

/**
 * Compares safe structural snapshots between failed and successful create_pnr attempts.
 */
final class SabrePnrAttemptStructureDiff
{
    /** @var list<string> */
    private const COMPARE_PATHS = [
        'endpoint_path',
        'payload_schema',
        'selected_payload_style',
        'safe_request_structure',
        'safe_airbook_structure',
        'safe_enhanced_airbook_structure',
        'safe_airprice_structure',
        'safe_postprocessing_structure',
        'safe_response_structure',
        'safe_enhanced_airbook_fingerprint',
    ];

    public function __construct(
        protected SabrePnrAttemptStructureRegenerator $regenerator,
    ) {}

    /**
     * @param  list<int>  $successAttemptIds
     * @return array<string, mixed>
     */
    public function compare(int $failedAttemptId, array $successAttemptIds): array
    {
        $failed = $this->regenerator->resolveForAttempt(
            \App\Models\SupplierBookingAttempt::query()->findOrFail($failedAttemptId),
        );

        $successRows = [];
        foreach ($successAttemptIds as $id) {
            $attemptId = (int) $id;
            if ($attemptId < 1) {
                continue;
            }
            $attempt = \App\Models\SupplierBookingAttempt::query()->find($attemptId);
            if ($attempt === null) {
                $successRows[] = ['attempt_id' => $attemptId, 'found' => false];
                continue;
            }
            $successRows[] = array_merge(
                ['attempt_id' => $attemptId, 'found' => true],
                $this->regenerator->resolveForAttempt($attempt),
            );
        }

        return [
            'failed_attempt_id' => $failedAttemptId,
            'success_attempt_ids' => array_values(array_map('intval', $successAttemptIds)),
            'failed' => $failed,
            'success' => $successRows,
            'field_diff' => $this->diffFailedAgainstSuccess($failed, $successRows),
            'highlighted_differences' => $this->highlightStructuralDifferences($failed, $successRows),
        ];
    }

    /**
     * @param  array<string, mixed>  $failed
     * @param  list<array<string, mixed>>  $successRows
     * @return array<string, mixed>
     */
    protected function diffFailedAgainstSuccess(array $failed, array $successRows): array
    {
        $foundSuccess = array_values(array_filter($successRows, static fn (array $r): bool => ($r['found'] ?? false) === true));
        if ($foundSuccess === []) {
            return ['comparable' => false, 'reason' => 'no_success_attempts_found'];
        }

        $diffs = [];
        foreach (self::COMPARE_PATHS as $path) {
            $failedValue = $this->valueAtPath($failed, $path);
            if ($failedValue === null) {
                continue;
            }
            $successValues = [];
            foreach ($foundSuccess as $row) {
                $val = $this->valueAtPath($row, $path);
                if ($val !== null) {
                    $successValues[(string) ($row['attempt_id'] ?? '0')] = $val;
                }
            }
            if ($successValues === []) {
                $diffs[$path] = ['failed_only' => $failedValue];
                continue;
            }
            $failedEncoded = json_encode($failedValue, JSON_UNESCAPED_UNICODE);
            $allMatch = true;
            foreach ($successValues as $encodedVal) {
                if (json_encode($encodedVal, JSON_UNESCAPED_UNICODE) !== $failedEncoded) {
                    $allMatch = false;
                    break;
                }
            }
            if (! $allMatch) {
                $diffs[$path] = [
                    'failed' => $failedValue,
                    'success' => $successValues,
                ];
            }
        }

        return [
            'comparable' => true,
            'diff_count' => count($diffs),
            'diffs' => $diffs,
        ];
    }

    /**
     * @param  array<string, mixed>  $failed
     * @param  list<array<string, mixed>>  $successRows
     * @return array<string, mixed>
     */
    protected function highlightStructuralDifferences(array $failed, array $successRows): array
    {
        $foundSuccess = array_values(array_filter($successRows, static fn (array $r): bool => ($r['found'] ?? false) === true));
        $reference = $foundSuccess[0] ?? [];
        $highlights = [];

        $failedAir = is_array($failed['safe_airbook_structure'] ?? null) ? $failed['safe_airbook_structure'] : [];
        $refAir = is_array($reference['safe_airbook_structure'] ?? null) ? $reference['safe_airbook_structure'] : [];
        foreach ([
            'flight_segment_count',
            'halt_on_status_codes',
            'retry_rebook_present',
            'airbook_redisplay_present',
            'ignore_after_present',
        ] as $key) {
            if (array_key_exists($key, $failedAir) && array_key_exists($key, $refAir)
                && json_encode($failedAir[$key]) !== json_encode($refAir[$key])) {
                $highlights['airbook'][$key] = ['failed' => $failedAir[$key], 'reference_success' => $refAir[$key]];
            }
        }

        $failedMatrix = is_array($failedAir['flight_segment_field_matrix'] ?? null) ? $failedAir['flight_segment_field_matrix'] : [];
        $refMatrix = is_array($refAir['flight_segment_field_matrix'] ?? null) ? $refAir['flight_segment_field_matrix'] : [];
        if ($failedMatrix !== [] && $refMatrix !== []
            && json_encode($failedMatrix) !== json_encode($refMatrix)) {
            $highlights['enhanced_airbook_flight_segment_matrix'] = [
                'failed' => $failedMatrix,
                'reference_success' => $refMatrix,
            ];
        }

        $failedPrice = is_array($failed['safe_airprice_structure'] ?? null) ? $failed['safe_airprice_structure'] : [];
        $refPrice = is_array($reference['safe_airprice_structure'] ?? null) ? $reference['safe_airprice_structure'] : [];
        foreach ([
            'passenger_type_present',
            'brand_qualifier_present',
            'validating_carrier_flight_qualifiers_present',
            'validating_carrier_pricing_qualifiers_present',
        ] as $key) {
            if (array_key_exists($key, $failedPrice) && array_key_exists($key, $refPrice)
                && $failedPrice[$key] !== $refPrice[$key]) {
                $highlights['airprice'][$key] = ['failed' => $failedPrice[$key], 'reference_success' => $refPrice[$key]];
            }
        }

        $failedPost = is_array($failed['safe_postprocessing_structure'] ?? null) ? $failed['safe_postprocessing_structure'] : [];
        $refPost = is_array($reference['safe_postprocessing_structure'] ?? null) ? $reference['safe_postprocessing_structure'] : [];
        foreach ([
            'end_transaction_present',
            'redisplay_reservation_present',
            'received_from_present',
            'ignore_after_present',
        ] as $key) {
            if (array_key_exists($key, $failedPost) && array_key_exists($key, $refPost)
                && $failedPost[$key] !== $refPost[$key]) {
                $highlights['postprocessing'][$key] = ['failed' => $failedPost[$key], 'reference_success' => $refPost[$key]];
            }
        }

        $failedResp = is_array($failed['safe_response_structure'] ?? null) ? $failed['safe_response_structure'] : [];
        if ($failedResp !== []) {
            $highlights['response'] = array_intersect_key($failedResp, array_flip([
                'application_results_status',
                'application_results_incomplete',
                'host_warning_sabre_codes',
                'safe_host_error_fingerprint',
            ]));
        }

        return $highlights;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function valueAtPath(array $row, string $path): mixed
    {
        if ($path === 'endpoint_path') {
            return data_get($row, 'safe_request_structure.endpoint_path')
                ?? data_get($row, 'endpoint_path')
                ?? data_get($row, 'create_endpoint_path');
        }
        if ($path === 'payload_schema' || $path === 'selected_payload_style') {
            return data_get($row, 'safe_request_structure.'.$path)
                ?? data_get($row, $path)
                ?? data_get($row, 'create_payload_style');
        }

        return $row[$path] ?? null;
    }
}
