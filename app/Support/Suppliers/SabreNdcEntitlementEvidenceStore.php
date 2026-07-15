<?php

namespace App\Support\Suppliers;

use Illuminate\Support\Facades\Cache;

/**
 * Persists Sabre NDC entitlement evidence (matrix, variant probes, carrier probes) — no secrets/payloads.
 */
final class SabreNdcEntitlementEvidenceStore
{
    private const MATRIX_PREFIX = 'sabre_ndc_evidence:matrix:';

    private const VARIANT_PREFIX = 'sabre_ndc_evidence:variant:';

    private const CARRIER_PREFIX = 'sabre_ndc_evidence:carrier:';

    /**
     * @param  array<string, mixed>  $summary
     * @param  list<array<string, mixed>>  $rows
     */
    public function storeMatrix(int $connectionId, array $summary, array $rows): void
    {
        $messageCodes = $this->collectMessageCodes($rows);
        $http200 = 0;
        $zeroOffers = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if ((int) ($row['http_status'] ?? 0) === 200) {
                $http200++;
            }
            if ((int) ($row['normalized_offer_count'] ?? 0) === 0) {
                $zeroOffers++;
            }
        }

        ksort($messageCodes);

        Cache::put(self::MATRIX_PREFIX.$connectionId, [
            'connection_id' => $connectionId,
            'recorded_at' => now()->toIso8601String(),
            'variant' => $summary['variant'] ?? null,
            'total_cells' => (int) ($summary['cells'] ?? count($rows)),
            'http_200_count' => $http200,
            'zero_offer_count' => $zeroOffers,
            'message_codes' => $messageCodes,
            'message_code_list' => array_map('strval', array_keys($messageCodes)),
            'routes_tested' => (int) ($summary['routes'] ?? 0),
            'days_tested' => (int) ($summary['days'] ?? 0),
            'carriers_tested' => is_array($summary['carriers_tested'] ?? null) ? $summary['carriers_tested'] : [],
        ], now()->addDays(14));
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function storeVariantProbe(int $connectionId, string $variant, array $result): void
    {
        Cache::put(self::VARIANT_PREFIX.$connectionId.':'.strtolower($variant), [
            'variant' => $variant,
            'recorded_at' => now()->toIso8601String(),
            'offer_count_raw' => (int) ($result['offer_count_raw'] ?? 0),
            'normalized_offer_count' => (int) ($result['normalized_offer_count'] ?? 0),
            'message_code' => $result['message_code'] ?? null,
            'no_offer_reason' => $result['no_offer_reason'] ?? null,
            'http_status' => $result['http_status'] ?? null,
        ], now()->addDays(14));
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function storeCarrierProbe(int $connectionId, string $route, string $carrier, array $result): void
    {
        Cache::put(self::CARRIER_PREFIX.$connectionId.':'.strtoupper($route).':'.strtoupper($carrier), [
            'route' => strtoupper($route),
            'carrier' => strtoupper($carrier),
            'recorded_at' => now()->toIso8601String(),
            'offer_count_raw' => (int) ($result['offer_count_raw'] ?? 0),
            'normalized_offer_count' => (int) ($result['normalized_offer_count'] ?? 0),
            'message_code' => $result['message_code'] ?? null,
        ], now()->addDays(14));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastMatrix(int $connectionId): ?array
    {
        $value = Cache::get(self::MATRIX_PREFIX.$connectionId);

        return is_array($value) ? $value : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastVariantProbe(int $connectionId, string $variant): ?array
    {
        $value = Cache::get(self::VARIANT_PREFIX.$connectionId.':'.strtolower($variant));

        return is_array($value) ? $value : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function carrierProbes(int $connectionId): array
    {
        $prefix = self::CARRIER_PREFIX.$connectionId.':';
        $out = [];

        if (! method_exists(Cache::getStore(), 'many')) {
            return $out;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildEvidenceSummary(int $connectionId): array
    {
        $matrix = $this->lastMatrix($connectionId) ?? [];
        $ndcOnly = $this->lastVariantProbe($connectionId, 'ndc_only') ?? [];
        $atpcoOnly = $this->lastVariantProbe($connectionId, 'atpco_only_diagnostic') ?? [];

        $messageCodeList = is_array($matrix['message_code_list'] ?? null)
            ? array_map('strval', $matrix['message_code_list'])
            : array_map('strval', array_keys(is_array($matrix['message_codes'] ?? null) ? $matrix['message_codes'] : []));

        $ndcRaw = (int) ($ndcOnly['offer_count_raw'] ?? 0);
        $atpcoRaw = (int) ($atpcoOnly['offer_count_raw'] ?? 0);

        return [
            'connection_id' => $connectionId,
            'last_matrix_total_cells' => (int) ($matrix['total_cells'] ?? 0),
            'last_matrix_http_200_count' => (int) ($matrix['http_200_count'] ?? 0),
            'last_matrix_zero_offer_count' => (int) ($matrix['zero_offer_count'] ?? 0),
            'last_matrix_message_codes' => array_values($messageCodeList),
            'last_matrix_recorded_at' => $matrix['recorded_at'] ?? null,
            'ndc_only_raw_offer_count' => $ndcRaw,
            'ndc_only_normalized_offer_count' => (int) ($ndcOnly['normalized_offer_count'] ?? 0),
            'atpco_diagnostic_raw_offer_count' => $atpcoRaw,
            'atpco_diagnostic_normalized_offer_count' => (int) ($atpcoOnly['normalized_offer_count'] ?? 0),
            'entitlement_gap_likely' => $ndcRaw === 0 && $atpcoRaw > 0,
            'primary_message_code' => $messageCodeList[0] ?? ($ndcOnly['message_code'] ?? null),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function collectMessageCodes(array $rows): array
    {
        $messageCodes = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $code = trim((string) ($row['message_code'] ?? ''));
            if ($code === '') {
                $legacyText = trim((string) ($row['message_text'] ?? ''));
                if (preg_match('/^\d{3,8}$/', $legacyText) === 1) {
                    $code = $legacyText;
                }
            }
            if ($code !== '') {
                $code = (string) $code;
                $messageCodes[$code] = ($messageCodes[$code] ?? 0) + 1;
            }
        }

        return $messageCodes;
    }
}
