<?php

namespace App\Support\Bookings;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Services\Booking\BookingOperationalPrecheckService;
use App\Services\Suppliers\Sabre\Cancel\SabreGdsCancelReadiness;
use App\Services\Suppliers\Sabre\SabreTripOrdersGetBookingItineraryMapper;
use App\Services\Suppliers\Sabre\Ticketing\SabreGdsTicketingReadiness;
use App\Support\Platform\PlatformModuleEnforcer;
use App\Support\Sabre\SabreCapabilityPosture;
use App\Support\Sabre\SabreReadinessReasonPresenter;
use Illuminate\Support\Str;

/**
 * Admin booking detail: supplier PNR / retry / ticketing action safety (B82).
 * UI + POST guards only — no Sabre payload or ticketing adapter changes.
 */
class AdminBookingSupplierActions
{
    public const RETRY_COOLDOWN_MINUTES = 5;

    public function __construct(
        protected BookingOperationalPrecheckService $operationalPrecheckService,
        protected SabrePnrCertificationSupport $sabrePnrCertificationSupport,
        protected PlatformModuleEnforcer $platformModuleEnforcer,
        protected SupplierBookingAttemptGuard $attemptGuard,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Booking $booking, bool $genericSupplierEligible, bool $genericTicketingEligible): array
    {
        $booking->loadMissing([
            'passengers',
            'contact',
            'supplierBookings',
            'supplierBookingAttempts',
            'tickets',
        ]);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $syncSidecarEarly = is_array($meta['pnr_itinerary_sync'] ?? null) ? $meta['pnr_itinerary_sync'] : [];
        $pnrItinerarySyncStatusEarly = trim((string) ($syncSidecarEarly['status'] ?? ''));
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        $isSabre = $provider === SupplierProvider::Sabre->value;
        $isSabreGds = $isSabre
            && app(SupplierLifecycleContextResolver::class)->isHandler($booking, SupplierLifecycleContextResolver::HANDLER_SABRE_GDS);
        $pnrCancelledOrReleased = $isSabreGds
            && app(SabreGdsPnrCancellationStateResolver::class)->isPnrCancelledOrReleased($booking, $meta);
        $pnrItinerarySyncedCanonical = $isSabreGds
            && app(SabreGdsPnrItinerarySyncResolver::class)->isSynced($booking, $meta);
        $isPiaNdc = $provider === SupplierProvider::PiaNdc->value;
        $isIati = $provider === SupplierProvider::Iati->value;
        $iatiContext = is_array($meta['iati_context'] ?? null) ? $meta['iati_context'] : [];
        $iatiMode = (string) ($iatiContext['mode'] ?? '');

        $pnr = trim((string) ($booking->pnr ?? ''));
        $supplierReference = trim((string) ($booking->supplier_reference ?? ''));
        $hasSupplierBookingRecord = $booking->supplierBookings->contains(
            fn ($item) => in_array((string) $item->status, ['created', 'pending_ticketing', 'ticketed'], true)
        );
        $hasIatiDeferredBook = $isIati
            && ($iatiMode === 'deferred_book' || $booking->supplierBookings->contains(
                fn ($item) => (string) $item->status === 'direct_book_required',
            ));
        $hasPnrOrReference = $pnr !== '' || $supplierReference !== '' || $hasSupplierBookingRecord;

        $latestAttempt = SupplierBookingAttemptResolution::resolveLatestMeaningfulCreateAttempt(
            $booking->supplierBookingAttempts,
        ) ?? $booking->supplierBookingAttempts->sortByDesc('created_at')->first();
        $safeSummary = is_array($latestAttempt?->safe_summary) ? $latestAttempt->safe_summary : [];
        $errorCode = (string) ($latestAttempt?->error_code ?? '');

        $staleSegment = $errorCode === 'sabre_passenger_records_stale_shop_segment';
        $staffReview = $errorCode === 'sabre_booking_application_error';
        $complexItineraryDeferred = $isSabre
            && ComplexItineraryPolicy::isComplex($booking)
            && ! ComplexItineraryPolicy::complexItineraryPnrEnabled();
        $offerRefreshRequiresAcceptance = $isSabre && SabreOfferRefreshAcceptance::requiresAcceptance($booking);
        $offerRefreshDiagnostics = $isSabre
            ? app(ControlledStaffOfferRefreshDiagnostics::class)->panelFromSafeSummary($safeSummary, $errorCode)
            : null;
        $rateLimitState = $this->resolveRateLimitState($latestAttempt, $safeSummary, $errorCode);

        $attemptOutcomeClassifiable = $latestAttempt !== null && $this->latestAttemptIsRetryable($latestAttempt);
        $pnrFailure = $isSabre
            ? SabrePnrFailureClassifier::classify($errorCode !== '' ? $errorCode : null, $safeSummary)
            : [
                'classification' => '',
                'next_action' => '',
                'retry_allowed' => true,
                'admin_message' => '',
                'customer_message' => '',
            ];
        $pnrFailureBlocksRetry = $isSabre
            && $attemptOutcomeClassifiable
            && ! ($pnrFailure['retry_allowed'] ?? false);
        if ($pnrFailureBlocksRetry && in_array($pnrFailure['classification'], [
            SabrePnrFailureClassifier::CLASSIFICATION_BOOKING_CLASS_MISMATCH,
            SabrePnrFailureClassifier::CLASSIFICATION_STALE_OR_MISSING_INVENTORY,
        ], true)) {
            $staleSegment = true;
        }
        if (($offerRefreshDiagnostics['recommended_staff_action'] ?? '') === ControlledStaffOfferRefreshDiagnostics::ACTION_FRESH_SEARCH) {
            $staleSegment = true;
            $staffReview = false;
        }
        if ($pnrFailureBlocksRetry && in_array($pnrFailure['classification'], [
            SabrePnrFailureClassifier::CLASSIFICATION_HOST_SELL_REJECTED_UC,
            SabrePnrFailureClassifier::CLASSIFICATION_NO_FARES_RBD_CARRIER,
            SabrePnrFailureClassifier::CLASSIFICATION_FARE_RBD_CARRIER_NOT_SELLABLE,
            SabrePnrFailureClassifier::CLASSIFICATION_PNR_REQUIRES_MANUAL_SABRE_PRICING,
            SabrePnrFailureClassifier::CLASSIFICATION_REVALIDATION_LINKAGE_INCOMPLETE,
            SabrePnrFailureClassifier::CLASSIFICATION_PROVIDER_APPLICATION_ERROR,
            SabrePnrFailureClassifier::CLASSIFICATION_HOST_AIR_BOOKING_NOOP,
            SabrePnrFailureClassifier::CLASSIFICATION_HOST_INVENTORY_OR_CERT_LIMITATION,
            SabrePnrFailureClassifier::CLASSIFICATION_UNKNOWN_STAFF_REVIEW,
        ], true)) {
            $staffReview = true;
        }

        $ticketIssued = $booking->tickets->isNotEmpty()
            || in_array((string) ($booking->ticketing_status ?? ''), ['ticketed', 'issued'], true);
        $paymentPaid = (string) ($booking->payment_status ?? 'unpaid') === 'paid';
        $liveTicketingSupported = $this->providerSupportsLiveTicketing($provider);
        $ticketingEnvEnabled = ! $isSabre || (bool) config('suppliers.sabre.ticketing_enabled', false);
        $ticketingDisabled = $isSabre && ! $ticketingEnvEnabled;
        $distributionChannel = $this->platformModuleEnforcer->distributionChannelFromBookingMeta($meta);
        $ticketingModuleBlock = $this->platformModuleEnforcer->ticketingBlockedMessage($provider !== '' ? $provider : null, $distributionChannel);
        $ticketingModuleEnabled = $ticketingModuleBlock === null;
        $providerModuleEnabled = $provider === ''
            || $this->platformModuleEnforcer->providerChannelEnabled($provider, $distributionChannel);

        $sabreCreateEligible = $isSabre && $this->sabrePassengerRecordsCreateEligible($booking, $meta);
        $connectingCert = $isSabre ? $this->connectingSameCarrierCertificationContext($booking) : [];
        $certifiedRouteDeferOnly = $isSabre
            && in_array($errorCode, [
                SabreCertifiedRouteSelector::ERROR_CODE_PENDING,
                SabreCertifiedRouteSelector::ERROR_CODE_NOT_CERTIFIED,
            ], true)
            && ($safeSummary['live_call_attempted'] ?? false) !== true;
        if (($connectingCert['admin_pnr_live_action_allowed'] ?? false) === true && $certifiedRouteDeferOnly) {
            $pnrFailureBlocksRetry = false;
            $staffReview = false;
        }
        $controlledOfferValidationFailure = $isSabre
            && SabrePnrFailureClassifier::isControlledStaffOfferValidationRetryable(
                $errorCode !== '' ? $errorCode : null,
                $safeSummary,
            );
        $controlledHostNoopDiagnosticFailure = $isSabre
            && SabrePnrFailureClassifier::isControlledStaffHostNoopDiagnosticRetryable(
                $errorCode !== '' ? $errorCode : null,
                $safeSummary,
            );
        $controlledOfferFreshnessRetryable = $controlledOfferValidationFailure
            && ! $hasPnrOrReference
            && $ticketingDisabled
            && ($connectingCert['admin_pnr_live_action_allowed'] ?? false) === true;
        $controlledHostNoopDiagnosticRetryable = $controlledHostNoopDiagnosticFailure
            && ! $hasPnrOrReference
            && $ticketingDisabled
            && ($connectingCert['admin_pnr_live_action_allowed'] ?? false) === true
            && $this->controlledStaffSafeRefreshOrOfferFreshnessReady($meta, $sabreCreateEligible);
        $controlledInitialCreateEligible = $latestAttempt === null
            && $this->controlledInitialCreateEligible(
                $booking,
                $meta,
                $connectingCert,
                $hasPnrOrReference,
                $ticketingDisabled,
                $sabreCreateEligible,
            );
        $controlledInitialCreateBlocked = $latestAttempt === null
            && $this->controlledInitialCreateCandidate($meta, $connectingCert)
            && ! $controlledInitialCreateEligible;
        if ($controlledOfferFreshnessRetryable) {
            $pnrFailureBlocksRetry = false;
            $staffReview = false;
            $pnrFailure['retry_allowed'] = true;
            $pnrFailure['classification'] = SabrePnrFailureClassifier::CLASSIFICATION_OFFER_FRESHNESS_RETRYABLE;
            $pnrFailure['next_action'] = SabrePnrFailureClassifier::NEXT_ACTION_RETRY_AFTER_OFFER_REFRESH;
            $pnrFailure['admin_message'] = 'Retry will refresh the Sabre offer before PNR creation.';
        } elseif ($controlledHostNoopDiagnosticRetryable) {
            $pnrFailureBlocksRetry = false;
            $staffReview = false;
            $pnrFailure['retry_allowed'] = true;
            $pnrFailure['classification'] = SabrePnrFailureClassifier::CLASSIFICATION_HOST_AIR_BOOKING_NOOP;
            $pnrFailure['next_action'] = SabrePnrFailureClassifier::NEXT_ACTION_DIAGNOSTIC_RETRY_AFTER_OFFER_REFRESH;
            $pnrFailure['admin_message'] = SabrePnrFailureClassifier::hostAirBookingNoopAdminMessage($safeSummary);
        } elseif ($controlledInitialCreateEligible) {
            $pnrFailureBlocksRetry = false;
            $staffReview = false;
            $pnrFailure['retry_allowed'] = true;
            $pnrFailure['classification'] = '';
            $pnrFailure['next_action'] = 'controlled_initial_create';
            $pnrFailure['admin_message'] = '';
        } elseif ($controlledOfferValidationFailure) {
            $pnrFailureBlocksRetry = true;
            $pnrFailure['retry_allowed'] = false;
            if (($pnrFailure['classification'] ?? '') === SabrePnrFailureClassifier::CLASSIFICATION_OFFER_FRESHNESS_RETRYABLE) {
                $pnrFailure['classification'] = SabrePnrFailureClassifier::CLASSIFICATION_UNKNOWN_STAFF_REVIEW;
                $pnrFailure['next_action'] = 'manual_staff_confirmation';
                $pnrFailure['admin_message'] = 'Supplier PNR failed — staff review required.';
            }
            $staffReview = true;
        } elseif ($controlledHostNoopDiagnosticFailure) {
            $pnrFailureBlocksRetry = true;
            $pnrFailure['retry_allowed'] = false;
            $staffReview = true;
        }
        $controlledConnectingPnrBlocked = $isSabre
            && ($connectingCert['connecting_same_carrier_candidate'] ?? false) === true
            && ($connectingCert['admin_staff_pnr_retry_route_allowed'] ?? false) === true
            && ($connectingCert['admin_staff_pnr_readiness_passed'] ?? $connectingCert['admin_staff_pnr_retry_allowed'] ?? false) !== true;
        $controlledPnrHostNoopBlocked = $isSabre
            && ($connectingCert['controlled_pnr_certification_status'] ?? '') === SabreCertifiedRouteSelector::CONTROLLED_PNR_HOST_NOOP_BLOCKED;
        $verifiedAutoPnrTerminalFareRbd = $isSabre && $this->verifiedAutoPnrTerminalFareRbdBlocked($meta);
        $operationalPnrReadiness = $isSabre
            ? app(SabreOperationalPnrReadiness::class)->evaluate($booking)
            : null;
        $operationalPnrEligible = $isSabre
            && ($operationalPnrReadiness['would_attempt_pnr'] ?? false) === true;
        if ($operationalPnrEligible) {
            $verifiedAutoPnrTerminalFareRbd = false;
            $controlledConnectingPnrBlocked = false;
            if (! $hasPnrOrReference && $ticketingDisabled && $sabreCreateEligible) {
                $staffReview = false;
                $pnrFailureBlocksRetry = false;
                $pnrFailure['retry_allowed'] = true;
                $controlledInitialCreateBlocked = false;
            }
        }
        $canPrepareSupplierContext = $isSabre
            && ($connectingCert['context_refresh_available'] ?? false) === true
            && ! $hasPnrOrReference;
        $nonSabreCreateEligible = ! $isSabre && $genericSupplierEligible && ! $hasSupplierBookingRecord;
        if ($isPiaNdc) {
            $nonSabreCreateEligible = false;
        }
        $iatiSupplierReadiness = $isIati
            ? IatiSupplierBookingEligibility::evaluate($booking, false)
            : null;
        if ($isIati && ($iatiSupplierReadiness['eligible'] ?? false) && ! $hasSupplierBookingRecord) {
            $nonSabreCreateEligible = true;
        }

        $blockCreateRetry = $hasPnrOrReference
            || $staleSegment
            || $staffReview
            || $complexItineraryDeferred
            || $offerRefreshRequiresAcceptance
            || $pnrFailureBlocksRetry
            || $controlledConnectingPnrBlocked
            || $controlledPnrHostNoopBlocked
            || $verifiedAutoPnrTerminalFareRbd
            || $controlledInitialCreateBlocked
            || $rateLimitState['in_cooldown'];

        $hasRetryableFailedAttempt = $latestAttempt !== null
            && $this->latestAttemptIsRetryable($latestAttempt)
            && $rateLimitState['cooldown_elapsed'];

        $basePnrEligible = ($sabreCreateEligible || $nonSabreCreateEligible) && ($isSabre || $paymentPaid);

        $canCreatePnr = ! $blockCreateRetry
            && $basePnrEligible
            && ! $hasRetryableFailedAttempt;

        $canRetryPnr = ! $blockCreateRetry
            && $basePnrEligible
            && $hasRetryableFailedAttempt;

        if ($hasPnrOrReference) {
            $staffReview = false;
            $staleSegment = false;
            $pnrFailure = [
                'classification' => SabrePnrFailureClassifier::CLASSIFICATION_SUPPLIER_PNR_BOOKED,
                'next_action' => 'supplier_pnr_workflow',
                'retry_allowed' => false,
                'admin_message' => '',
                'customer_message' => '',
                'retry_blocker_reasons' => [],
            ];
            $pnrFailureBlocksRetry = false;
            $canCreatePnr = false;
            $canRetryPnr = false;
        } elseif ($hasIatiDeferredBook) {
            $canCreatePnr = false;
            $canRetryPnr = false;
        }

        $piaNdcManual = $isPiaNdc
            ? app(AdminBookingSupplierActionGate::class)->piaNdcManualTicketing($booking, $genericTicketingEligible)
            : null;

        $sabreGdsTicketing = $isSabre
            ? app(AdminSabreGdsTicketingPanelsPresenter::class)->gdsTicketingPanel($booking)
            : ['show' => false];
        $sabreGdsCancel = $isSabreGds
            ? app(AdminSabreGdsCancelPanelsPresenter::class)->gdsCancelPanel($booking)
            : ['show' => false];
        $sabreGdsIssueReady = $isSabre
            && ($sabreGdsTicketing['show'] ?? false)
            && ($sabreGdsTicketing['action_state'] ?? '') === SabreGdsTicketingReadiness::ACTION_ISSUE_TICKET
            && ($sabreGdsTicketing['can_execute'] ?? false)
            && ! $pnrCancelledOrReleased;

        $canIssueTicketAction = $ticketingModuleEnabled
            && $providerModuleEnabled
            && $paymentPaid
            && ($pnr !== '' || $supplierReference !== '' || $hasIatiDeferredBook)
            && ! $ticketIssued
            && ($isPiaNdc
                ? (bool) ($piaNdcManual['can_manual_issue'] ?? false)
                : $genericTicketingEligible);

        $canIssueTicketLive = $canIssueTicketAction
            && $liveTicketingSupported
            && $ticketingEnvEnabled;

        if ($sabreGdsIssueReady && $paymentPaid && ! $ticketIssued && $hasPnrOrReference) {
            $canIssueTicketAction = true;
            $canIssueTicketLive = true;
        }

        if ($pnrCancelledOrReleased) {
            $canIssueTicketAction = false;
            $canIssueTicketLive = false;
            $sabreGdsIssueReady = false;
        }

        $canReleaseSabreGdsPnr = $isSabreGds
            && $hasPnrOrReference
            && ! $ticketIssued
            && ! $pnrCancelledOrReleased
            && ($sabreGdsCancel['can_execute'] ?? false)
            && ($sabreGdsCancel['action_state'] ?? '') === SabreGdsCancelReadiness::ACTION_CANCEL_SABRE_PNR;

        $supplierStatusMessage = $isIati
            ? $this->iatiSupplierStatusMessage(
                $hasPnrOrReference,
                $hasIatiDeferredBook,
                $pnr,
                $supplierReference,
                $ticketIssued,
                $iatiMode,
                trim((string) ($iatiContext['last_sync_status'] ?? '')),
            )
            : $this->supplierStatusMessage(
                $hasPnrOrReference,
                $pnr,
                $supplierReference,
                $ticketIssued,
                $ticketingDisabled,
                $liveTicketingSupported,
                $pnrItinerarySyncStatusEarly,
                $pnrCancelledOrReleased,
                $pnrItinerarySyncedCanonical,
            );

        $primary = $this->resolvePrimaryCta(
            $hasPnrOrReference,
            $paymentPaid,
            $ticketIssued,
            $staleSegment,
            $staffReview,
            $rateLimitState,
            $canCreatePnr,
            $canRetryPnr,
            $canIssueTicketLive,
            $ticketingDisabled,
            $liveTicketingSupported,
            (string) ($pnrFailure['classification'] ?? ''),
            $sabreGdsIssueReady,
            $pnrCancelledOrReleased,
        );

        $pnrSnapshot = $meta['pnr_itinerary_snapshot'] ?? null;
        $hasPnrItinerarySnapshot = is_array($pnrSnapshot)
            && is_array($pnrSnapshot['segments'] ?? null)
            && $pnrSnapshot['segments'] !== [];
        $syncSidecar = is_array($meta['pnr_itinerary_sync'] ?? null) ? $meta['pnr_itinerary_sync'] : [];
        $pnrItinerarySyncStatus = trim((string) ($syncSidecar['status'] ?? ''));
        $pnrItinerarySyncedAt = trim((string) ($syncSidecar['synced_at'] ?? $syncSidecar['attempted_at'] ?? ''));
        $pnrItineraryRetrieveEndpoint = trim((string) ($syncSidecar['endpoint_path'] ?? ''));
        if ($pnrItineraryRetrieveEndpoint === '') {
            $pnrItineraryRetrieveEndpoint = SabreTripOrdersGetBookingItineraryMapper::ENDPOINT_PATH;
        }
        $pnrItineraryRetrieveResult = match ($pnrItinerarySyncStatus) {
            'synced' => 'success',
            'partial_resource_unavailable' => 'partial',
            '' => 'not_attempted',
            default => 'failed',
        };
        $pnrItinerarySyncReason = match ($pnrItinerarySyncStatus) {
            'partial_resource_unavailable' => ($syncSidecar['airline_locator_present'] ?? false) === true
                ? 'Carrier locator detected, but full itinerary was not synced. Verify with airline/carrier before ticketing.'
                : 'Partial verification data detected, but full itinerary was not synced. Verify manually before ticketing.',
            default => $pnrItineraryRetrieveResult === 'failed'
                ? trim((string) ($syncSidecar['reason_code'] ?? $pnrItinerarySyncStatus))
                : null,
        };
        $isCancelled = $booking->status === BookingStatus::Cancelled || $pnrCancelledOrReleased;
        $canSyncPnrItinerary = $isSabre && $hasPnrOrReference && ! $ticketIssued && ! $isCancelled;
        $syncBlock = $this->resolveSyncBlockReason($booking, [
            'can_sync_pnr_itinerary' => $canSyncPnrItinerary,
            'is_sabre' => $isSabre,
            'has_pnr_or_reference' => $hasPnrOrReference,
            'ticket_issued' => $ticketIssued,
        ], $meta);
        $syncPnrItineraryLabel = $hasPnrItinerarySnapshot ? 'Re-sync PNR itinerary (retrieve/sync)' : 'Retrieve/sync PNR itinerary';
        $syncPnrItineraryHelp = $hasPnrItinerarySnapshot
            ? 'Refresh final airline segment times from Sabre getBooking.'
            : 'Pull final airline itinerary from Sabre getBooking into this booking.';

        return [
            'provider' => $provider,
            'is_sabre' => $isSabre,
            'ticket_issued' => $ticketIssued,
            'has_pnr_or_reference' => $hasPnrOrReference,
            'pnr' => $pnr !== '' ? $pnr : null,
            'supplier_reference' => $supplierReference !== '' ? $supplierReference : null,
            'supplier_status_message' => $supplierStatusMessage,
            'supplier_status_variant' => $this->supplierStatusVariant($hasPnrOrReference, $staleSegment, $staffReview, (string) ($pnrFailure['classification'] ?? '')),
            'stale_segment' => $staleSegment,
            'stale_context' => $staleSegment ? $this->staleSegmentContext($safeSummary) : [],
            'staff_review' => $staffReview,
            'staff_review_summary' => $staffReview
                ? $this->staffReviewSummary($pnrFailure, $safeSummary)
                : null,
            'uc_segment_context' => ($pnrFailure['classification'] ?? '') === SabrePnrFailureClassifier::CLASSIFICATION_HOST_SELL_REJECTED_UC
                ? $this->ucSegmentContext($safeSummary)
                : [],
            'pnr_failure_classification' => (string) ($pnrFailure['classification'] ?? ''),
            'pnr_failure_next_action' => (string) ($pnrFailure['next_action'] ?? ''),
            'pnr_failure_admin_message' => (string) ($pnrFailure['admin_message'] ?? ''),
            'pnr_failure_retry_allowed' => (bool) ($pnrFailure['retry_allowed'] ?? true),
            'pnr_failure_retry_blocker_reasons' => is_array($pnrFailure['retry_blocker_reasons'] ?? null)
                ? array_values(array_slice($pnrFailure['retry_blocker_reasons'], 0, 8))
                : [],
            'rate_limit' => $rateLimitState,
            'can_create_pnr' => $canCreatePnr,
            'complex_itinerary_deferred' => $complexItineraryDeferred,
            'complex_itinerary_message' => $complexItineraryDeferred ? ComplexItineraryPolicy::adminDeferMessage() : null,
            'create_pnr_reason' => $this->createPnrReason(
                $booking,
                $hasPnrOrReference,
                $staleSegment,
                $staffReview,
                $complexItineraryDeferred,
                $offerRefreshRequiresAcceptance,
                $rateLimitState,
                $sabreCreateEligible,
                $nonSabreCreateEligible,
                $paymentPaid,
                $isSabre,
                $isIati,
                $isPiaNdc,
                (string) ($pnrFailure['admin_message'] ?? ''),
                $pnrFailureBlocksRetry,
                (string) ($connectingCert['status_message'] ?? ''),
                $controlledInitialCreateEligible,
            ),
            'can_retry_pnr' => $canRetryPnr,
            'retry_pnr_reason' => $this->retryPnrReason(
                $hasPnrOrReference,
                $staleSegment,
                $staffReview,
                $complexItineraryDeferred,
                $rateLimitState,
                $canRetryPnr,
                $latestAttempt,
                (string) ($pnrFailure['admin_message'] ?? ''),
                (string) ($pnrFailure['classification'] ?? ''),
                is_array($pnrFailure['retry_blocker_reasons'] ?? null) ? $pnrFailure['retry_blocker_reasons'] : [],
                $controlledInitialCreateEligible,
            ),
            'retry_pnr_refresh_helper' => $controlledOfferFreshnessRetryable
                ? 'Retry will refresh the Sabre offer before PNR creation.'
                : ($controlledHostNoopDiagnosticRetryable
                    ? SabrePnrFailureClassifier::hostNoopDiagnosticRetryHelper($safeSummary)
                    : ($controlledInitialCreateEligible
                        ? 'Controlled create will refresh the Sabre offer and attempt Passenger Records create once.'
                        : null)),
            'can_issue_ticket_action' => $canIssueTicketAction,
            'can_issue_ticket_live' => $canIssueTicketLive,
            'ticketing_module_enabled' => $ticketingModuleEnabled,
            'provider_module_enabled' => $providerModuleEnabled,
            'ticketing_env_enabled' => $ticketingEnvEnabled,
            'issue_ticket_disabled_reason' => $pnrCancelledOrReleased
                ? 'Sabre/GDS PNR has been released/cancelled. Ticketing is no longer available for this PNR.'
                : ($sabreGdsIssueReady
                ? ''
                : $this->issueTicketDisabledReason(
                    $hasPnrOrReference,
                    $ticketIssued,
                    $ticketingModuleEnabled,
                    $providerModuleEnabled,
                    $ticketingDisabled,
                    $liveTicketingSupported,
                    $canIssueTicketAction,
                    $canIssueTicketLive,
                    $paymentPaid,
                    $genericTicketingEligible,
                )),
            'ticketing_status_message' => $pnrCancelledOrReleased
                ? 'Sabre/GDS PNR released/cancelled — handle refund/credit manually or close booking.'
                : ($sabreGdsIssueReady
                ? (string) ($sabreGdsTicketing['admin_message'] ?? 'Unticketed Sabre GDS PNR is ready for Enhanced Air Ticket issuance.')
                : $this->ticketingStatusMessage(
                    $hasPnrOrReference,
                    $ticketIssued,
                    $ticketingModuleEnabled,
                    $providerModuleEnabled,
                    $ticketingDisabled,
                    $liveTicketingSupported,
                    $canIssueTicketLive,
                    $canIssueTicketAction,
                    $paymentPaid,
                    $genericTicketingEligible,
                )),
            'can_retry_ticketing' => ($isIati && $hasIatiDeferredBook && $paymentPaid && ! $ticketIssued)
                || ($isPiaNdc && (bool) ($piaNdcManual['can_retry_ticketing'] ?? false)),
            'retry_ticketing_reason' => $isIati && $hasIatiDeferredBook
                ? 'Confirm / book the deferred IATI order from the ticketing panel.'
                : ($isPiaNdc && (bool) ($piaNdcManual['can_retry_ticketing'] ?? false)
                    ? 'Retry ticketing after the previous attempt failed.'
                    : 'Automated ticketing retry is disabled until live ticketing is certified.'),
            'pia_ndc_manual_ticketing' => $piaNdcManual,
            'sabre_gds_ticketing' => $sabreGdsTicketing,
            'sabre_gds_cancel' => $sabreGdsCancel,
            'sabre_gds_issue_ready' => $sabreGdsIssueReady,
            'sabre_gds_pnr_cancelled_or_released' => $pnrCancelledOrReleased,
            'sabre_gds_manual_close_message' => $pnrCancelledOrReleased
                ? 'Handle refund/credit manually or close booking manually.'
                : null,
            'can_release_sabre_gds_pnr' => $canReleaseSabreGdsPnr,
            'release_sabre_gds_pnr_label' => 'Release PNR',
            'release_sabre_gds_pnr_help' => 'Cancels the unticketed Sabre/GDS PNR. Refund/credit handling remains manual.',
            'release_sabre_gds_pnr_confirm_phrase' => SabreGdsPnrCancellationStateResolver::releaseConfirmPhrase($booking),
            'pnr_itinerary_synced_canonical' => $pnrItinerarySyncedCanonical,
            'create_supplier_booking_label' => $isIati
                ? ($hasIatiDeferredBook ? 'Direct Book Required' : 'Create IATI Supplier Booking')
                : 'Create supplier booking / PNR',
            'issue_ticket_label' => $isIati
                ? ($hasIatiDeferredBook ? 'Confirm / Book IATI Order' : 'Confirm / Book IATI Order')
                : 'Issue ticket',
            'iati_deferred_book' => $hasIatiDeferredBook,
            'iati_mode' => $iatiMode !== '' ? $iatiMode : null,
            'iati_status_message' => $isIati && $hasIatiDeferredBook
                ? 'Direct book required after payment/admin approval.'
                : ($isIati && $hasPnrOrReference && $pnr === '' ? 'IATI PNR pending sync.' : null),
            'iati_supplier_booking_readiness' => $iatiSupplierReadiness,
            'primary_cta_label' => $primary['label'],
            'primary_cta_tab' => $primary['tab'],
            'primary_cta_hash' => $primary['hash'],
            'safe_summary_display_keys' => $this->safeSummaryDisplayKeys($safeSummary, $staleSegment, $staffReview, $offerRefreshDiagnostics),
            'offer_refresh_diagnostics' => $offerRefreshDiagnostics,
            'can_sync_pnr_itinerary' => $canSyncPnrItinerary,
            'sync_block_reason_code' => $syncBlock['code'] ?? null,
            'sync_block_message' => $syncBlock['message'] ?? null,
            'sync_pnr_itinerary_label' => $syncPnrItineraryLabel,
            'sync_pnr_itinerary_help' => $syncPnrItineraryHelp,
            'pnr_itinerary_sync_status' => $pnrItinerarySyncStatus !== '' ? $pnrItinerarySyncStatus : null,
            'pnr_itinerary_synced_at' => $pnrItinerarySyncedAt !== '' ? $pnrItinerarySyncedAt : null,
            'pnr_itinerary_retrieve_endpoint' => $pnrItineraryRetrieveEndpoint,
            'pnr_itinerary_retrieve_result' => $pnrItineraryRetrieveResult,
            'pnr_itinerary_sync_reason' => $pnrItinerarySyncReason !== '' && $pnrItinerarySyncReason !== null
                ? $pnrItinerarySyncReason
                : null,
            'has_pnr_itinerary_snapshot' => $hasPnrItinerarySnapshot,
            'connecting_same_carrier_candidate' => (bool) ($connectingCert['connecting_same_carrier_candidate'] ?? false),
            'connecting_same_carrier_enabled' => (bool) ($connectingCert['connecting_same_carrier_enabled'] ?? false),
            'connecting_same_carrier_public_checkout_enabled' => (bool) ($connectingCert['connecting_same_carrier_public_checkout_enabled'] ?? false),
            'controlled_certification_required' => (bool) ($connectingCert['controlled_certification_required'] ?? false),
            'admin_staff_pnr_retry_route_allowed' => (bool) ($connectingCert['admin_staff_pnr_retry_route_allowed'] ?? false),
            'admin_staff_pnr_readiness_passed' => (bool) ($connectingCert['admin_staff_pnr_readiness_passed'] ?? $connectingCert['admin_staff_pnr_retry_allowed'] ?? false),
            'admin_staff_pnr_retry_allowed' => (bool) ($connectingCert['admin_staff_pnr_readiness_passed'] ?? $connectingCert['admin_staff_pnr_retry_allowed'] ?? false),
            'admin_pnr_live_action_allowed' => (bool) ($connectingCert['admin_pnr_live_action_allowed'] ?? false),
            'controlled_pnr_certification_status' => (string) (
                $connectingCert['controlled_pnr_certification_status']
                ?? SabreCertifiedRouteSelector::CONTROLLED_PNR_UNKNOWN_CONTROLLED_ONLY
            ),
            'controlled_pnr_certification_label' => $this->controlledPnrCertificationLabel(
                (string) ($connectingCert['controlled_pnr_certification_status'] ?? SabreCertifiedRouteSelector::CONTROLLED_PNR_UNKNOWN_CONTROLLED_ONLY),
            ),
            'controlled_pnr_verified_booking_id' => $connectingCert['controlled_pnr_verified_booking_id'] ?? null,
            'controlled_pnr_verified_pnr_present' => (bool) ($connectingCert['controlled_pnr_verified_pnr_present'] ?? false),
            'controlled_pnr_airline_locator_present' => (bool) ($connectingCert['controlled_pnr_airline_locator_present'] ?? false),
            'controlled_pnr_ticketing_enabled' => (bool) ($connectingCert['controlled_pnr_ticketing_enabled'] ?? false),
            'context_refresh_available' => (bool) ($connectingCert['context_refresh_available'] ?? false),
            'can_prepare_supplier_context' => $canPrepareSupplierContext,
            'prepare_supplier_context_label' => 'Prepare supplier PNR context',
            'prepare_supplier_context_help' => 'Rebuild Sabre pricing linkage from stored shop identifiers (no PNR, no ticketing). Retry supplier booking automatically re-shops stale fares when needed.',
            'pricing_context_ready' => (bool) ($connectingCert['pricing_context_ready'] ?? false),
            'pricing_context_missing_fields' => is_array($connectingCert['pricing_context_missing_fields'] ?? null)
                ? array_values(array_slice($connectingCert['pricing_context_missing_fields'], 0, 12))
                : [],
            'last_context_refresh_status' => trim((string) ($meta['sabre_pricing_context_refresh']['status'] ?? '')) ?: null,
            'last_context_refresh_at' => trim((string) ($meta['sabre_pricing_context_refresh']['refreshed_at'] ?? '')) ?: null,
            'iati_like_connecting_ready' => (bool) ($connectingCert['iati_like_connecting_ready'] ?? false),
            'connecting_certification_status_message' => (string) ($connectingCert['status_message'] ?? ''),
            'connecting_wire_blockers' => is_array($connectingCert['blocker_reasons'] ?? null)
                ? array_values(array_slice($connectingCert['blocker_reasons'], 0, 8))
                : [],
            'pnr_retrieve_safety' => PnrItinerarySyncSafetyPresenter::forBooking($booking),
            'sabre_capability_posture' => $isSabre
                ? (new SabreCapabilityPosture)->bookingViewSummary()
                : null,
            'verified_auto_pnr_readiness' => $isSabre
                ? app(SabreVerifiedAutoPnrReadiness::class)->evaluate($booking)
                : null,
            'operational_pnr_readiness' => $operationalPnrReadiness,
            'operational_pnr_eligible' => $operationalPnrEligible,
            'last_operational_pnr_attempt_status' => $this->resolveLastOperationalPnrAttemptStatus($meta, $latestAttempt),
            'operational_pnr_reason_code' => trim((string) ($meta['operational_auto_pnr_reason_code'] ?? '')) ?: null,
            'branded_fare_public_auto_pnr_eligibility' => $isSabre
                ? $this->normalizeStoredBf7iEligibility($meta)
                : null,
            'pre_checkout_sellability' => $isSabre
                ? [
                    'dry_run' => is_array($meta['pre_checkout_sellability_dry_run'] ?? null)
                        ? $meta['pre_checkout_sellability_dry_run']
                        : null,
                    'presentation' => SabrePreCheckoutSellabilityPresentation::resolveForBooking($booking),
                ]
                : null,
            'controlled_pnr_readiness' => $isSabre
                ? app(SabreControlledPnrReadiness::class)->evaluate($booking, ['context' => 'admin_ui'])
                : null,
        ];
    }

