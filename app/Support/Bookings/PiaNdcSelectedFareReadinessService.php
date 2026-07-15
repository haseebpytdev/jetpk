<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\FlightSearch\FlightSearchResultStore;
use App\Services\Suppliers\PiaNdc\PiaNdcOfferPriceService;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * R12Q: PIA NDC selected-fare PNR-readiness gate before passenger checkout and on results cards.
 */
final class PiaNdcSelectedFareReadinessService
{
    public const CUSTOMER_UNAVAILABLE_MESSAGE = 'Selected fare is no longer available. Please choose another fare.';

    public const CHECKOUT_PNR_FAILURE_MESSAGE = 'Airline reservation could not be created. Fare is no longer available. Please select another fare.';

    public const META_KEY = 'pia_ndc_selected_fare_readiness';

    public function __construct(
        private readonly FlightSearchResultStore $searchStore,
        private readonly PiaNdcOfferPriceService $offerPriceService,
    ) {}

    /**
     * @param  array<string, mixed>  $offer
     * @return array{
     *     ready: bool,
     *     readiness_status: string,
     *     failed_reason_code: ?string,
     *     selected_option_key: ?string,
     *     fare_type_code: ?string,
     *     offer_item_ref_id: ?string,
     *     payment_time_limit: ?string,
     *     live_offer_price_checked: bool,
     *     diagnostics: array<string, mixed>
     * }
     */
    public function evaluateForCheckout(
        array $offer,
        string $searchId,
        string $offerId,
        ?string $fareOptionKey = null,
        ?array $selectedIntent = null,
        ?SupplierConnection $connection = null,
        bool $runLiveOfferPrice = true,
    ): array {
        $base = $this->evaluateStructural($offer, $searchId, $offerId, $fareOptionKey, $selectedIntent);
        if (! $base['ready']) {
            return $base;
        }

        if (! $runLiveOfferPrice || ! $this->checkoutOfferPriceEnabled()) {
            $base['live_offer_price_checked'] = false;

            return $base;
        }

        $connection ??= $this->resolveConnectionFromOffer($offer);
        if ($connection === null) {
            $base['ready'] = false;
            $base['readiness_status'] = 'not_ready';
            $base['failed_reason_code'] = 'missing_supplier_connection';
            $base['live_offer_price_checked'] = false;

            return $base;
        }

        $ctx = is_array($base['diagnostics']['provider_context'] ?? null)
            ? $base['diagnostics']['provider_context']
            : [];
        $supplierTotal = (float) ($base['diagnostics']['expected_supplier_total'] ?? 0);
        $live = $this->offerPriceService->validateCheckoutAvailability(
            $connection,
            $ctx,
            $supplierTotal > 0 ? $supplierTotal : null,
            $base['fare_type_code'],
            $base['offer_item_ref_id'],
        );

        $base['live_offer_price_checked'] = true;
        $base['diagnostics']['live_offer_price'] = $live['summary'] ?? [];

        if (! ($live['available'] ?? false)) {
            $base['ready'] = false;
            $base['readiness_status'] = 'not_ready';
            $base['failed_reason_code'] = (string) ($live['reason_code'] ?? 'offer_price_unavailable');
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $option
     * @param  array<string, mixed>  $offer
     */
    public function isOptionStructurallyReady(array $option, array $offer): bool
    {
        return $this->evaluateStructuralForOption($option, $offer)['ready'];
    }

    /**
     * @param  array<string, mixed>  $option
     * @param  array<string, mixed>  $offer
     * @return array{
     *     ready: bool,
     *     readiness_status: string,
     *     failed_reason_code: ?string,
     *     selected_option_key: ?string,
     *     fare_type_code: ?string,
     *     offer_item_ref_id: ?string,
     *     payment_time_limit: ?string,
     *     live_offer_price_checked: bool,
     *     diagnostics: array<string, mixed>
     * }
     */
    public function evaluateStructuralForOption(array $option, array $offer): array
    {
        $optionKey = trim((string) ($option['option_key'] ?? ''));
        if (
            ($option['pia_ndc_provider_backed'] ?? false) === true
            && is_array($option['provider_context'] ?? null)
            && $option['provider_context'] !== []
        ) {
            return $this->evaluateResolvedIntent($offer, $option, $optionKey);
        }

        $intent = FlightOfferDisplayPresenter::sanitizeSelectedFareFamilyIntent($option, $offer);
        if ($intent === []) {
            return $this->notReady('provider_context_unresolved', $optionKey);
        }

        return $this->evaluateResolvedIntent($offer, $intent, $optionKey);
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return array{
     *     ready: bool,
     *     readiness_status: string,
     *     failed_reason_code: ?string,
     *     selected_option_key: ?string,
     *     fare_type_code: ?string,
     *     offer_item_ref_id: ?string,
     *     payment_time_limit: ?string,
     *     live_offer_price_checked: bool,
     *     diagnostics: array<string, mixed>
     * }
     */
    public function evaluateStructural(
        array $offer,
        string $searchId = '',
        string $offerId = '',
        ?string $fareOptionKey = null,
        ?array $selectedIntent = null,
    ): array {
        if (! PiaNdcFareFamilyPolicy::appliesToOffer($offer)) {
            return $this->notReady('not_pia_ndc');
        }

        if ($searchId !== '' && $offerId !== '') {
            $cached = $this->searchStore->findOffer($searchId, $offerId);
            if ($cached === null && ! $this->offerIdMatches($offer, $offerId)) {
                return $this->notReady('offer_missing_from_search_cache', $fareOptionKey);
            }
        }

        $resolutionOffer = $offer;
        if ($fareOptionKey !== null && $fareOptionKey !== '') {
            $resolved = FlightOfferDisplayPresenter::findFareFamilyOptionByKey($offer, $fareOptionKey);
            if ($resolved === null) {
                return $this->notReady('fare_option_key_not_found', $fareOptionKey);
            }

            $intent = FlightOfferDisplayPresenter::sanitizeSelectedFareFamilyIntent($resolved, $offer);
            if ($intent === []) {
                return $this->notReady('provider_context_unresolved', $fareOptionKey);
            }

            return $this->evaluateResolvedIntent($offer, $intent, $fareOptionKey);
        }

        if (is_array($selectedIntent) && $selectedIntent !== []) {
            $sanitized = PiaNdcFareFamilyPolicy::sanitizeSelectedIntentForPiaNdc($selectedIntent, $offer);
            if ($sanitized === null) {
                return $this->notReady('selected_intent_not_provider_backed', trim((string) ($selectedIntent['option_key'] ?? '')));
            }

            return $this->evaluateResolvedIntent($offer, $sanitized, trim((string) ($sanitized['option_key'] ?? '')));
        }

        $single = PiaNdcFareFamilyPolicy::buildProviderBackedFareFamilyOption($offer);
        if ($single === null) {
            return $this->notReady('no_provider_backed_option');
        }

        return $this->evaluateResolvedIntent($offer, $single, trim((string) ($single['option_key'] ?? '')));
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function logReadiness(array $result, string $stage): void
    {
        Log::channel('pia-ndc')->info('pia_ndc_selected_fare_readiness', [
            'stage' => $stage,
            'readiness_status' => $result['readiness_status'] ?? null,
            'failed_reason_code' => $result['failed_reason_code'] ?? null,
            'selected_option_key' => $result['selected_option_key'] ?? null,
            'fare_type_code' => $result['fare_type_code'] ?? null,
            'offer_item_ref_id' => $result['offer_item_ref_id'] ?? null,
            'payment_time_limit' => $result['payment_time_limit'] ?? null,
            'live_offer_price_checked' => (bool) ($result['live_offer_price_checked'] ?? false),
        ]);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function persistReadinessMeta(Booking $booking, array $result): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta[self::META_KEY] = [
            'readiness_status' => $result['readiness_status'] ?? null,
            'failed_reason_code' => $result['failed_reason_code'] ?? null,
            'selected_option_key' => $result['selected_option_key'] ?? null,
            'fare_type_code' => $result['fare_type_code'] ?? null,
            'offer_item_ref_id' => $result['offer_item_ref_id'] ?? null,
            'payment_time_limit' => $result['payment_time_limit'] ?? null,
            'live_offer_price_checked' => (bool) ($result['live_offer_price_checked'] ?? false),
            'evaluated_at' => now()->toIso8601String(),
        ];
        $booking->forceFill(['meta' => $meta])->save();
    }

    public function bookingHasActiveOptionPnr(Booking $booking): bool
    {
        $pnr = trim((string) ($booking->pnr ?? ''));
        $supplierRef = trim((string) ($booking->supplier_reference ?? ''));
        $apiId = trim((string) ($booking->supplier_api_booking_id ?? ''));
        if ($pnr === '' && $supplierRef === '' && $apiId === '') {
            return false;
        }

        $status = strtolower(trim((string) ($booking->supplier_booking_status ?? '')));

        return in_array($status, ['option_pnr_created', 'pending_payment_or_ticketing', 'pending_ticketing'], true);
    }

    private function checkoutOfferPriceEnabled(): bool
    {
        return (bool) config('suppliers.pia_ndc.checkout_offer_price_enabled', true);
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    private function offerIdMatches(array $offer, string $offerId): bool
    {
        $needle = trim($offerId);
        if ($needle === '') {
            return false;
        }

        foreach (['id', 'offer_id'] as $key) {
            $candidate = trim((string) ($offer[$key] ?? ''));
            if ($candidate !== '' && hash_equals($candidate, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    private function resolveConnectionFromOffer(array $offer): ?SupplierConnection
    {
        $connectionId = (int) ($offer['supplier_connection_id'] ?? 0);
        if ($connectionId <= 0) {
            return null;
        }

        $connection = SupplierConnection::query()->find($connectionId);
        if ($connection === null || ! $connection->is_active || $connection->provider !== SupplierProvider::PiaNdc) {
            return null;
        }

        return $connection;
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $intent
     * @return array{
     *     ready: bool,
     *     readiness_status: string,
     *     failed_reason_code: ?string,
     *     selected_option_key: ?string,
     *     fare_type_code: ?string,
     *     offer_item_ref_id: ?string,
     *     payment_time_limit: ?string,
     *     live_offer_price_checked: bool,
     *     diagnostics: array<string, mixed>
     * }
     */
    private function evaluateResolvedIntent(array $offer, array $intent, string $optionKey): array
    {
        if (($intent['is_synthetic_default'] ?? false) === true) {
            return $this->notReady('synthetic_branded_option', $optionKey);
        }

        if (($intent['pia_ndc_provider_backed'] ?? false) !== true) {
            return $this->notReady('not_provider_backed', $optionKey);
        }

        $ctx = PiaNdcFareFamilyPolicy::extractProviderContextFromSelected($intent, $offer);
        if ($ctx === []) {
            return $this->notReady('incomplete_provider_context', $optionKey);
        }

        $reason = $this->validateProviderContextFields($ctx, $offer);
        if ($reason !== null) {
            return $this->notReady($reason, $optionKey, $ctx);
        }

        if (! $this->selectedPriceAndFamilyAlign($intent, $ctx, $offer)) {
            return $this->notReady('fare_family_price_mismatch', $optionKey, $ctx);
        }

        $fareType = trim((string) ($ctx['fare_type_code'] ?? ''));
        $itemRef = trim((string) ($ctx['offer_item_ref_id'] ?? ''));
        $paymentTtl = trim((string) ($ctx['payment_time_limit'] ?? ''));
        $supplierTotal = (float) ($intent['price_total'] ?? data_get($offer, 'fare_breakdown.supplier_total', 0));

        return [
            'ready' => true,
            'readiness_status' => 'ready',
            'failed_reason_code' => null,
            'selected_option_key' => $optionKey !== '' ? $optionKey : null,
            'fare_type_code' => $fareType !== '' ? $fareType : null,
            'offer_item_ref_id' => $itemRef !== '' ? $itemRef : null,
            'payment_time_limit' => $paymentTtl !== '' ? $paymentTtl : null,
            'live_offer_price_checked' => false,
            'diagnostics' => [
                'provider_context' => $ctx,
                'expected_supplier_total' => $supplierTotal > 0 ? $supplierTotal : null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $offer
     */
    private function validateProviderContextFields(array $ctx, array $offer): ?string
    {
        if (trim((string) ($ctx['shopping_response_ref_id'] ?? '')) === '') {
            return 'missing_shopping_response_ref_id';
        }
        if (trim((string) ($ctx['offer_ref_id'] ?? '')) === '') {
            return 'missing_offer_ref_id';
        }
        if (trim((string) ($ctx['fare_type_code'] ?? '')) === '') {
            return 'missing_fare_type_code';
        }

        $hasItemRef = trim((string) ($ctx['offer_item_ref_id'] ?? '')) !== ''
            || (is_array($ctx['offer_item_refs'] ?? null) && $ctx['offer_item_refs'] !== []);
        if (! $hasItemRef) {
            return 'missing_offer_item_ref_id';
        }

        if (! $this->hasPaxRefs($ctx)) {
            return 'missing_pax_refs';
        }

        if (! $this->hasJourneyOrSegmentRefs($ctx, $offer)) {
            return 'missing_journey_or_segment_refs';
        }

        $ttl = trim((string) ($ctx['payment_time_limit'] ?? ''));
        if ($ttl === '') {
            return 'missing_payment_time_limit';
        }

        if ($this->paymentTimeLimitExpired($ttl)) {
            return 'payment_time_limit_expired';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function hasPaxRefs(array $ctx): bool
    {
        if (trim((string) ($ctx['pax_ref_id'] ?? '')) !== '') {
            return true;
        }

        $items = is_array($ctx['offer_item_refs'] ?? null) ? $ctx['offer_item_refs'] : [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (trim((string) ($item['pax_ref_id'] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $offer
     */
    private function hasJourneyOrSegmentRefs(array $ctx, array $offer): bool
    {
        $journeyRefs = is_array($ctx['pax_journey_ref_ids'] ?? null) ? $ctx['pax_journey_ref_ids'] : [];
        if ($journeyRefs !== []) {
            return true;
        }

        $segmentRefs = is_array($ctx['pax_segment_ref_ids'] ?? null) ? $ctx['pax_segment_ref_ids'] : [];
        if ($segmentRefs !== []) {
            return true;
        }

        $segments = is_array($offer['segments'] ?? null) ? $offer['segments'] : [];
        foreach ($segments as $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $origin = trim((string) ($segment['origin'] ?? $segment['departure_airport'] ?? ''));
            $destination = trim((string) ($segment['destination'] ?? $segment['arrival_airport'] ?? ''));
            $departure = trim((string) ($segment['departure_at'] ?? $segment['depart_at'] ?? ''));
            if ($origin !== '' && $destination !== '' && $departure !== '') {
                return true;
            }
        }

        return false;
    }

    private function paymentTimeLimitExpired(string $paymentTimeLimit): bool
    {
        try {
            return Carbon::parse($paymentTimeLimit)->isPast();
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * @param  array<string, mixed>  $intent
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $offer
     */
    private function selectedPriceAndFamilyAlign(array $intent, array $ctx, array $offer): bool
    {
        $intentName = trim((string) ($intent['name'] ?? $intent['brand_name'] ?? ''));
        $fareType = trim((string) ($ctx['fare_type_code'] ?? ''));
        if ($intentName !== '' && $fareType !== '' && ! PiaNdcFareFamilyPolicy::labelsMatch($intentName, $fareType)) {
            return false;
        }

        $intentPrice = (float) ($intent['price_total'] ?? 0);
        if ($intentPrice <= 0 && isset($intent['displayed_price']) && is_numeric($intent['displayed_price'])) {
            $intentPrice = (float) (int) $intent['displayed_price'];
        }
        $offerTotal = (float) data_get($offer, 'fare_breakdown.supplier_total', $offer['supplier_total'] ?? 0);
        if ($intentPrice > 0 && $offerTotal > 0 && abs($intentPrice - $offerTotal) > max(10.0, $offerTotal * 0.02)) {
            foreach (PiaNdcFareFamilyPolicy::collectProviderBackedBrandOptions($offer) as $option) {
                $optionCtx = is_array($option['provider_context'] ?? null) ? $option['provider_context'] : [];
                if (PiaNdcFareFamilyPolicy::providerContextsAlign($ctx, $optionCtx)) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>|null  $ctx
     * @return array{
     *     ready: bool,
     *     readiness_status: string,
     *     failed_reason_code: ?string,
     *     selected_option_key: ?string,
     *     fare_type_code: ?string,
     *     offer_item_ref_id: ?string,
     *     payment_time_limit: ?string,
     *     live_offer_price_checked: bool,
     *     diagnostics: array<string, mixed>
     * }
     */
    private function notReady(string $reason, ?string $optionKey = null, ?array $ctx = null): array
    {
        return [
            'ready' => false,
            'readiness_status' => 'not_ready',
            'failed_reason_code' => $reason,
            'selected_option_key' => $optionKey !== null && $optionKey !== '' ? $optionKey : null,
            'fare_type_code' => $ctx !== null ? (trim((string) ($ctx['fare_type_code'] ?? '')) ?: null) : null,
            'offer_item_ref_id' => $ctx !== null ? (trim((string) ($ctx['offer_item_ref_id'] ?? '')) ?: null) : null,
            'payment_time_limit' => $ctx !== null ? (trim((string) ($ctx['payment_time_limit'] ?? '')) ?: null) : null,
            'live_offer_price_checked' => false,
            'diagnostics' => $ctx !== null ? ['provider_context' => $ctx] : [],
        ];
    }
}
