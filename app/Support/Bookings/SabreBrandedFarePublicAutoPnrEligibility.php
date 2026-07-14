<?php

namespace App\Support\Bookings;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Booking\BookingOperationalPrecheckService;
use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use Illuminate\Support\Facades\Log;

/**
 * BF7-H/I: Read-only eligibility gate for same-carrier branded-fare public Auto-PNR.
 * BF7-I persists safe checkout diagnostics in {@see self::META_KEY}; no live Sabre HTTP, no PNR create.
 */
final class SabreBrandedFarePublicAutoPnrEligibility
{
    public const META_KEY = 'sabre_public_auto_pnr_eligibility';

    public const LOG_EVENT = 'sabre_public_auto_pnr_eligibility_evaluated';

    public const REASON_ELIGIBLE = 'eligible';

    public const REASON_ELIGIBLE_PENDING = 'eligible_pending_public_pnr_enablement';

    /** @var list<int> */
    public const BLOCKED_BOOKING_IDS = [43, 46];

    /** @var list<string> */
    public const MANUAL_PAYMENT_METHODS = [
        'pay_later_booking_request',
        'offline_bank_transfer',
        'office_confirmation',
    ];

    /** @var list<string> */
    public const CONDITION_IDS = [
        'auto_pnr_flag_enabled',
        'public_flag_enabled',
        'payment_mode_manual',
        'selected_fare_family_present',
        'brand_code_present',
        'brand_shape_object_content',
        'same_carrier_chain',
        'no_mixed_interline_carrier',
        'no_risky_itinerary_block',
        'passenger_fields_complete',
        'refresh_context_safe',
        'not_blocked_booking_id',
        'cert_safe_environment',
    ];

    /** @var array<string, string> */
    private const CONDITION_REASON_MAP = [
        'auto_pnr_flag_enabled' => 'auto_pnr_flag_disabled',
        'public_flag_enabled' => 'public_flag_disabled',
        'payment_mode_manual' => 'payment_mode_not_manual',
        'selected_fare_family_present' => 'selected_fare_family_missing',
        'brand_code_present' => 'brand_code_missing',
        'brand_shape_object_content' => 'brand_shape_not_object_content',
        'same_carrier_chain' => 'not_same_carrier_connecting',
        'no_mixed_interline_carrier' => 'mixed_or_interline_carrier',
        'no_risky_itinerary_block' => 'risky_itinerary_blocked',
        'passenger_fields_complete' => 'passenger_fields_incomplete',
        'refresh_context_safe' => 'refresh_context_unsafe',
        'not_blocked_booking_id' => 'blocked_booking_id_43_or_46',
        'cert_safe_environment' => 'environment_not_cert_safe',
    ];