    /**
     * Sprint 11B: Safe admin labels for same-carrier 2-segment controlled certification (no payloads/PII).
     *
     * @return array<string, mixed>
     */
    protected function connectingSameCarrierCertificationContext(Booking $booking): array
    {
        $diag = $this->sabrePnrCertificationSupport->buildMultiSegmentPnrReadinessDiagnostics($booking);
        $candidate = ($diag['connecting_same_carrier_candidate'] ?? false) === true;
        $enabled = ($diag['connecting_same_carrier_enabled'] ?? false) === true;
        $public = ($diag['connecting_same_carrier_public_checkout_enabled'] ?? false) === true;
        $routeRetry = ($diag['admin_staff_pnr_retry_route_allowed'] ?? false) === true;
        $readinessPassed = ($diag['admin_staff_pnr_readiness_passed'] ?? $diag['admin_staff_pnr_retry_allowed'] ?? false) === true;
        $liveAction = ($diag['admin_pnr_live_action_allowed'] ?? false) === true;
        $controlled = ($diag['controlled_certification_required'] ?? false) === true;
        $blockers = is_array($diag['blocker_reasons'] ?? null) ? $diag['blocker_reasons'] : [];
        $certStatus = (string) ($diag['controlled_pnr_certification_status'] ?? SabreCertifiedRouteSelector::CONTROLLED_PNR_UNKNOWN_CONTROLLED_ONLY);

        $message = '';
        if ($candidate) {
            $message = 'Same-carrier 2-segment candidate · '.$this->controlledPnrCertificationLabel($certStatus);
            if (! $enabled) {
                $message .= ' · Controlled certification required (SABRE_CPNR_CONNECTING_SAME_CARRIER_GDS_ENABLED=false)';
            } elseif ($certStatus === SabreCertifiedRouteSelector::CONTROLLED_PNR_HOST_NOOP_BLOCKED) {
                $message .= ' · Host rejected / do not retry same itinerary';
            } elseif ($controlled) {
                $message .= ' · Controlled certification required · Public checkout not enabled';
                if ($routeRetry && ! $readinessPassed) {
                    $message .= ' · Admin/staff PNR route allowed; readiness not passed (wire/pricing)';
                    if ($blockers !== []) {
                        $message .= ' ('.implode(', ', array_slice($blockers, 0, 4)).')';
                    }
                } elseif ($readinessPassed && ! $liveAction) {
                    $message .= ' · Readiness passed; live PNR action blocked (IATI-like connecting/B65/policy)';
                } elseif ($liveAction) {
                    $message .= ' · Admin/staff PNR live action allowed';
                }
            } elseif ($public) {
                $message .= ' · Public checkout live PNR enabled when readiness passes';
            }
        }

        return array_merge($diag, [
            'status_message' => $message,
        ]);
    }

