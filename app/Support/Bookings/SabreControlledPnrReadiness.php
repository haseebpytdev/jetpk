<?php

namespace App\Support\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\Booking\BookingOperationalPrecheckService;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Support\Platform\PlatformModuleGate;
use App\Support\Sabre\SabreReadinessReasonPresenter;

/**
 * F9/F9B/F9C/F9E: Unified controlled Sabre PNR readiness facade (read-only evaluation).
 *
 * Composes operational, certification, pricing evaluators, and F9B controlled context digest
 * for admin/CLI controlled PNR lane. F9C: usable controlled context requires explicit
 * {@code meta.controlled_pnr_manual_review} approval before manual_review clears. F9E:
 * fare-change gate requires {@code meta.controlled_pnr_fare_change_acceptance} + offer refresh
 * acceptance before controlled retry. Does not perform live Sabre HTTP, ticketing, or cancellation.
 */
final class SabreControlledPnrReadiness
{
    public const BLOCKER_NOT_SABRE = 'not_sabre_booking';

    public const BLOCKER_MISSING_CONNECTION = 'missing_supplier_connection';

    public const BLOCKER_MISSING_REFERENCE = 'missing_booking_reference';

    public const BLOCKER_EXISTING_PNR = 'existing_pnr_present';

    public const BLOCKER_TICKETED = 'ticketed_booking_blocked';

    public const BLOCKER_CANCELLED = 'cancelled_booking_blocked';

    public const BLOCKER_MISSING_PASSENGERS = 'missing_passengers';

    public const BLOCKER_MISSING_PASSENGER_FIELDS = 'missing_required_passenger_fields';

    public const BLOCKER_MISSING_CONTACT = 'missing_contact';

    public const BLOCKER_MISSING_PRICING = 'missing_pricing_context';

    public const BLOCKER_MISSING_REVALIDATION = 'missing_revalidation_context';

    public const BLOCKER_REVALIDATION_UNUSABLE = 'revalidation_empty_or_unusable_response';

    public const BLOCKER_NO_FARE_LINKAGE = 'no_usable_fare_linkage';

    public const BLOCKER_STALE_PRICING = 'stale_pricing';

    public const BLOCKER_REVALIDATION_EXPIRED = 'revalidation_expired';

    public const BLOCKER_OFFER_REFRESH_CONFIRMATION = 'offer_refresh_customer_confirmation_required';

    public const BLOCKER_PRICE_CHANGE_CONFIRMATION = 'price_change_confirmation_required';

    public const BLOCKER_SUPPLIER_MUTATION_DISABLED = 'supplier_mutation_disabled';

    public const BLOCKER_PUBLIC_AUTO_PNR_DISABLED = 'public_auto_pnr_disabled';

    public const BLOCKER_ADMIN_CONFIRMATION = 'admin_confirmation_required';

    public const BLOCKER_MANUAL_REVIEW = 'manual_review_required';

    public const BLOCKER_UNSUPPORTED_ITINERARY = 'unsupported_itinerary';

    public const BLOCKER_MIXED_CARRIER = 'mixed_carrier_interline_blocked';

    public const BLOCKER_NO_GDS_OFFER = 'no_eligible_gds_offer';

    public const BLOCKER_PAYMENT_NOT_VERIFIED = 'payment_not_verified';

    public function __construct(
        protected SabrePnrCertificationSupport $certificationSupport,
        protected SabreOperationalPnrReadiness $operationalReadiness,
        protected SabreBookingService $sabreBookingService,
        protected BookingOperationalPrecheckService $operationalPrecheck,
        protected SabreReadinessReasonPresenter $reasonPresenter,
        protected SabreControlledPnrContextDigest $contextDigest,
        protected SabreControlledPnrManualReviewApproval $manualReviewApproval,
        protected SabreControlledPnrFareChangeAcceptance $fareChangeAcceptance,
    ) {}

