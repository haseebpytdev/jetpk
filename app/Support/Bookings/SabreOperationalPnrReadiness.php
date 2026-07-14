<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Services\Booking\BookingOperationalPrecheckService;
use App\Support\Sabre\SabrePnrLaneDiagnostics;
use App\Support\Suppliers\SupplierPnrFlagGate;

/**
 * BF7-J-OPS: Structural operational gate for same-carrier connecting Sabre PNR create.
 * Does not require verified-route evidence — historical evidence is diagnostics-only via {@see SabreVerifiedAutoPnrReadiness}.
 */
final class SabreOperationalPnrReadiness
{
    public const CUSTOMER_FAILURE_NOTICE = 'Booking request received. Our team is reviewing supplier confirmation.';

    public const REASON_ELIGIBLE_OPERATIONAL = 'eligible_operational';

    public const REASON_BLOCKED_BY_FLAGS = 'blocked_by_flags';

    public const REASON_BLOCKED_ALREADY_HAS_PNR = 'blocked_already_has_pnr';

    public const REASON_BLOCKED_ALREADY_HAS_SUPPLIER_REFERENCE = 'blocked_already_has_supplier_reference';

    public const REASON_BLOCKED_SUCCESSFUL_SUPPLIER_BOOKING = 'blocked_successful_supplier_booking';

    public const REASON_BLOCKED_MISSING_REQUIRED_DOCUMENTS = 'blocked_missing_required_documents';

    public const REASON_BLOCKED_MIXED_CARRIER = 'blocked_mixed_carrier';

    public const REASON_BLOCKED_HOST_NOOP = 'blocked_host_noop';

    public const REASON_BLOCKED_NOT_SABRE = 'blocked_not_sabre';

    public const REASON_BLOCKED_TICKETING_ENABLED = 'blocked_ticketing_enabled';

    public const REASON_BLOCKED_PAYMENT_MODE = 'blocked_payment_mode';

    public const REASON_BLOCKED_NO_SUPPLIER_CONNECTION = 'blocked_no_supplier_connection';

    public const REASON_BLOCKED_NOT_SAME_CARRIER_CONNECTING = 'blocked_not_same_carrier_connecting';

    public const REASON_BLOCKED_OFFER_SNAPSHOT_MISSING = 'blocked_offer_snapshot_missing';

    public const REASON_BLOCKED_SABRE_BOOKING_CONTEXT_MISSING = 'blocked_sabre_booking_context_missing';

    public const REASON_BLOCKED_SAFE_REFRESH_INCOMPLETE = 'blocked_safe_refresh_incomplete';

    /** @var list<string> */
    public const CONDITION_IDS = [
        'operational_auto_pnr_enabled',
        'pnr_create_enabled',
        'ticketing_disabled',
        'gds_enabled',
        'payment_mode_manual',
        'provider_is_sabre',
        'supplier_connection_id_present',
        'no_pnr',
        'no_supplier_reference',
        'no_successful_supplier_booking',
        'passenger_fields_complete',
        'not_mixed_carrier',
        'same_carrier_connecting',
        'not_host_noop',
        'offer_snapshot_present',
        'sabre_booking_context_present',
        'safe_refresh_context_complete',
    ];

