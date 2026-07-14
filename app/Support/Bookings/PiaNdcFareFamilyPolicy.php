<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Support\FlightSearch\OfferBaggageResolver;

/**
 * PIA NDC fare-family safety: only provider-backed offer context may be selected for checkout.
 */
final class PiaNdcFareFamilyPolicy
{
    public static function appliesToOffer(array $offer): bool
    {
        $provider = strtolower(trim((string) ($offer['supplier_provider'] ?? '')));

        return $provider === SupplierProvider::PiaNdc->value;
    }

    public static function appliesToBooking(Booking $booking): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));

        return $provider === SupplierProvider::PiaNdc->value;
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>
     */
    public static function extractProviderContextFromOffer(array $offer): array
    {
        if (is_array($offer['provider_context'] ?? null) && $offer['provider_context'] !== []) {
            return $offer['provider_context'];
        }

        $raw = is_array($offer['raw_payload'] ?? null) ? $offer['raw_payload'] : [];
        if (is_array($raw['provider_context'] ?? null) && $raw['provider_context'] !== []) {
            return $raw['provider_context'];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function hasOrderCreateReadyContext(array $context): bool
    {
        $offerRef = trim((string) ($context['offer_ref_id'] ?? ''));
        $shoppingRef = trim((string) ($context['shopping_response_ref_id'] ?? ''));
        $hasItemRef = trim((string) ($context['offer_item_ref_id'] ?? '')) !== ''
            || (is_array($context['offer_item_refs'] ?? null) && $context['offer_item_refs'] !== []);
        $fareBasis = trim((string) ($context['fare_basis'] ?? ''));
        $rbd = trim((string) ($context['rbd'] ?? ''));
        $fareType = trim((string) ($context['fare_type_code'] ?? ''));

        return $offerRef !== ''
            && $shoppingRef !== ''
            && $hasItemRef
            && ($fareBasis !== '' || $rbd !== '' || $fareType !== '');
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>|null  $offer
     */
    public static function providerFareFamilyLabel(array $context, ?array $offer = null): string
    {
        $fareType = trim((string) ($context['fare_type_code'] ?? ''));
        if ($fareType !== '') {
            return $fareType;
        }

        if (is_array($offer)) {
            $fromOffer = trim((string) ($offer['fare_family'] ?? ''));
            if ($fromOffer !== '') {
                return $fromOffer;
            }
        }

        return 'Standard Fare';
    }

    /**
     * @param  array<string, mixed>  $option
     */
    public static function optionHasOwnProviderContext(array $option): bool
    {
        $ctx = is_array($option['provider_context'] ?? null) ? $option['provider_context'] : [];

        return self::hasOrderCreateReadyContext($ctx);
    }

    /**
     * @param  array<string, mixed>  $option
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>
     */
    public static function extractProviderContextFromOption(array $option, array $offer = []): array
    {
        if (is_array($option['provider_context'] ?? null) && $option['provider_context'] !== []) {
            return $option['provider_context'];
        }

        $sourceOfferId = trim((string) ($option['source_offer_id'] ?? ''));
        if ($sourceOfferId !== '') {
            $members = data_get($offer, 'itinerary_fare_group.members_by_id');
            if (is_array($members) && is_array($members[$sourceOfferId] ?? null)) {
                $member = $members[$sourceOfferId];
                $memberCtx = self::extractProviderContextFromOffer($member);
                if ($memberCtx !== []) {
                    return $memberCtx;
                }
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $selected
     * @param  array<string, mixed>  $validatedSnapshot
     * @return array<string, mixed>
     */
    public static function extractProviderContextFromSelected(array $selected, array $validatedSnapshot = []): array
    {
        if (is_array($selected['provider_context'] ?? null) && $selected['provider_context'] !== []) {
            return $selected['provider_context'];
        }

        $optionKey = trim((string) ($selected['option_key'] ?? ''));
        if ($optionKey !== '' && $validatedSnapshot !== []) {
            foreach (self::collectProviderBackedBrandOptions($validatedSnapshot) as $option) {
                if (trim((string) ($option['option_key'] ?? '')) === $optionKey) {
                    $ctx = is_array($option['provider_context'] ?? null) ? $option['provider_context'] : [];
                    if ($ctx !== []) {
                        return $ctx;
                    }
                }
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    public static function providerContextsAlign(array $a, array $b): bool
    {
        $offerA = trim((string) ($a['offer_ref_id'] ?? ''));
        $offerB = trim((string) ($b['offer_ref_id'] ?? ''));
        $itemA = trim((string) ($a['offer_item_ref_id'] ?? ''));
        $itemB = trim((string) ($b['offer_item_ref_id'] ?? ''));

        if ($offerA === '' || $offerB === '' || $itemA === '' || $itemB === '') {
            return false;
        }

        return hash_equals($offerA, $offerB) && hash_equals($itemA, $itemB);
    }

    /**
     * Provider-backed PIA NDC branded fare options (one per distinct offer context).
     *
     * @param  array<string, mixed>  $offer
     * @return list<array<string, mixed>>
     */
    public static function collectProviderBackedBrandOptions(array $offer, bool $applyDedup = true): array
    {
        if (! self::appliesToOffer($offer)) {
            return [];
        }

        $rawOptions = is_array($offer['fare_family_options'] ?? null) ? $offer['fare_family_options'] : [];
        $selectable = [];
        $seen = [];

        foreach ($rawOptions as $row) {
            if (! is_array($row)) {
                continue;
            }

            $ctx = self::extractProviderContextFromOption($row, $offer);
            if (! self::hasOrderCreateReadyContext($ctx)) {
                continue;
            }

            $dedupeKey = trim((string) ($ctx['offer_ref_id'] ?? '')).'|'.trim((string) ($ctx['offer_item_ref_id'] ?? ''));
            if ($dedupeKey === '|' || isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;

            $built = self::buildProviderBackedFareFamilyOptionFromRow($offer, $row, $ctx);
            if ($built !== null) {
                $selectable[] = $built;
            }
        }

        if ($selectable !== []) {
            usort($selectable, static function (array $a, array $b): int {
                $priceA = (float) ($a['price_total'] ?? 0);
                $priceB = (float) ($b['price_total'] ?? 0);
                if ($priceA > 0 && $priceB > 0 && abs($priceA - $priceB) > 0.01) {
                    return $priceA <=> $priceB;
                }

                return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
            });

            if (! $applyDedup) {
                return PiaNdcBrandedFareDedup::enrichVariantPresentation($selectable);
            }

            $deduped = PiaNdcBrandedFareDedup::dedupeOptions($selectable, $offer, [
                'search_type' => data_get($offer, 'search_criteria.trip_type'),
            ]);

            return $deduped['options'];
        }

        $single = self::buildProviderBackedFareFamilyOption($offer);
        if ($single === null) {
            return [];
        }

        return PiaNdcBrandedFareDedup::enrichVariantPresentation([$single]);
    }

    public static function hasMultipleProviderBackedBrands(array $offer): bool
    {
        return count(self::collectProviderBackedBrandOptions($offer)) >= 2;
    }

    public static function labelsMatch(?string $selected, ?string $provider): bool
    {
        $a = self::normalizeLabel($selected);
        $b = self::normalizeLabel($provider);
        if ($a === '' || $b === '') {
            return false;
        }

        if ($a === $b) {
            return true;
        }

        return str_starts_with($a, $b) || str_starts_with($b, $a);
    }

    /**
     * @param  array<string, mixed>  $option
     * @param  array<string, mixed>  $providerContext
     */
    public static function fareFamilyOptionMatchesProviderContext(array $option, array $providerContext): bool
    {
        if (self::optionHasOwnProviderContext($option)) {
            $optionCtx = is_array($option['provider_context'] ?? null) ? $option['provider_context'] : [];
            $offerRef = trim((string) ($optionCtx['offer_ref_id'] ?? ''));
            $providerRef = trim((string) ($providerContext['offer_ref_id'] ?? ''));

            return $offerRef !== '' && $providerRef !== '' && hash_equals($providerRef, $offerRef);
        }

        $optionName = (string) ($option['name'] ?? $option['brand_name'] ?? '');

        return self::labelsMatch($optionName, self::providerFareFamilyLabel($providerContext))
            || self::labelsMatch((string) ($option['brand_code'] ?? ''), (string) ($providerContext['fare_type_code'] ?? ''));
    }

    /**
     * @param  list<array<string, mixed>>  $options
     * @param  array<string, mixed>  $offer
     * @return list<array<string, mixed>>
     */
    public static function filterSelectableFareFamilyOptions(array $options, array $offer): array
    {
        if (! self::appliesToOffer($offer)) {
            return $options;
        }

        $providerBacked = self::collectProviderBackedBrandOptions($offer);
        if ($providerBacked !== []) {
            return $providerBacked;
        }

        $ctx = self::extractProviderContextFromOffer($offer);
        if (! self::hasOrderCreateReadyContext($ctx)) {
            return [];
        }

        $single = self::buildProviderBackedFareFamilyOption($offer, $ctx);

        return $single !== null ? [$single] : [];
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>|null  $context
     * @return array<string, mixed>|null
     */
    public static function buildProviderBackedFareFamilyOption(array $offer, ?array $context = null): ?array
    {
        $ctx = $context ?? self::extractProviderContextFromOffer($offer);
        if (! self::hasOrderCreateReadyContext($ctx)) {
            return null;
        }

        $name = self::providerFareFamilyLabel($ctx, $offer);
        $supplierTotal = (float) data_get($offer, 'fare_breakdown.supplier_total', $offer['supplier_total'] ?? 0);
        if ($supplierTotal <= 0) {
            $supplierTotal = (float) (($offer['base_fare'] ?? 0) + ($offer['taxes'] ?? 0));
        }

        $currency = strtoupper(trim((string) ($offer['supplier_currency'] ?? $offer['currency'] ?? 'PKR')));

        return self::buildProviderBackedFareFamilyOptionFromRow(
            $offer,
            [
                'name' => $name,
                'brand_name' => $name,
                'price_total' => $supplierTotal > 0 ? $supplierTotal : null,
            ],
            $ctx,
            isDefault: true,
        );
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>|null
     */
    public static function buildProviderBackedFareFamilyOptionFromRow(
        array $offer,
        array $row,
        array $ctx,
        bool $isDefault = false,
    ): ?array {
        if (! self::hasOrderCreateReadyContext($ctx)) {
            return null;
        }

        $name = trim((string) ($row['name'] ?? $row['brand_name'] ?? ''));
        if ($name === '') {
            $name = self::providerFareFamilyLabel($ctx, $offer);
        }

        $supplierTotal = (float) ($row['price_total'] ?? 0);
        if ($supplierTotal <= 0) {
            $supplierTotal = (float) data_get($offer, 'fare_breakdown.supplier_total', $offer['supplier_total'] ?? 0);
        }
        if ($supplierTotal <= 0) {
            $supplierTotal = (float) (($offer['base_fare'] ?? 0) + ($offer['taxes'] ?? 0));
        }

        $currency = strtoupper(trim((string) ($row['currency'] ?? $offer['supplier_currency'] ?? $offer['currency'] ?? 'PKR')));
        $offerRef = trim((string) ($ctx['offer_ref_id'] ?? ''));
        $itemRef = trim((string) ($ctx['offer_item_ref_id'] ?? ''));
        $optionKey = trim((string) ($row['option_key'] ?? ''));
        if ($optionKey === '') {
            $optionKey = 'pia-ndc-brand-'.substr(hash('sha256', $offerRef.$itemRef), 0, 12);
        }

        $displayedPrice = (int) round((float) ($row['displayed_price'] ?? $offer['final_customer_price'] ?? $offer['displayed_price'] ?? $supplierTotal));

        $built = array_filter([
            'name' => $name,
            'brand_name' => $name,
            'brand_code' => trim((string) ($ctx['fare_type_code'] ?? $row['brand_code'] ?? '')),
            'fare_basis' => trim((string) ($ctx['fare_basis'] ?? $row['fare_basis'] ?? '')),
            'booking_class' => trim((string) ($ctx['rbd'] ?? $row['booking_class'] ?? '')),
            'price_total' => $supplierTotal > 0 ? $supplierTotal : null,
            'currency' => $currency !== '' ? $currency : 'PKR',
            'displayed_price' => $displayedPrice > 0 ? $displayedPrice : null,
            'displayed_currency' => 'PKR',
            'price_display' => $displayedPrice > 0 ? 'PKR '.number_format($displayedPrice, 0, '.', ',') : null,
            'option_key' => $optionKey,
            'source_offer_id' => trim((string) ($row['source_offer_id'] ?? '')) ?: null,
            'is_default' => $isDefault,
            'is_synthetic_default' => false,
            'is_grouped_offer_option' => (bool) ($row['is_grouped_offer_option'] ?? false),
            'pia_ndc_provider_backed' => true,
            'provider_context' => $ctx,
            'selectable' => true,
            'display_only' => false,
        ], static fn (mixed $v): bool => $v !== null && $v !== '');

        return self::mergeProviderBackedFareDisplayFields($built, $row, $offer);
    }

    /**
     * Preserve supplier/member baggage and fare-rule fields on provider-backed PIA NDC options.
     *
     * @param  array<string, mixed>  $built
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>
     */
    protected static function mergeProviderBackedFareDisplayFields(array $built, array $row, array $offer): array
    {
        foreach ([
            'carry_on_summary',
            'check_in_summary',
            'baggage_summary',
            'baggage_lines',
            'meal_included',
            'refundable_display',
            'refund_rule',
            'modification_rule',
            'cancellation_rule',
            'cabin',
            'booking_class',
            'details_availability',
        ] as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }
            $value = $row[$key];
            if ($value === null || $value === '') {
                continue;
            }
            $built[$key] = $value;
        }

        $memberOffer = self::resolveMemberOfferForOption($row, $offer);
        $baggageSource = $memberOffer ?? $offer;
        $built = OfferBaggageResolver::enrichFareOptionRow($built, $baggageSource);

        $resolved = OfferBaggageResolver::resolveFromOffer($baggageSource);
        if (empty($built['carry_on_summary']) && ! empty($resolved['cabin'])) {
            $built['carry_on_summary'] = $resolved['cabin'];
        }
        if (empty($built['check_in_summary']) && ! empty($resolved['checked'])) {
            $built['check_in_summary'] = $resolved['checked'];
        }
        if (empty($built['baggage_summary']) && ! empty($resolved['summary'])) {
            $built['baggage_summary'] = $resolved['summary'];
        }

        return $built;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>|null
     */
    protected static function resolveMemberOfferForOption(array $row, array $offer): ?array
    {
        $sourceId = trim((string) ($row['source_offer_id'] ?? ''));
        if ($sourceId === '') {
            return null;
        }

        $members = data_get($offer, 'itinerary_fare_group.members_by_id');
        if (! is_array($members)) {
            return null;
        }

        $member = $members[$sourceId] ?? null;

        return is_array($member) ? $member : null;
    }

    /**
     * @param  array<string, mixed>|null  $selected
     * @param  array<string, mixed>  $validatedSnapshot
     * @return array<string, mixed>|null
     */
    public static function sanitizeSelectedIntentForPiaNdc(?array $selected, array $validatedSnapshot): ?array
    {
        $defaultCtx = is_array($validatedSnapshot['provider_context'] ?? null)
            ? $validatedSnapshot['provider_context']
            : self::extractProviderContextFromOffer($validatedSnapshot);

        if (! self::hasOrderCreateReadyContext($defaultCtx)) {
            return null;
        }

        $providerBacked = self::buildProviderBackedIntentFromContext($defaultCtx, $validatedSnapshot);
        if ($selected === null) {
            return $providerBacked;
        }

        $selectedCtx = self::extractProviderContextFromSelected($selected, $validatedSnapshot);
        if ($selectedCtx !== [] && self::hasOrderCreateReadyContext($selectedCtx)) {
            $matched = null;
            foreach (self::collectProviderBackedBrandOptions($validatedSnapshot) as $option) {
                $optionCtx = is_array($option['provider_context'] ?? null) ? $option['provider_context'] : [];
                if (self::providerContextsAlign($selectedCtx, $optionCtx)) {
                    $matched = $option;
                    break;
                }
            }

            if ($matched === null) {
                return null;
            }

            $intent = array_merge($matched, [
                'option_key' => trim((string) ($selected['option_key'] ?? $matched['option_key'] ?? '')),
                'name' => (string) ($selected['name'] ?? $matched['name'] ?? ''),
                'brand_name' => (string) ($selected['brand_name'] ?? $selected['name'] ?? $matched['brand_name'] ?? ''),
                'pia_ndc_provider_backed' => true,
                'provider_context' => $selectedCtx,
            ]);

            if (isset($selected['displayed_price']) && is_numeric($selected['displayed_price'])) {
                $intent['displayed_price'] = (int) $selected['displayed_price'];
                $intent['price_display'] = 'PKR '.number_format((int) $selected['displayed_price'], 0, '.', ',');
            }

            return $intent;
        }

        $name = (string) ($selected['name'] ?? $selected['brand_name'] ?? '');
        if (! self::labelsMatch($name, self::providerFareFamilyLabel($defaultCtx, $validatedSnapshot))) {
            return null;
        }

        $supplierTotal = (float) data_get($validatedSnapshot, 'fare_breakdown.supplier_total', $validatedSnapshot['supplier_total'] ?? 0);
        if ($supplierTotal > 0) {
            $selected['price_total'] = $supplierTotal;
            $displayed = (int) round((float) ($validatedSnapshot['final_customer_price'] ?? $validatedSnapshot['displayed_price'] ?? $supplierTotal));
            if ($displayed > 0) {
                $selected['displayed_price'] = $displayed;
                $selected['price_display'] = 'PKR '.number_format($displayed, 0, '.', ',');
            }
        }

        $selected['pia_ndc_provider_backed'] = true;
        $selected['fare_basis'] = trim((string) ($defaultCtx['fare_basis'] ?? $selected['fare_basis'] ?? ''));
        $selected['booking_class'] = trim((string) ($defaultCtx['rbd'] ?? $selected['booking_class'] ?? ''));
        $selected['provider_context'] = $defaultCtx;

        return $selected;
    }

    /**
     * @param  array<string, mixed>  $validatedSnapshot
     * @param  array<string, mixed>|null  $selected
     * @return array<string, mixed>
     */
    public static function applySelectedBrandToValidatedSnapshot(array $validatedSnapshot, ?array $selected): array
    {
        if (! self::appliesToOffer($validatedSnapshot) || $selected === null) {
            return $validatedSnapshot;
        }

        $selectedCtx = self::extractProviderContextFromSelected($selected, $validatedSnapshot);
        if (! self::hasOrderCreateReadyContext($selectedCtx)) {
            return $validatedSnapshot;
        }

        $validatedSnapshot['provider_context'] = $selectedCtx;
        $rawPayload = is_array($validatedSnapshot['raw_payload'] ?? null) ? $validatedSnapshot['raw_payload'] : [];
        $rawPayload['provider_context'] = $selectedCtx;
        $validatedSnapshot['raw_payload'] = $rawPayload;

        $brandLabel = trim((string) ($selected['name'] ?? $selected['brand_name'] ?? ''));
        if ($brandLabel !== '') {
            $validatedSnapshot['fare_family'] = $brandLabel;
        }

        $supplierTotal = (float) ($selected['price_total'] ?? 0);
        if ($supplierTotal <= 0 && isset($selected['displayed_price']) && is_numeric($selected['displayed_price'])) {
            $supplierTotal = (float) (int) $selected['displayed_price'];
        }
        if ($supplierTotal > 0) {
            $fareBreakdown = is_array($validatedSnapshot['fare_breakdown'] ?? null) ? $validatedSnapshot['fare_breakdown'] : [];
            $fareBreakdown['supplier_total'] = $supplierTotal;
            $validatedSnapshot['fare_breakdown'] = $fareBreakdown;
            $validatedSnapshot['supplier_total'] = $supplierTotal;
        }

        return $validatedSnapshot;
    }

    public static function selectedIntentMatchesValidatedSnapshot(Booking $booking): bool
    {
        if (! self::appliesToBooking($booking)) {
            return true;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $validated = is_array($meta['validated_offer_snapshot'] ?? null) ? $meta['validated_offer_snapshot'] : [];
        if ($validated === []) {
            return false;
        }

        $ctx = is_array($validated['provider_context'] ?? null)
            ? $validated['provider_context']
            : self::extractProviderContextFromOffer($validated);
        if (! self::hasOrderCreateReadyContext($ctx)) {
            return false;
        }

        $selected = is_array($meta['selected_fare_family_option'] ?? null)
            ? $meta['selected_fare_family_option']
            : null;
        if ($selected === null) {
            return true;
        }

        $selectedCtx = self::extractProviderContextFromSelected($selected, $validated);
        if ($selectedCtx !== [] && self::hasOrderCreateReadyContext($selectedCtx)) {
            return self::providerContextsAlign($selectedCtx, $ctx);
        }

        return self::labelsMatch(
            (string) ($selected['name'] ?? $selected['brand_name'] ?? ''),
            self::providerFareFamilyLabel($ctx, $validated),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function brandAlignmentDiagnostic(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $validated = is_array($meta['validated_offer_snapshot'] ?? null) ? $meta['validated_offer_snapshot'] : [];
        $selected = is_array($meta['selected_fare_family_option'] ?? null) ? $meta['selected_fare_family_option'] : [];
        $validatedCtx = is_array($validated['provider_context'] ?? null)
            ? $validated['provider_context']
            : self::extractProviderContextFromOffer($validated);
        $selectedCtx = self::extractProviderContextFromSelected($selected, $validated);

        return [
            'selected_brand' => $selected['name'] ?? null,
            'selected_offer_ref_id' => $selectedCtx['offer_ref_id'] ?? null,
            'selected_offer_item_ref_id' => $selectedCtx['offer_item_ref_id'] ?? null,
            'validated_fare_type_code' => $validatedCtx['fare_type_code'] ?? null,
            'validated_offer_ref_id' => $validatedCtx['offer_ref_id'] ?? null,
            'validated_offer_item_ref_id' => $validatedCtx['offer_item_ref_id'] ?? null,
            'aligned' => self::selectedIntentMatchesValidatedSnapshot($booking),
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $validatedSnapshot
     * @return array<string, mixed>
     */
    public static function buildProviderBackedIntentFromContext(array $ctx, array $validatedSnapshot): array
    {
        $option = self::buildProviderBackedFareFamilyOption($validatedSnapshot, $ctx);

        return $option ?? [
            'name' => self::providerFareFamilyLabel($ctx, $validatedSnapshot),
            'brand_name' => self::providerFareFamilyLabel($ctx, $validatedSnapshot),
            'brand_code' => trim((string) ($ctx['fare_type_code'] ?? '')),
            'fare_basis' => trim((string) ($ctx['fare_basis'] ?? '')),
            'booking_class' => trim((string) ($ctx['rbd'] ?? '')),
            'pia_ndc_provider_backed' => true,
            'provider_context' => $ctx,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public static function reconcileBookingMeta(array $meta): array
    {
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? '')));
        if ($provider !== SupplierProvider::PiaNdc->value) {
            return $meta;
        }

        $validated = is_array($meta['validated_offer_snapshot'] ?? null) ? $meta['validated_offer_snapshot'] : [];
        if ($validated === []) {
            return $meta;
        }

        $selected = is_array($meta['selected_fare_family_option'] ?? null)
            ? $meta['selected_fare_family_option']
            : null;
        $sanitized = self::sanitizeSelectedIntentForPiaNdc($selected, $validated);
        if ($sanitized === null) {
            if ($selected !== null) {
                return $meta;
            }

            $sanitized = self::sanitizeSelectedIntentForPiaNdc(null, $validated);
        }

        if ($sanitized === null) {
            return $meta;
        }

        $meta['selected_fare_family_option'] = $sanitized;
        $meta['validated_offer_snapshot'] = self::applySelectedBrandToValidatedSnapshot($validated, $sanitized);

        $supplierTotal = (float) data_get($meta['validated_offer_snapshot'], 'fare_breakdown.supplier_total', $validated['supplier_total'] ?? 0);
        if ($supplierTotal > 0) {
            $meta['selected_fare_total'] = $supplierTotal;
            $meta['revalidated_fare_total'] = $supplierTotal;
        }

        return $meta;
    }

    private static function normalizeLabel(?string $label): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim((string) $label)) ?? '');
    }
}