    /**
     * @param  array{
     *     context?: string,
     *     admin_confirmation_provided?: bool,
     *     require_admin_confirmation?: bool,
     * }  $options
     * @return array<string, mixed>
     */
    public function evaluate(Booking $booking, array $options = []): array
    {
        $booking->loadMissing(['passengers', 'contact', 'supplierBookings', 'tickets']);

        $context = (string) ($options['context'] ?? 'readiness');
        $adminConfirmationProvided = ($options['admin_confirmation_provided'] ?? false) === true;
        $requireAdminConfirmation = ($options['require_admin_confirmation'] ?? false) === true;

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $isSabre = $this->certificationSupport->isSabreBooking($booking);
        $connectionId = (int) ($meta['supplier_connection_id'] ?? 0);
        $hasExistingPnr = $this->detectExistingPnr($booking);
        $isTicketed = $this->isTicketed($booking);
        $isCancelled = $booking->status === BookingStatus::Cancelled;

        $blockers = [];
        $warnings = [];

        if (! $isSabre) {
            $blockers[] = self::BLOCKER_NOT_SABRE;
        }

        if (trim((string) ($booking->reference_code ?? '')) === '') {
            $blockers[] = self::BLOCKER_MISSING_REFERENCE;
        }

        if ($connectionId <= 0) {
            $blockers[] = self::BLOCKER_MISSING_CONNECTION;
        }

        if ($hasExistingPnr) {
            $blockers[] = self::BLOCKER_EXISTING_PNR;
        }

        if ($isTicketed) {
            $blockers[] = self::BLOCKER_TICKETED;
        }

        if ($isCancelled) {
            $blockers[] = self::BLOCKER_CANCELLED;
        }

        $passengerCount = $booking->passengers->count();
        $hasPassengers = $passengerCount > 0;
        $hasContact = $booking->contact !== null
            && trim((string) ($booking->contact->email ?? '')) !== '';

        if (! $hasPassengers) {
            $blockers[] = self::BLOCKER_MISSING_PASSENGERS;
        } elseif ($isSabre) {
            $passengerErrors = $this->operationalPrecheck->validatePassengerReadiness($booking);
            if ($passengerErrors !== []) {
                $blockers[] = self::BLOCKER_MISSING_PASSENGER_FIELDS;
            }
        }

        if (! $hasContact) {
            $blockers[] = self::BLOCKER_MISSING_CONTACT;
        }

        $pricingReadiness = $isSabre
            ? $this->sabreBookingService->assessAutoPnrPricingContextReadinessForBooking($booking)
            : [];
        $certReadiness = $isSabre ? $this->certificationSupport->buildReadiness($booking) : [];
        $multiDiag = $isSabre
            ? $this->certificationSupport->buildMultiSegmentPnrReadinessDiagnostics($booking)
            : [];

        $hasPricingContext = ($pricingReadiness['auto_pnr_pricing_context_ready'] ?? false) === true;
        $hasStrongLinkage = ($pricingReadiness['has_revalidation_linkage_complete'] ?? false) === true;

        $contextDigest = $isSabre ? $this->contextDigest->classify($booking) : [];
        $hasUsableControlledContext = ($contextDigest['has_usable_controlled_pnr_context'] ?? false) === true;
        $hasRevalidationContext = $hasStrongLinkage || $hasUsableControlledContext;

        if ($hasUsableControlledContext) {
            $warnings[] = 'controlled_certified_context_used';
            if (($contextDigest['has_legacy_success_revalidation_signal'] ?? false) === true) {
                $warnings[] = 'legacy_revalidation_signal_used';
            }
        }

        $controlledBurnInApproved = $hasUsableControlledContext
            && $this->manualReviewApproval->isApproved($meta);

        if ($controlledBurnInApproved) {
            $warnings[] = 'manual_review_approved';
        }

        $fareChangeGateActive = $this->fareChangeAcceptance->fareChangeGateActive($meta);
        $controlledFareChangeAccepted = $this->fareChangeAcceptance->isAccepted($meta);

        if ($controlledFareChangeAccepted) {
            $warnings[] = SabreControlledPnrFareChangeAcceptance::WARNING_CONTROLLED_FARE_CHANGE_ACCEPTED;
        }

        if ($fareChangeGateActive && ! $controlledFareChangeAccepted) {
            $blockers[] = self::BLOCKER_OFFER_REFRESH_CONFIRMATION;
            if (($meta['requires_price_change_confirmation'] ?? false) === true
                || ($meta[SabreOfferRefreshAcceptance::META_PRICE_CHANGED] ?? false) === true) {
                $blockers[] = self::BLOCKER_PRICE_CHANGE_CONFIRMATION;
            }
        }

        if ($isSabre && ! $hasPricingContext) {
            $blockers[] = self::BLOCKER_MISSING_PRICING;
            if ($this->pricingIndicatesNoFareLinkage($pricingReadiness)) {
                $blockers[] = self::BLOCKER_NO_FARE_LINKAGE;
            }
        }

        if ($isSabre && $this->revalidationPolicyRequiresLinkage($pricingReadiness, $certReadiness) && ! $hasRevalidationContext) {
            $blockers[] = self::BLOCKER_MISSING_REVALIDATION;
        }

        if ($isSabre && $this->revalidationResponseUnusable($meta)) {
            $blockers[] = self::BLOCKER_REVALIDATION_UNUSABLE;
        }

        if ($isSabre) {
            foreach ($this->contextDigest->freshnessBlockers($meta) as $freshnessBlocker) {
                $blockers[] = $freshnessBlocker;
            }
        }

        if ($isSabre && ($multiDiag['mixed_carrier'] ?? false) === true) {
            $blockers[] = self::BLOCKER_MIXED_CARRIER;
        }

        if ($isSabre) {
            $tripType = (string) ($certReadiness['trip_type'] ?? $this->certificationSupport->detectTripType($booking));
            $segmentCount = (int) ($certReadiness['segment_count'] ?? 0);
            if ($segmentCount <= 0) {
                $blockers[] = self::BLOCKER_NO_GDS_OFFER;
            } elseif ($tripType === 'multi_city' || $tripType === 'unknown') {
                $blockers[] = self::BLOCKER_UNSUPPORTED_ITINERARY;
            }
        }

        if ($hasUsableControlledContext && ! $controlledBurnInApproved) {
            $blockers[] = self::BLOCKER_MANUAL_REVIEW;
        } elseif ($isSabre && ($multiDiag['admin_pnr_live_action_allowed'] ?? false) !== true) {
            $blockers[] = self::BLOCKER_MANUAL_REVIEW;
        }

        if ($isSabre && ($multiDiag['controlled_pnr_certification_status'] ?? '') === SabreCertifiedRouteSelector::CONTROLLED_PNR_HOST_NOOP_BLOCKED) {
            $blockers[] = self::BLOCKER_MANUAL_REVIEW;
        }

        if (($meta['defer_supplier_booking_to_manual_review'] ?? false) === true && ! $controlledBurnInApproved) {
            $blockers[] = self::BLOCKER_MANUAL_REVIEW;
        }

        if ($this->paymentGateRequired($booking, $meta) && ! $this->paymentVerified($booking)) {
            $blockers[] = self::BLOCKER_PAYMENT_NOT_VERIFIED;
        }

        $mutationFlags = $this->mutationFlagsSnapshot();
        if (! ($mutationFlags['booking_live_call_enabled'] ?? false)) {
            $blockers[] = self::BLOCKER_SUPPLIER_MUTATION_DISABLED;
        }

        if (! ($mutationFlags['supplier_booking_module_enabled'] ?? false)) {
            $blockers[] = self::BLOCKER_SUPPLIER_MUTATION_DISABLED;
        }

        if (! ($mutationFlags['public_auto_pnr_enabled'] ?? false)) {
            $warnings[] = self::BLOCKER_PUBLIC_AUTO_PNR_DISABLED;
        }

        if ($requireAdminConfirmation && ! $adminConfirmationProvided) {
            $blockers[] = self::BLOCKER_ADMIN_CONFIRMATION;
        }

        $blockers = $this->uniqueBlockers($blockers);
        $warnings = $this->uniqueBlockers($warnings);

        $hardBlockers = array_values(array_filter(
            $blockers,
            static fn (string $code): bool => ! in_array($code, [
                self::BLOCKER_SUPPLIER_MUTATION_DISABLED,
                self::BLOCKER_ADMIN_CONFIRMATION,
            ], true),
        ));

        $structurallyReady = $hardBlockers === [];
        $eligible = $structurallyReady;
        if ($requireAdminConfirmation && ! $adminConfirmationProvided) {
            $eligible = false;
        }

        $canAttempt = $structurallyReady
            && ($mutationFlags['supplier_booking_module_enabled'] ?? false)
            && ! in_array(self::BLOCKER_MANUAL_REVIEW, $blockers, true);

        $liveCallBlockers = array_values(array_filter(
            $blockers,
            static fn (string $code): bool => in_array($code, [
                self::BLOCKER_SUPPLIER_MUTATION_DISABLED,
                self::BLOCKER_ADMIN_CONFIRMATION,
            ], true),
        ));

        $liveSupplierCallAllowed = $canAttempt && $liveCallBlockers === [];

        if ($requireAdminConfirmation && ! $adminConfirmationProvided && $canAttempt) {
            $reasonCode = self::BLOCKER_ADMIN_CONFIRMATION;
        } elseif ($requireAdminConfirmation && ! $adminConfirmationProvided && in_array(self::BLOCKER_ADMIN_CONFIRMATION, $blockers, true)) {
            $reasonCode = self::BLOCKER_ADMIN_CONFIRMATION;
        } else {
            $reasonCode = $blockers[0] ?? ($eligible ? 'eligible_controlled_pnr' : 'blocked_ineligible');
        }
        $humanMessage = $this->reasonPresenter->messageForCode($reasonCode);

        $payloadPreviewAvailable = $isSabre && $hasPricingContext && ! $hasExistingPnr;
        $retrieveAfterCreate = $this->canRetrieveAfterCreate($booking);

        return [
            'eligible' => $eligible,
            'can_attempt_supplier_pnr' => $canAttempt,
            'live_supplier_call_allowed' => $liveSupplierCallAllowed,
            'reason_code' => $reasonCode,
            'human_message' => $humanMessage,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'booking_reference' => (string) ($booking->reference_code ?? ''),
            'booking_id' => $booking->id,
            'supplier_connection_present' => $connectionId > 0,
            'supplier_connection_id' => $connectionId > 0 ? $connectionId : null,
            'is_sabre_booking' => $isSabre,
            'is_ticketed' => $isTicketed,
            'is_cancelled' => $isCancelled,
            'has_existing_pnr' => $hasExistingPnr,
            'has_required_passengers' => $hasPassengers,
            'has_required_contact' => $hasContact,
            'has_pricing_context' => $hasPricingContext,
            'has_revalidation_context' => $hasRevalidationContext,
            'has_payment_gate' => $this->paymentGateRequired($booking, $meta),
            'controlled_context_classification' => (string) ($contextDigest['controlled_context_classification'] ?? ''),
            'controlled_context_reason_code' => (string) ($contextDigest['controlled_context_reason_code'] ?? ''),
            'controlled_context_warnings' => is_array($contextDigest['controlled_context_warnings'] ?? null)
                ? array_values($contextDigest['controlled_context_warnings'])
                : [],
            'controlled_context_blockers' => is_array($contextDigest['controlled_context_blockers'] ?? null)
                ? array_values($contextDigest['controlled_context_blockers'])
                : [],
            'has_usable_controlled_pnr_context' => $hasUsableControlledContext,
            'controlled_pnr_manual_review_approved' => $controlledBurnInApproved,
            'controlled_pnr_fare_change_accepted' => $controlledFareChangeAccepted,
            'fare_change_gate_active' => $fareChangeGateActive,
            'admin_pnr_live_action_allowed' => ($multiDiag['admin_pnr_live_action_allowed'] ?? false) === true,
            'pricing_context_ready' => ($multiDiag['pricing_context_ready'] ?? false) === true,
            'controlled_pnr_certification_status' => (string) ($multiDiag['controlled_pnr_certification_status'] ?? ''),
            'multi_segment_blocker_reasons' => is_array($multiDiag['blocker_reasons'] ?? null)
                ? array_values($multiDiag['blocker_reasons'])
                : [],
            'environment_label' => (string) config('app.env', 'production'),
            'mutation_flags_snapshot' => $mutationFlags,
            'payload_preview_available' => $payloadPreviewAvailable,
            'retrieve_after_create_available' => $retrieveAfterCreate,
            'recommended_next_action' => $this->recommendedNextAction([
                'eligible' => $eligible,
                'can_attempt_supplier_pnr' => $canAttempt,
                'live_supplier_call_allowed' => $liveSupplierCallAllowed,
                'has_existing_pnr' => $hasExistingPnr,
                'retrieve_after_create_available' => $retrieveAfterCreate,
                'blockers' => $blockers,
                'warnings' => $warnings,
                'context' => $context,
            ]),
            'ticketing_disabled' => ! (bool) config('suppliers.sabre.ticketing_enabled', false),
            'cancellation_disabled' => ! (bool) config('suppliers.sabre.cancel_enabled', false),
            'live_supplier_call_attempted' => false,
            'pnr_create_attempted' => false,
            'ticketing_attempted' => false,
            'cancellation_attempted' => false,
        ];
    }