    /** @var array<string, string> */
    private const CONDITION_REASON_MAP = [
        'operational_auto_pnr_enabled' => self::REASON_BLOCKED_BY_FLAGS,
        'pnr_create_enabled' => self::REASON_BLOCKED_BY_FLAGS,
        'ticketing_disabled' => self::REASON_BLOCKED_TICKETING_ENABLED,
        'gds_enabled' => self::REASON_BLOCKED_BY_FLAGS,
        'payment_mode_manual' => self::REASON_BLOCKED_PAYMENT_MODE,
        'provider_is_sabre' => self::REASON_BLOCKED_NOT_SABRE,
        'supplier_connection_id_present' => self::REASON_BLOCKED_NO_SUPPLIER_CONNECTION,
        'no_pnr' => self::REASON_BLOCKED_ALREADY_HAS_PNR,
        'no_supplier_reference' => self::REASON_BLOCKED_ALREADY_HAS_SUPPLIER_REFERENCE,
        'no_successful_supplier_booking' => self::REASON_BLOCKED_SUCCESSFUL_SUPPLIER_BOOKING,
        'passenger_fields_complete' => self::REASON_BLOCKED_MISSING_REQUIRED_DOCUMENTS,
        'same_carrier_connecting' => self::REASON_BLOCKED_NOT_SAME_CARRIER_CONNECTING,
        'not_mixed_carrier' => self::REASON_BLOCKED_MIXED_CARRIER,
        'not_host_noop' => self::REASON_BLOCKED_HOST_NOOP,
        'offer_snapshot_present' => self::REASON_BLOCKED_OFFER_SNAPSHOT_MISSING,
        'sabre_booking_context_present' => self::REASON_BLOCKED_SABRE_BOOKING_CONTEXT_MISSING,
        'safe_refresh_context_complete' => self::REASON_BLOCKED_SAFE_REFRESH_INCOMPLETE,
    ];