    protected function controlledPnrCertificationLabel(string $status): string
    {
        return match ($status) {
            SabreCertifiedRouteSelector::CONTROLLED_PNR_VERIFIED => 'Verified controlled PNR-capable',
            SabreCertifiedRouteSelector::CONTROLLED_PNR_HOST_NOOP_BLOCKED => 'Host rejected / do not retry same itinerary',
            default => 'Unknown controlled-only',
        };
    }

    /**
     * POST guard for supplier-booking route (admin retry/create).
     */
    public function assertSupplierBookingPostAllowed(Booking $booking, bool $genericSupplierEligible): ?string
    {
        $state = $this->build($booking, $genericSupplierEligible, false);

        $controlledBlock = $this->controlledPnrPostBlockMessage($state);
        if ($controlledBlock !== null) {
            return $controlledBlock;
        }

        if ($state['can_create_pnr'] || $state['can_retry_pnr']) {
            return null;
        }

        if (($state['operational_pnr_eligible'] ?? false) === true && ! ($state['has_pnr_or_reference'] ?? false)) {
            return null;
        }

        if ($state['has_pnr_or_reference']) {
            return 'Supplier PNR already exists for this booking.';
        }

        if ($state['stale_segment']) {
            return 'Flight/class is no longer available. Ask the customer to search and select again.';
        }

        if ($state['staff_review']) {
            $classified = trim((string) ($state['pnr_failure_admin_message'] ?? ''));

            return $classified !== ''
                ? $classified.' Retry is not available for this outcome.'
                : 'Supplier booking failed — staff review is required before retrying.';
        }

        if (trim((string) ($state['pnr_failure_admin_message'] ?? '')) !== '' && ! ($state['pnr_failure_retry_allowed'] ?? true)) {
            return (string) $state['pnr_failure_admin_message'].' Retry is not available for this outcome.';
        }

        if ($state['complex_itinerary_deferred'] ?? false) {
            return ComplexItineraryPolicy::adminDeferMessage();
        }

        if ($state['rate_limit']['in_cooldown']) {
            return 'Sabre is busy — wait before retrying PNR creation.';
        }

        return (string) ($state['create_pnr_reason'] ?: 'Supplier PNR action is not available for this booking.');
    }

