<?php

namespace App\Services\Suppliers\Sabre\Diagnostics;

use App\Services\Suppliers\Sabre\Gds\SabreRevalidationPayloadBuilder;

/**
 * Sprint 11K-J: Diagnostic-only comparison of revalidation payload styles (no live Sabre HTTP).
 */
final class SabreRevalidationPayloadStyleComparator
{
    public const BASELINE_STYLE = 'bfm_revalidate_v1';

    public const IATI_LIKE_STYLE = 'iati_like_bfm_revalidate_v1';

    public const PRICING_CONTEXT_STYLE = 'bfm_revalidate_with_pricing_context';

    /** @var list<string> Sprint 11K-L launch probe (two styles only). */
    public const LAUNCH_PROBE_STYLES = [
        self::BASELINE_STYLE,
        self::PRICING_CONTEXT_STYLE,
    ];

    /** @var list<string> */
    private const BASELINE_LINKAGE_PROBE_KEYS = [
        'has_price_request_information',
        'has_vendor_pref',
        'has_origin_destination_information',
        'selected_offer_context_present',
        'itinerary_ref_present',
        'booking_classes_present',
        'fare_basis_present',
        'validating_carrier_present',
    ];

    /** @var list<string> */
    public const DEFAULT_COMPARE_STYLES = [
        self::BASELINE_STYLE,
        self::IATI_LIKE_STYLE,
        self::PRICING_CONTEXT_STYLE,
    ];

    /** @var list<string> */
    private const COVERAGE_BOOLEAN_KEYS = [
        'has_pos',
        'has_pcc',
        'has_data_sources',
        'has_request_type',
        'has_50itins',
        'has_seats_requested',
        'has_price_request_information',
        'has_vendor_pref',
        'has_origin_destination_information',
        'selected_offer_context_present',
        'pricing_context_present',
    ];

    public function __construct(
        protected SabreRevalidationPayloadBuilder $builder,
    ) {}

    /**
     * @param  array<string, mixed>  $internalDraft
     * @param  list<string>|null  $styles
     * @return array<string, mixed>
     */
    public function compareForDraft(array $internalDraft, ?array $styles = null): array
    {
        $styles = $styles ?? self::DEFAULT_COMPARE_STYLES;
        $summaries = [];
        foreach ($styles as $style) {
            $payload = $this->builder->buildPayload($internalDraft, $style);
            $summaries[$style] = $this->builder->normalizedPayloadCoverageSummary($payload);
        }

        $baseline = is_array($summaries[self::BASELINE_STYLE] ?? null) ? $summaries[self::BASELINE_STYLE] : [];
        $iati = is_array($summaries[self::IATI_LIKE_STYLE] ?? null) ? $summaries[self::IATI_LIKE_STYLE] : [];
        $pricing = is_array($summaries[self::PRICING_CONTEXT_STYLE] ?? null) ? $summaries[self::PRICING_CONTEXT_STYLE] : [];

        return [
            'report_version' => 'sabre_revalidate_payload_coverage_compare_v1',
            'active_config_style' => (string) config('suppliers.sabre.revalidate_payload_style', self::BASELINE_STYLE),
            'production_default_unchanged' => true,
            'recommended_production_default' => self::BASELINE_STYLE,
            'styles' => $summaries,
            'iati_stronger_than_baseline_fields' => $this->fieldsStrongerThanBaseline($iati, $baseline),
            'baseline_stronger_than_iati_fields' => $this->fieldsStrongerThanBaseline($baseline, $iati),
            'pricing_context_stronger_than_baseline_fields' => $this->fieldsStrongerThanBaseline($pricing, $baseline),
            'iati_segment_count_delta' => ($iati['segment_count'] ?? 0) - ($baseline['segment_count'] ?? 0),
            'safe_to_enable_iati_like_via_config' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<string, mixed>  $reference
     * @return list<string>
     */
    protected function fieldsStrongerThanBaseline(array $candidate, array $reference): array
    {
        $stronger = [];
        foreach (self::COVERAGE_BOOLEAN_KEYS as $key) {
            if (($candidate[$key] ?? false) === true && ($reference[$key] ?? false) !== true) {
                $stronger[] = $key;
            }
        }

        return $stronger;
    }

    /**
     * Sprint 11K-L: Compare only launch candidate styles (no live HTTP).
     *
     * @param  array<string, mixed>  $internalDraft
     * @return array<string, mixed>
     */
    public function compareLaunchStylesForDraft(array $internalDraft): array
    {
        $rows = [];
        foreach (self::LAUNCH_PROBE_STYLES as $style) {
            $payload = $this->builder->buildPayload($internalDraft, $style);
            $rows[$style] = $this->builder->launchStyleProbeSummary($payload);
        }

        $baseline = is_array($rows[self::BASELINE_STYLE] ?? null) ? $rows[self::BASELINE_STYLE] : [];
        $pricing = is_array($rows[self::PRICING_CONTEXT_STYLE] ?? null) ? $rows[self::PRICING_CONTEXT_STYLE] : [];
        $baselinePreserved = $this->baselineLinkagePreservedInPricingStyle($baseline, $pricing);
        $pricingAdds = $this->pricingStyleAddsBeyondBaseline($baseline, $pricing);

        return [
            'report_version' => 'sabre_revalidate_style_probe_v1',
            'active_config_style' => (string) config('suppliers.sabre.revalidate_payload_style', self::BASELINE_STYLE),
            'production_default_unchanged' => true,
            'recommended_production_default' => self::BASELINE_STYLE,
            'styles' => $rows,
            'pricing_context_adds_vs_baseline' => $pricingAdds,
            'baseline_linkage_preserved_in_pricing_style' => $baselinePreserved,
            'launch_recommendation' => $this->resolveLaunchRecommendation($baselinePreserved, $pricing),
        ];
    }

    /**
     * @param  array<string, mixed>  $baseline
     * @param  array<string, mixed>  $pricing
     * @return list<string>
     */
    protected function pricingStyleAddsBeyondBaseline(array $baseline, array $pricing): array
    {
        $adds = [];
        foreach ([
            'pricing_context_present',
            'pricing_information_index_present',
            'leg_refs_present',
            'schedule_refs_present',
        ] as $key) {
            if (($pricing[$key] ?? false) === true && ($baseline[$key] ?? false) !== true) {
                $adds[] = $key;
            }
        }
        $baselineDigest = (string) ($baseline['payload_coverage_summary'] ?? '');
        $pricingDigest = (string) ($pricing['payload_coverage_summary'] ?? '');
        if ($pricingDigest !== $baselineDigest && str_contains($pricingDigest, 'recon_pricing')) {
            $adds[] = 'reconstructed_pricing_context';
        }

        return array_values(array_unique($adds));
    }

    /**
     * @param  array<string, mixed>  $baseline
     * @param  array<string, mixed>  $pricing
     */
    protected function baselineLinkagePreservedInPricingStyle(array $baseline, array $pricing): bool
    {
        foreach (self::BASELINE_LINKAGE_PROBE_KEYS as $key) {
            if (($baseline[$key] ?? false) === true && ($pricing[$key] ?? false) !== true) {
                return false;
            }
        }

        return ($pricing['pricing_context_present'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $pricing
     */
    protected function resolveLaunchRecommendation(bool $baselinePreserved, array $pricing): string
    {
        if (! $baselinePreserved) {
            return 'keep_bfm_revalidate_v1';
        }
        if (($pricing['pricing_context_present'] ?? false) === true) {
            return 'prepare_env_flip_after_cert_http_approval';
        }

        return 'keep_bfm_revalidate_v1';
    }
}
