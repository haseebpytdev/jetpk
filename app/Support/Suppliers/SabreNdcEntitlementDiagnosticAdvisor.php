<?php

namespace App\Support\Suppliers;

/**
 * Suggested next action for Sabre NDC entitlement / zero-offer diagnostics (admin/CLI only).
 */
final class SabreNdcEntitlementDiagnosticAdvisor
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function suggest(array $context): string
    {
        $messageCodes = is_array($context['last_matrix_message_codes'] ?? null)
            ? $context['last_matrix_message_codes']
            : [];
        $zeroOffers = (int) ($context['last_matrix_zero_offer_count'] ?? 0);
        $http200 = (int) ($context['last_matrix_http_200_count'] ?? 0);
        $cells = (int) ($context['last_matrix_total_cells'] ?? 0);
        $ndcEnabled = (bool) ($context['ndc_live_search_http_enabled'] ?? false);
        $blockers = is_array($context['blockers'] ?? null) ? $context['blockers'] : [];

        if ($blockers !== []) {
            return 'Resolve NDC lane blockers before live entitlement testing: '.implode(', ', $blockers).'.';
        }

        if (! $ndcEnabled) {
            return 'Enable SABRE_NDC_SEARCH_ENABLED for live HTTP probes, then re-run sabre:ndc-search-market-matrix.';
        }

        if ($cells === 0) {
            return 'Run sabre:ndc-search-market-matrix with --send to capture route/date entitlement evidence.';
        }

        if ($http200 > 0 && $zeroOffers === $cells && (bool) ($context['entitlement_gap_likely'] ?? false)) {
            return 'NDC-only shop returns zero offers while ATPCO diagnostic returns offers on the same PCC/route — ask Sabre to confirm NDC PCC activation and per-airline NDC content entitlements for tested markets.';
        }

        if ($http200 > 0 && $zeroOffers === $cells && isset($messageCodes['27131'])) {
            return 'Sabre message 27131 across all matrix cells usually means no shop results for the tested criteria — verify PCC NDC airline entitlements with Sabre, then retry with --carriers per airline and diagnostic variants ndc_only vs atpco_only_diagnostic.';
        }

        if ($http200 > 0 && $zeroOffers === $cells) {
            return 'HTTP 200 with zero offers across all matrix cells — compare ndc_only vs atpco_only_diagnostic variants and per-carrier filters; if ATPCO returns offers but NDC does not, suspect NDC content/entitlement gap.';
        }

        if ($zeroOffers > 0 && $zeroOffers < $cells) {
            return 'Mixed matrix results — narrow to routes/dates/carriers that returned offers and compare request variants.';
        }

        return 'NDC matrix shows offers on some cells — continue with targeted dry-runs before changing public search defaults.';
    }
}