    /**
     * F9: additive POST guard from unified controlled PNR readiness.
     *
     * @param  array<string, mixed>  $state
     */
    protected function controlledPnrPostBlockMessage(array $state): ?string
    {
        $controlled = is_array($state['controlled_pnr_readiness'] ?? null)
            ? $state['controlled_pnr_readiness']
            : null;
        if ($controlled === null) {
            return null;
        }

        $hardCodes = [
            SabreControlledPnrReadiness::BLOCKER_EXISTING_PNR,
            SabreControlledPnrReadiness::BLOCKER_TICKETED,
            SabreControlledPnrReadiness::BLOCKER_CANCELLED,
            SabreControlledPnrReadiness::BLOCKER_SUPPLIER_MUTATION_DISABLED,
            SabreControlledPnrReadiness::BLOCKER_MISSING_PRICING,
            SabreControlledPnrReadiness::BLOCKER_MISSING_REVALIDATION,
            SabreControlledPnrReadiness::BLOCKER_REVALIDATION_UNUSABLE,
            SabreControlledPnrReadiness::BLOCKER_NO_FARE_LINKAGE,
            SabreControlledPnrReadiness::BLOCKER_STALE_PRICING,
            SabreControlledPnrReadiness::BLOCKER_REVALIDATION_EXPIRED,
            SabreControlledPnrReadiness::BLOCKER_OFFER_REFRESH_CONFIRMATION,
            SabreControlledPnrReadiness::BLOCKER_PRICE_CHANGE_CONFIRMATION,
            SabreControlledPnrReadiness::BLOCKER_MANUAL_REVIEW,
        ];

        $blockers = is_array($controlled['blockers'] ?? null) ? $controlled['blockers'] : [];
        foreach ($hardCodes as $code) {
            if (in_array($code, $blockers, true)) {
                $presenter = app(SabreReadinessReasonPresenter::class);

                return $presenter->messageForCode($code);
            }
        }

        return null;
    }

