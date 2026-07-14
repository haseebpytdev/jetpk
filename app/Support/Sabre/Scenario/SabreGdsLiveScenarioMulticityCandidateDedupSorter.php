<?php

namespace App\Support\Sabre\Scenario;

/**
 * De-duplicates and sorts Sabre multi-city plan candidates for clean display-oriented output.
 */
final class SabreGdsLiveScenarioMulticityCandidateDedupSorter
{
    public const DEDUP_KEY_VERSION = 'v1';

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @return array{candidates: list<array<string, mixed>>, diagnostics: array<string, mixed>}
     */
    public function deduplicateAndSort(array $candidates): array
    {
        $before = count($candidates);
        $retained = [];

        foreach ($candidates as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $key = $this->buildDedupKey($candidate);
            if (! isset($retained[$key])) {
                $retained[$key] = $candidate;

                continue;
            }

            if ($this->shouldPreferCandidate($candidate, $retained[$key])) {
                $retained[$key] = $candidate;
            }
        }

        $deduped = array_values($retained);
        usort($deduped, $this->compareCandidates(...));

        $after = count($deduped);

        return [
            'candidates' => $deduped,
            'diagnostics' => [
                'multicity_dedup_enabled' => true,
                'multicity_dedup_key_version' => self::DEDUP_KEY_VERSION,
                'multicity_candidates_before_dedup' => $before,
                'multicity_candidates_after_dedup' => $after,
                'multicity_duplicate_candidates_removed_count' => max(0, $before - $after),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    public function buildDedupKey(array $candidate): string
    {
        $payload = [
            'route_by_slice' => $this->normalizeRouteBySlice($candidate['route_by_slice'] ?? []),
            'carrier_chain' => strtoupper(trim((string) ($candidate['carrier_chain'] ?? ''))),
            'validating_carrier' => strtoupper(trim((string) ($candidate['validating_carrier'] ?? ''))),
            'brand_code' => strtoupper(trim((string) ($candidate['brand_code'] ?? ''))),
            'total_fare' => $this->normalizeFare($candidate['total_fare'] ?? null),
            'currency' => strtoupper(trim((string) ($candidate['currency'] ?? ''))),
            'fare_basis_codes_by_segment_count' => (int) ($candidate['fare_basis_codes_by_segment_count'] ?? 0),
            'booking_classes_by_segment_count' => (int) ($candidate['booking_classes_by_segment_count'] ?? 0),
            'cabin_by_segment_count' => (int) ($candidate['cabin_by_segment_count'] ?? 0),
            'segment_marketing_carriers' => $this->normalizeCarrierList($candidate['segment_marketing_carriers'] ?? []),
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @param  array<string, mixed>  $existing
     */
    protected function shouldPreferCandidate(array $incoming, array $existing): bool
    {
        $incomingFare = (float) ($incoming['total_fare'] ?? PHP_FLOAT_MAX);
        $existingFare = (float) ($existing['total_fare'] ?? PHP_FLOAT_MAX);
        if ($incomingFare !== $existingFare) {
            return $incomingFare < $existingFare;
        }

        $incomingHasKey = ($incoming['supplier_offer_key_present'] ?? false) === true;
        $existingHasKey = ($existing['supplier_offer_key_present'] ?? false) === true;
        if ($incomingHasKey !== $existingHasKey) {
            return $incomingHasKey;
        }

        $incomingSource = trim((string) ($incoming['source_offer_id'] ?? $incoming['internal_offer_key'] ?? ''));
        $existingSource = trim((string) ($existing['source_offer_id'] ?? $existing['internal_offer_key'] ?? ''));

        return $incomingSource !== '' && ($existingSource === '' || strcmp($incomingSource, $existingSource) < 0);
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    protected function compareCandidates(array $a, array $b): int
    {
        $fareCompare = $this->normalizeFare($a['total_fare'] ?? null) <=> $this->normalizeFare($b['total_fare'] ?? null);
        if ($fareCompare !== 0) {
            return $fareCompare;
        }

        $classCompare = $this->classificationSortRank($a) <=> $this->classificationSortRank($b);
        if ($classCompare !== 0) {
            return $classCompare;
        }

        $validatingCompare = strcmp(
            strtoupper(trim((string) ($a['validating_carrier'] ?? ''))),
            strtoupper(trim((string) ($b['validating_carrier'] ?? ''))),
        );
        if ($validatingCompare !== 0) {
            return $validatingCompare;
        }

        return strcmp(
            strtoupper(trim((string) ($a['brand_code'] ?? ''))),
            strtoupper(trim((string) ($b['brand_code'] ?? ''))),
        );
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    protected function classificationSortRank(array $candidate): int
    {
        return match ((string) ($candidate['classification'] ?? '')) {
            SabreGdsLiveScenarioMulticityClassifier::CATEGORY_SAME_CARRIER => 0,
            SabreGdsLiveScenarioMulticityClassifier::CATEGORY_MIXED_CARRIER => 1,
            SabreGdsLiveScenarioMulticityClassifier::CATEGORY_INTERLINE => 2,
            SabreGdsLiveScenarioMulticityClassifier::CATEGORY_DISCONTINUOUS => 3,
            default => 4,
        };
    }

    /**
     * @param  mixed  $fare
     */
    protected function normalizeFare(mixed $fare): float
    {
        if ($fare === null || $fare === '') {
            return PHP_FLOAT_MAX;
        }

        return round((float) $fare, 2);
    }

    /**
     * @param  mixed  $routes
     * @return list<string>
     */
    protected function normalizeRouteBySlice(mixed $routes): array
    {
        if (! is_array($routes)) {
            return [];
        }

        $out = [];
        foreach ($routes as $route) {
            $normalized = strtoupper(trim((string) $route));
            if ($normalized !== '') {
                $out[] = $normalized;
            }
        }

        return $out;
    }

    /**
     * @param  mixed  $carriers
     * @return list<string>
     */
    protected function normalizeCarrierList(mixed $carriers): array
    {
        if (! is_array($carriers)) {
            return [];
        }

        $out = [];
        foreach ($carriers as $carrier) {
            $normalized = strtoupper(trim((string) $carrier));
            if ($normalized !== '') {
                $out[] = $normalized;
            }
        }

        sort($out);

        return $out;
    }
}
