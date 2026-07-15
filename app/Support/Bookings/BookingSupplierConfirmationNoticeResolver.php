<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Services\Suppliers\PiaNdc\PiaNdcOptionPnrService;
use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;

/**
 * Supplier-scoped checkout / confirmation notices — no cross-supplier meta leakage.
 */
final class BookingSupplierConfirmationNoticeResolver
{
    public const SABRE_REFRESH_SUCCESS_NOTICE = 'Fare availability and price were successfully refreshed before reservation creation. No fare changes were detected. Ticketing is pending payment verification.';

    /**
     * @param  array<string, mixed>|null  $checkoutOutcome  Sabre public review dry-run / checkout outcome
     * @return array{scope: string, provider: string, notice: string, reason_code: string|null}|null
     */
    public static function resolveForBooking(Booking $booking, ?array $checkoutOutcome = null, ?string $legacySessionNotice = null): ?array
    {
        return app(SupplierLifecycleRouter::class)->confirmationNotice($booking, $checkoutOutcome, $legacySessionNotice);
    }

    /**
     * @param  array<string, mixed>|null  $checkoutOutcome
     * @return array{notice: string, reason_code: string|null}|null
     */
    public static function resolveSabreGdsNotice(Booking $booking, ?array $checkoutOutcome, ?string $legacySessionNotice): ?array
    {
        if (! app(SupplierLifecycleContextResolver::class)->isHandler($booking, SupplierLifecycleContextResolver::HANDLER_SABRE_GDS)) {
            return null;
        }

        return self::resolveSabreNotice($booking, $checkoutOutcome, $legacySessionNotice);
    }