    /**
     * POST guard for PNR itinerary sync route (admin/staff).
     */
    public function assertSyncPnrItineraryPostAllowed(Booking $booking): ?string
    {
        $state = $this->build($booking, false, false);

        if ($state['can_sync_pnr_itinerary'] ?? false) {
            return null;
        }

        return (string) ($state['sync_block_message'] ?? 'Retrieve/sync PNR itinerary is not available for this booking.');
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $meta
     * @return array{code: string, message: string}|null
     */
    protected function resolveSyncBlockReason(Booking $booking, array $state, array $meta): ?array
    {
        if ($state['can_sync_pnr_itinerary'] ?? false) {
            return null;
        }

        $presenter = app(SabreReadinessReasonPresenter::class);
        $code = 'manual_review_required';

        if (($state['is_sabre'] ?? false) !== true) {
            $code = 'not_sabre_booking';
        } elseif (! ($state['has_pnr_or_reference'] ?? false)) {
            $code = 'missing_sabre_pnr';
        } elseif ($booking->status === BookingStatus::Cancelled) {
            $code = 'cancelled_booking_blocked';
        } elseif ($state['ticket_issued'] ?? false) {
            $code = 'ticketed_booking_blocked';
        } elseif ((int) ($meta['supplier_connection_id'] ?? 0) <= 0) {
            $code = 'missing_supplier_connection';
        }

        return [
            'code' => $presenter->normalizeCode($code),
            'message' => $presenter->messageForCode($code),
        ];
    }

    /**
     * POST guard for prepare-supplier-pnr-context (Sprint 11D; snapshot rebuild only).
     */
    public function assertPrepareSupplierContextPostAllowed(Booking $booking): ?string
    {
        $state = $this->build($booking, true, false);

        if ($state['can_prepare_supplier_context'] ?? false) {
            return null;
        }

        if ($state['has_pnr_or_reference']) {
            return 'Supplier PNR already exists — pricing context preparation is not needed.';
        }

        if (! ($state['is_sabre'] ?? false)) {
            return 'Pricing context preparation is only available for Sabre bookings.';
        }

        if (! ($state['connecting_same_carrier_candidate'] ?? false)) {
            return 'Pricing context preparation is only available for same-carrier 2-segment controlled certification bookings.';
        }

        if ($state['pricing_context_ready'] ?? false) {
            return 'Sabre pricing context is already complete.';
        }

        if (! ($state['admin_staff_pnr_retry_route_allowed'] ?? false)) {
            return 'Controlled certification route is not enabled for this booking.';
        }

        return 'Pricing context preparation is not available for this booking.';
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function controlledStaffSafeRefreshOrOfferFreshnessReady(array $meta, bool $sabreCreateEligible): bool
    {
        if ($sabreCreateEligible) {
            return true;
        }

        $assess = app(SabreSafeRefreshContext::class)->assess($meta);

        return ($assess['safe_refresh_context_complete'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $connectingCert
     */
    protected function controlledInitialCreateEligible(
        Booking $booking,
        array $meta,
        array $connectingCert,
        bool $hasPnrOrReference,
        bool $ticketingDisabled,
        bool $sabreCreateEligible,
    ): bool {
        if ($hasPnrOrReference || ! $ticketingDisabled || ! $sabreCreateEligible) {
            return false;
        }

        if (! $this->controlledInitialCreateCandidate($meta, $connectingCert)) {
            return false;
        }

        $booking->loadMissing('supplierBookings');
        $hasSupplierBookingRecord = $booking->supplierBookings->contains(
            fn ($item) => in_array((string) $item->status, ['created', 'pending_ticketing', 'ticketed'], true),
        );
        if ($hasSupplierBookingRecord) {
            return false;
        }

        $safeRefresh = app(SabreSafeRefreshContext::class)->assess($meta);

        return ($safeRefresh['safe_refresh_context_complete'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $connectingCert
     */
    protected function controlledInitialCreateCandidate(array $meta, array $connectingCert): bool
    {
        return ($meta['defer_supplier_booking_to_manual_review'] ?? false) === true
            && (string) ($meta['supplier_pnr_deferred_reason'] ?? '') === SabreCertifiedRouteSelector::DEFER_REASON
            && ($connectingCert['admin_pnr_live_action_allowed'] ?? false) === true
            && ($connectingCert['connecting_same_carrier_candidate'] ?? false) === true;
    }

    protected function providerSupportsLiveTicketing(string $provider): bool
    {
        return in_array($provider, [
            SupplierProvider::Iati->value,
            SupplierProvider::PiaNdc->value,
        ], true);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function sabrePassengerRecordsCreateEligible(Booking $booking, array $meta): bool
    {
        if ($booking->passengers->isEmpty()) {
            return false;
        }

        if ($booking->contact === null) {
            return false;
        }

        $hasContact = filled($booking->contact->email) || filled($booking->contact->phone);
        if (! $hasContact) {
            return false;
        }

        if ($this->operationalPrecheckService->validatePassengerReadiness($booking) !== []) {
            return false;
        }

        $hasValidationSnapshot = isset($meta['validated_offer_snapshot']) || isset($meta['normalized_offer_snapshot']);
        $validationStatus = (string) ($meta['offer_validation_status'] ?? '');
        $offerIsValid = in_array($validationStatus, ['valid', 'validated', 'ok', 'pass', 'fresh'], true)
            || ($validationStatus === '' && $hasValidationSnapshot);

        return $offerIsValid && $hasValidationSnapshot;
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     * @return array{in_cooldown:bool,cooldown_elapsed:bool,message:?string,http_status:?int}
     */
    protected function resolveRateLimitState(?SupplierBookingAttempt $attempt, array $safeSummary, string $errorCode): array
    {
        $httpStatus = isset($safeSummary['http_status']) ? (int) $safeSummary['http_status'] : null;
        $messages = $this->stringifySafeMessages($safeSummary);
        $isRateLimitCode = in_array($errorCode, ['sabre_booking_connection_error', 'transport_timeout'], true)
            || $httpStatus === 429
            || Str::contains(strtolower($messages), ['too many requests', '429']);

        if (! $isRateLimitCode || $attempt === null) {
            return [
                'in_cooldown' => false,
                'cooldown_elapsed' => true,
                'message' => null,
                'http_status' => $httpStatus,
            ];
        }

        $anchor = $attempt->completed_at ?? $attempt->attempted_at ?? $attempt->created_at;
        $elapsed = $anchor === null || $anchor->lte(now()->subMinutes(self::RETRY_COOLDOWN_MINUTES));

        return [
            'in_cooldown' => ! $elapsed,
            'cooldown_elapsed' => $elapsed,
            'message' => $elapsed ? null : 'Sabre busy / retry later',
            'http_status' => $httpStatus,
        ];
    }

    protected function latestAttemptIsRetryable(?SupplierBookingAttempt $attempt): bool
    {
        if ($attempt === null) {
            return false;
        }

        if (in_array(strtolower((string) $attempt->status), ['processing', 'pending'], true)) {
            return false;
        }

        return in_array(strtolower((string) $attempt->status), ['failed', 'manual_review', 'needs_review'], true);
    }

    protected function iatiSupplierStatusMessage(
        bool $hasReference,
        bool $deferredBook,
        string $pnr,
        string $supplierReference,
        bool $ticketIssued,
        string $mode,
        string $lastSyncStatus,
    ): string {
        if ($deferredBook) {
            return 'Direct book required · Admin approval required · Confirm / book IATI order after payment.';
        }

        if (! $hasReference) {
            return 'IATI supplier booking not created yet. Create IATI supplier booking after payment verification.';
        }

        $parts = [];
        if ($mode === 'option') {
            $parts[] = 'IATI option / hold pending';
        } elseif ($mode === 'book') {
            $parts[] = 'IATI order booked';
        } else {
            $parts[] = 'IATI supplier order present';
        }

        if ($supplierReference !== '') {
            $parts[] = 'Order: '.$supplierReference;
        }

        if ($pnr !== '') {
            $parts[] = 'PNR: '.$pnr;
        } else {
            $parts[] = 'IATI PNR pending sync';
        }

        if ($lastSyncStatus === 'synced') {
            $parts[] = 'Last sync successful';
        }

        if ($ticketIssued) {
            $parts[] = 'Ticketing completed';
        } elseif ($hasReference) {
            $parts[] = 'Confirm / book IATI order pending';
        }

        return implode(' · ', $parts);
    }

    protected function supplierStatusMessage(
        bool $hasPnr,
        string $pnr,
        string $supplierReference,
        bool $ticketIssued,
        bool $ticketingDisabled,
        bool $liveTicketingSupported,
        string $pnrItinerarySyncStatus = '',
        bool $pnrCancelledOrReleased = false,
        bool $pnrItinerarySyncedCanonical = false,
    ): string {
        if (! $hasPnr) {
            return '';
        }

        $ref = $pnr !== '' ? $pnr : ($supplierReference !== '' ? $supplierReference : '—');
        if ($pnrCancelledOrReleased) {
            return implode(' · ', [
                'Sabre/GDS PNR released/cancelled',
                'PNR: '.$ref.' (released/cancelled)',
                'Handle refund/credit manually or close booking',
            ]);
        }

        $lead = ($pnrItinerarySyncStatus === 'synced' || $pnrItinerarySyncedCanonical)
            ? 'Supplier PNR created · itinerary synced from Sabre'
            : 'Supplier PNR already created. Continue with sync/payment/ticketing workflow.';
        $parts = [
            $lead,
            'PNR: '.$ref,
        ];

        if ($ticketIssued) {
            $parts[] = 'Ticketing completed';
        } elseif ($ticketingDisabled || ! $liveTicketingSupported) {
            $parts[] = 'Ticketing disabled / manual ticketing required';
        } else {
            $parts[] = 'Ticketing pending';
        }

        return implode(' · ', $parts);
    }

    /**
     * @param  array{in_cooldown:bool,cooldown_elapsed:bool,message:?string,http_status:?int}  $rateLimit
     * @return array{label:string,tab:string,hash:string}
     */
    protected function supplierStatusVariant(
        bool $hasPnr,
        bool $staleSegment,
        bool $staffReview,
        string $classification,
    ): string {
        if ($hasPnr) {
            return 'success';
        }
        if ($staleSegment || in_array($classification, [
            SabrePnrFailureClassifier::CLASSIFICATION_BOOKING_CLASS_MISMATCH,
            SabrePnrFailureClassifier::CLASSIFICATION_STALE_OR_MISSING_INVENTORY,
        ], true)) {
            return 'warning';
        }
        if ($staffReview || in_array($classification, [
            SabrePnrFailureClassifier::CLASSIFICATION_HOST_SELL_REJECTED_UC,
            SabrePnrFailureClassifier::CLASSIFICATION_NO_FARES_RBD_CARRIER,
            SabrePnrFailureClassifier::CLASSIFICATION_FARE_RBD_CARRIER_NOT_SELLABLE,
            SabrePnrFailureClassifier::CLASSIFICATION_PNR_REQUIRES_MANUAL_SABRE_PRICING,
            SabrePnrFailureClassifier::CLASSIFICATION_REVALIDATION_LINKAGE_INCOMPLETE,
            SabrePnrFailureClassifier::CLASSIFICATION_PROVIDER_APPLICATION_ERROR,
            SabrePnrFailureClassifier::CLASSIFICATION_HOST_AIR_BOOKING_NOOP,
            SabrePnrFailureClassifier::CLASSIFICATION_HOST_INVENTORY_OR_CERT_LIMITATION,
            SabrePnrFailureClassifier::CLASSIFICATION_UNKNOWN_STAFF_REVIEW,
        ], true)) {
            return 'danger';
        }

        return 'secondary';
    }

    protected function resolvePrimaryCta(
        bool $hasPnr,
        bool $paymentPaid,
        bool $ticketIssued,
        bool $staleSegment,
        bool $staffReview,
        array $rateLimit,
        bool $canCreatePnr,
        bool $canRetryPnr,
        bool $canIssueTicketLive,
        bool $ticketingDisabled,
        bool $liveTicketingSupported,
        string $pnrFailureClassification = '',
        bool $sabreGdsIssueReady = false,
        bool $pnrCancelledOrReleased = false,
    ): array {
        if ($hasPnr && $pnrCancelledOrReleased) {
            return ['label' => 'Handle refund/credit manually', 'tab' => 'refunds', 'hash' => ''];
        }

        if ($hasPnr) {
            if (! $paymentPaid) {
                return ['label' => 'Record payment', 'tab' => 'payments', 'hash' => 'payments'];
            }

            if (! $ticketIssued && ($sabreGdsIssueReady || $canIssueTicketLive)) {
                return ['label' => 'Issue ticket', 'tab' => 'ticketing', 'hash' => 'ticketing-panel'];
            }

            if (! $ticketIssued && ($ticketingDisabled || ! $liveTicketingSupported || ! $canIssueTicketLive)) {
                return ['label' => 'Manual ticketing required', 'tab' => 'ticketing', 'hash' => 'ticketing-panel'];
            }

            if ($ticketIssued) {
                return ['label' => 'Generate itinerary', 'tab' => 'ticketing', 'hash' => 'ticketing-panel'];
            }

            return ['label' => 'Review booking workflow', 'tab' => 'overview', 'hash' => ''];
        }

        if ($staleSegment || in_array($pnrFailureClassification, [
            SabrePnrFailureClassifier::CLASSIFICATION_BOOKING_CLASS_MISMATCH,
            SabrePnrFailureClassifier::CLASSIFICATION_STALE_OR_MISSING_INVENTORY,
            SabrePnrFailureClassifier::CLASSIFICATION_HOST_SELL_REJECTED_UC,
        ], true)) {
            return ['label' => 'Search again required', 'tab' => 'supplier', 'hash' => 'supplier-pnr-panel'];
        }

        if ($staffReview || in_array($pnrFailureClassification, [
            SabrePnrFailureClassifier::CLASSIFICATION_NO_FARES_RBD_CARRIER,
            SabrePnrFailureClassifier::CLASSIFICATION_PNR_REQUIRES_MANUAL_SABRE_PRICING,
            SabrePnrFailureClassifier::CLASSIFICATION_REVALIDATION_LINKAGE_INCOMPLETE,
            SabrePnrFailureClassifier::CLASSIFICATION_PROVIDER_APPLICATION_ERROR,
            SabrePnrFailureClassifier::CLASSIFICATION_HOST_INVENTORY_OR_CERT_LIMITATION,
            SabrePnrFailureClassifier::CLASSIFICATION_UNKNOWN_STAFF_REVIEW,
        ], true)) {
            return ['label' => 'Staff review required', 'tab' => 'supplier', 'hash' => 'supplier-pnr-panel'];
        }

        if ($rateLimit['in_cooldown']) {
            return ['label' => 'Sabre busy / retry later', 'tab' => 'supplier', 'hash' => 'supplier-pnr-panel'];
        }

        if ($canRetryPnr) {
            return ['label' => 'Retry PNR', 'tab' => 'supplier', 'hash' => 'supplier-pnr-panel'];
        }

        if ($canCreatePnr) {
            return ['label' => 'Create supplier booking / PNR', 'tab' => 'supplier', 'hash' => 'supplier-pnr-panel'];
        }

        if ($canIssueTicketLive) {
            return ['label' => 'Issue ticket', 'tab' => 'ticketing', 'hash' => 'ticketing-panel'];
        }

        if ($ticketIssued) {
            return ['label' => 'Generate itinerary', 'tab' => 'ticketing', 'hash' => 'ticketing-panel'];
        }

        if (! $paymentPaid) {
            return ['label' => 'Record or verify payment', 'tab' => 'payments', 'hash' => 'payments'];
        }

        return ['label' => 'Review booking workflow', 'tab' => 'overview', 'hash' => ''];
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     * @return array<string, string>
     */
    protected function staleSegmentContext(array $safeSummary): array
    {
        $out = [];
        foreach (['stale_segment_route', 'stale_segment_flight', 'probable_issue'] as $key) {
            if (! empty($safeSummary[$key]) && is_scalar($safeSummary[$key])) {
                $out[$key] = (string) $safeSummary[$key];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     * @return array<string, string>
     */
    protected function ucSegmentContext(array $safeSummary): array
    {
        $out = [];
        $status = strtoupper(trim((string) ($safeSummary['airline_segment_status'] ?? '')));
        if ($status !== '') {
            $out['segment_status_returned'] = $status;
        } elseif (SabrePnrFailureClassifier::safeSummaryIndicatesHostSellRejectedUc($safeSummary)) {
            $out['segment_status_returned'] = 'UC';
        }
        $flights = [];
        foreach ((array) ($safeSummary['affected_flight_numbers'] ?? []) as $flight) {
            if (is_scalar($flight) && trim((string) $flight) !== '') {
                $flights[] = strtoupper(trim((string) $flight));
            }
        }
        if ($flights !== []) {
            $out['affected_flights'] = implode(', ', array_values(array_unique(array_slice($flights, 0, 8))));
        }
        if (($safeSummary['halt_on_status_received'] ?? false) === true) {
            $out['halt_on_status'] = 'received';
        }
        $out['meaning'] = 'Airline host did not confirm/sell one or more segments.';
        $out['suggested_action'] = 'Choose another itinerary or re-shop for fresh availability.';
        $out['retry_same_offer'] = 'not recommended';

        return $out;
    }

    /**
     * @param  array<string, mixed>  $pnrFailure
     * @param  array<string, mixed>  $safeSummary
     */
    protected function staffReviewSummary(array $pnrFailure, array $safeSummary): string
    {
        if (($pnrFailure['classification'] ?? '') === SabrePnrFailureClassifier::CLASSIFICATION_HOST_SELL_REJECTED_UC) {
            $msg = trim((string) ($pnrFailure['admin_message'] ?? ''));
            if ($msg !== '') {
                return $msg;
            }
        }

        $admin = trim((string) ($pnrFailure['admin_message'] ?? ''));

        return $admin !== '' ? $admin : $this->summarizeApplicationError($safeSummary);
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     */
    protected function summarizeApplicationError(array $safeSummary): string
    {
        $messages = $safeSummary['response_error_messages'] ?? $safeSummary['application_error_messages'] ?? null;
        $text = $this->stringifySafeMessages(['messages' => $messages]);
        $upper = strtoupper($text);
        $hints = [];
        if (Str::contains($upper, ['NO FARES', 'RBD', 'CARRIER'])) {
            $hints[] = 'No fares / RBD / carrier restriction';
        }
        if (Str::contains($upper, [' UC', 'UC '])) {
            $hints[] = 'Unable to confirm (UC)';
        }
        if (Str::contains($upper, 'FLIGHT NOOP')) {
            $hints[] = 'Flight no-op';
        }

        if ($hints !== []) {
            return 'Supplier booking failed — staff review required. Likely: '.implode('; ', $hints).'.';
        }

        return 'Supplier booking failed — staff review required.';
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     */
    protected function stringifySafeMessages(array $safeSummary): string
    {
        $parts = [];
        foreach (['response_error_messages', 'application_error_messages', 'messages', 'message'] as $key) {
            $val = $safeSummary[$key] ?? null;
            if (is_string($val)) {
                $parts[] = $val;
            } elseif (is_array($val)) {
                foreach ($val as $item) {
                    if (is_scalar($item)) {
                        $parts[] = (string) $item;
                    }
                }
            }
        }

        return implode(' ', $parts);
    }

    protected function createPnrReason(
        Booking $booking,
        bool $hasPnr,
        bool $stale,
        bool $staffReview,
        bool $complexDeferred,
        bool $offerRefreshRequiresAcceptance,
        array $rateLimit,
        bool $sabreEligible,
        bool $nonSabreEligible,
        bool $paid,
        bool $isSabre,
        bool $isIati,
        bool $isPiaNdc = false,
        string $pnrFailureAdminMessage = '',
        bool $pnrFailureBlocksRetry = false,
        string $connectingCertificationStatusMessage = '',
        bool $controlledInitialCreateEligible = false,
    ): string {
        if ($hasPnr) {
            return 'PNR or supplier reference already exists.';
        }
        if ($isPiaNdc && ! $hasPnr) {
            return 'PIA NDC option PNR is created automatically when the customer submits the booking request.';
        }
        if ($isIati && ! $hasPnr) {
            $readiness = IatiSupplierBookingEligibility::evaluate($booking, false);
            if (! ($readiness['eligible'] ?? false) && ($readiness['missing'] ?? []) !== []) {
                $missing = array_values(array_filter(
                    $readiness['missing'],
                    function (string $code) use ($booking): bool {
                        if ($code !== 'supplier_booking_in_progress') {
                            return true;
                        }

                        return $this->attemptGuard->resolveActiveAttempt(
                            $booking,
                            SupplierProvider::Iati->value,
                            'create_pnr',
                        ) !== null;
                    },
                ));
                if ($missing !== []) {
                    return 'IATI supplier booking blocked: '.str_replace('_', ' ', implode(', ', $missing));
                }
            }
        }
        if ($complexDeferred) {
            return ComplexItineraryPolicy::adminDeferMessage();
        }
        if ($offerRefreshRequiresAcceptance) {
            return SabreOfferRefreshAcceptance::ADMIN_MESSAGE;
        }
        if ($pnrFailureBlocksRetry && $pnrFailureAdminMessage !== '') {
            return $pnrFailureAdminMessage;
        }
        if ($stale) {
            return 'Flight/class no longer available. Ask customer to search and select again.';
        }
        if ($staffReview) {
            return $pnrFailureAdminMessage !== ''
                ? $pnrFailureAdminMessage
                : 'Supplier booking failed — staff review required.';
        }
        if ($rateLimit['in_cooldown']) {
            return (string) ($rateLimit['message'] ?? 'Sabre busy / retry later');
        }
        if (! $isSabre && ! $paid) {
            return 'Payment must be verified first.';
        }
        if ($isSabre && ! $sabreEligible) {
            return 'Passenger, contact, or offer snapshot prerequisites are not complete.';
        }
        if ($controlledInitialCreateEligible) {
            return '';
        }
        if ($isSabre) {
            $connectingMsg = trim($connectingCertificationStatusMessage);
            if ($connectingMsg !== '' && str_contains($connectingMsg, 'Host rejected')) {
                return $connectingMsg;
            }
            if ($connectingMsg !== '' && (str_contains($connectingMsg, 'readiness not passed') || str_contains($connectingMsg, 'blocked until wire'))) {
                return $connectingMsg;
            }
            if ($connectingMsg !== '' && str_contains($connectingMsg, 'Controlled certification required')) {
                return $connectingMsg;
            }
        }
        if (! $isSabre && ! $nonSabreEligible) {
            if ($isIati) {
                $readiness = IatiSupplierBookingEligibility::evaluate($booking, false);
                $missing = array_values(array_filter(
                    $readiness['missing'] ?? [],
                    function (string $code) use ($booking): bool {
                        if ($code !== 'supplier_booking_in_progress') {
                            return true;
                        }

                        return $this->attemptGuard->resolveActiveAttempt(
                            $booking,
                            SupplierProvider::Iati->value,
                            'create_pnr',
                        ) !== null;
                    },
                ));
                if ($missing !== []) {
                    return 'IATI supplier booking blocked: '.str_replace('_', ' ', implode(', ', $missing));
                }
            }

            return 'Offer validation and booking prerequisites are not complete.';
        }

        return '';
    }

    protected function retryPnrReason(
        bool $hasPnr,
        bool $stale,
        bool $staffReview,
        bool $complexDeferred,
        array $rateLimit,
        bool $canRetry,
        ?SupplierBookingAttempt $attempt,
        string $pnrFailureAdminMessage = '',
        string $pnrFailureClassification = '',
        array $retryBlockerReasons = [],
        bool $controlledInitialCreateEligible = false,
    ): string {
        if ($canRetry) {
            return '';
        }
        if ($controlledInitialCreateEligible) {
            return 'Initial controlled create is available; no prior supplier attempt is required.';
        }
        if ($hasPnr) {
            return 'PNR already created.';
        }
        if ($complexDeferred) {
            return ComplexItineraryPolicy::adminDeferMessage();
        }
        if ($pnrFailureClassification === SabrePnrFailureClassifier::CLASSIFICATION_HOST_INVENTORY_OR_CERT_LIMITATION) {
            return SabrePnrFailureClassifier::RETRY_REASON_HOST_NOOP_TERMINAL;
        }
        if ($retryBlockerReasons !== []) {
            return 'Retry not allowed ('.implode(', ', array_map('strval', $retryBlockerReasons)).') — '
                .($pnrFailureAdminMessage !== '' ? $pnrFailureAdminMessage : 'choose another itinerary or fresh search.');
        }
        if ($pnrFailureAdminMessage !== '' && $pnrFailureClassification !== '') {
            return 'Retry not allowed — '.$pnrFailureAdminMessage;
        }
        if ($stale) {
            return 'Retry not allowed for stale segment — search again required.';
        }
        if ($staffReview) {
            return $pnrFailureAdminMessage !== ''
                ? 'Retry not allowed — '.$pnrFailureAdminMessage
                : 'Staff review required — automatic retry disabled.';
        }
        if ($rateLimit['in_cooldown']) {
            return (string) ($rateLimit['message'] ?? 'Sabre busy / retry later');
        }
        if ($attempt === null) {
            return 'No supplier attempt to retry.';
        }

        return 'Latest attempt is not eligible for retry.';
    }

    protected function issueTicketDisabledReason(
        bool $hasPnr,
        bool $ticketIssued,
        bool $ticketingModuleEnabled,
        bool $providerModuleEnabled,
        bool $ticketingDisabled,
        bool $liveSupported,
        bool $canIssueTicketAction,
        bool $canIssueTicketLive,
        bool $paymentPaid,
        bool $genericTicketingEligible,
    ): string {
        if ($ticketIssued) {
            return 'Tickets have already been issued for this booking.';
        }
        if (! $ticketingModuleEnabled || ! $providerModuleEnabled) {
            return 'Ticketing is disabled for this deployment.';
        }
        if (! $hasPnr) {
            return 'Create or attach PNR before ticketing.';
        }
        if (! $paymentPaid) {
            return 'Payment must be verified before ticketing.';
        }
        if (! $genericTicketingEligible) {
            return 'Booking is not eligible for ticketing yet.';
        }
        if ($ticketingDisabled) {
            return 'Sabre ticketing is disabled in environment settings.';
        }
        if (! $liveSupported || ! $canIssueTicketLive) {
            return 'Ticketing disabled / manual ticketing required.';
        }
        if ($canIssueTicketAction) {
            return '';
        }

        return 'Manual ticketing required.';
    }

    protected function ticketingStatusMessage(
        bool $hasPnr,
        bool $ticketIssued,
        bool $ticketingModuleEnabled,
        bool $providerModuleEnabled,
        bool $ticketingDisabled,
        bool $liveSupported,
        bool $canIssueLive,
        bool $canIssueTicketAction,
        bool $paymentPaid,
        bool $genericTicketingEligible,
    ): string {
        if ($ticketIssued) {
            return 'Tickets issued.';
        }
        if (! $ticketingModuleEnabled || ! $providerModuleEnabled) {
            return 'Ticketing is disabled for this deployment.';
        }
        if (! $hasPnr) {
            return 'Create or attach PNR before ticketing.';
        }
        if (! $paymentPaid) {
            return 'Payment must be verified before ticketing.';
        }
        if (! $genericTicketingEligible) {
            return 'Booking is not eligible for ticketing yet.';
        }
        if ($ticketingDisabled) {
            return 'Sabre ticketing is disabled in environment settings.';
        }
        if (! $liveSupported || ! $canIssueLive) {
            return 'Ticketing disabled / manual ticketing required.';
        }
        if ($canIssueTicketAction && $canIssueLive) {
            return 'Ready for automated ticketing.';
        }

        return 'Manual ticketing required.';
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     * @return list<string>
     */
    protected function safeSummaryDisplayKeys(
        array $safeSummary,
        bool $stale,
        bool $staffReview,
        ?array $offerRefreshDiagnostics = null,
    ): array {
        $allowed = [
            'source',
            'reason',
            'reason_code',
            'prior_error_code',
            'endpoint_path',
            'payload_schema',
            'http_status',
            'revalidation_skipped_by_config',
            'revalidation_bypass_enabled',
            'ticketing_enabled',
            'stale_segment_route',
            'stale_segment_flight',
            'probable_issue',
        ];

        if ($offerRefreshDiagnostics !== null) {
            $allowed = array_merge($allowed, [
                'refresh_attempted',
                'refresh_available',
                'refresh_status',
                'refresh_reason_code',
                'refresh_message',
                'missing_context_fields',
                'checkout_search_id_present',
                'search_criteria_present',
                'offer_reference_present',
                'shop_identifiers_present',
                'safe_refresh_context_present',
                'safe_refresh_context_complete',
                'can_rebuild_from_safe_context',
                'recommended_staff_action',
                'refresh_match_found',
                'refresh_reasons',
                'refresh_stage',
                'refresh_exception_class',
                'refresh_exception_code',
                'refresh_exception_message_safe',
                'fresh_search_attempted',
                'fresh_search_result_present',
                'fresh_search_error_code',
                'match_attempted',
                'match_found',
                'apply_refresh_attempted',
                'meta_stamp_attempted',
            ]);
        }

        if ($staffReview) {
            $allowed = array_merge($allowed, [
                'response_error_messages',
                'airline_segment_status',
                'affected_flight_numbers',
                'halt_on_status_received',
                'retry_blocker_reasons',
            ]);
        }

        return array_values(array_filter($allowed, fn (string $k) => array_key_exists($k, $safeSummary)));
    }

    /**
     * E5F: Terminal verified public auto-PNR fare/RBD failure — block same-offer create/retry.
     *
     * @param  array<string, mixed>  $meta
     */
    protected function verifiedAutoPnrTerminalFareRbdBlocked(array $meta): bool
    {
        if (($meta['verified_multiseg_auto_pnr_result'] ?? '') === 'failed'
            && ($meta['verified_multiseg_auto_pnr_reason_code'] ?? '') === SabreVerifiedAutoPnrReadiness::VERIFIED_AUTO_PNR_TERMINAL_FAILURE_REASON) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function resolveLastOperationalPnrAttemptStatus(array $meta, ?SupplierBookingAttempt $latestAttempt): ?string
    {
        $fromMeta = trim((string) ($meta['operational_auto_pnr_result'] ?? ''));
        if ($fromMeta !== '') {
            return $fromMeta;
        }

        if ($latestAttempt !== null
            && strtolower((string) $latestAttempt->action) === 'create_pnr') {
            return strtolower((string) $latestAttempt->status);
        }

        return null;
    }

    /**
     * BF7-I: Whitelist stored checkout eligibility meta for admin display (no raw JSON dump).
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>|null
     */
    protected function normalizeStoredBf7iEligibility(array $meta): ?array
    {
        $key = SabreBrandedFarePublicAutoPnrEligibility::META_KEY;
        if (! array_key_exists($key, $meta)) {
            return null;
        }

        $stored = $meta[$key];
        if (! is_array($stored)) {
            return [
                'eligible' => false,
                'reason_code' => 'stored_meta_malformed',
                'failed_conditions' => [],
            ];
        }

        $failed = is_array($stored['failed_conditions'] ?? null)
            ? array_values(array_map(static fn ($c): string => (string) $c, $stored['failed_conditions']))
            : [];

        return [
            'eligible' => ($stored['eligible'] ?? false) === true,
            'reason_code' => trim((string) ($stored['reason_code'] ?? '')) ?: '-',
            'failed_conditions' => $failed,
            'selected_brand_code' => is_scalar($stored['selected_brand_code'] ?? null)
                ? (string) $stored['selected_brand_code']
                : null,
            'brand_shape' => trim((string) ($stored['brand_shape'] ?? '')) ?: null,
            'carrier_chain' => trim((string) ($stored['carrier_chain'] ?? '')) ?: null,
            'ticketing_enabled' => ($stored['ticketing_enabled'] ?? false) === true,
            'public_flag_enabled' => ($stored['public_flag_enabled'] ?? false) === true,
            'auto_pnr_flag_enabled' => ($stored['auto_pnr_flag_enabled'] ?? false) === true,
            'evaluated_at' => trim((string) ($stored['evaluated_at'] ?? '')) ?: null,
        ];
    }
}
