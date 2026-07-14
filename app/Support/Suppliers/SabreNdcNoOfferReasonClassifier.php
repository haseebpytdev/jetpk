<?php

namespace App\Support\Suppliers;

/**
 * Admin/log-only classification for public Sabre NDC no-fare outcomes.
 */
final class SabreNdcNoOfferReasonClassifier
{
    /**
     * @param  array<string, mixed>  $diagnostics
     */
    public static function classify(array $diagnostics): string
    {
        $blockers = is_array($diagnostics['blockers'] ?? null) ? $diagnostics['blockers'] : [];

        if (in_array('ndc_lane_not_selected', $blockers, true)) {
            return 'ndc_search_not_selected';
        }

        if (in_array('search_disabled_by_env', $blockers, true)) {
            return 'ndc_live_search_disabled';
        }

        if ($blockers !== [] && ! in_array('search_disabled_by_env', $blockers, true)) {
            return 'ndc_search_not_selected';
        }

        $httpStatus = (int) ($diagnostics['http_status'] ?? 0);
        $safeFamily = (string) ($diagnostics['safe_error_family'] ?? '');

        if ($httpStatus === 401) {
            return 'ndc_auth_error';
        }

        if ($httpStatus === 403 || $safeFamily === 'auth_or_entitlement') {
            return 'ndc_entitlement_or_permission_error';
        }

        if ($httpStatus === 400 && ($safeFamily === 'request_validation' || ($diagnostics['validation_paths'] ?? []) !== [])) {
            return 'ndc_request_validation_error';
        }

        if ($httpStatus >= 400 && $httpStatus < 600) {
            return 'ndc_http_error';
        }

        $reasonCode = (string) ($diagnostics['reason_code'] ?? '');

        if ($reasonCode === 'sabre_ndc_timeout' || $safeFamily === 'transport_timeout') {
            return 'ndc_http_error';
        }

        if ($reasonCode === 'sabre_ndc_provider_error' || $safeFamily === 'unexpected') {
            return 'ndc_http_error';
        }

        if (self::suggestsEntitlementIssue($diagnostics)) {
            return 'ndc_entitlement_or_permission_error';
        }

        if ($reasonCode === 'sabre_ndc_zero_offers' || ($httpStatus >= 200 && $httpStatus < 300)) {
            return self::classifyZeroOfferOutcome($diagnostics);
        }

        return 'ndc_unknown_empty_response';
    }

    /**
     * @param  array<string, mixed>  $diagnostics
     */
    private static function classifyZeroOfferOutcome(array $diagnostics): string
    {
        $offerCountRaw = (int) ($diagnostics['offer_count_raw'] ?? $diagnostics['offer_count'] ?? 0);
        $normalized = (int) ($diagnostics['normalized_offer_count'] ?? 0);
        $itineraryCount = (int) ($diagnostics['itinerary_count'] ?? 0);
        $pricingCount = (int) ($diagnostics['pricing_information_count'] ?? 0);
        $scheduleCount = (int) ($diagnostics['schedule_desc_count'] ?? 0);
        $responseShape = (string) ($diagnostics['response_shape'] ?? '');

        if ($responseShape === 'unknown' || $responseShape === 'malformed' || $responseShape === 'non_json') {
            return 'ndc_unknown_empty_response';
        }

        if ($offerCountRaw > 0 && $normalized === 0) {
            return 'ndc_parser_zero_offers';
        }

        if (($itineraryCount > 0 || $pricingCount > 0 || $scheduleCount > 0) && $normalized === 0) {
            return 'ndc_parser_zero_offers';
        }

        if ($offerCountRaw === 0 && $itineraryCount === 0 && $pricingCount === 0) {
            return 'ndc_zero_offers';
        }

        return 'ndc_normalizer_rejected_all';
    }

    /**
     * @param  array<string, mixed>  $diagnostics
     */
    private static function suggestsEntitlementIssue(array $diagnostics): bool
    {
        $rows = array_merge(
            is_array($diagnostics['message_rows'] ?? null) ? $diagnostics['message_rows'] : [],
            is_array($diagnostics['error_rows'] ?? null) ? $diagnostics['error_rows'] : [],
            is_array($diagnostics['warning_rows'] ?? null) ? $diagnostics['warning_rows'] : [],
        );

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $code = strtolower((string) ($row['code'] ?? ''));
            $message = strtolower((string) ($row['message'] ?? ''));

            if (str_contains($code, 'permission')
                || str_contains($code, 'entitlement')
                || str_contains($code, 'notauth')
                || (str_contains($code, 'ndc') && (str_contains($code, 'denied') || str_contains($code, 'disabled')))) {
                return true;
            }

            foreach (['not entitled', 'not authorized', 'permission denied', 'no ndc content', 'content not available', 'not enabled for ndc'] as $needle) {
                if ($message !== '' && str_contains($message, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }
}