    /**
     * @param  array<string, mixed>|null  $checkoutOutcome
     * @return array{notice: string, reason_code: string|null}|null
     */
    public static function resolveSabreNdcNotice(Booking $booking, ?array $checkoutOutcome, ?string $legacySessionNotice): ?array
    {
        if (! app(SupplierLifecycleContextResolver::class)->isHandler($booking, SupplierLifecycleContextResolver::HANDLER_SABRE_NDC)) {
            return null;
        }

        if ($legacySessionNotice !== null && trim($legacySessionNotice) !== '' && ! self::looksLikeSabreGdsRevalidationNotice($legacySessionNotice)) {
            return ['notice' => trim($legacySessionNotice), 'reason_code' => null];
        }

        $statusOut = is_array($checkoutOutcome) ? strtolower(trim((string) ($checkoutOutcome['status'] ?? ''))) : '';
        if ($statusOut === 'pending_payment_or_ticketing' || $statusOut === 'created') {
            $orderRef = trim((string) ($checkoutOutcome['provider_booking_id'] ?? $booking->supplier_reference ?? ''));

            return [
                'notice' => $orderRef !== ''
                    ? (string) __('Sabre NDC order created. Order reference: :ref. Servicing remains pending.', ['ref' => $orderRef])
                    : (string) __('Sabre NDC order submitted. Servicing remains pending.'),
                'reason_code' => null,
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $checkoutOutcome
     * @return array{notice: string, reason_code: string|null}|null
     */
    public static function resolveSabreCheckoutSuccessNotice(Booking $booking, ?array $checkoutOutcome = null): ?array
    {
        if (! app(SupplierLifecycleContextResolver::class)->isHandler($booking, SupplierLifecycleContextResolver::HANDLER_SABRE_GDS)) {
            return null;
        }

        if (! ($checkoutOutcome['success'] ?? false)) {
            return null;
        }

        if (self::sabreHasRevalidationSuccessIndicators($booking, $checkoutOutcome)) {
            return [
                'notice' => (string) __(self::SABRE_REFRESH_SUCCESS_NOTICE),
                'reason_code' => null,
            ];
        }

        return self::resolveSabreSkippedRevalidationWarning($booking, $checkoutOutcome);
    }

    public static function sabreHasRevalidationSuccessIndicators(Booking $booking, ?array $checkoutOutcome = null): bool
    {
        if (! app(SupplierLifecycleContextResolver::class)->isHandler($booking, SupplierLifecycleContextResolver::HANDLER_SABRE_GDS)) {
            return false;
        }

        if ($booking->fare_revalidated_at !== null) {
            return true;
        }

        $meta = BookingSupplierProviderResolver::meta($booking);
        $checkoutOutcome = is_array($checkoutOutcome) ? $checkoutOutcome : [];

        $statusCandidates = [
            strtolower(trim((string) ($meta['revalidation_status'] ?? ''))),
            strtolower(trim((string) ($meta['selected_offer_revalidation_status'] ?? ''))),
            strtolower(trim((string) ($meta['offer_refresh_status'] ?? ''))),
        ];

        if (in_array('success', $statusCandidates, true) || in_array('refreshed', $statusCandidates, true)) {
            return true;
        }

        if (($meta['sabre_checkout_outcome']['live_call_attempted'] ?? false) === true
            || ($checkoutOutcome['live_call_attempted'] ?? false) === true) {
            return true;
        }

        if (trim((string) ($booking->pnr ?? '')) !== '' || trim((string) ($checkoutOutcome['pnr'] ?? '')) !== '') {
            return true;
        }

        return false;
    }

    /**
     * Merge selected branded fare into Sabre booking context meta (authoritative selection).
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public static function reconcileSabreBrandedFareMeta(array $meta): array
    {
        if (strtolower(trim((string) ($meta['supplier_provider'] ?? ''))) !== SupplierProvider::Sabre->value) {
            return $meta;
        }

        $channel = strtolower(trim((string) (
            $meta['distribution_channel']
            ?? data_get($meta, 'sabre_booking_context.distribution_channel')
            ?? ''
        )));
        if ($channel === SupplierLifecycleContextResolver::CHANNEL_NDC) {
            return $meta;
        }

        $selectedIntent = is_array($meta['selected_fare_family_option'] ?? null)
            ? $meta['selected_fare_family_option']
            : null;
        if ($selectedIntent === null || $selectedIntent === []) {
            return $meta;
        }

        $builder = app(SabreBookingPayloadBuilder::class);
        $fareKey = trim((string) ($meta['fare_option_key'] ?? ''));
        $sanitized = $builder->sanitizeSelectedFareFamilyForSabreContext(
            $selectedIntent,
            $fareKey !== '' ? $fareKey : null,
        );
        if ($sanitized === []) {
            return $meta;
        }

        $context = is_array($meta['sabre_booking_context'] ?? null) ? $meta['sabre_booking_context'] : [];
        $meta['sabre_booking_context'] = $builder->mergeSelectedFareFamilyIntoSabreBookingContext($context, $sanitized);

        foreach ([
            'selected_fare_family_option' => $selectedIntent,
            'selected_brand_code' => $sanitized['brand_code'] ?? null,
            'selected_brand_name' => $sanitized['brand_name'] ?? ($selectedIntent['name'] ?? null),
            'selected_brand_price' => $sanitized['price_display'] ?? ($selectedIntent['price_display'] ?? null),
            'selected_brand_baggage' => $sanitized['baggage'] ?? ($selectedIntent['baggage_summary'] ?? null),
            'selected_supplier_channel' => $meta['distribution_channel'] ?? data_get($meta, 'flight_offer_snapshot.distribution_channel'),
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => $meta['supplier_connection_id'] ?? null,
        ] as $key => $value) {
            if ($value !== null && $value !== '') {
                $meta[$key] = $value;
            }
        }

        return $meta;
    }

    /**
     * @param  array<string, mixed>|null  $checkoutOutcome
     * @return array{notice: string, reason_code: string|null}|null
     */
    protected static function resolveSabreNotice(Booking $booking, ?array $checkoutOutcome, ?string $legacySessionNotice): ?array
    {
        if (is_array($checkoutOutcome) && ($checkoutOutcome['success'] ?? false)) {
            $parts = [];
            $reasonCode = null;

            if (($checkoutOutcome['prebooking_revalidation_skipped_reason'] ?? '') === 'pnr_only_ticketing_disabled') {
                $parts[] = (string) __(
                    'PNR created using the fare shown from search. That fare is subject to confirmation by staff before ticketing or final payment.'
                );
            } else {
                $revalidationNotice = self::resolveSabreCheckoutSuccessNotice($booking, $checkoutOutcome);
                if ($revalidationNotice !== null) {
                    $parts[] = $revalidationNotice['notice'];
                    $reasonCode = $revalidationNotice['reason_code'] ?? null;
                }
            }

            $statusOut = (string) ($checkoutOutcome['status'] ?? '');
            if ($statusOut === 'dry_run') {
                $schema = strtolower(trim((string) config('suppliers.sabre.booking_schema', '')));
                $parts[] = $schema === 'trip_orders_create_booking'
                    ? (string) __('Sabre Trip Orders booking dry-run prepared. No live PNR attempted.')
                    : (string) __('Sabre booking dry-run prepared.');
            } elseif ($statusOut === 'pending_payment_or_ticketing') {
                $pnrOut = trim((string) ($checkoutOutcome['pnr'] ?? ''));
                $parts[] = $pnrOut !== ''
                    ? (string) __('PNR created. Ticketing remains pending/manual.')
                    : (string) __('Sabre booking reference received. No PNR/locator yet. Ticketing is pending/manual.');
            } elseif ($statusOut === 'needs_review') {
                $parts[] = (string) __('Booking request submitted for staff review. No ticket has been issued.');
            }

            $notice = trim(implode(' ', array_filter($parts, static fn (string $p): bool => $p !== '')));
            if ($notice !== '') {
                return ['notice' => $notice, 'reason_code' => $reasonCode];
            }
        }

        if ($legacySessionNotice !== null && trim($legacySessionNotice) !== '') {
            if (self::sabreHasRevalidationSuccessIndicators($booking, $checkoutOutcome)
                && self::looksLikeSkippedRevalidationWarning($legacySessionNotice)) {
                return [
                    'notice' => (string) __(self::SABRE_REFRESH_SUCCESS_NOTICE),
                    'reason_code' => null,
                ];
            }

            return ['notice' => trim($legacySessionNotice), 'reason_code' => null];
        }

        $meta = BookingSupplierProviderResolver::meta($booking);
        $storedOutcome = is_array($meta['sabre_checkout_outcome'] ?? null) ? $meta['sabre_checkout_outcome'] : null;
        if ($storedOutcome !== null) {
            return self::resolveSabreCheckoutSuccessNotice($booking, $storedOutcome);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $checkoutOutcome
     * @return array{notice: string, reason_code: string|null}|null
     */
    protected static function resolveSabreSkippedRevalidationWarning(Booking $booking, array $checkoutOutcome): ?array
    {
        if (! ($checkoutOutcome['revalidation_skipped_by_config'] ?? false)) {
            return null;
        }

        if (self::sabreHasRevalidationSuccessIndicators($booking, $checkoutOutcome)) {
            return [
                'notice' => (string) __(self::SABRE_REFRESH_SUCCESS_NOTICE),
                'reason_code' => null,
            ];
        }

        $meta = BookingSupplierProviderResolver::meta($booking);
        if (($meta['defer_supplier_booking_to_manual_review'] ?? false) === true
            || ($meta['controlled_pnr_manual_review']['approved'] ?? false) === true) {
            return [
                'notice' => (string) __('Sabre booking proceeded under staff review with revalidation not completed. Reason: admin_override_defer. Ticketing remains disabled.'),
                'reason_code' => 'admin_override_defer',
            ];
        }

        if (($checkoutOutcome['revalidation_bypass_enabled'] ?? false) === true) {
            return [
                'notice' => (string) __('Sabre booking was attempted without completed fare revalidation because revalidation bypass is enabled for this test. Reason: revalidation_bypass_enabled. Ticketing remains disabled.'),
                'reason_code' => 'revalidation_bypass_enabled',
            ];
        }

        if (($checkoutOutcome['live_call_attempted'] ?? false) !== true) {
            return [
                'notice' => (string) __('Sabre booking continued without fare refresh before supplier contact. Reason: revalidation_skipped_without_refresh. Ticketing remains disabled.'),
                'reason_code' => 'revalidation_skipped_without_refresh',
            ];
        }

        return [
            'notice' => (string) __('Sabre booking was attempted without completed fare revalidation because pre-booking revalidation is disabled in configuration. Reason: revalidation_skipped_config_disabled. Ticketing remains disabled.'),
            'reason_code' => 'revalidation_skipped_config_disabled',
        ];
    }

    public static function resolvePiaNdcNotice(Booking $booking, ?string $legacySessionNotice): ?string
    {
        if ($legacySessionNotice !== null && trim($legacySessionNotice) !== '' && ! self::looksLikeSabreNotice($legacySessionNotice)) {
            return trim($legacySessionNotice);
        }

        $meta = BookingSupplierProviderResolver::meta($booking);
        if (($meta['pia_ndc_auto_option_pnr']['status'] ?? '') === 'failed') {
            return PiaNdcOptionPnrService::AUTO_FAILURE_CUSTOMER_NOTICE;
        }

        return null;
    }

    public static function looksLikeSabreNotice(string $notice): bool
    {
        return self::looksLikeSabreGdsRevalidationNotice($notice);
    }

    public static function looksLikeSabreGdsRevalidationNotice(string $notice): bool
    {
        $normalized = strtolower($notice);

        return str_contains($normalized, 'sabre')
            || str_contains($normalized, 'revalidation')
            || str_contains($normalized, 'trip orders')
            || str_contains($normalized, 'passenger records');
    }

    protected static function looksLikeSkippedRevalidationWarning(string $notice): bool
    {
        $normalized = strtolower($notice);

        return str_contains($normalized, 'without completed fare revalidation')
            || str_contains($normalized, 'revalidation bypass')
            || str_contains($normalized, 'pre-booking revalidation is disabled');
    }
}