    public function __construct(
        protected SabrePnrCertificationSupport $certificationSupport,
        protected SabreCertifiedRouteSelector $routeSelector,
        protected SabreSafeRefreshContext $safeRefreshContext,
        protected BookingOperationalPrecheckService $operationalPrecheck,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function evaluate(Booking $booking): array
    {
        $booking->loadMissing(['passengers', 'contact', 'supplierBookings']);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        $providerIsSabre = $provider === SupplierProvider::Sabre->value;

        $pnrPresent = trim((string) ($booking->pnr ?? '')) !== '';
        $supplierReferencePresent = trim((string) ($booking->supplier_reference ?? '')) !== '';
        $successfulSupplierBookingPresent = $booking->supplierBookings->contains(
            fn ($item) => in_array((string) $item->status, ['created', 'pending_ticketing', 'ticketed'], true),
        );

        $ticketingEnabled = (bool) config('suppliers.sabre.ticketing_enabled', false);
        $flagGate = app(SupplierPnrFlagGate::class);
        $supplierFlags = $flagGate->sabreFlags();
        $gdsEnabled = SabreCertifiedRouteSelector::isConnectingSameCarrierGdsEnabled();
        $publicCheckoutEnabled = SabreCertifiedRouteSelector::isConnectingSameCarrierPublicCheckoutEnabled();
        $operationalAutoPnrEnabled = self::isOperationalAutoPnrEnabled();
        $pnrCreateEnabled = $flagGate->sabrePnrCreateFeatureEnabled();

        $paymentMode = $this->resolvePaymentMode($booking, $meta);
        $supplierConnectionIdPresent = trim((string) ($meta['supplier_connection_id'] ?? '')) !== '';

        $readiness = $providerIsSabre ? $this->certificationSupport->buildReadiness($booking) : [];
        $multiDiag = $providerIsSabre
            ? $this->certificationSupport->buildMultiSegmentPnrReadinessDiagnostics($booking)
            : [];
        $tripType = $providerIsSabre ? $this->certificationSupport->detectTripType($booking) : 'unknown';
        $segmentCount = (int) ($readiness['segment_count'] ?? 0);
        $carriers = is_array($readiness['carrier_chain'] ?? null) ? $readiness['carrier_chain'] : [];
        if ($carriers === []) {
            $validatingOnly = strtoupper(trim((string) ($readiness['validating_carrier'] ?? '')));
            if ($validatingOnly !== '') {
                $carriers = [$validatingOnly];
            }
        }
        $sameCarrier = count($carriers) === 1 && $segmentCount >= 1;
        $mixedCarrier = ($multiDiag['mixed_carrier'] ?? false) === true;
        $codesharePresent = ($multiDiag['codeshare_present'] ?? false) === true
            || ($readiness['has_codeshare_segment'] ?? false) === true;
        $validatingCarrierMismatch = ($readiness['validating_carrier_mismatch'] ?? false) === true;

        $selection = $providerIsSabre ? $this->routeSelector->selectForBooking($booking) : [];
        $certStatus = (string) (
            $selection['controlled_pnr_certification_status']
            ?? SabreCertifiedRouteSelector::CONTROLLED_PNR_UNKNOWN_CONTROLLED_ONLY
        );

        $safeRefreshAssess = $this->safeRefreshContext->assess($meta);
        $passengerErrors = $this->operationalPrecheck->validatePassengerReadiness($booking);
        $offerSnapshotPresent = $this->resolveOfferSnapshot($meta) !== [];
        $sabreBookingContextPresent = $this->sabreBookingContextPresent($meta);

        $sameCarrierConnecting = ($tripType === 'one_way_connecting'
            && $segmentCount === 2
            && $sameCarrier
            && ! $mixedCarrier
            && ! $codesharePresent
            && ! $validatingCarrierMismatch)
            || ($segmentCount === 1 && $sameCarrier && ! $mixedCarrier && ! $codesharePresent && ! $validatingCarrierMismatch);

        $conditionResults = [
            'operational_auto_pnr_enabled' => $operationalAutoPnrEnabled,
            'public_checkout_pnr_enabled' => $publicCheckoutEnabled,
            'pnr_create_enabled' => $pnrCreateEnabled,
            'ticketing_disabled' => ! $ticketingEnabled,
            'gds_enabled' => $gdsEnabled,
            'payment_mode_manual' => in_array($paymentMode, SabreBrandedFarePublicAutoPnrEligibility::MANUAL_PAYMENT_METHODS, true),
            'provider_is_sabre' => $providerIsSabre,
            'supplier_connection_id_present' => $supplierConnectionIdPresent,
            'no_pnr' => ! $pnrPresent,
            'no_supplier_reference' => ! $supplierReferencePresent,
            'no_successful_supplier_booking' => ! $successfulSupplierBookingPresent,
            'passenger_fields_complete' => $passengerErrors === [],
            'same_carrier_connecting' => $sameCarrierConnecting,
            'not_mixed_carrier' => ! $mixedCarrier && ! $validatingCarrierMismatch && ! $codesharePresent,
            'not_host_noop' => $certStatus !== SabreCertifiedRouteSelector::CONTROLLED_PNR_HOST_NOOP_BLOCKED,
            'offer_snapshot_present' => $offerSnapshotPresent,
            'sabre_booking_context_present' => $sabreBookingContextPresent,
            'safe_refresh_context_complete' => ($safeRefreshAssess['safe_refresh_context_present'] ?? false) === true
                && ($safeRefreshAssess['safe_refresh_context_complete'] ?? false) === true,
        ];

        $blockingConditions = SabrePnrLaneDiagnostics::blockingConditionsFromResults(
            $conditionResults,
            SabrePnrLaneDiagnostics::LANE_OPERATIONAL_AUTO_PNR,
        );

        $wouldAttemptPnr = $blockingConditions === [];
        $reasonCode = $wouldAttemptPnr
            ? self::REASON_ELIGIBLE_OPERATIONAL
            : (self::CONDITION_REASON_MAP[$blockingConditions[0]] ?? 'blocked_ineligible');

        return [
            'would_attempt_pnr' => $wouldAttemptPnr,
            'reason_code' => $reasonCode,
            'blocking_conditions' => $blockingConditions,
            'provider' => $providerIsSabre ? SupplierProvider::Sabre->value : $provider,
            'supplier_connection_id_present' => $supplierConnectionIdPresent,
            'payment_mode' => $paymentMode,
            'ticketing_enabled' => $ticketingEnabled,
            'supplier_pnr_flags' => $supplierFlags,
            'pnr_create_enabled' => $pnrCreateEnabled,
            'same_carrier' => $sameCarrier,
            'mixed_carrier' => $mixedCarrier,
            'pnr_present' => $pnrPresent,
            'supplier_reference_present' => $supplierReferencePresent,
            'successful_supplier_booking_present' => $successfulSupplierBookingPresent,
            'passenger_required_fields_complete' => $passengerErrors === [],
            'document_required_fields_complete' => $passengerErrors === [],
            'sabre_booking_context_present' => $sabreBookingContextPresent,
            'safe_refresh_context_present' => ($safeRefreshAssess['safe_refresh_context_present'] ?? false) === true,
            'public_checkout_pnr_enabled' => $publicCheckoutEnabled,
            'operational_auto_pnr_enabled' => $operationalAutoPnrEnabled,
            'controlled_pnr_certification_status' => $certStatus,
            'segment_count' => $segmentCount,
            'trip_type' => $tripType,
            'condition_results' => $conditionResults,
        ];
    }

    public function wouldAttemptPnr(Booking $booking): bool
    {
        return ($this->evaluate($booking)['would_attempt_pnr'] ?? false) === true;
    }

    /**
     * BF7-J-OPS-FIX1: Legacy {@code defer_supplier_booking_to_manual_review} must not block the operational lane.
     */
    public function bypassesLegacyDeferManualReview(Booking $booking): bool
    {
        if ((bool) config('suppliers.sabre.ticketing_enabled', false)) {
            return false;
        }

        if (! self::isOperationalAutoPnrEnabled()) {
            return false;
        }

        return $this->wouldAttemptPnr($booking);
    }

    public static function isOperationalAutoPnrEnabled(): bool
    {
        return SabreCertifiedRouteSelector::isConnectingSameCarrierGdsEnabled()
            && SabreCertifiedRouteSelector::isConnectingSameCarrierPublicCheckoutEnabled()
            && (bool) config('suppliers.sabre.verified_multiseg_auto_pnr_enabled', false);
    }

    /**
     * Persist safe operational PNR checkout meta (no raw Sabre payloads or PII).
     *
     * @param  array<string, mixed>  $readiness
     */
    public function persistCheckoutMeta(
        Booking $booking,
        array $readiness,
        bool $attempted,
        ?string $result = null,
        ?string $attemptReasonCode = null,
    ): void {
        $meta = is_array($booking->meta) ? $booking->meta : [];

        $meta['operational_pnr_readiness'] = $readiness;
        $meta['operational_auto_pnr_enabled'] = self::isOperationalAutoPnrEnabled();
        $meta['operational_auto_pnr_attempted'] = $attempted;
        if ($result !== null) {
            $meta['operational_auto_pnr_result'] = $result;
        }

        $reasonCode = $attemptReasonCode ?? (string) ($readiness['reason_code'] ?? '');
        if ($result === 'failed' && $attemptReasonCode !== null && $attemptReasonCode !== '') {
            $reasonCode = $attemptReasonCode;
        }
        $meta['operational_auto_pnr_reason_code'] = $reasonCode;

        $booking->forceFill(['meta' => $meta])->save();
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function resolveOfferSnapshot(array $meta): array
    {
        if (is_array($meta['normalized_offer_snapshot'] ?? null)) {
            return $meta['normalized_offer_snapshot'];
        }
        if (is_array($meta['validated_offer_snapshot'] ?? null)) {
            return $meta['validated_offer_snapshot'];
        }
        if (is_array($meta['flight_offer_snapshot'] ?? null)) {
            return $meta['flight_offer_snapshot'];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function sabreBookingContextPresent(array $meta): bool
    {
        if (is_array($meta['sabre_booking_context'] ?? null) && $meta['sabre_booking_context'] !== []) {
            return true;
        }

        $snapshot = $this->resolveOfferSnapshot($meta);
        $raw = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];
        $ctx = is_array($raw['sabre_booking_context'] ?? null) ? $raw['sabre_booking_context'] : [];
        if ($ctx !== []) {
            return true;
        }

        return is_array($snapshot['sabre_booking_context'] ?? null) && $snapshot['sabre_booking_context'] !== [];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function resolvePaymentMode(Booking $booking, array $meta): string
    {
        $fromMeta = trim((string) ($meta['confirmation_method'] ?? $meta['booking_method'] ?? ''));
        if ($fromMeta !== '') {
            return $fromMeta;
        }

        return trim((string) ($booking->confirmation_method ?? ''));
    }
}