    public function detectExistingPnr(Booking $booking): bool
    {
        $booking->loadMissing(['supplierBookings']);

        if (trim((string) ($booking->pnr ?? '')) !== '') {
            return true;
        }

        if (trim((string) ($booking->supplier_reference ?? '')) !== '') {
            return true;
        }

        if (trim((string) ($booking->supplier_api_booking_id ?? '')) !== '') {
            return true;
        }

        return $booking->supplierBookings->contains(
            fn ($item) => in_array((string) $item->status, ['created', 'pending_ticketing', 'ticketed'], true),
        );
    }

    public function canRetrieveAfterCreate(Booking $booking): bool
    {
        if (! $this->detectExistingPnr($booking)) {
            return false;
        }

        if ($this->isTicketed($booking)) {
            return false;
        }

        if (! $this->certificationSupport->isSabreBooking($booking)) {
            return false;
        }

        return PlatformModuleGate::routeEnabled('supplier_booking');
    }

    /**
     * @return array<string, mixed>
     */
    public function mutationFlagsSnapshot(): array
    {
        $publicAutoPnrEnabled = SabreOperationalPnrReadiness::isOperationalAutoPnrEnabled();

        return [
            'booking_enabled' => (bool) config('suppliers.sabre.booking_enabled', false),
            'booking_live_call_enabled' => (bool) config('suppliers.sabre.booking_live_call_enabled', false),
            'ticketing_enabled' => (bool) config('suppliers.sabre.ticketing_enabled', false),
            'cancel_enabled' => (bool) config('suppliers.sabre.cancel_enabled', false),
            'cancel_live_call_enabled' => (bool) config('suppliers.sabre.cancel_live_call_enabled', false),
            'verified_multiseg_auto_pnr_enabled' => (bool) config('suppliers.sabre.verified_multiseg_auto_pnr_enabled', false),
            'cpnr_connecting_same_carrier_gds_enabled' => (bool) config('suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled', false),
            'cpnr_connecting_same_carrier_public_checkout_enabled' => (bool) config('suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled', false),
            'public_auto_pnr_enabled' => $publicAutoPnrEnabled,
            'supplier_booking_module_enabled' => PlatformModuleGate::routeEnabled('supplier_booking'),
            'sabre_gds_module_enabled' => PlatformModuleGate::routeEnabled('sabre_gds'),
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function recommendedNextAction(array $result): string
    {
        if (($result['has_existing_pnr'] ?? false) === true) {
            if (($result['retrieve_after_create_available'] ?? false) === true) {
                return 'PNR exists — use Retrieve/sync PNR itinerary when ready. Ticketing and cancellation remain disabled.';
            }

            return 'PNR exists — manual review only. Do not attempt duplicate PNR creation.';
        }

        if (in_array(self::BLOCKER_ADMIN_CONFIRMATION, (array) ($result['blockers'] ?? []), true)) {
            return 'Run controlled PNR readiness, then use explicit confirmation before any live supplier create.';
        }

        if (in_array(self::BLOCKER_SUPPLIER_MUTATION_DISABLED, (array) ($result['blockers'] ?? []), true)) {
            return 'Supplier mutation is disabled — review readiness only. Enable live booking calls only after ops approval.';
        }

        if (in_array(self::BLOCKER_MISSING_PRICING, (array) ($result['blockers'] ?? []), true)
            || in_array(self::BLOCKER_MISSING_REVALIDATION, (array) ($result['blockers'] ?? []), true)) {
            return 'Prepare supplier PNR context or revalidate pricing before controlled PNR create.';
        }

        if (in_array(self::BLOCKER_MANUAL_REVIEW, (array) ($result['blockers'] ?? []), true)) {
            return 'Record operator approval via sabre:approve-controlled-pnr before controlled PNR create.';
        }

        if (in_array(self::BLOCKER_OFFER_REFRESH_CONFIRMATION, (array) ($result['blockers'] ?? []), true)
            || in_array(self::BLOCKER_PRICE_CHANGE_CONFIRMATION, (array) ($result['blockers'] ?? []), true)) {
            return 'Accept fare change via sabre:accept-controlled-pnr-fare-change before controlled PNR create retry.';
        }

        if (($result['live_supplier_call_allowed'] ?? false) === true) {
            return 'Controlled PNR create may be attempted with explicit admin/command confirmation only.';
        }

        if (($result['can_attempt_supplier_pnr'] ?? false) === true) {
            return 'Booking is structurally ready — live supplier call remains blocked until mutation flags and confirmation pass.';
        }

        return 'Resolve readiness blockers before attempting controlled Sabre PNR creation.';
    }

    protected function isTicketed(Booking $booking): bool
    {
        if ($booking->status === BookingStatus::Ticketed) {
            return true;
        }

        return $booking->supplierBookings->contains(
            fn ($item) => (string) $item->status === 'ticketed',
        ) || $booking->tickets->isNotEmpty();
    }

    /**
     * @param  array<string, mixed>  $pricingReadiness
     */
    protected function pricingIndicatesNoFareLinkage(array $pricingReadiness): bool
    {
        $missing = is_array($pricingReadiness['missing_pricing_context_fields'] ?? null)
            ? $pricingReadiness['missing_pricing_context_fields']
            : [];

        return in_array('offer_snapshot', $missing, true)
            || in_array('itinerary_reference', $missing, true)
            || in_array('pricing_information_ref', $missing, true);
    }

    /**
     * @param  array<string, mixed>  $pricingReadiness
     * @param  array<string, mixed>  $certReadiness
     */
    protected function revalidationPolicyRequiresLinkage(array $pricingReadiness, array $certReadiness): bool
    {
        $policy = $this->certificationSupport->certificationRevalidatePolicy($pricingReadiness, $certReadiness);

        return ($policy['required'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function revalidationResponseUnusable(array $meta): bool
    {
        $status = strtolower(trim((string) ($meta['sabre_revalidation_status'] ?? $meta['revalidation_status'] ?? '')));
        if ($status === '') {
            return false;
        }

        return in_array($status, [
            'failed',
            'empty',
            'unusable',
            'error',
            'sabre_revalidation_empty_or_unusable_response',
        ], true);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function offerPricingStale(array $meta): bool
    {
        $refreshStatus = strtolower(trim((string) ($meta['offer_refresh_status'] ?? '')));
        if ($refreshStatus === 'stale') {
            return true;
        }

        return ($meta['offer_stale'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function paymentGateRequired(Booking $booking, array $meta): bool
    {
        $method = strtolower(trim((string) ($meta['confirmation_method'] ?? $booking->confirmation_method ?? '')));

        return in_array($method, ['pay_now', 'card', 'online_payment'], true);
    }

    protected function paymentVerified(Booking $booking): bool
    {
        return in_array(strtolower(trim((string) ($booking->payment_status ?? ''))), ['paid', 'captured', 'completed'], true);
    }

    /**
     * @param  list<string>  $codes
     * @return list<string>
     */
    protected function uniqueBlockers(array $codes): array
    {
        $out = [];
        foreach ($codes as $code) {
            $normalized = $this->reasonPresenter->normalizeCode((string) $code);
            if ($normalized !== '' && ! in_array($normalized, $out, true)) {
                $out[] = $normalized;
            }
        }

        return $out;
    }
}
