<?php

namespace App\Support\FlightSearch;

/**
 * Safe, non-mutating audit of selected fare context for results → checkout handoff.
 * Never logs raw fare keys, provider payloads, or credentials.
 */
class SelectedFareContextAuditor
{
    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $criteria
     * @param  array<string, mixed>|null  $draftIntent
     * @return array<string, mixed>
     */
    public static function buildReport(
        array $offer,
        string $searchId,
        string $offerId,
        string $fareOptionKey,
        array $criteria = [],
        ?array $draftIntent = null,
    ): array {
        $provider = strtolower(trim((string) ($offer['supplier_provider'] ?? $offer['provider'] ?? '')));
        $resolutionOffer = $offer;
        $resolved = $fareOptionKey !== ''
            ? FlightOfferDisplayPresenter::findFareFamilyOptionByKey($resolutionOffer, $fareOptionKey)
            : null;

        $selection = $fareOptionKey !== ''
            ? FlightOfferDisplayPresenter::applySelectedFareFamilyOptionToOffer($resolutionOffer, $fareOptionKey)
            : ['offer' => $offer, 'resolved' => null, 'error_code' => null, 'error_message' => null];

        $option = is_array($resolved) ? $resolved : null;
        $resolvedRow = is_array($option) ? $option : [];
        $intent = is_array($draftIntent) && $draftIntent !== []
            ? $draftIntent
            : ($resolvedRow !== []
                ? FlightOfferDisplayPresenter::sanitizeSelectedFareFamilyIntent($resolvedRow, $resolutionOffer)
                : []);

        $snapshotAudit = self::auditBookingSnapshotContext($offer, $fareOptionKey, $intent);
        $selectionResolved = $resolvedRow !== [] || ($snapshotAudit['selection_resolved'] ?? false);
        $selectionErrorCode = $selection['error_code'] ?? null;
        if ($selectionResolved) {
            $selectionErrorCode = null;
        }

        $sourceOfferId = trim((string) ($resolvedRow['source_offer_id'] ?? $intent['source_offer_id'] ?? ''));
        $passengerCounts = is_array($offer['fare_breakdown']['passenger_counts'] ?? null)
            ? $offer['fare_breakdown']['passenger_counts']
            : [];

        $confirmedTotal = is_numeric($offer['fare_breakdown']['supplier_total'] ?? null)
            ? (float) $offer['fare_breakdown']['supplier_total']
            : (is_numeric($offer['final_customer_price'] ?? null) ? (float) $offer['final_customer_price'] : null);

        $selectedPrice = isset($intent['displayed_price']) && is_numeric($intent['displayed_price'])
            ? (int) $intent['displayed_price']
            : (isset($resolvedRow['price_total']) && is_numeric($resolvedRow['price_total'])
                ? (int) round((float) $resolvedRow['price_total'])
                : (isset($intent['price_total']) && is_numeric($intent['price_total'])
                    ? (int) round((float) $intent['price_total'])
                    : null));

        return array_merge([
            'provider' => $provider !== '' ? $provider : null,
            'offer_id' => $offerId !== '' ? $offerId : trim((string) ($offer['offer_id'] ?? $offer['id'] ?? '')),
            'search_id' => $searchId !== '' ? $searchId : null,
            'selected_fare_option_id' => FlightOfferDisplayPresenter::safeFareOptionKeyForLog($fareOptionKey),
            'fare_option_key_present' => $fareOptionKey !== '',
            'source_offer_id' => $sourceOfferId !== '' ? $sourceOfferId : null,
            'is_grouped_offer_option' => (bool) ($resolvedRow['is_grouped_offer_option'] ?? false),
            'fare_option_name' => trim((string) ($intent['name'] ?? $intent['brand_name'] ?? $intent['fare_family_label'] ?? $resolvedRow['name'] ?? '')) ?: null,
            'selected_price_display' => trim((string) ($intent['price_display'] ?? '')) ?: null,
            'selected_price_amount' => $selectedPrice,
            'confirmed_total' => $confirmedTotal,
            'checked_baggage' => BaggageDisplayNormalizer::normalizeLabel(
                (string) ($intent['baggage_summary'] ?? $intent['baggage'] ?? $intent['check_in_summary'] ?? $resolvedRow['check_in_summary'] ?? $resolvedRow['baggage_summary'] ?? '')
            ),
            'cabin_baggage' => BaggageDisplayNormalizer::normalizeLabel(
                (string) ($intent['carry_on_summary'] ?? $resolvedRow['carry_on_summary'] ?? '')
            ),
            'refund_summary' => trim((string) ($resolvedRow['refund_summary'] ?? $offer['refund_summary'] ?? '')) ?: null,
            'change_summary' => trim((string) ($resolvedRow['change_summary'] ?? $offer['change_summary'] ?? '')) ?: null,
            'fare_policy_source' => trim((string) ($offer['fare_policy_source'] ?? $offer['customer_display_fields']['fare_policy_source'] ?? '')) ?: null,
            'passenger_count' => [
                'adults' => (int) ($passengerCounts['adults'] ?? $criteria['adults'] ?? 1),
                'children' => (int) ($passengerCounts['children'] ?? $criteria['children'] ?? 0),
                'infants' => (int) ($passengerCounts['infants'] ?? $criteria['infants'] ?? 0),
            ],
            'currency' => trim((string) ($offer['pricing_currency'] ?? $offer['currency'] ?? $offer['fare_breakdown']['currency'] ?? 'PKR')) ?: 'PKR',
            'selection_error_code' => $selectionErrorCode,
            'selection_active' => FlightOfferDisplayPresenter::brandedFaresSelectionActiveForOffer($offer),
            'revalidation_required' => $provider === 'iati' || $provider === 'sabre',
            'supplier_mutation_attempted' => false,
        ], $snapshotAudit, [
            'selection_resolved' => $selectionResolved || ($snapshotAudit['selection_resolved'] ?? false),
        ]);
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $intent
     * @return array<string, mixed>
     */
    protected static function auditBookingSnapshotContext(array $offer, string $fareOptionKey, array $intent): array
    {
        $defaults = [
            'snapshot_context_present' => false,
            'snapshot_context_valid' => false,
            'selection_resolution_source' => null,
            'live_search_resolution' => $fareOptionKey !== '' ? 'attempted' : 'not_requested',
        ];

        if ($intent === []) {
            return $defaults;
        }

        $raw = is_array($offer['raw_payload'] ?? null) ? $offer['raw_payload'] : [];
        $providerContext = is_array($raw['provider_context'] ?? null) ? $raw['provider_context'] : [];
        $departureFareKeyPresent = trim((string) ($providerContext['departure_fare_key'] ?? '')) !== ''
            || trim((string) ($intent['departure_fare_key'] ?? '')) !== '';
        $snapshotPresent = $departureFareKeyPresent || $intent !== [];

        if (! $snapshotPresent) {
            return array_merge($defaults, [
                'live_search_resolution' => 'not_available',
            ]);
        }

        $intentKey = trim((string) ($intent['option_key'] ?? $intent['id'] ?? ''));
        $effectiveKey = $fareOptionKey !== '' ? $fareOptionKey : $intentKey;
        $contextSelected = trim((string) ($providerContext['selected_fare_option_id'] ?? $providerContext['selected_branded_fare_id'] ?? ''));
        $keyMatches = $effectiveKey === ''
            || $intentKey === ''
            || $effectiveKey === $intentKey
            || ($contextSelected !== '' && ($effectiveKey === $contextSelected || $intentKey === $contextSelected));

        $confirmedTotal = is_numeric($offer['fare_breakdown']['supplier_total'] ?? null)
            ? (float) $offer['fare_breakdown']['supplier_total']
            : null;
        $intentTotal = isset($intent['displayed_price']) && is_numeric($intent['displayed_price'])
            ? (float) $intent['displayed_price']
            : (isset($intent['price_total']) && is_numeric($intent['price_total']) ? (float) $intent['price_total'] : null);
        $priceMatches = $confirmedTotal === null
            || $intentTotal === null
            || abs($confirmedTotal - $intentTotal) <= 0.01;

        $snapshotValid = $departureFareKeyPresent && $keyMatches && $priceMatches && $intentKey !== '';
        $selectionResolved = $snapshotValid || ($departureFareKeyPresent && $priceMatches && $intentKey !== '' && $effectiveKey !== '');

        return [
            'snapshot_context_present' => true,
            'snapshot_context_valid' => $snapshotValid,
            'selection_resolution_source' => $selectionResolved ? 'booking_snapshot' : null,
            'live_search_resolution' => 'not_available',
            'selection_resolved' => $selectionResolved,
        ];
    }
}