    public function __construct(
        protected SabrePnrCertificationSupport $certificationSupport,
        protected SabreSafeRefreshContext $safeRefreshContext,
        protected SabreBookingPayloadBuilder $bookingPayloadBuilder,
        protected BookingOperationalPrecheckService $operationalPrecheck,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function evaluate(Booking $booking): array
    {
        $booking->loadMissing(['passengers', 'contact']);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $ticketingEnabled = (bool) config('suppliers.sabre.ticketing_enabled', false);
        $publicFlagEnabled = (bool) config('suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled', false);
        $autoPnrFlagEnabled = (bool) config('suppliers.sabre.verified_multiseg_auto_pnr_enabled', false);
        $brandShape = $this->bookingPayloadBuilder->resolveActiveAirPriceBrandShapeSelector();
        $paymentMode = $this->resolvePaymentMode($booking, $meta);
        $selectedBrandCode = $this->bookingPayloadBuilder->selectedFareFamilyBrandCodeFromBookingMetaForInspect($meta);

        $readiness = $this->certificationSupport->buildReadiness($booking);
        $carriers = is_array($readiness['carrier_chain'] ?? null) ? $readiness['carrier_chain'] : [];
        if ($carriers === []) {
            $validatingOnly = strtoupper(trim((string) ($readiness['validating_carrier'] ?? '')));
            if ($validatingOnly !== '') {
                $carriers = [$validatingOnly];
            }
        }
        $segmentCount = (int) ($readiness['segment_count'] ?? 0);

        $multiDiag = $this->certificationSupport->buildMultiSegmentPnrReadinessDiagnostics($booking);
        $carrierChain = trim((string) ($multiDiag['marketing_carriers_by_segment'] ?? ''));
        if ($carrierChain === '' || $carrierChain === '—') {
            $carrierChain = implode('→', $carriers);
        }
        $mixedCarrier = ($multiDiag['mixed_carrier'] ?? false) === true;
        $codesharePresent = ($multiDiag['codeshare_present'] ?? false) === true
          || ($readiness['has_codeshare_segment'] ?? false) === true;
        $validatingCarrierMismatch = ($readiness['validating_carrier_mismatch'] ?? false) === true;

        $fareOptionKey = trim((string) ($meta['fare_option_key'] ?? ''));
        $selectedIntent = is_array($meta['selected_fare_family_option'] ?? null)
          ? $meta['selected_fare_family_option']
          : null;
        $sanitizedFareFamily = $this->bookingPayloadBuilder->sanitizeSelectedFareFamilyForSabreContext(
            $selectedIntent,
            $fareOptionKey !== '' ? $fareOptionKey : null,
        );

        $compareGateEnabled = (bool) config('suppliers.sabre.branded_fares_airprice_brand_shape_compare_enabled', false);
        $safeRefreshAssess = $this->safeRefreshContext->assess($meta);
        $passengerErrors = $this->operationalPrecheck->validatePassengerReadiness($booking);

        $riskyItineraryBlocked = $segmentCount >= 2
          && (bool) config('suppliers.sabre.passenger_records_block_risky_itinerary_live', true)
          && ($multiDiag['passenger_records_multi_segment_eligible'] ?? false) !== true;

        $sameCarrierChain = count($carriers) === 1 && ($carriers[0] ?? '') !== '';

        $conditionResults = [
            'auto_pnr_flag_enabled' => $autoPnrFlagEnabled,
            'public_flag_enabled' => $publicFlagEnabled,
            'payment_mode_manual' => in_array($paymentMode, self::MANUAL_PAYMENT_METHODS, true),
            'selected_fare_family_present' => $sanitizedFareFamily !== [],
            'brand_code_present' => $selectedBrandCode !== null && $selectedBrandCode !== '',
            'brand_shape_object_content' => ! $compareGateEnabled
              && $brandShape === SabreBookingPayloadBuilder::DEFAULT_AIRPRICE_BRAND_SHAPE_SELECTOR,
            'same_carrier_chain' => $sameCarrierChain,
            'no_mixed_interline_carrier' => ! $mixedCarrier && ! $validatingCarrierMismatch && ! $codesharePresent,
            'no_risky_itinerary_block' => ! $riskyItineraryBlocked,
            'passenger_fields_complete' => $passengerErrors === [],
            'refresh_context_safe' => ($safeRefreshAssess['safe_refresh_context_present'] ?? false) === true
              && ($safeRefreshAssess['safe_refresh_context_complete'] ?? false) === true,
            'not_blocked_booking_id' => ! in_array((int) $booking->id, self::BLOCKED_BOOKING_IDS, true),
            'cert_safe_environment' => $this->isCertSafeEnvironment($booking),
        ];

        $failedConditions = [];
        foreach (self::CONDITION_IDS as $conditionId) {
            if (($conditionResults[$conditionId] ?? false) !== true) {
                $failedConditions[] = $conditionId;
            }
        }

        $eligible = $failedConditions === [];
        $reasonCode = $eligible
          ? self::REASON_ELIGIBLE
          : (self::CONDITION_REASON_MAP[$failedConditions[0]] ?? 'ineligible');

        return [
            'eligible' => $eligible,
            'reason_code' => $reasonCode,
            'failed_conditions' => $failedConditions,
            'selected_brand_code' => $selectedBrandCode,
            'brand_shape' => $brandShape,
            'carrier_chain' => $carrierChain,
            'payment_mode' => $paymentMode,
            'ticketing_enabled' => $ticketingEnabled,
            'public_flag_enabled' => $publicFlagEnabled,
            'auto_pnr_flag_enabled' => $autoPnrFlagEnabled,
            'booking_id' => (int) $booking->id,
            'live_supplier_call_attempted' => false,
            'condition_results' => $conditionResults,
        ];
    }

    /**
     * @param  array<string, mixed>  $evaluation
     * @return array<string, mixed>
     */
    public function toSafeMetaSummary(array $evaluation): array
    {
        $eligible = ($evaluation['eligible'] ?? false) === true;
        $reasonCode = (string) ($evaluation['reason_code'] ?? '');
        if ($eligible) {
            $reasonCode = self::REASON_ELIGIBLE_PENDING;
        }

        $failed = is_array($evaluation['failed_conditions'] ?? null)
            ? array_values($evaluation['failed_conditions'])
            : [];

        return [
            'eligible' => $eligible,
            'reason_code' => $reasonCode,
            'failed_conditions' => $failed,
            'selected_brand_code' => $evaluation['selected_brand_code'] ?? null,
            'brand_shape' => (string) ($evaluation['brand_shape'] ?? ''),
            'carrier_chain' => (string) ($evaluation['carrier_chain'] ?? ''),
            'payment_mode' => (string) ($evaluation['payment_mode'] ?? ''),
            'ticketing_enabled' => (bool) ($evaluation['ticketing_enabled'] ?? false),
            'public_flag_enabled' => (bool) ($evaluation['public_flag_enabled'] ?? false),
            'auto_pnr_flag_enabled' => (bool) ($evaluation['auto_pnr_flag_enabled'] ?? false),
            'live_supplier_call_attempted' => false,
            'evaluated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Evaluate, persist safe meta summary, and log (no supplier calls).
     *
     * @return array<string, mixed>
     */
    public function persistCheckoutEvaluation(Booking $booking): array
    {
        $evaluation = $this->evaluate($booking);
        $summary = $this->toSafeMetaSummary($evaluation);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta[self::META_KEY] = $summary;
        $booking->forceFill(['meta' => $meta])->save();

        Log::info(self::LOG_EVENT, [
            'event' => self::LOG_EVENT,
            'booking_id' => $booking->id,
            'booking_reference' => $booking->booking_reference,
            'eligible' => $summary['eligible'],
            'reason_code' => $summary['reason_code'],
            'failed_conditions' => $summary['failed_conditions'],
            'selected_brand_code' => $summary['selected_brand_code'],
            'brand_shape' => $summary['brand_shape'],
            'live_supplier_call_attempted' => false,
        ]);

        return $summary;
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

    protected function isCertSafeEnvironment(Booking $booking): bool
    {
        $env = (string) config('app.env', 'production');
        if (in_array($env, ['local', 'testing'], true)) {
            return true;
        }

        if ($env !== 'production') {
            return false;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $connectionId = (int) ($meta['supplier_connection_id'] ?? 0);
        if ($connectionId < 1) {
            return false;
        }

        $connection = SupplierConnection::query()->find($connectionId);
        if ($connection === null) {
            return false;
        }

        $resolvedUrl = SabreInspectGate::resolveSabreBaseUrlForGate($connection);
        if ($resolvedUrl === '' || SabreInspectGate::isProductionLiveSabreHost($resolvedUrl)) {
            return false;
        }

        return SabreInspectGate::isCertSabreHost($resolvedUrl);
    }
}
