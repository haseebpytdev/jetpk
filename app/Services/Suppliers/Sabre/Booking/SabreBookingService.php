<?php

namespace App\Services\Suppliers\Sabre\Booking;

use App\Console\Commands\SabreCheckBookingEndpointsCommand;
use App\Data\SabreBookingOperationResult;
use App\Data\SupplierBookingResultData;
use App\Enums\SupplierProvider;
use App\Exceptions\SabreRevalidateGatekeeperException;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Models\SupplierDiagnosticLog;
use App\Models\User;
use App\Services\FlightSearch\FlightSearchResultStore;
use App\Services\Suppliers\Sabre\Cancel\SabreBookingCancelService;
use App\Services\Suppliers\Sabre\Core\SabreBookingClient;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Services\Suppliers\Sabre\Gds\SabreBookingOfferRefreshService;
use App\Services\Suppliers\Sabre\Gds\SabreRevalidationPayloadBuilder;
use App\Services\Suppliers\Sabre\Gds\SabreSegmentFreshShopSellabilityService;
use App\Services\Suppliers\Sabre\SabreFlightSearchNormalizer;
use App\Services\Suppliers\Sabre\SabreStoredPricingContextDigest;
use App\Support\Bookings\BookingSupplierConfirmationNoticeResolver;
use App\Support\Bookings\ComplexItineraryPolicy;
use App\Support\Bookings\ControlledStaffOfferRefreshDiagnostics;
use App\Support\Bookings\PublicCheckoutFareChangeState;
use App\Support\Bookings\SabreAdminManualPnrFallbackReadiness;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabreControlledFinalPnrRetryAllowanceGate;
use App\Support\Bookings\SabreControlledPnrApprovalOverrideGate;
use App\Support\Bookings\SabreControlledPnrContextDigest;
use App\Support\Bookings\SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate;
use App\Support\Bookings\SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate;
use App\Support\Bookings\SabreControlledPnrRetryAllowanceGate;
use App\Support\Bookings\SabreCpnrOperationalAllowNnPolicy;
use App\Support\Bookings\SabreCreatePayloadSafeSummary;
use App\Support\Bookings\SabreGdsAutoPnrLifecycleService;
use App\Support\Bookings\SabreHostErrorClassifier;
use App\Support\Bookings\SabreHostRejectionFingerprint;
use App\Support\Bookings\SabreOfferRefreshAcceptance;
use App\Support\Bookings\SabreOperationalAllowNnStrategyChangedRetryGate;
use App\Support\Bookings\SabreOperationalPnrReadiness;
use App\Support\Bookings\SabrePnrCertificationSupport;
use App\Support\Bookings\SabrePnrFailureClassifier;
use App\Support\Bookings\SabrePassengerRecordsItineraryGuardPolicy;
use App\Support\Bookings\SabreSafeRefreshContext;
use App\Support\Bookings\SabreVerifiedAutoPnrReadiness;
use App\Support\Bookings\SupplierBookingAttemptResolution;
use App\Support\Bookings\SupplierBookingPreflightGuard;
use App\Support\FlightSearch\FareSelectionIntegrityValidator;
use App\Support\FlightSearch\SabreOfferFreshness;
use App\Support\Sabre\GdsPnrCreate\SabreGdsAutoPnrContextCompletionService;
use App\Support\Sabre\GdsPnrCreate\SabreGdsMixedCarrierFareBasisPayloadPreflight;
use App\Support\Sabre\GdsPnrCreate\SabreGdsPnrCreateStrategyEvidenceRecorder;
use App\Support\Sabre\GdsPnrCreate\SabreGdsPnrCreateStrategyRegistry;
use App\Support\Sabre\GdsPnrCreate\SabreGdsPnrCreateStrategySelector;
use App\Support\Sabre\SabreCpnrIatiWireSchemaValidator;
use App\Support\Sabre\SabreHostSellClassifier;
use App\Support\Sabre\SabreHostSellFingerprint;
use App\Support\Sabre\SabreHostSellResponseCollector;
use App\Support\Sabre\SabrePassengerRecordsApplicationResultDigest;
use App\Support\Sabre\SabrePassengerRecordsHttpValidationExcerptBuilder;
use App\Support\Sabre\SabrePassengerRecordsPayloadDigest;
use App\Support\Sabre\SabrePassengerRecordsV25WireSchemaValidator;
use App\Support\Sabre\SabrePnrAttemptStructureSnapshot;
use App\Support\Security\SensitiveDataRedactor;
use App\Support\Suppliers\SabreItineraryTimingValidator;
use App\Support\Suppliers\SabrePassengerRecordsMultiSegmentSellVerifier;
use App\Support\Suppliers\SabreTraditionalCpnrIatiWireStructureDiagnostic;
use App\Support\Suppliers\SupplierPnrFlagGate;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sabre booking orchestration: config gates for booking/ticketing/live HTTP
 * ({@see self::mayPerformLiveSabreBookingCall()}), normalized-offer validation/revalidation,
 * Trip Orders {@code /v1/trip/orders/createBooking} vs CreatePassengerNameRecordRQ-style payloads ({@see SabreBookingPayloadBuilder}),
 * application-level **HTTP 200** error parsing ({@see SabreBookingClient}), {@see self::effectiveSabreBookingSchema()} selection, and public-checkout
 * Sabre snapshot merge/finalize, admin {@see self::createSupplierBooking()} with attempt logging,
 * PNR persistence, retrieve/cancel/issue-ticket helpers, public checkout {@see self::runPublicReviewDryRun()} (validates then may call live {@code createBooking} when enabled),
 * and {@see self::inspectBookingPayloadShapeForCommand()}. **B21:** opt-in {@code allow_createbooking_without_revalidation} skips failed revalidation with audited {@code createBooking}.
 * **B74:** {@see self::isPnrOnlyPassengerRecordsLiveCreateEnabled()} skips mandatory pre-booking revalidation and B63/B65/B67 live blocks for PNR-only + ticketing off + Passenger Records schema (search fare; staff recheck before ticketing).
 * **B77:** {@see SabreSegmentFreshShopSellabilityService} — PNR-only + traditional wire: optional pre-POST OW shop per segment ({@code passenger_records_fresh_shop_guard_before_live}); blocks live CPNR when fresh shop does not confirm stored flight/time/RBD — {@code sabre_passenger_records_stale_shop_segment}, no ticketing.
 * **B75:** {@see self::inspectPassengerRecordsAirBookSegmentSellDiagnosticsForCommand()} + {@code sabre:inspect-booking-payload --segment-sell-diagnostics} — safe {@code AirBook} segment sell rows / gaps / route continuity + last attempt digests for **0411** triage (no raw wire, no PII).
 * **B78:** {@see self::inspectPassengerRecordsFareContextDiagnosticsForCommand()} + {@code sabre:inspect-booking-payload --fare-context-diagnostics} — snapshot fare/VC/pricing/component hints + root {@code AirPrice} {@code OptionalQualifiers} key list / PassengerType codes; {@code last_supplier_attempt_error} when stored summary/message contains {@code *NO FARES/RBD/CARRIER}-class text (no raw Sabre body, no PCC).
 * **B22/B23:** {@code createbooking_payload_style} Trip Orders {@code flightOffer}/{@code flightDetails} (nested or **B23** root/product-array wire); {@see self::previewRedactedTripOrdersCreateBookingForCommand()}, {@see self::previewTripOrdersWireJsonForInspectCommand()}, {@see self::compareTripOrdersCreateBookingStylesForCommand()} (**B24:** compare live send records safe {@code supplier_booking_attempts} + JSON report; HTTP 4xx digest on booking client).
 * **B38:** {@code compareBookingEndpointsForCommand()} matrix for Trip Orders vs legacy passenger-record paths; {@code agencyPhoneMissingClassifierForTripOrdersCompareRow()} + {@code previewRedactedTraditionalPnrForCommand()} (inspect-only traditional PNR preview). **2026-05-15:** {@code inspectTraditionalCpnrIatiStructureDiffForCommand()} — key-path diff vs frozen Binham IATI GDS CPNR template (no HTTP).
 * **B39:** Traditional style on {@code sabre:inspect-booking-payload --wire-preview-json} routes through CPNR wire + {@code summarizeTraditionalPnrWirePostBody}; {@code compareBookingEndpointsForCommand --send} validates traditional wire before POST; HTTP **403** rows add {@code entitlement_hint}.
 * **B40:** {@code bookingCapabilityReportForCommand()} aggregates Trip Orders vs traditional entitlement signals from stored attempts + inspect previews (no live POST); {@code compareBookingEndpointsForCommand --send} records {@code compare_booking_endpoint} attempts; agency-phone classifier adds profile/traditional-forbidden hints; blind-variant warning threshold on compare sends.
 * **B41:** {@code bookingCapabilityReportForCommand()} uses the same traditional CPNR wire inspect path as {@code previewTripOrdersWireJsonForInspectCommand(..., TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1)} for {@code traditional_pnr_preview_valid}; per-path entitlement defaults to {@code unknown_not_tested_after_b40} until a stored {@code compare_booking_endpoint} row exists; rollup adds {@code traditional_pnr_endpoints_forbidden}, unknown/forbidden counts, and conditional {@code recommended_next_action} when Trip Orders shows {@code AGENCY_PHONE_MISSING} and all four traditional paths last saw HTTP 403.
 * **B42:** {@code discoverBookingEndpointsProbeForConnection()} POSTs {@code {}} only (OAuth once) for an expanded REST path matrix; {@code expandedEndpointDiscoverySummaryFromRows()} rolls up counts + {@code possible_create_candidates}; optional JSON at {@code storage/app/sabre-booking-endpoint-discovery.json} is merged into {@code bookingCapabilityReportForCommand()} as {@code expanded_endpoint_discovery_summary} when present (no raw bodies).
 * **B43:** Passenger Records {@code ?mode=create} / {@code ?mode=update} on discovery + compare allowlist (query preserved on {@code endpoint_path} / URL); capability report tracks {@code /v2.*.0/passenger/records?mode=create} entitlement and adjusts {@code recommended_next_action} when {@code /v2.5.0/passenger/records?mode=create} is non-403; traditional CPNR wire adds {@code haltOnAirPriceError}, {@code targetCity} (PCC, never logged), root {@code AirPrice} array + {@code PriceRequestInformation.Retain} (**B47/B50:** not nested under {@code AirBook}), extra wire diagnostics.
 * **B46:** Traditional CPNR omits invalid root {@code haltOnAirBookError}; inspect contract expects {@code wire_has_halt_on_air_book_error=false}.
 * **B47:** {@code traditionalPnrWireInspectPreviewMatchesContract} requires {@code wire_airbook_has_air_price=false}, {@code wire_airbook_has_price_quote_information=false}, {@code wire_airbook_has_fare_breakdown_summary=false}, and {@code wire_has_root_air_price=true} (with existing {@code wire_has_air_price} mirror).
 * **B50:** Root {@code CreatePassengerNameRecordRQ.AirPrice} must be a JSON **array** of price rows (not an object); {@code wire_root_air_price_type=array}, {@code wire_root_air_price_retain_present=true}; {@code traditionalPnrWireInspectPreviewMatchesContract} enforces array + retain diagnostics.
 * **B48:** Traditional {@code AirBook.FlightSegment} forbids {@code CabinCode}/{@code ClassOfService}/{@code FareBasisCode}/{@code Number}; inspect contract expects the four {@code wire_flight_segment_has_*} flags {@code false} and {@code wire_flight_segment_has_res_book_desig_code=true}.
 * **B49:** {@code traditionalPnrWireInspectPreviewMatchesContract} requires {@code wire_flight_segment_number_in_party_valid=true} (Sabre REST expects {@code NumberInParty} string primitive on every segment).
 * **B52:** Traditional CPNR {@code PostProcessing} requires {@code EndTransaction} (minimal {@code Source.ReceivedFrom}), forbids {@code EndTransactionRQ}; keeps {@code RedisplayReservation}. {@code traditionalPnrWireInspectPreviewMatchesContract} requires {@code wire_post_processing_has_end_transaction=true}, {@code wire_post_processing_has_end_transaction_rq=false}, {@code wire_post_processing_has_redisplay_reservation=true} (ticketing unchanged).
 * **B53:** Traditional CPNR {@code AddRemark.Remark.Type} uses Sabre enum casing ({@code General}, not {@code GENERAL}); {@code traditionalPnrWireInspectPreviewMatchesContract} requires {@code wire_remark_type_enum_valid=true} when {@code wire_remarks_count} &gt; 0.
 * **B54:** Traditional CPNR forbids {@code SpecialReqDetails.SpecialService.Service}; {@code traditionalPnrWireInspectPreviewMatchesContract} requires {@code wire_special_service_has_service=false}.
 * **B55:** Traditional CPNR forbids {@code TravelItineraryAddInfo.AgencyInfo.Telephone}; {@code traditionalPnrWireInspectPreviewMatchesContract} requires {@code wire_agency_info_has_telephone=false}.
 * **B56:** Traditional CPNR requires {@code TravelItineraryAddInfo.CustomerInfo.PersonName} as a JSON **array**; {@code traditionalPnrWireInspectPreviewMatchesContract} requires {@code wire_customer_person_name_array_valid=true}.
 * **B57:** {@code compareBookingEndpointsForCommand --send} rows + {@code supplier_booking_attempts.safe_summary} include Passenger Records host-warning digest ({@code application_results_incomplete}, {@code host_warning_*}) and {@code wire_iati_*} template deltas (no raw bodies).
 * **B58:** Traditional CPNR {@code CustomerInfo.Email} rows require {@code Type=TO}; {@code traditionalPnrWireInspectPreviewMatchesContract} requires {@code wire_customer_email_type_valid=true} whenever {@code wire_has_email=true}.
 * **B59:** Root {@code AirPrice} {@code OptionalQualifiers.PricingQualifiers.PassengerType} rows (string {@code Quantity}); {@code traditionalPnrWireInspectPreviewMatchesContract} requires {@code wire_air_price_passenger_type_contract_valid=true} when named passengers exist on the wire.
 * **B60:** {@code summarizeTraditionalPnrWirePostBody} optional booking {@code meta} → {@code wire_segment_sell_context_*}, {@code wire_offer_snapshot_*}, {@code wire_offer_has_raw_sabre_identifiers}, {@code wire_offer_has_brand_candidates}, {@code wire_brand_candidate_keys_sanitized}; {@code compareBookingEndpointsForCommand --send} rows + {@code safe_summary} surface {@code wire_segment_sell_context_all_required_present}, {@code wire_offer_snapshot_present}, {@code wire_offer_has_brand_candidates} (diagnostics only).
 * **B61:** Gated {@code suppliers.sabre.traditional_cpnr_airbook_retry_redisplay} adds AirBook {@code RetryRebook}/{@code RedisplayReservation} on traditional CPNR wire; {@code traditionalPnrWireInspectPreviewMatchesContract} requires {@code wire_airbook_retry_redisplay_numeric_contract_valid} and {@code wire_airbook_retry_rebook_contract_valid} (**B61B:** {@code RetryRebook.Option}) when enabled and forbids helper keys when disabled; {@see SabreTraditionalCpnrIatiWireStructureDiagnostic::analyze} optionally suppresses those prefixes in {@code key_paths_only_in_iati_template}.
 * **B45:** Passenger Records HTTP 400 REST top-level {@code errorCode}/{@code message}/{@code status}/{@code type} + {@code timeStamp} presence merge into {@code digestBookingResponseJsonForProbe}; {@code compareBookingEndpointsForCommand --send} rows + {@code supplier_booking_attempts.safe_summary} carry capped {@code response_top_level_*}, {@code response_timestamp_present}, {@code request_body_non_empty}, {@code wire_has_create_passenger_name_record_rq}.
 * **Sprint 1A:** {@see self::buildSabreBookingContextDiagnosticSummary()} + log keys {@code sabre.booking.context_summary}, {@code sabre.booking.revalidation_summary}, {@code sabre.booking.pnr_attempt_summary}; incomplete context → {@code needs_review} / manual review (no endpoint/version changes).
 * **Sprint 2B:** {@see self::decidePassengerRecordsPayloadStyle()} — certified/config gating for {@code iati_like_cpnr_v2_4_gds} (no global style switch; forced config + ineligible → manual review, no live POST). CPNR eligibility is independent of {@code SABRE_TICKETING_ENABLED} / live ticketing flags.
 * **Sprint 8:** IATI-like Passenger Records POST + {@code SupplierBookingAttempt.safe_summary} use {@see resolvePassengerRecordsEndpointPathForAttempt()} / persisted {@code actual_endpoint_path} (not config fallback after attempt state clears).
 * **Sprint 3:** {@see self::decideSabreBookingFreshnessStrategy()} — IATI-like CPNR waives mandatory BFM pre-booking revalidation; prefers offer refresh/re-shop when configured; traditional path keeps config-driven revalidation.
 * **Sprint 6:** {@see self::assessIatiLikeCpnrFreshnessContextReadiness()} — certified IATI-like CPNR may proceed after successful offer refresh or revalidation meta without search handoff revalidation linkage ({@code iati_cpnr_context_ready_without_revalidation_linkage}); traditional v2.5 unchanged.
 * **E1C:** {@see self::controlledStaffOfferRefreshBeforePnr()} — admin/staff controlled PNR re-shops stale offer context before live create (public checkout unchanged).
 * **E3:** {@see SabreCreatePayloadSafeSummary} + {@see SabreBookingPayloadBuilder::summarizeCreatePayloadForAttempt()} persist safe create-payload segment summary on Passenger Records create attempts ({@code create_segments_summary}, {@code create_segment_source}); {@see SabreCreateAttemptSafeCompare} for Booking 40/41/43-style diffs (no raw CPNR bodies).
 * **E1D:** {@see SabreOfferFreshness::stampBookingMetaAfterSuccessfulOfferRefresh()} — after controlled refresh, stamps all freshness/revalidation meta keys checked by {@see SabreOfferFreshness::blocksBookingSubmit()} / {@see self::offerFreshnessBlockBeforePnr()}; {@see SabrePnrFailureClassifier::isControlledStaffOfferValidationRetryable()} unlocks admin retry after {@code sabre_offer_validation_failed}.
 * **D5 / S1B:** {@see self::maybeAutoSyncPnrItineraryAfterPublicCheckout()} delegates to {@see SabreGdsAutoPnrLifecycleService::maybeAutoSyncPnrItineraryAfterPnrCreate()} after any successful PNR persistence (public + staff); never blocks confirmation or removes PNR on sync failure.
 */
class SabreBookingService
{
    /** @var array<string, mixed>|null Request-scoped certified route for public checkout (no fallback chains). */
    private ?array $attemptCertifiedRouteSelection = null;

    /** @var array<string, mixed>|null Request-scoped Passenger Records style decision (Sprint 2B). */
    private ?array $attemptPassengerRecordsStyleDecision = null;

    /** @var array<string, mixed>|null Request-scoped BF7-J-OPS-FIX2 operational allow-NN HaltOnStatus decision. */
    private ?array $attemptOperationalAllowNnDecision = null;

    /** @var array<string, mixed> */
    private array $strategyChangedRetryAuditSlice = [];

    /** @var array<string, mixed>|null Request-scoped freshness / revalidation strategy (Sprint 3). */
    private ?array $attemptFreshnessStrategyDecision = null;

    public function __construct(
        protected SabreBookingPayloadBuilder $bookingPayloadBuilder,
        protected SabreBookingClient $bookingClient,
        protected SabreClient $sabreClient,
        protected SabreRevalidationPayloadBuilder $revalidationBuilder,
        protected SabreSegmentFreshShopSellabilityService $freshShopSellability,
        protected SabreBookingOfferRefreshService $offerRefresh,
        protected SabreCertifiedRouteSelector $certifiedRouteSelector,
        protected SupplierBookingPreflightGuard $preflightGuard,
        protected SabreControlledPnrApprovalOverrideGate $controlledPnrApprovalOverrideGate,
        protected SabreControlledPnrRetryAllowanceGate $controlledPnrRetryAllowanceGate,
        protected SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate $controlledPnrRetryAfterAirpriceVcFixAllowanceGate,
        protected SabreControlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate $controlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate,
        protected SabreControlledFinalPnrRetryAllowanceGate $controlledFinalPnrRetryAllowanceGate,
    ) {}

    /**
     * B74: PNR-only + ticketing disabled + Passenger Records schema — live CPNR from search fare; Sabre decides sellability.
     */
    public function isPnrOnlyPassengerRecordsLiveCreateEnabled(): bool
    {
        if ((string) config('suppliers.sabre.booking_mode', 'pnr_only') !== 'pnr_only') {
            return false;
        }
        if ($this->isTicketingEnabled()) {
            return false;
        }

        return $this->effectiveSabreBookingSchema() === 'create_passenger_name_record';
    }

    /**
     * B74: PNR-only Passenger Records skips mandatory pre-booking revalidation (search fare accepted; staff recheck before ticketing).
     */
    public function isPnrOnlyPreBookingRevalidationWaived(): bool
    {
        if (! $this->isPnrOnlyPassengerRecordsLiveCreateEnabled()) {
            return false;
        }

        return (bool) config('suppliers.sabre.pnr_only_waive_mandatory_revalidation', false);
    }

    /**
     * B77: Pre-POST OW shop segment confirmation (PNR-only mode, traditional Passenger Records wire only).
     *
     * @param  array<string, mixed>  $diagFlags  {@see SabreBookingPayloadBuilder::summarizeEnvelopeForDiagnostics()}
     */
    protected function shouldRunPassengerRecordsFreshShopGuardBeforeLive(array $diagFlags): bool
    {
        if (! (bool) config('suppliers.sabre.passenger_records_fresh_shop_guard_before_live', true)) {
            return false;
        }
        if ((string) config('suppliers.sabre.booking_mode', 'pnr_only') !== 'pnr_only') {
            return false;
        }
        if (($diagFlags['payload_schema'] ?? '') !== SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1) {
            return false;
        }

        return true;
    }

    /**
     * B77 + C4: Per-segment OW guard; certification may fall back to full-itinerary re-shop confirmation.
     *
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $options
     * @return array{block: bool, stale_segment_report: array<string, mixed>|null, fresh_shop_guard_result: array<string, mixed>}
     */
    protected function evaluatePassengerRecordsFreshShopGuard(
        array $offer,
        SupplierConnection $connection,
        ?int $bookingIdForDiagnostics,
        array $options,
        array $diagFlags,
    ): array {
        $emptyGuard = [
            'per_segment_guard_passed' => true,
            'per_segment_block_reason' => null,
            'full_itinerary_guard_attempted' => false,
            'full_itinerary_guard_passed' => false,
            'full_itinerary_guard_reason' => null,
            'allowed_by_full_itinerary_confirmation' => false,
        ];

        if (! $this->shouldRunPassengerRecordsFreshShopGuardBeforeLive($diagFlags)) {
            return ['block' => false, 'stale_segment_report' => null, 'fresh_shop_guard_result' => $emptyGuard];
        }

        $criteria = is_array($offer['search_criteria'] ?? null) ? $offer['search_criteria'] : [];
        $offerForGuard = $criteria !== [] ? array_merge($offer, ['search_criteria' => $criteria]) : $offer;
        if ($this->freshShopSellability->extractStoredSegmentsFromOfferSnapshot($offerForGuard) === []) {
            return ['block' => false, 'stale_segment_report' => null, 'fresh_shop_guard_result' => $emptyGuard];
        }

        $reports = $this->freshShopSellability->segmentReportsForOffer($offerForGuard, $connection);
        $staleReport = null;
        foreach ($reports as $rep) {
            if (! $this->freshShopSellability->segmentPassesPnrFreshShopGuard($rep)) {
                $staleReport = $rep;
                break;
            }
        }

        if ($staleReport === null) {
            return [
                'block' => false,
                'stale_segment_report' => null,
                'fresh_shop_guard_result' => array_merge($emptyGuard, [
                    'per_segment_guard_passed' => true,
                ]),
            ];
        }

        $guardResult = [
            'per_segment_guard_passed' => false,
            'per_segment_block_reason' => (string) ($staleReport['probable_issue'] ?? 'segment_guard_failed'),
            'full_itinerary_guard_attempted' => false,
            'full_itinerary_guard_passed' => false,
            'full_itinerary_guard_reason' => null,
            'allowed_by_full_itinerary_confirmation' => false,
        ];

        $certificationFallback = ($options['certification_full_itinerary_fallback'] ?? false) === true;
        if ($certificationFallback
            && $bookingIdForDiagnostics !== null
            && $this->segmentFailureEligibleForFullItineraryFallback($staleReport)) {
            $booking = Booking::query()->find($bookingIdForDiagnostics);
            if ($booking !== null) {
                $guardResult['full_itinerary_guard_attempted'] = true;
                $validation = $this->offerRefresh->validateCurrentSnapshotAgainstFreshItinerary($booking);
                if (($validation['can_trust_for_pnr'] ?? false) === true) {
                    $guardResult['full_itinerary_guard_passed'] = true;
                    $guardResult['full_itinerary_guard_reason'] = 'full_itinerary_confirmed';
                    $guardResult['allowed_by_full_itinerary_confirmation'] = true;

                    Log::notice('sabre.booking.passenger_records_fresh_shop_full_itinerary_override', [
                        'booking_id' => $bookingIdForDiagnostics,
                        'stale_segment_index' => $staleReport['index'] ?? null,
                        'probable_issue' => $staleReport['probable_issue'] ?? null,
                    ]);

                    return [
                        'block' => false,
                        'stale_segment_report' => $staleReport,
                        'fresh_shop_guard_result' => $guardResult,
                    ];
                }
                $reasons = is_array($validation['reasons'] ?? null) ? $validation['reasons'] : [];
                $guardResult['full_itinerary_guard_reason'] = $reasons !== []
                    ? implode(',', array_slice(array_map('strval', $reasons), 0, 8))
                    : 'full_itinerary_not_confirmed';
            }
        }

        return [
            'block' => true,
            'stale_segment_report' => $staleReport,
            'fresh_shop_guard_result' => $guardResult,
        ];
    }

    /**
     * @param  array<string, mixed>  $segmentReport
     */
    protected function segmentFailureEligibleForFullItineraryFallback(array $segmentReport): bool
    {
        if (($segmentReport['fresh_flight_found'] ?? false) !== true) {
            return false;
        }
        if (($segmentReport['fresh_same_time_found'] ?? false) !== true) {
            return false;
        }

        return (string) ($segmentReport['probable_issue'] ?? '') === 'booking_class_mismatch';
    }

    public function prebookingRevalidationSkippedReason(): ?string
    {
        $fresh = $this->attemptFreshnessStrategyDecision;
        if (is_array($fresh) && ($fresh['revalidation_skipped'] ?? false) === true) {
            $reason = trim((string) ($fresh['revalidation_skip_reason'] ?? ''));
            if ($reason !== '') {
                return $reason;
            }
        }

        return $this->isPnrOnlyPreBookingRevalidationWaived()
            ? 'pnr_only_ticketing_disabled'
            : null;
    }

    protected function gdsFareValidationBlockedCustomerMessage(): string
    {
        return (string) __('Sabre fare validation could not be completed, so the PNR/reservation was not attempted.');
    }

    protected function isSabreGdsPassengerRecordsCheckoutPath(?array $styleDecision = null): bool
    {
        if ($this->effectiveSabreBookingSchema() !== 'create_passenger_name_record') {
            return false;
        }

        $styleDecision = is_array($styleDecision) ? $styleDecision : [];
        $selectedStyle = trim((string) (
            $styleDecision['selected_payload_style']
            ?? $styleDecision['selected_style']
            ?? ''
        ));

        return ($styleDecision['iati_like_selected'] ?? false) !== true
            && ! SabreBookingPayloadBuilder::isIatiLikeCpnrV24GdsWireStyle($selectedStyle);
    }

    /**
     * @param  array<string, mixed>  $refreshSlice
     */
    protected function gdsSafeOfferRefreshSatisfiesPrePnrRevalidation(
        ?Booking $booking,
        array $refreshSlice,
        bool $contextReady,
        bool $fareChanged,
    ): bool {
        if (! $contextReady || $fareChanged) {
            return false;
        }

        if (($refreshSlice['requires_customer_confirmation'] ?? false) === true
            && ($refreshSlice['accepted'] ?? false) !== true) {
            return false;
        }

        $refreshResult = strtolower(trim((string) ($refreshSlice['refresh_result'] ?? '')));
        $refreshStatus = strtolower(trim((string) ($refreshSlice['refresh_status'] ?? '')));
        $refreshOk = $refreshResult === 'ok'
            || in_array($refreshStatus, ['refreshed', 'success'], true)
            || ($refreshSlice['refresh_or_revalidation_satisfied'] ?? false) === true;

        if (! $refreshOk) {
            return false;
        }

        if ($booking !== null) {
            $selected = (float) ($booking->selected_fare_total ?? 0);
            $revalidated = (float) ($booking->revalidated_fare_total ?? 0);
            if ($selected > 0 && $revalidated > 0 && abs($selected - $revalidated) > 0.009) {
                return false;
            }
        }

        return true;
    }

    protected function contextualRevalidationFailureMessage(string $tripOrdersMessage, ?int $http = null): string
    {
        if ($this->isSabreGdsPassengerRecordsCheckoutPath($this->attemptPassengerRecordsStyleDecision)) {
            return $this->gdsFareValidationBlockedCustomerMessage();
        }

        if ($http !== null) {
            return 'Sabre revalidation returned HTTP '.$http.'; Trip Orders booking was not attempted.';
        }

        return $tripOrdersMessage;
    }

    /**
     * @param  array<string, mixed>  $baseResult
     * @param  array<string, mixed>  $revalidationOutcome
     * @return array<string, mixed>
     */
    protected function enrichGdsPrePnrRevalidationFailureResult(array $baseResult, array $revalidationOutcome): array
    {
        if (! $this->isSabreGdsPassengerRecordsCheckoutPath($this->attemptPassengerRecordsStyleDecision)) {
            return $baseResult;
        }

        $strategySlice = $this->passengerRecordsStyleStrategySliceForDiagnostics();
        $customerMsg = $this->gdsFareValidationBlockedCustomerMessage();

        return array_merge($baseResult, $strategySlice, [
            'message' => $customerMsg,
            'customer_safe_message' => $customerMsg,
            'error_code' => 'sabre_gds_fare_validation_failed',
            'reason_code' => 'sabre_gds_fare_validation_failed',
            'pnr_block_reason_code' => 'sabre_gds_fare_validation_failed',
            'pnr_attempted' => false,
            'live_call_attempted' => false,
            'http_status' => null,
            'revalidation_http_status' => $revalidationOutcome['http_status'] ?? null,
            'revalidation_endpoint_path' => $revalidationOutcome['endpoint_path'] ?? null,
            'revalidation_application_status' => $revalidationOutcome['application_status'] ?? null,
            'revalidation_reason_code' => (string) ($revalidationOutcome['reason_code'] ?? 'sabre_revalidation_failed'),
            'freshness_satisfied' => false,
            'freshness_source' => 'revalidation',
            'revalidation_attempted' => true,
            'provider' => SupplierProvider::Sabre->value,
            'distribution_channel' => 'gds',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function passengerRecordsStyleStrategySliceForDiagnostics(): array
    {
        $decision = is_array($this->attemptPassengerRecordsStyleDecision)
            ? $this->attemptPassengerRecordsStyleDecision
            : [];
        $used = trim((string) (
            $decision['selected_payload_style']
            ?? $decision['selected_strategy_code']
            ?? ''
        ));
        if ($used === '') {
            return [];
        }

        return array_filter([
            'pnr_strategy_selected' => $used,
            'pnr_strategy_used' => $used,
            'payload_schema' => $used,
            'selected_payload_style' => $used,
            'endpoint_path' => $decision['selected_endpoint_path'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected function gdsCheckoutOperationalDiagnosticSlice(array $result, ?Booking $booking = null): array
    {
        if ($this->effectiveSabreBookingSchema() !== 'create_passenger_name_record') {
            return [];
        }

        $fresh = is_array($this->attemptFreshnessStrategyDecision) ? $this->attemptFreshnessStrategyDecision : [];
        $refreshSlice = $this->offerRefreshFreshnessSliceFromBooking($booking);
        $meta = $booking !== null && is_array($booking->meta) ? $booking->meta : [];
        $freshnessSatisfied = ($result['freshness_satisfied'] ?? $fresh['freshness_satisfied'] ?? false) === true;
        $freshnessSource = trim((string) ($result['freshness_source'] ?? $fresh['freshness_source'] ?? 'none'));
        $brand = trim((string) (
            data_get($result, 'selected_brand_code')
            ?? data_get($meta, 'sabre_booking_context.selected_brand_code')
            ?? data_get($meta, 'selected_fare_family_option.brand_code')
            ?? ''
        ));
        $fareBasisCodes = data_get($meta, 'sabre_booking_context.fare_basis_codes_by_segment')
            ?? data_get($meta, 'selected_fare_family_option.fare_basis_codes_by_segment');
        $fareBasis = is_array($fareBasisCodes) && $fareBasisCodes !== []
            ? trim((string) ($fareBasisCodes[0] ?? ''))
            : '';

        return array_filter([
            'provider' => SupplierProvider::Sabre->value,
            'distribution_channel' => trim((string) data_get($meta, 'distribution_channel', 'gds')) ?: 'gds',
            'freshness_satisfied' => $freshnessSatisfied,
            'freshness_source' => $freshnessSource !== '' && $freshnessSource !== 'none' ? $freshnessSource : null,
            'offer_refresh_attempted' => ($refreshSlice['refresh_attempted'] ?? false) === true,
            'offer_refresh_status' => $refreshSlice['refresh_status'] ?? null,
            'offer_refresh_result' => $refreshSlice['refresh_result'] ?? null,
            'revalidation_attempted' => (bool) ($result['revalidation_attempted'] ?? false),
            'revalidation_http_status' => $result['revalidation_http_status'] ?? null,
            'revalidation_reason_code' => $result['revalidation_reason_code'] ?? $result['reason_code'] ?? null,
            'revalidation_skipped_reason' => $fresh['revalidation_skip_reason'] ?? null,
            'selected_brand_code' => $brand !== '' ? $brand : null,
            'selected_fare_basis' => $fareBasis !== '' ? $fareBasis : null,
            'selected_total' => $booking?->selected_fare_total,
            'pnr_attempted' => (bool) ($result['pnr_attempted'] ?? ($result['live_call_attempted'] ?? false)),
            'pnr_strategy_selected' => $result['pnr_strategy_selected'] ?? null,
            'pnr_strategy_used' => $result['pnr_strategy_used'] ?? null,
            'pnr_block_reason_code' => $result['pnr_block_reason_code'] ?? null,
            'customer_safe_message' => $result['customer_safe_message'] ?? ($result['message'] ?? null),
        ], static fn ($v) => $v !== null && $v !== '');
    }

    public function isRevalidationBeforeBookingEnabled(): bool
    {
        if ($this->isPnrOnlyPreBookingRevalidationWaived()) {
            return false;
        }

        return (bool) config('suppliers.sabre.revalidate_before_booking', false);
    }

    /**
     * When true with {@see self::isRevalidationBeforeBookingEnabled()}, a failed pre-{@code createBooking} revalidation
     * does not block Trip Orders booking (B21 — audited; keep false outside controlled tests).
     */
    public function isAllowCreateBookingWithoutRevalidation(): bool
    {
        if (app()->environment('production')) {
            return false;
        }

        return (bool) config('suppliers.sabre.allow_createbooking_without_revalidation', false);
    }

    /**
     * Resolved booking payload family: {@code create_passenger_name_record} or {@code trip_orders_create_booking}.
     */
    public function effectiveSabreBookingSchema(): string
    {
        if ($this->attemptCertifiedRouteSelection !== null) {
            $schema = trim((string) ($this->attemptCertifiedRouteSelection['booking_schema'] ?? ''));
            if ($schema !== '') {
                return $schema;
            }
        }

        $explicit = trim((string) config('suppliers.sabre.booking_schema', ''));
        if ($explicit === 'passenger_records_create_pnr') {
            $explicit = 'create_passenger_name_record';
        }
        if ($explicit !== '' && in_array($explicit, ['create_passenger_name_record', 'trip_orders_create_booking'], true)) {
            return $explicit;
        }
        $path = (string) config('suppliers.sabre.booking_path', '');
        if (str_contains($path, '/v1/trip/orders/createBooking')) {
            return 'trip_orders_create_booking';
        }

        return 'create_passenger_name_record';
    }

    /**
     * @param  array<string, mixed>  $apiDraft  {@see SabreBookingPayloadBuilder::buildInternalDraft()} without \_valid
     * @param  array<string, mixed>  $offer  Normalized offer (ticketing hints)
     * @return array<string, mixed>
     */
    protected function buildLiveBookingEnvelope(
        array $apiDraft,
        array $offer,
        ?SupplierConnection $connection = null,
        ?int $bookingId = null,
    ): array {
        $hints = $this->ticketingHintsFromOffer($offer);

        if ($this->effectiveSabreBookingSchema() === 'trip_orders_create_booking') {
            return $this->bookingPayloadBuilder->buildTripOrdersCreateBookingEnvelope($apiDraft, $hints);
        }

        $style = $this->resolvePassengerRecordsPayloadStyleForAttempt();

        if ($connection === null) {
            $connId = (int) ($apiDraft['supplier_connection_id'] ?? 0);
            if ($connId > 0) {
                $connection = SupplierConnection::query()->find($connId);
            }
        }

        $apiDraft = $this->applyOperationalAllowNnPolicyToApiDraft($apiDraft, $connection, $bookingId);

        return $this->bookingPayloadBuilder->buildPassengerRecordsCpnrWireForStyle($apiDraft, $hints, $style);
    }

    /**
     * BF7-J-OPS-FIX2: CERT operational allow-NN — set {@code _ota_cert_allow_nn_diagnostic} on draft when policy passes.
     *
     * @param  array<string, mixed>  $apiDraft
     * @return array<string, mixed>
     */
    protected function applyOperationalAllowNnPolicyToApiDraft(
        array $apiDraft,
        ?SupplierConnection $connection,
        ?int $bookingId,
    ): array {
        $this->attemptOperationalAllowNnDecision = null;

        if ($this->effectiveSabreBookingSchema() !== 'create_passenger_name_record') {
            return $apiDraft;
        }

        $style = $this->resolvePassengerRecordsPayloadStyleForAttempt();
        $endpoint = $this->resolvePassengerRecordsEndpointPathForAttempt();
        $booking = $bookingId !== null ? Booking::query()->find($bookingId) : null;

        $decision = app(SabreCpnrOperationalAllowNnPolicy::class)->evaluate(
            $apiDraft,
            $style,
            $endpoint,
            $connection,
            $booking,
        );
        $this->attemptOperationalAllowNnDecision = $decision;

        if (($decision['should_omit_nn_wn'] ?? false) === true) {
            $apiDraft['_ota_cert_allow_nn_diagnostic'] = true;
        }

        return $apiDraft;
    }

    /**
     * @return array<string, mixed>
     */
    protected function operationalAllowNnDecisionDiagnosticSlice(): array
    {
        if (! is_array($this->attemptOperationalAllowNnDecision)) {
            return [];
        }

        return array_filter([
            'allow_nn_cert_operational' => ($this->attemptOperationalAllowNnDecision['allow_nn_cert_operational'] ?? false) === true,
            'halt_on_status_nn_omitted' => ($this->attemptOperationalAllowNnDecision['halt_on_status_nn_omitted'] ?? false) === true,
            'halt_on_status_policy' => is_string($this->attemptOperationalAllowNnDecision['halt_on_status_policy'] ?? null)
                ? (string) $this->attemptOperationalAllowNnDecision['halt_on_status_policy']
                : null,
            'operational_allow_nn_block_reason' => is_string($this->attemptOperationalAllowNnDecision['block_reason'] ?? null)
                && trim((string) $this->attemptOperationalAllowNnDecision['block_reason']) !== ''
                ? (string) $this->attemptOperationalAllowNnDecision['block_reason']
                : null,
        ], static fn ($value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  'public_checkout'|'admin'|'staff'|'system'|'manual'  $source
     * @return array<string, mixed>
     */
    protected function resolveStrategyChangedRetryAuditSlice(
        Booking $booking,
        bool $allowControlledStaffPnr,
        string $source,
    ): array {
        $booking->loadMissing('supplierBookingAttempts');
        $meaningfulAttempt = SupplierBookingAttemptResolution::resolveLatestMeaningfulCreateAttempt(
            $booking->supplierBookingAttempts,
        );
        $gate = app(SabreOperationalAllowNnStrategyChangedRetryGate::class);
        if (! $gate->allows($booking, $meaningfulAttempt, $allowControlledStaffPnr, $source)) {
            return [];
        }

        return $gate->buildRetryPolicyAuditSlice($meaningfulAttempt);
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $ep
     * @param  array<string, mixed>  $digestSlice
     * @return array<string, mixed>
     */
    protected function sabreBookingApplicationErrorAttemptSafeSummary(
        array $result,
        array $ep,
        array $digestSlice,
        string $source,
        array $safeKeys = [],
    ): array {
        $applicationDigest = is_array($result['passenger_records_application_digest'] ?? null)
            ? $result['passenger_records_application_digest']
            : [];
        $applicationDigestSlice = $applicationDigest !== []
            ? app(SabrePassengerRecordsApplicationResultDigest::class)->attemptSafeSummarySlice($applicationDigest)
            : [];

        $mixedProof = is_array($result['mixed_carrier_preflight_proof'] ?? null)
            ? $result['mixed_carrier_preflight_proof']
            : [];

        $hostContext = self::buildSabreHostClassificationContextFromResult($result);
        $liveAttempted = ($result['live_call_attempted'] ?? false) === true;

        $summary = SensitiveDataRedactor::redact(array_merge([
            'source' => $source,
            'http_status' => $result['http_status'] ?? null,
            'endpoint_path' => $ep['endpoint_path'] ?? null,
            'booking_schema' => (string) ($result['booking_schema'] ?? $this->effectiveSabreBookingSchema()),
            'payload_schema' => self::resolvePayloadSchemaForSummary($result),
            'ticketing_disabled' => true,
            'ticketing_pending' => true,
            'live_call_attempted' => $liveAttempted,
            'pnr_attempted' => ($result['pnr_attempted'] ?? $liveAttempted) === true,
            'public_auto_pnr_attempted' => ($result['public_auto_pnr_attempted'] ?? $liveAttempted) === true,
            'response_safe_keys' => $safeKeys,
            'passenger_count' => (int) ($result['passenger_count'] ?? 0),
            'segment_count' => (int) ($result['segment_count'] ?? 0),
        ], $digestSlice, $applicationDigestSlice, $mixedProof, SabreHostErrorClassifier::buildPersistedSlice(
            $hostContext,
            array_intersect_key($result, array_flip([
                'live_call_attempted', 'booking_schema', 'payload_schema', 'segment_count', 'passenger_count',
            ])),
        ), self::passengerRecordsEndpointSliceFromResult($result), self::createPayloadAndStructureSliceFromResult($result), $this->strategyChangedRetryAuditSlice, array_intersect_key($ep, array_flip(['endpoint_host', 'endpoint_path']))));

        return $this->appendAutoPnrContextCompletionToAttemptSummary($summary, $result);
    }

    /**
     * Passenger Records wire style: Sprint 2B {@see decidePassengerRecordsPayloadStyle()} when set; else certified route or config default.
     */
    protected function resolvePassengerRecordsPayloadStyleForAttempt(): string
    {
        if (is_array($this->attemptPassengerRecordsStyleDecision)) {
            $strategyCode = trim((string) ($this->attemptPassengerRecordsStyleDecision['selected_strategy_code'] ?? ''));
            if ($strategyCode !== ''
                && app(SabreGdsPnrCreateStrategyRegistry::class)->isSupported($strategyCode)) {
                return $strategyCode;
            }

            $selected = trim((string) (
                $this->attemptPassengerRecordsStyleDecision['selected_payload_style']
                ?? $this->attemptPassengerRecordsStyleDecision['selected_strategy_code']
                ?? $this->attemptPassengerRecordsStyleDecision['selected_style']
                ?? ''
            ));
            if ($selected !== '') {
                return $this->bookingPayloadBuilder->normalizePassengerRecordsBookingPayloadStyle($selected);
            }
        }

        if (is_array($this->attemptPassengerRecordsStyleDecision)) {
            $selection = is_array($this->attemptPassengerRecordsStyleDecision['gds_strategy_selection'] ?? null)
                ? $this->attemptPassengerRecordsStyleDecision['gds_strategy_selection']
                : [];
            $blocked = is_array($selection['blocked_strategies'] ?? null) ? $selection['blocked_strategies'] : [];
            if (in_array(SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS, $blocked, true)) {
                if ($this->attemptCertifiedRouteSelection !== null) {
                    $fromRoute = trim((string) ($this->attemptCertifiedRouteSelection['payload_style'] ?? ''));
                    if (SabreBookingPayloadBuilder::isIatiLikeCpnrV24GdsWireStyle($fromRoute)) {
                        return $this->bookingPayloadBuilder->normalizePassengerRecordsBookingPayloadStyle(
                            SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1,
                        );
                    }
                }
            }
        }

        if ($this->attemptCertifiedRouteSelection !== null) {
            $fromRoute = trim((string) ($this->attemptCertifiedRouteSelection['payload_style'] ?? ''));
            if ($fromRoute !== '') {
                return $this->bookingPayloadBuilder->normalizePassengerRecordsBookingPayloadStyle($fromRoute);
            }
        }

        return $this->bookingPayloadBuilder->resolvePassengerRecordsBookingPayloadStyle();
    }

    /**
     * Passenger Records REST path for the current attempt (style decision → certified route → style/config fallback).
     */
    protected function resolvePassengerRecordsEndpointPathForAttempt(): string
    {
        if (is_array($this->attemptPassengerRecordsStyleDecision)) {
            $selected = trim((string) ($this->attemptPassengerRecordsStyleDecision['selected_endpoint_path'] ?? ''));
            if ($selected !== '') {
                return $selected;
            }
        }
        if (is_array($this->attemptCertifiedRouteSelection)) {
            $fromRoute = trim((string) ($this->attemptCertifiedRouteSelection['endpoint_path'] ?? ''));
            if ($fromRoute !== '') {
                return $fromRoute;
            }
        }

        return $this->bookingPayloadBuilder->resolvePassengerRecordsCreateEndpointPath(
            $this->resolvePassengerRecordsPayloadStyleForAttempt()
        );
    }

    /**
     * Safe endpoint diagnostics for createBooking results and {@see SupplierBookingAttempt} summaries (no payloads/PII).
     *
     * @return array<string, mixed>
     */
    protected function passengerRecordsEndpointPersistenceSlice(?string $actualEndpointPath = null): array
    {
        $decision = is_array($this->attemptPassengerRecordsStyleDecision)
            ? $this->attemptPassengerRecordsStyleDecision
            : [];
        $selected = trim((string) ($decision['selected_endpoint_path'] ?? ''));
        $fallbackStyle = trim((string) (
            $decision['fallback_style']
            ?? $decision['fallback_payload_style']
            ?? SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1
        ));
        $configuredTraditional = $this->bookingPayloadBuilder->resolvePassengerRecordsCreateEndpointPath(
            SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1
        );
        $configuredFallback = $this->bookingPayloadBuilder->resolvePassengerRecordsCreateEndpointPath(
            $fallbackStyle !== '' ? $fallbackStyle : SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1
        );
        $actual = trim((string) ($actualEndpointPath ?? ''));
        if ($actual === '') {
            $actual = $this->resolvePassengerRecordsEndpointPathForAttempt();
        }
        $payloadStyle = trim((string) (
            $decision['selected_payload_style']
            ?? $decision['selected_style']
            ?? ''
        ));

        return array_filter([
            'endpoint_path' => $actual !== '' ? $actual : null,
            'actual_endpoint_path' => $actual !== '' ? $actual : null,
            'selected_endpoint_path' => $selected !== '' ? $selected : null,
            'configured_endpoint_path' => $configuredFallback !== '' ? $configuredFallback : null,
            'configured_traditional_endpoint_path' => $configuredTraditional !== '' ? $configuredTraditional : null,
            'selected_payload_style' => $payloadStyle !== '' ? $payloadStyle : null,
            'iati_like_selected' => ($decision['iati_like_selected'] ?? false) === true ? true : null,
        ], static fn ($v) => $v !== null);
    }

    /**
     * @param  array<string, mixed>  $result  Output of {@see createBooking()}
     * @return array<string, mixed>
     */
    protected function resolveEndpointSummaryPreferringBookingResult(array $result, int $connectionId): array
    {
        $summary = $this->resolveBookingEndpointSummary($connectionId);
        $actual = trim((string) ($result['actual_endpoint_path'] ?? $result['endpoint_path'] ?? ''));
        if ($actual !== '') {
            $summary['endpoint_path'] = $actual;
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected static function passengerRecordsEndpointSliceFromResult(array $result): array
    {
        return array_intersect_key($result, array_flip([
            'endpoint_path',
            'actual_endpoint_path',
            'selected_endpoint_path',
            'configured_endpoint_path',
            'configured_traditional_endpoint_path',
            'selected_payload_style',
            'iati_like_selected',
        ]));
    }

    /**
     * @param  array<string, mixed>  $apiDraft
     * @return array<string, mixed>
     */
    protected function buildCreatePayloadSafeSummaryForLiveAttempt(
        array $envelope,
        array $offer,
        array $apiDraft,
        ?int $bookingId,
        string $endpointPath,
        string $payloadStyle,
    ): array {
        if ($this->effectiveSabreBookingSchema() !== 'create_passenger_name_record') {
            return [];
        }

        $snapshotSegs = array_values(is_array($offer['segments'] ?? null) ? $offer['segments'] : []);
        $b65 = is_array($apiDraft['_b65_multi_segment_prep'] ?? null) ? $apiDraft['_b65_multi_segment_prep'] : [];
        $summary = app(SabreCreatePayloadSafeSummary::class)->summarize(
            $envelope,
            $snapshotSegs,
            [
                'create_endpoint_path' => $endpointPath,
                'create_payload_style' => $payloadStyle,
                'create_segment_source' => app(SabreCreatePayloadSafeSummary::class)->resolveSegmentSource($bookingId, $offer),
                'create_segment_order_repaired' => (bool) ($b65['segment_order_repaired_for_sell'] ?? false),
                'create_date_repair_applied' => (bool) ($b65['date_repair_applied'] ?? false),
            ],
        );

        $merged = is_array($summary) ? array_merge($summary, $this->operationalAllowNnDecisionDiagnosticSlice()) : [];

        $strategyCode = trim((string) (
            is_array($this->attemptPassengerRecordsStyleDecision)
                ? ($this->attemptPassengerRecordsStyleDecision['selected_payload_style']
                    ?? $this->attemptPassengerRecordsStyleDecision['selected_strategy_code']
                    ?? '')
                : ''
        ));
        if ($strategyCode === '') {
            $strategyCode = $payloadStyle;
        }
        $merged['create_payload_style'] = $strategyCode;
        $merged['payload_schema'] = $strategyCode;
        $merged['selected_payload_style'] = $strategyCode;
        $merged['endpoint_path'] = $endpointPath;
        if (is_array($this->attemptPassengerRecordsStyleDecision['gds_strategy_selection'] ?? null)) {
            $merged['gds_strategy_selection'] = $this->attemptPassengerRecordsStyleDecision['gds_strategy_selection'];
        }

        $wire = $this->bookingPayloadBuilder->stripOtaInternalKeysFromBookingWire($envelope);
        $cpnr = is_array($wire['CreatePassengerNameRecordRQ'] ?? null) ? $wire['CreatePassengerNameRecordRQ'] : [];
        $payloadDigest = app(SabrePassengerRecordsPayloadDigest::class)->digest($wire, [
            'endpoint_path' => $endpointPath,
            'payload_style' => $payloadStyle,
            'payload_schema' => 'create_passenger_name_record',
            'version' => is_scalar($cpnr['version'] ?? null) ? (string) $cpnr['version'] : null,
            'passenger_count' => count(is_array($apiDraft['passengers'] ?? null) ? $apiDraft['passengers'] : []),
            'selected_context_segments' => $this->selectedContextSegmentsForPayloadDigest($apiDraft, $offer),
            'api_draft' => $apiDraft,
            'validating_carrier' => $apiDraft['validating_carrier'] ?? $offer['validating_carrier'] ?? null,
            'brand_code' => $this->resolveBrandCodeForPayloadDigest($offer, $apiDraft),
            'missing_revalidation_linkage' => false,
            'legacy_revalidation_signal_used' => false,
            'stale_offer_context' => false,
        ]);
        $merged[SabrePassengerRecordsPayloadDigest::SLIM_DIGEST_KEY] = $payloadDigest;

        if (SabreBookingPayloadBuilder::isPassengerRecordsV25GdsWireStyle($payloadStyle)) {
            $merged['v25_airprice_pricing_qualifiers_digest'] = $this->bookingPayloadBuilder
                ->summarizeV25AirPricePricingQualifiersStructuralDigest(
                    $wire,
                    $this->v25BrandContextForQualifierDigest($apiDraft),
                );
        }

        $merged = array_merge(
            $merged,
            app(SabrePnrAttemptStructureSnapshot::class)->buildFromWire($envelope, [
                'endpoint_path' => $endpointPath,
                'payload_schema' => $strategyCode !== '' ? $strategyCode : $payloadStyle,
                'selected_payload_style' => $strategyCode !== '' ? $strategyCode : $payloadStyle,
                'structure_snapshot_source' => 'live_pre_call',
            ]),
        );

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected static function pnrStructureSnapshotSliceFromResult(array $result): array
    {
        $summary = is_array($result['create_payload_safe_summary'] ?? null)
            ? $result['create_payload_safe_summary']
            : [];

        return app(SabrePnrAttemptStructureSnapshot::class)->sliceForPersistence($summary);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected static function pnrResponseStructureSliceFromResult(array $result): array
    {
        if (($result['live_call_attempted'] ?? false) !== true) {
            return [];
        }
        $digest = array_merge(
            self::passengerRecordsApplicationDigestSliceFromResult($result),
            array_intersect_key($result, array_flip([
                'http_status', 'pnr', 'response_error_codes',
            ])),
        );
        if ($digest === []) {
            return [];
        }

        return [
            'safe_response_structure' => app(SabrePnrAttemptStructureSnapshot::class)->buildResponseStructure($digest),
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected static function createPayloadSafeSummarySliceFromResult(array $result): array
    {
        $summary = is_array($result['create_payload_safe_summary'] ?? null)
            ? $result['create_payload_safe_summary']
            : [];
        if ($summary === []) {
            return [];
        }

        return app(SabreCreatePayloadSafeSummary::class)->sliceForAttemptPersistence($summary);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected static function createPayloadAndStructureSliceFromResult(array $result): array
    {
        return array_merge(
            self::createPayloadSafeSummarySliceFromResult($result),
            self::pnrStructureSnapshotSliceFromResult($result),
            self::pnrResponseStructureSliceFromResult($result),
        );
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $createSummary
     * @return array<string, mixed>
     */
    protected function withCreatePayloadSafeSummary(array $result, array $createSummary): array
    {
        if ($createSummary === []) {
            return $result;
        }

        return array_merge($result, ['create_payload_safe_summary' => $createSummary]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function controlledF9jRecordedSlice(bool $recorded): array
    {
        return $recorded ? ['controlled_f9j_retry_recorded' => true] : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function controlledF9lSchemaRecoveryRecordedSlice(bool $recorded): array
    {
        return $recorded ? ['controlled_f9l_schema_recovery_recorded' => true] : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function controlledF9qFinalRetryRecordedSlice(bool $recorded): array
    {
        return $recorded ? ['controlled_f9q_final_retry_recorded' => true] : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function controlledRetryRecordedSlices(bool $f9jRecorded, bool $f9lRecorded, bool $f9qRecorded = false): array
    {
        return array_merge(
            $this->controlledF9jRecordedSlice($f9jRecorded),
            $this->controlledF9lSchemaRecoveryRecordedSlice($f9lRecorded),
            $this->controlledF9qFinalRetryRecordedSlice($f9qRecorded),
        );
    }

    /**
     * Sprint 2B: Safe selection/gating for IATI-like CPNR v2.4 GDS wire (no raw PCC, payload, credentials, or PII).
     *
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $apiDraft
     * @param  array<string, mixed>|null  $routeSelection  {@see SabreCertifiedRouteSelector::selectForBooking()}
     * @return array<string, mixed>
     */
    public function decidePassengerRecordsPayloadStyle(
        array $offer,
        array $apiDraft,
        ?SupplierConnection $connection = null,
        ?array $routeSelection = null,
    ): array {
        $traditional = SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1;
        $iatiStyle = SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS;
        $configStyle = trim((string) config('suppliers.sabre.booking_payload_style', ''));
        $forcedByConfig = $configStyle === $iatiStyle;
        $certifiedGdsEnabled = (bool) config('suppliers.sabre.cpnr_iati_style_certified_gds_enabled', false);

        $fallbackStyle = $traditional;
        if ($routeSelection !== null) {
            $fromRoute = trim((string) ($routeSelection['payload_style'] ?? ''));
            if ($fromRoute !== '') {
                $fallbackStyle = $this->bookingPayloadBuilder->normalizePassengerRecordsBookingPayloadStyle($fromRoute);
            }
        } elseif ($configStyle !== '' && ! $forcedByConfig) {
            $fallbackStyle = $this->bookingPayloadBuilder->normalizePassengerRecordsBookingPayloadStyle($configStyle);
        }

        $fallbackEndpoint = $this->bookingPayloadBuilder->resolvePassengerRecordsCreateEndpointPath($fallbackStyle);
        $considerIati = $forcedByConfig
            || ($certifiedGdsEnabled
                && $routeSelection !== null
                && $this->routeSelectionAllowsIatiLikeCpnrConsideration($routeSelection));

        $base = [
            'selected_style' => $fallbackStyle,
            'selected_endpoint_path' => $fallbackEndpoint,
            'selected_endpoint_version' => SabreBookingPayloadBuilder::isIatiLikeCpnrV24GdsWireStyle($fallbackStyle) ? '2.4.0' : '2.5.0',
            'eligible' => false,
            'fallback_style' => $fallbackStyle,
            'reason_code' => '',
            'reasons' => [],
            'certified_route_result' => $this->safeCertifiedRouteResultSlice($routeSelection),
            'gds_compatible' => self::inferSabreDistributionChannel($offer) !== 'ndc',
            'supplier_connection_present' => (int) ($apiDraft['supplier_connection_id'] ?? $offer['supplier_connection_id'] ?? 0) > 0,
            'supplier_connection_resolved' => $connection !== null,
            'pcc_present' => false,
            'target_city_present' => false,
            'rbd_complete' => false,
            'segment_context_complete' => false,
            'iati_like_available' => true,
            'forced_by_config' => $forcedByConfig,
            'selected_by_certified_route' => false,
            'manual_review_required' => false,
            'iati_like_selection_considered' => $considerIati,
            'iati_like_selected' => false,
            'iati_like_eligible' => false,
            'iati_like_reason_code' => '',
            'cpnr_required_blocks_present' => [],
            'cpnr_required_blocks_missing' => [],
            'booking_path_requires_revalidation' => $this->isRevalidationBeforeBookingEnabled(),
            'iati_style_expects_revalidation_waiver_or_refresh' => true,
        ];

        if (! $considerIati) {
            return $base;
        }

        $reasons = [];
        $pcc = $this->safePccFingerprintFromDraft($apiDraft, $connection);
        $base['pcc_present'] = (bool) ($pcc['pcc_present'] ?? false);
        $base['target_city_present'] = $base['pcc_present'];

        if ($base['gds_compatible'] !== true) {
            $reasons[] = 'unsupported_distribution_channel';
        }
        if ($base['supplier_connection_present'] !== true) {
            $reasons[] = 'missing_supplier_connection_id';
        }
        if ($connection !== null && $connection->provider !== SupplierProvider::Sabre) {
            $reasons[] = 'supplier_connection_unresolved';
            $base['supplier_connection_resolved'] = false;
        } elseif ($connection === null && $base['supplier_connection_present'] === true) {
            $reasons[] = 'supplier_connection_unresolved';
            $base['supplier_connection_resolved'] = false;
        }
        if ($base['pcc_present'] !== true) {
            $reasons[] = 'pcc_missing';
        }
        if ($base['target_city_present'] !== true) {
            $reasons[] = 'target_city_missing';
        }

        $segs = is_array($apiDraft['segments'] ?? null) ? array_values($apiDraft['segments']) : [];
        if ($segs === []) {
            $reasons[] = 'segment_context_incomplete';
        }
        $bookable = self::segmentBookableContextCoverage($segs);
        $structural = self::segmentStructuralContextCoverage($segs);
        $sellable = self::segmentSellableFieldCoverage($segs);
        $base['rbd_complete'] = (int) ($bookable['rbd_total_segments'] ?? 0) > 0
            && (int) ($bookable['rbd_missing_count'] ?? 0) === 0;
        $base['segment_context_complete'] = (int) ($sellable['segment_sellable_total'] ?? 0) > 0
            && (int) ($sellable['segment_sellable_incomplete_count'] ?? 0) === 0;
        if (! $base['rbd_complete']) {
            $reasons[] = 'rbd_missing_all_segments';
        }
        if (! $base['segment_context_complete']) {
            $reasons[] = 'segment_context_incomplete';
        }

        if ($routeSelection !== null && ($routeSelection['live_booking_allowed'] ?? false) !== true) {
            $category = (string) ($routeSelection['category'] ?? '');
            $controlledConnecting = $category === SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_CONNECTING_SAME_CARRIER_GDS
                && SabreCertifiedRouteSelector::isConnectingSameCarrierGdsEnabled();
            if (! $controlledConnecting) {
                $reasons[] = 'non_certified_route';
            }
        }
        if ($routeSelection !== null && ! $this->routeSelectionAllowsIatiLikeCpnrConsideration($routeSelection)) {
            $category = (string) ($routeSelection['category'] ?? '');
            if ($category === SabreCertifiedRouteSelector::CATEGORY_RETURN
                || $category === SabreCertifiedRouteSelector::CATEGORY_MULTI_CITY) {
                $reasons[] = 'unsupported_trip_type';
            } elseif (! in_array($category, [
                SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_DIRECT_SAME_CARRIER,
                SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_CONNECTING_SAME_CARRIER_GDS,
            ], true)) {
                $reasons[] = 'non_certified_route';
            }
        }
        if ($routeSelection !== null
            && (string) ($routeSelection['category'] ?? '') === SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_CONNECTING_SAME_CARRIER_GDS) {
            if (count($segs) !== 2) {
                $reasons[] = 'connecting_segment_count_not_two';
            }
            if (! SabreCertifiedRouteSelector::isConnectingSameCarrierGdsEnabled()) {
                $reasons[] = 'connecting_same_carrier_gds_disabled';
            }
            $fbTotal = count($segs);
            $fbPresent = 0;
            foreach ($segs as $segRow) {
                if (! is_array($segRow)) {
                    continue;
                }
                if (trim((string) ($segRow['fare_basis_code'] ?? '')) !== '') {
                    $fbPresent++;
                }
            }
            if ($fbTotal > 0 && $fbPresent < $fbTotal) {
                $reasons[] = 'fare_basis_incomplete';
            }
        }

        $eligiblePreWire = $reasons === [];
        $blockDiag = [];
        if ($eligiblePreWire) {
            $hints = $this->ticketingHintsFromOffer($offer);
            $wire = $this->bookingPayloadBuilder->buildPassengerRecordsCpnrWireForStyle($apiDraft, $hints, $iatiStyle);
            $blockDiag = $this->bookingPayloadBuilder->summarizeEnvelopeForDiagnostics($wire);
            $blocks = $this->bookingPayloadBuilder->assessIatiLikeCpnrRequiredBlocks($blockDiag);
            $base['cpnr_required_blocks_present'] = $blocks['cpnr_required_blocks_present'];
            $base['cpnr_required_blocks_missing'] = $blocks['cpnr_required_blocks_missing'];
            if ($blocks['cpnr_required_blocks_missing'] !== []) {
                $reasons[] = 'cpnr_required_blocks_missing';
            }
            if (($blockDiag['brand_code_present'] ?? false) !== true) {
                foreach ($segs as $segRow) {
                    if (is_array($segRow) && trim((string) ($segRow['fare_basis_code'] ?? '')) !== '') {
                        $reasons[] = 'branded_fare_context_incomplete';
                        break;
                    }
                }
            }
        }

        $eligible = $reasons === [];
        $reasonCode = $eligible ? '' : ($reasons[0] ?? 'iati_style_not_eligible');
        if (! $eligible && $forcedByConfig) {
            $reasonCode = 'iati_style_not_eligible';
        }

        $selectedByCertified = $eligible
            && $certifiedGdsEnabled
            && ! $forcedByConfig
            && $routeSelection !== null
            && $this->routeSelectionAllowsIatiLikeCpnrConsideration($routeSelection);

        if ($eligible) {
            $selectedStyle = $iatiStyle;
            $selectedEndpoint = $this->bookingPayloadBuilder->resolvePassengerRecordsCreateEndpointPath($iatiStyle);
            $manualReview = false;
        } else {
            $selectedStyle = $fallbackStyle;
            $selectedEndpoint = $fallbackEndpoint;
            $manualReview = $forcedByConfig;
        }

        return array_merge($base, [
            'selected_style' => $selectedStyle,
            'selected_endpoint_path' => $selectedEndpoint,
            'selected_endpoint_version' => SabreBookingPayloadBuilder::isIatiLikeCpnrV24GdsWireStyle($selectedStyle) ? '2.4.0' : '2.5.0',
            'eligible' => $eligible,
            'fallback_style' => $fallbackStyle,
            'reason_code' => $reasonCode,
            'reasons' => array_values(array_unique($reasons)),
            'selected_by_certified_route' => $selectedByCertified,
            'manual_review_required' => $manualReview,
            'iati_like_selected' => SabreBookingPayloadBuilder::isIatiLikeCpnrV24GdsWireStyle($selectedStyle),
            'iati_like_eligible' => $eligible,
            'iati_like_reason_code' => $eligible ? '' : $reasonCode,
            'selected_payload_style' => $selectedStyle,
            'fallback_payload_style' => $fallbackStyle,
        ]);
    }

    /**
     * Sprint 3: safe freshness / revalidation decision before live Passenger Records or Trip Orders booking (no raw payloads, PCC, or PII).
     *
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $apiDraft
     * @param  array<string, mixed>|null  $styleDecision  Output of {@see decidePassengerRecordsPayloadStyle()} when available
     * @return array<string, mixed>
     */
    public function decideSabreBookingFreshnessStrategy(
        array $offer,
        array $apiDraft,
        ?SupplierConnection $connection = null,
        ?array $styleDecision = null,
        ?Booking $booking = null,
    ): array {
        $styleDecision = is_array($styleDecision) ? $styleDecision : [];
        $selectedStyle = trim((string) (
            $styleDecision['selected_payload_style']
            ?? $styleDecision['selected_style']
            ?? ''
        ));
        $endpointPath = trim((string) ($styleDecision['selected_endpoint_path'] ?? ''));
        if ($endpointPath === '') {
            $connId = (int) ($apiDraft['supplier_connection_id'] ?? $offer['supplier_connection_id'] ?? 0);
            $endpointPath = (string) ($this->resolveBookingEndpointSummary($connId)['endpoint_path'] ?? '');
        }

        $iatiLikeSelected = ($styleDecision['iati_like_selected'] ?? false) === true
            || SabreBookingPayloadBuilder::isIatiLikeCpnrV24GdsWireStyle($selectedStyle);
        $registryFreshnessTrusted = in_array(
            (string) ($styleDecision['selection_reason'] ?? ''),
            [
                SabreGdsPnrCreateStrategySelector::REASON_KNOWN_GOOD,
                SabreGdsPnrCreateStrategySelector::REASON_CERTIFIED_ROUTE_MATRIX,
            ],
            true,
        ) && ($styleDecision['eligible'] ?? false) === true && $iatiLikeSelected;
        $allowWithout = $this->isAllowCreateBookingWithoutRevalidation();
        $refreshBeforePnr = (bool) config('suppliers.sabre.refresh_offer_before_public_pnr', true);
        $configRevalidate = (bool) config('suppliers.sabre.revalidate_before_booking', false);
        $bookingMode = (string) config('suppliers.sabre.booking_mode', 'pnr_only');
        $bookingSchema = $this->effectiveSabreBookingSchema();
        $refreshSlice = $this->offerRefreshFreshnessSliceFromBooking($booking);

        $handoff = is_array($offer['sabre_booking_context'] ?? null) ? $offer['sabre_booking_context'] : [];
        if ($handoff === []) {
            $handoff = is_array(data_get($offer, 'raw_payload.sabre_booking_context'))
                ? data_get($offer, 'raw_payload.sabre_booking_context')
                : [];
        }
        $contextReady = ($styleDecision['segment_context_complete'] ?? true) === true
            && ($styleDecision['rbd_complete'] ?? true) === true
            && ($styleDecision['supplier_connection_present'] ?? (int) ($apiDraft['supplier_connection_id'] ?? 0) > 0) === true;
        $iatiContextAssess = $iatiLikeSelected
            ? $this->assessIatiLikeCpnrFreshnessContextReadiness(
                $styleDecision,
                $handoff,
                $refreshSlice,
                $refreshBeforePnr,
                $allowWithout,
            )
            : [
                'iati_context_ready_for_booking_payload' => false,
                'iati_context_ready_without_revalidation_linkage' => false,
                'iati_context_waived_revalidation_linkage' => false,
            ];
        if ($iatiLikeSelected) {
            $contextReady = ($iatiContextAssess['iati_context_ready_for_booking_payload'] ?? false) === true;
        }

        $fareChanged = ($refreshSlice['fare_changed'] ?? false) === true;
        $priceSnapshotPresent = is_array($offer['fare_breakdown'] ?? null)
            || is_array($apiDraft['fare'] ?? null)
            || trim((string) data_get($offer, 'raw_payload.fare_option_key', '')) !== '';

        if ($iatiLikeSelected) {
            $strategy = 'iati_cpnr_refresh_or_waiver';
            $revalidationRequired = false;
            $revalidationSkipped = true;
            $revalidationSkipReason = 'iati_cpnr_revalidation_waived';
            $refreshRequired = $refreshBeforePnr;
        } else {
            $revalidationRequired = $this->isRevalidationBeforeBookingEnabled();
            $revalidationSkipped = ! $revalidationRequired;
            $revalidationSkipReason = $revalidationSkipped
                ? ($this->isPnrOnlyPreBookingRevalidationWaived()
                    ? 'pnr_only_ticketing_disabled'
                    : ($configRevalidate ? null : 'revalidation_disabled_by_config'))
                : null;
            $strategy = $revalidationRequired ? 'traditional_bfm_revalidation' : 'traditional_config_skip';
            $refreshRequired = false;
        }

        $freshnessSatisfied = false;
        $freshnessSource = 'none';
        if ($iatiLikeSelected
            && $registryFreshnessTrusted
            && $this->effectiveSabreBookingSchema() === 'create_passenger_name_record'
            && $this->gdsSafeOfferRefreshSatisfiesPrePnrRevalidation($booking, $refreshSlice, $contextReady, $fareChanged)) {
            $revalidationRequired = false;
            $revalidationSkipped = true;
            $revalidationSkipReason = 'safe_offer_refresh_satisfied';
            $strategy = 'gds_offer_refresh_satisfied';
            $freshnessSatisfied = true;
            $freshnessSource = 'offer_refresh';
        } elseif (! $iatiLikeSelected
            && $this->isSabreGdsPassengerRecordsCheckoutPath($styleDecision)
            && $this->gdsSafeOfferRefreshSatisfiesPrePnrRevalidation($booking, $refreshSlice, $contextReady, $fareChanged)) {
            $revalidationRequired = false;
            $revalidationSkipped = true;
            $revalidationSkipReason = 'safe_offer_refresh_satisfied';
            $strategy = 'gds_offer_refresh_satisfied';
            $freshnessSatisfied = true;
            $freshnessSource = 'offer_refresh';
        }

        $refreshAttempted = ($refreshSlice['refresh_attempted'] ?? false) === true;
        $refreshAvailable = ($refreshSlice['refresh_available'] ?? false) === true;
        $refreshResult = isset($refreshSlice['refresh_result']) ? (string) $refreshSlice['refresh_result'] : null;
        $refreshStatus = isset($refreshSlice['refresh_status']) ? (string) $refreshSlice['refresh_status'] : null;

        $manualReviewRequired = false;
        $blocksBooking = false;
        $reasonCode = '';

        if ($iatiLikeSelected) {
            if ($registryFreshnessTrusted && $freshnessSatisfied) {
                $manualReviewRequired = false;
                $blocksBooking = false;
                $reasonCode = '';
            } elseif ($fareChanged && ($refreshSlice['requires_customer_confirmation'] ?? false) === true
                && ($refreshSlice['accepted'] ?? false) !== true) {
                $manualReviewRequired = true;
                $blocksBooking = true;
                $reasonCode = 'fare_changed_review_required';
            } elseif (! $contextReady) {
                $manualReviewRequired = true;
                $blocksBooking = true;
                $reasonCode = ($styleDecision['eligible'] ?? true) === false
                    ? 'iati_cpnr_context_not_ready'
                    : 'context_incomplete_manual_review';
            } elseif ($refreshRequired && ! $refreshAttempted && ! $allowWithout) {
                $manualReviewRequired = true;
                $blocksBooking = true;
                $reasonCode = 'refresh_required_but_missing';
            } elseif ($refreshRequired && $refreshAttempted && $refreshStatus === 'unavailable' && ! $allowWithout && ! $contextReady) {
                $manualReviewRequired = true;
                $blocksBooking = true;
                $reasonCode = 'freshness_strategy_failed';
            } elseif ($refreshRequired && ! $refreshAvailable && $allowWithout && $contextReady) {
                $refreshResult = 'refresh_not_available_allowed_by_config';
            } elseif ($refreshRequired && ($refreshAttempted || $refreshAvailable)) {
                if ($refreshResult === null || $refreshResult === '') {
                    $refreshResult = 'refresh_offer_before_pnr';
                }
            }
        } elseif ($revalidationRequired) {
            $revalidationSkipReason = null;
        }

        return array_filter([
            'strategy' => $strategy,
            'revalidation_required' => $revalidationRequired,
            'revalidation_attempted' => false,
            'revalidation_skipped' => $revalidationSkipped,
            'revalidation_skip_reason' => $revalidationSkipReason,
            'refresh_required' => $refreshRequired,
            'refresh_attempted' => $refreshAttempted,
            'refresh_available' => $refreshAvailable,
            'refresh_result' => $refreshResult,
            'refresh_status' => $refreshStatus,
            'blocks_booking' => $blocksBooking,
            'manual_review_required' => $manualReviewRequired,
            'reason_code' => $reasonCode !== '' ? $reasonCode : null,
            'selected_payload_style' => $selectedStyle !== '' ? $selectedStyle : null,
            'selected_endpoint_path' => $endpointPath !== '' ? $endpointPath : null,
            'booking_mode' => $bookingMode,
            'booking_schema' => $bookingSchema,
            'allow_without_revalidation' => $allowWithout,
            'refresh_offer_before_public_pnr' => $refreshBeforePnr,
            'iati_like_selected' => $iatiLikeSelected,
            'iati_like_expects_revalidation_waiver_or_refresh' => $iatiLikeSelected,
            'fare_changed' => $fareChanged,
            'price_snapshot_present' => $priceSnapshotPresent,
            'context_ready_for_booking_payload' => $contextReady,
            'iati_context_ready_for_booking_payload' => $iatiLikeSelected
                ? (($iatiContextAssess['iati_context_ready_for_booking_payload'] ?? false) === true)
                : null,
            'iati_context_ready_without_revalidation_linkage' => $iatiLikeSelected
                ? (($iatiContextAssess['iati_context_ready_without_revalidation_linkage'] ?? false) === true)
                : null,
            'iati_context_waived_revalidation_linkage' => $iatiLikeSelected
                ? (($iatiContextAssess['iati_context_waived_revalidation_linkage'] ?? false) === true)
                : null,
            'iati_freshness_ready_reason' => $iatiLikeSelected
                && (($iatiContextAssess['iati_context_waived_revalidation_linkage'] ?? false) === true)
                ? 'iati_cpnr_context_ready_without_revalidation_linkage'
                : null,
            'freshness_satisfied' => $freshnessSatisfied,
            'freshness_source' => $freshnessSource !== 'none' ? $freshnessSource : null,
        ], static fn ($v) => $v !== null);
    }

    /**
     * Sprint 6: certified IATI-like CPNR context readiness for freshness (may waive search handoff revalidation linkage after successful refresh).
     *
     * @param  array<string, mixed>  $styleDecision
     * @param  array<string, mixed>  $handoff
     * @param  array<string, mixed>  $refreshSlice
     * @return array{
     *     iati_context_ready_for_booking_payload: bool,
     *     iati_context_ready_without_revalidation_linkage: bool,
     *     iati_context_waived_revalidation_linkage: bool
     * }
     */
    protected function assessIatiLikeCpnrFreshnessContextReadiness(
        array $styleDecision,
        array $handoff,
        array $refreshSlice,
        bool $refreshRequired,
        bool $allowWithoutRefresh,
    ): array {
        $notReady = [
            'iati_context_ready_for_booking_payload' => false,
            'iati_context_ready_without_revalidation_linkage' => false,
            'iati_context_waived_revalidation_linkage' => false,
        ];

        $selectedStyle = trim((string) (
            $styleDecision['selected_payload_style']
            ?? $styleDecision['selected_style']
            ?? ''
        ));
        $iatiSelected = ($styleDecision['iati_like_selected'] ?? false) === true
            || SabreBookingPayloadBuilder::isIatiLikeCpnrV24GdsWireStyle($selectedStyle);
        if (! $iatiSelected) {
            return $notReady;
        }

        $iatiEligible = ($styleDecision['iati_like_eligible'] ?? $styleDecision['eligible'] ?? false) === true;
        $registryFreshnessTrusted = in_array(
            (string) ($styleDecision['selection_reason'] ?? ''),
            [
                SabreGdsPnrCreateStrategySelector::REASON_KNOWN_GOOD,
                SabreGdsPnrCreateStrategySelector::REASON_CERTIFIED_ROUTE_MATRIX,
            ],
            true,
        ) && trim((string) ($styleDecision['selected_strategy_code'] ?? $styleDecision['selected_payload_style'] ?? '')) !== '';
        if ($registryFreshnessTrusted && ($styleDecision['eligible'] ?? false) === true) {
            if (($refreshSlice['fare_changed'] ?? false) === true) {
                return $notReady;
            }

            $refreshOk = ! $refreshRequired
                || $allowWithoutRefresh
                || (($refreshSlice['refresh_or_revalidation_satisfied'] ?? false) === true);

            if (! $refreshOk) {
                return $notReady;
            }

            return [
                'iati_context_ready_for_booking_payload' => true,
                'iati_context_ready_without_revalidation_linkage' => true,
                'iati_context_waived_revalidation_linkage' => true,
            ];
        }

        $certified = is_array($styleDecision['certified_route_result'] ?? null)
            ? $styleDecision['certified_route_result']
            : [];
        $routeStatus = (string) ($certified['route_status'] ?? '');
        $routeCertified = in_array($routeStatus, [
            SabreCertifiedRouteSelector::STATUS_CERTIFIED,
            SabreCertifiedRouteSelector::STATUS_CONTROLLED_CERTIFIED,
        ], true);
        $cpnrMissing = is_array($styleDecision['cpnr_required_blocks_missing'] ?? null)
            ? $styleDecision['cpnr_required_blocks_missing']
            : [];

        $structuralOk = $iatiEligible
            && $routeCertified
            && ($styleDecision['gds_compatible'] ?? false) === true
            && ($styleDecision['supplier_connection_present'] ?? false) === true
            && ($styleDecision['pcc_present'] ?? false) === true
            && ($styleDecision['target_city_present'] ?? false) === true
            && ($styleDecision['rbd_complete'] ?? false) === true
            && ($styleDecision['segment_context_complete'] ?? false) === true
            && $cpnrMissing === [];

        if (! $structuralOk) {
            return $notReady;
        }

        if (($refreshSlice['fare_changed'] ?? false) === true) {
            return $notReady;
        }

        $refreshOk = ! $refreshRequired
            || $allowWithoutRefresh
            || (($refreshSlice['refresh_or_revalidation_satisfied'] ?? false) === true);

        if (! $refreshOk) {
            return $notReady;
        }

        if (($handoff['ready_for_booking_payload'] ?? false) === true) {
            return [
                'iati_context_ready_for_booking_payload' => true,
                'iati_context_ready_without_revalidation_linkage' => false,
                'iati_context_waived_revalidation_linkage' => false,
            ];
        }

        return [
            'iati_context_ready_for_booking_payload' => true,
            'iati_context_ready_without_revalidation_linkage' => true,
            'iati_context_waived_revalidation_linkage' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function offerRefreshFreshnessSliceFromBooking(?Booking $booking): array
    {
        if ($booking === null) {
            return [
                'refresh_attempted' => false,
                'refresh_available' => false,
                'refresh_result' => null,
                'refresh_status' => null,
                'fare_changed' => false,
                'requires_customer_confirmation' => false,
                'accepted' => false,
            ];
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $status = trim((string) ($meta['offer_refresh_status'] ?? ''));
        $revalidationStatus = strtolower(trim((string) ($meta['revalidation_status'] ?? '')));
        $selectedOfferRevalidation = strtolower(trim((string) ($meta['selected_offer_revalidation_status'] ?? '')));
        $revalidationSuccess = $revalidationStatus === 'success'
            || $selectedOfferRevalidation === 'success'
            || $booking->fare_revalidated_at !== null;
        $offerRefreshed = in_array($status, ['refreshed', 'success'], true);
        $refreshAttempted = $status !== '' || $revalidationSuccess;
        $requiresConfirmation = SabreOfferRefreshAcceptance::requiresAcceptance($booking);
        $accepted = ($meta[SabreOfferRefreshAcceptance::META_ACCEPTED] ?? false) === true;
        $fareChanged = ($meta[SabreOfferRefreshAcceptance::META_PRICE_CHANGED] ?? false) === true;
        $refreshAvailable = $offerRefreshed || $revalidationSuccess;
        $refreshStatus = $offerRefreshed
            ? $status
            : ($revalidationSuccess ? 'revalidated' : ($refreshAttempted ? $status : null));
        $refreshResult = match (true) {
            $offerRefreshed && ! $fareChanged => 'ok',
            $offerRefreshed && $fareChanged => 'fare_changed',
            $revalidationSuccess && ! $fareChanged => 'ok',
            $refreshAttempted => 'attempted',
            default => null,
        };
        $refreshOrRevalidationSatisfied = ! $fareChanged && (
            $offerRefreshed
            || ($offerRefreshed && $revalidationSuccess)
            || $revalidationSuccess
        );

        return [
            'refresh_attempted' => $refreshAttempted,
            'refresh_available' => $refreshAvailable,
            'refresh_result' => $refreshResult,
            'refresh_status' => $refreshStatus !== '' ? $refreshStatus : null,
            'fare_changed' => $fareChanged,
            'requires_customer_confirmation' => $requiresConfirmation,
            'accepted' => $accepted,
            'revalidation_success' => $revalidationSuccess,
            'offer_refreshed' => $offerRefreshed,
            'refresh_or_revalidation_satisfied' => $refreshOrRevalidationSatisfied,
        ];
    }

    /**
     * @param  array<string, mixed>  $decision
     * @return array<string, mixed>
     */
    public function freshnessStrategyDiagnosticSlice(array $decision): array
    {
        return array_intersect_key($decision, array_flip([
            'strategy',
            'freshness_strategy',
            'revalidation_required',
            'revalidation_skipped',
            'revalidation_skip_reason',
            'refresh_required',
            'refresh_available',
            'refresh_attempted',
            'refresh_result',
            'refresh_status',
            'fare_changed',
            'freshness_blocks_booking',
            'manual_review_required',
            'selected_payload_style',
            'iati_like_selected',
            'iati_like_expects_revalidation_waiver_or_refresh',
            'reason_code',
            'context_ready_for_booking_payload',
            'iati_context_ready_for_booking_payload',
            'iati_context_ready_without_revalidation_linkage',
            'iati_context_waived_revalidation_linkage',
            'iati_freshness_ready_reason',
            'allow_without_revalidation',
            'refresh_offer_before_public_pnr',
            'freshness_satisfied',
            'freshness_source',
        ]));
    }

    protected function customerStaffConfirmationBookingMessage(): string
    {
        return (string) __('This booking requires staff confirmation before supplier confirmation.');
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $apiDraft
     */
    protected function initializeFreshnessStrategyDecisionForAttempt(
        array $offer,
        array $apiDraft,
        ?SupplierConnection $connection,
        ?int $bookingIdForDiagnostics,
        array $options = [],
    ): void {
        $booking = $bookingIdForDiagnostics !== null && $bookingIdForDiagnostics > 0
            ? Booking::query()->find($bookingIdForDiagnostics)
            : null;
        $decision = $this->decideSabreBookingFreshnessStrategy(
            $offer,
            $apiDraft,
            $connection,
            $this->attemptPassengerRecordsStyleDecision,
            $booking,
        );
        if ($this->isScenarioRunnerPnrCreateActive($options)) {
            $decision = $this->applyScenarioRunnerFreshnessOverride($decision, $options);
        }
        $this->attemptFreshnessStrategyDecision = app(SabreGdsAutoPnrLifecycleService::class)
            ->reconcileObsoleteIatiWaiverFlags($decision, $booking);
        $this->attemptFreshnessStrategyDecision['freshness_strategy'] = $this->attemptFreshnessStrategyDecision['strategy'] ?? null;
        $this->attemptFreshnessStrategyDecision['freshness_blocks_booking'] = $this->attemptFreshnessStrategyDecision['blocks_booking'] ?? false;
    }

    /**
     * @param  array<string, mixed>  $decision
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function applyScenarioRunnerFreshnessOverride(array $decision, array $options): array
    {
        $completion = is_array($options['auto_pnr_context_completion'] ?? null)
            ? $options['auto_pnr_context_completion']
            : [];
        $style = is_array($this->attemptPassengerRecordsStyleDecision)
            ? $this->attemptPassengerRecordsStyleDecision
            : [];
        $iatiSelected = ($style['iati_like_selected'] ?? false) === true;
        $status = trim((string) ($completion['auto_pnr_context_completion_status'] ?? ''));
        $completionReady = ($completion['public_auto_pnr_attempt_ready'] ?? false) === true
            && in_array($status, [
                SabreGdsAutoPnrContextCompletionService::STATUS_COMPLETE,
                SabreGdsAutoPnrContextCompletionService::STATUS_REPAIRED,
            ], true);

        if (! $iatiSelected || ! $completionReady) {
            return $decision;
        }

        $decision['revalidation_required'] = false;
        $decision['revalidation_skipped'] = true;
        $decision['revalidation_skip_reason'] = 'scenario_runner_context_completion';
        $decision['strategy'] = 'scenario_runner_context_completion';
        $decision['freshness_source'] = 'scenario_runner_context_completion';
        $decision['blocks_booking'] = false;
        $decision['manual_review_required'] = false;
        $decision['reason_code'] = '';

        return $decision;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function passengerRecordsFreshnessManualReviewGateForLiveAttempt(
        ?int $bookingIdForDiagnostics,
        ?string $bookingRefEarly,
        int $paxCount,
        int $segCount,
        int $connId,
        string $selectedOffer,
        float $fareAmt,
        string $fareCur,
    ): ?array {
        $decision = $this->attemptFreshnessStrategyDecision;
        if (! is_array($decision) || ($decision['manual_review_required'] ?? false) !== true) {
            return null;
        }

        $reasonCode = trim((string) ($decision['reason_code'] ?? 'freshness_strategy_failed'));
        if ($reasonCode === '') {
            $reasonCode = 'freshness_strategy_failed';
        }
        $ep = $this->resolveBookingEndpointSummary($connId);
        $contextSummary = array_merge(
            $this->freshnessStrategyDiagnosticSlice($decision),
            $this->passengerRecordsStyleDecisionDiagnosticSlice(
                is_array($this->attemptPassengerRecordsStyleDecision) ? $this->attemptPassengerRecordsStyleDecision : []
            ),
            [
                'booking_id' => $bookingIdForDiagnostics,
                'booking_reference' => $bookingRefEarly,
                'safe_reason_code' => $reasonCode,
            ],
        );
        $this->logSabreBookingContextSummary($contextSummary);
        $this->logSabrePnrAttemptSummaryFromLiveResult(
            $bookingIdForDiagnostics,
            $bookingRefEarly,
            $contextSummary,
            ['http_status' => null, 'message' => 'Sabre booking freshness check requires manual review.'],
            false,
            true,
            $reasonCode,
        );

        return array_merge([
            'success' => false,
            'status' => 'needs_review',
            'message' => $this->customerStaffConfirmationBookingMessage(),
            'live_call_attempted' => false,
            'live_call_allowed' => true,
            'passenger_count' => $paxCount,
            'segment_count' => $segCount,
            'supplier_connection_id' => $connId,
            'selected_offer_id' => $selectedOffer,
            'fare_amount' => $fareAmt,
            'fare_currency' => $fareCur,
            'pnr' => null,
            'provider_booking_id' => null,
            'provider_status' => null,
            'http_status' => null,
            'reason_code' => $reasonCode,
            'error_code' => $reasonCode,
            'booking_schema' => $this->effectiveSabreBookingSchema(),
            'payload_schema' => (string) ($decision['selected_payload_style'] ?? $this->expectedSabrePayloadSchemaHintForFailures()),
            'ticketing_enabled' => false,
            'booking_context_summary' => $contextSummary,
            'freshness_strategy_decision' => $this->freshnessStrategyDiagnosticSlice($decision),
        ], array_intersect_key($ep, array_flip(['endpoint_host', 'endpoint_path'])));
    }

    /**
     * @param  array<string, mixed>|null  $routeSelection
     * @return array<string, mixed>|null
     */
    protected function safeCertifiedRouteResultSlice(?array $routeSelection): ?array
    {
        if ($routeSelection === null) {
            return null;
        }

        return [
            'category' => $routeSelection['category'] ?? null,
            'route_status' => $routeSelection['route_status'] ?? null,
            'endpoint_path' => $routeSelection['endpoint_path'] ?? null,
            'payload_style' => $routeSelection['payload_style'] ?? null,
            'live_booking_allowed' => $routeSelection['live_booking_allowed'] ?? null,
            'iati_like_preference_enabled' => $routeSelection['iati_like_preference_enabled'] ?? null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return array{segment_sellable_total: int, segment_sellable_complete_count: int, segment_sellable_incomplete_count: int}
     */
    protected static function segmentSellableFieldCoverage(array $segments): array
    {
        $total = 0;
        $complete = 0;
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $total++;
            $origin = strtoupper(trim((string) ($seg['origin'] ?? '')));
            $dest = strtoupper(trim((string) ($seg['destination'] ?? '')));
            $dep = trim((string) ($seg['departure_at'] ?? $seg['depart_at'] ?? ''));
            $arr = trim((string) ($seg['arrival_at'] ?? $seg['arrive_at'] ?? ''));
            $carrier = strtoupper(trim((string) ($seg['airline_code'] ?? $seg['carrier'] ?? $seg['marketing_carrier'] ?? '')));
            $flight = trim((string) ($seg['flight_number'] ?? ''));
            if ($origin !== '' && $dest !== '' && $dep !== '' && $arr !== '' && $carrier !== '' && $flight !== '') {
                $complete++;
            }
        }

        return [
            'segment_sellable_total' => $total,
            'segment_sellable_complete_count' => $complete,
            'segment_sellable_incomplete_count' => max(0, $total - $complete),
        ];
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $apiDraft
     * @param  array<string, mixed>|null  $routeSelection
     */
    protected function initializePassengerRecordsStyleDecisionForAttempt(
        array $offer,
        array $apiDraft,
        ?array $routeSelection,
        array $options = [],
    ): void {
        $this->attemptPassengerRecordsStyleDecision = null;
        if ($this->effectiveSabreBookingSchema() !== 'create_passenger_name_record') {
            return;
        }

        $connId = (int) ($apiDraft['supplier_connection_id'] ?? 0);
        $connection = $connId > 0 ? SupplierConnection::query()->find($connId) : null;
        if ($connection !== null && $connection->provider !== SupplierProvider::Sabre) {
            $connection = null;
        }

        $registry = app(SabreGdsPnrCreateStrategyRegistry::class);
        $legacyDiagnostic = $this->decidePassengerRecordsPayloadStyle($offer, $apiDraft, $connection, $routeSelection);
        $overrideStrategy = trim((string) ($options['gds_pnr_strategy_code'] ?? $options['strategy_override'] ?? ''));
        if ($overrideStrategy !== '' && $registry->isSupported($overrideStrategy)) {
            $selection = is_array($options['gds_strategy_selection'] ?? null) ? $options['gds_strategy_selection'] : [];
            $blocked = is_array($selection['blocked_strategies'] ?? null) ? $selection['blocked_strategies'] : [];
            if ($blocked !== [] && in_array($overrideStrategy, $blocked, true)) {
                $this->attemptPassengerRecordsStyleDecision = array_merge($legacyDiagnostic, [
                    'selected_strategy_code' => null,
                    'selected_style' => null,
                    'selected_payload_style' => null,
                    'selected_endpoint_path' => null,
                    'eligible' => false,
                    'manual_review_required' => true,
                    'reason_code' => SabreGdsPnrCreateStrategySelector::REASON_NO_ELIGIBLE,
                    'gds_strategy_selection' => $selection,
                ]);

                return;
            }
            $wireStyle = $registry->wireStyleForStrategy($overrideStrategy);
            $iatiSelected = SabreBookingPayloadBuilder::isIatiLikeCpnrV24GdsWireStyle($overrideStrategy);
            $this->attemptPassengerRecordsStyleDecision = array_merge($legacyDiagnostic, [
                'selected_strategy_code' => $overrideStrategy,
                'selected_style' => $wireStyle,
                'selected_payload_style' => $overrideStrategy,
                'selected_endpoint_path' => $registry->endpointPathForStrategy($overrideStrategy),
                'selected_endpoint_version' => $registry->endpointVersionForStrategy($overrideStrategy),
                'admin_confirmed_strategy_fallback' => ($options['admin_confirmed_gds_pnr_strategy_fallback'] ?? false) === true,
                'eligible' => true,
                'manual_review_required' => false,
                'gds_strategy_selection' => $selection !== [] ? $selection : null,
                'selection_reason' => (string) ($selection['selection_reason'] ?? 'registry_strategy_override'),
                'iati_like_selected' => $iatiSelected,
                'iati_like_eligible' => $iatiSelected,
            ]);

            return;
        }

        $bookingId = isset($options['booking_id_for_strategy']) ? (int) $options['booking_id_for_strategy'] : null;
        if ($bookingId === null && isset($options['booking_id'])) {
            $bookingId = (int) $options['booking_id'];
        }
        $booking = $bookingId > 0 ? Booking::query()->find($bookingId) : null;
        if ($booking !== null) {
            $selection = is_array($options['gds_strategy_selection'] ?? null)
                ? $options['gds_strategy_selection']
                : app(SabreGdsPnrCreateStrategySelector::class)->selectForBooking($booking);
            $selectedStrategy = trim((string) ($selection['selected_strategy'] ?? ''));
            $blocked = is_array($selection['blocked_strategies'] ?? null) ? $selection['blocked_strategies'] : [];
            if ($selectedStrategy !== '' && in_array($selectedStrategy, $blocked, true)) {
                $selectedStrategy = '';
            }
            if ($selectedStrategy === '') {
                $this->attemptPassengerRecordsStyleDecision = array_merge($legacyDiagnostic, [
                    'selected_strategy_code' => null,
                    'selected_style' => null,
                    'selected_payload_style' => null,
                    'selected_endpoint_path' => null,
                    'eligible' => false,
                    'manual_review_required' => true,
                    'reason_code' => (string) ($selection['reason_code'] ?? SabreGdsPnrCreateStrategySelector::REASON_NO_ELIGIBLE),
                    'gds_strategy_selection' => $selection,
                ]);
            } else {
                $wireStyle = $registry->wireStyleForStrategy($selectedStrategy);
                $iatiSelected = SabreBookingPayloadBuilder::isIatiLikeCpnrV24GdsWireStyle($selectedStrategy);
                $this->attemptPassengerRecordsStyleDecision = array_merge($legacyDiagnostic, [
                    'selected_strategy_code' => $selectedStrategy,
                    'selected_style' => $wireStyle,
                    'selected_payload_style' => $selectedStrategy,
                    'selected_endpoint_path' => $registry->endpointPathForStrategy($selectedStrategy),
                    'selected_endpoint_version' => $registry->endpointVersionForStrategy($selectedStrategy),
                    'gds_strategy_selection' => $selection,
                    'manual_review_required' => false,
                    'eligible' => true,
                    'iati_like_selected' => $iatiSelected,
                    'iati_like_eligible' => $iatiSelected,
                    'selection_reason' => (string) ($selection['selection_reason'] ?? ''),
                ]);
            }
        } else {
            $this->attemptPassengerRecordsStyleDecision = $legacyDiagnostic;
        }

        if (is_array($this->attemptCertifiedRouteSelection)
            && is_array($this->attemptPassengerRecordsStyleDecision)
            && trim((string) ($this->attemptPassengerRecordsStyleDecision['selected_endpoint_path'] ?? '')) !== '') {
            $this->attemptCertifiedRouteSelection['endpoint_path'] = $this->attemptPassengerRecordsStyleDecision['selected_endpoint_path'];
            $this->attemptCertifiedRouteSelection['payload_style'] = $this->attemptPassengerRecordsStyleDecision['selected_payload_style']
                ?? $this->attemptPassengerRecordsStyleDecision['selected_style']
                ?? null;
        }
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>|null
     */
    protected function gdsPnrStrategyNoEligibleGateForLiveAttempt(
        ?int $bookingIdForDiagnostics,
        ?string $bookingRefEarly,
        int $paxCount,
        int $segCount,
        int $connId,
        string $selectedOffer,
        float $fareAmt,
        string $fareCur,
        array $options = [],
    ): ?array {
        if (($options['admin_confirmed_gds_pnr_strategy_fallback'] ?? false) === true) {
            return null;
        }

        $decision = $this->attemptPassengerRecordsStyleDecision;
        if (! is_array($decision)) {
            return null;
        }

        if (($decision['gds_strategy_registry_fallback'] ?? false) === true) {
            return null;
        }

        $blocked = is_array($decision['gds_strategy_selection']['blocked_strategies'] ?? null)
            ? $decision['gds_strategy_selection']['blocked_strategies']
            : [];
        $selectedStrategy = trim((string) ($decision['selected_strategy_code'] ?? ''));
        if ($selectedStrategy !== ''
            && in_array($selectedStrategy, $blocked, true)) {
            $reasonCode = SabreGdsPnrCreateStrategySelector::REASON_PREVIOUS_FORMAT_FAILURE_BLOCKS_AUTO;
            $ep = $this->resolveBookingEndpointSummary($connId);

            return array_merge([
                'success' => false,
                'status' => 'needs_review',
                'message' => $this->customerStaffConfirmationBookingMessage(),
                'live_call_attempted' => false,
                'live_call_allowed' => true,
                'passenger_count' => $paxCount,
                'segment_count' => $segCount,
                'supplier_connection_id' => $connId,
                'selected_offer_id' => $selectedOffer,
                'fare_amount' => $fareAmt,
                'fare_currency' => $fareCur,
                'pnr' => null,
                'reason_code' => $reasonCode,
                'error_code' => $reasonCode,
                'booking_schema' => $this->effectiveSabreBookingSchema(),
                'gds_strategy_selection' => $decision['gds_strategy_selection'] ?? null,
                'manual_review_required' => true,
            ], array_intersect_key($ep, array_flip(['endpoint_host', 'endpoint_path'])));
        }

        if ($selectedStrategy !== '') {
            return null;
        }

        $reasonCode = 'sabre_gds_no_eligible_pnr_strategy';
        $blockingConditions = array_values(array_unique(array_filter(array_merge(
            [(string) ($decision['reason_code'] ?? SabreGdsPnrCreateStrategySelector::REASON_NO_ELIGIBLE)],
            is_array($decision['gds_strategy_selection']['blocked_strategies'] ?? null)
                ? $decision['gds_strategy_selection']['blocked_strategies']
                : $blocked,
        ))));
        $ep = $this->resolveBookingEndpointSummary($connId);

        return array_merge([
            'success' => false,
            'status' => 'needs_review',
            'message' => $this->customerStaffConfirmationBookingMessage(),
            'live_call_attempted' => false,
            'live_call_allowed' => true,
            'passenger_count' => $paxCount,
            'segment_count' => $segCount,
            'supplier_connection_id' => $connId,
            'selected_offer_id' => $selectedOffer,
            'fare_amount' => $fareAmt,
            'fare_currency' => $fareCur,
            'pnr' => null,
            'reason_code' => $reasonCode,
            'error_code' => $reasonCode,
            'pnr_attempted' => false,
            'blocking_conditions' => $blockingConditions,
            'booking_schema' => $this->effectiveSabreBookingSchema(),
            'gds_strategy_selection' => $decision['gds_strategy_selection'] ?? null,
            'manual_review_required' => true,
        ], array_intersect_key($ep, array_flip(['endpoint_host', 'endpoint_path'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function passengerDataFromBookingForCommand(Booking $booking): array
    {
        return $this->passengerDataFromBooking($booking);
    }

    /**
     * Admin/operator-confirmed single-strategy GDS PNR fallback (explicit command lane).
     *
     * @return array<string, mixed>
     */
    public function createBookingWithStrategyForAdminFallback(Booking $booking, string $strategyCode): array
    {
        $strategyCode = trim($strategyCode);
        $readiness = app(SabreAdminManualPnrFallbackReadiness::class)
            ->evaluate($booking, $strategyCode);

        if (($readiness['allowed'] ?? false) !== true) {
            return [
                'success' => false,
                'status' => 'validation_failed',
                'message' => 'Admin manual PNR fallback preflight failed.',
                'live_call_attempted' => false,
                'live_call_allowed' => false,
                'preflight_passed' => false,
                'reason_code' => (string) ($readiness['reason_code'] ?? 'blocked_by_flags'),
                'blocking_conditions' => is_array($readiness['blocking_conditions'] ?? null)
                    ? $readiness['blocking_conditions']
                    : [],
                'pnr' => null,
            ];
        }

        $booking->loadMissing(['passengers', 'contact']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null)
            ? $meta['normalized_offer_snapshot']
            : (is_array($meta['validated_offer_snapshot'] ?? null)
                ? $meta['validated_offer_snapshot']
                : (is_array($meta['flight_offer_snapshot'] ?? null) ? $meta['flight_offer_snapshot'] : []));
        $offer = $this->mergePublicReviewSabreSnapshotFromBooking($booking, $snapshot);

        $options = [
            'mode' => 'admin_manual_strategy_fallback',
            'gds_pnr_strategy_code' => $strategyCode,
            'admin_confirmed_gds_pnr_strategy_fallback' => true,
            'allow_controlled_staff_pnr' => true,
            'skip_auto_pnr_flag_gate' => true,
            'ticketing_enabled_required' => false,
            'source' => 'admin_supplier_action',
        ];

        $result = $this->createBooking(
            $offer,
            $this->passengerDataFromBooking($booking),
            $booking->id,
            $options,
        );
        $result = $this->mergeControlledStaffPnrOptionsIntoBookingResult($result, $options);
        $result['preflight_passed'] = true;

        if (($result['live_call_attempted'] ?? false) === true) {
            $this->finalizePublicCheckoutSabreStorage($booking->fresh(), $result);
        }

        return $result;
    }

    /**
     * Operator-approved scenario runner: one live GDS PNR create with explicit strategy (no public checkout flag dependency).
     *
     * @param  array<string, mixed>  $strategySelection
     * @param  array<string, mixed>  $completion
     * @return array<string, mixed>
     */
    public function createBookingForScenarioRunner(
        Booking $booking,
        string $strategyCode,
        array $strategySelection,
        array $completion,
        array $runnerOptions = [],
    ): array {
        $strategyCode = trim($strategyCode);
        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown', 'supplierBookings']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null)
            ? $meta['normalized_offer_snapshot']
            : (is_array($meta['validated_offer_snapshot'] ?? null)
                ? $meta['validated_offer_snapshot']
                : (is_array($meta['flight_offer_snapshot'] ?? null) ? $meta['flight_offer_snapshot'] : []));
        $offer = $this->mergePublicReviewSabreSnapshotFromBooking($booking, $snapshot);

        $options = [
            'mode' => 'scenario_runner',
            'source' => 'scenario_runner',
            'operator_approved_live_pnr_create' => true,
            'public_auto_pnr_flag_override' => true,
            'gds_pnr_strategy_code' => $strategyCode,
            'gds_strategy_selection' => $strategySelection,
            'auto_pnr_context_completion' => $completion,
            'skip_auto_pnr_flag_gate' => true,
            'max_pnr_attempts' => 1,
            'ticketing_enabled_required' => false,
            'scenario_runner_strategy_option' => strtolower(trim((string) ($runnerOptions['strategy'] ?? 'auto'))) ?: 'auto',
        ];

        $result = $this->createBooking(
            $offer,
            $this->passengerDataFromBooking($booking),
            $booking->id,
            $options,
        );
        $result['source'] = 'scenario_runner';
        $liveAttempted = ($result['live_call_attempted'] ?? false) === true;
        $result = array_merge($result, $this->gdsPnrStrategyResultSlice($strategySelection), [
            'auto_pnr_context_completion' => $completion,
            'pnr_attempted' => $liveAttempted,
            'public_auto_pnr_attempted' => $liveAttempted,
            'pnr_strategy_used' => $strategyCode !== '' ? $strategyCode : ($result['payload_schema'] ?? null),
            'scenario_runner_override_applied' => ($strategySelection['scenario_runner_override_applied'] ?? false) === true,
        ]);
        $booking->refresh();
        $freshMeta = is_array($booking->meta) ? $booking->meta : [];
        $preflightProof = is_array($freshMeta[SabreGdsMixedCarrierFareBasisPayloadPreflight::META_PREFLIGHT_PROOF_KEY] ?? null)
            ? $freshMeta[SabreGdsMixedCarrierFareBasisPayloadPreflight::META_PREFLIGHT_PROOF_KEY]
            : [];
        if ($preflightProof !== []) {
            $result['mixed_carrier_preflight_proof'] = $preflightProof;
        }
        $this->persistPublicCheckoutStrategyMeta($booking, $strategySelection, $result);
        $this->finalizePublicCheckoutSabreStorage($booking->fresh(), $result);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function isAdminManualStrategyFallbackActive(array $options): bool
    {
        if (($options['admin_confirmed_gds_pnr_strategy_fallback'] ?? false) === true) {
            return true;
        }

        return ($options['mode'] ?? '') === 'admin_manual_strategy_fallback';
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function isScenarioRunnerPnrCreateActive(array $options): bool
    {
        return ($options['mode'] ?? '') === 'scenario_runner'
            && ($options['operator_approved_live_pnr_create'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function isOperatorApprovedPnrBypassActive(array $options): bool
    {
        return $this->isAdminManualStrategyFallbackActive($options)
            || $this->isScenarioRunnerPnrCreateActive($options);
    }

    /**
     * Build read-only wire context for GDS PNR strategy digest (no live HTTP).
     *
     * @return array{valid: bool, snapshot?: array<string, mixed>, api_draft?: array<string, mixed>, hints?: array<string, mixed>, meta?: array<string, mixed>}
     */
    public function buildGdsPnrStrategyWireContext(Booking $booking, ?array $metaOverride = null): array
    {
        $booking->loadMissing(['passengers', 'contact']);
        $meta = $metaOverride ?? (is_array($booking->meta) ? $booking->meta : []);
        $snapshot = $this->mergePublicReviewSabreSnapshotFromBooking(
            $booking,
            $this->offerSnapshotFromBookingMeta($meta),
            $metaOverride,
        );
        $gate = $this->validateNormalizedSabreOffer($snapshot);
        if (! $gate->success) {
            return ['valid' => false];
        }

        $draft = $this->prepareBookingPayload($snapshot, $this->passengerDataFromBooking($booking));
        if (($draft['_valid'] ?? false) !== true) {
            return ['valid' => false];
        }

        $apiDraft = $draft;
        unset($apiDraft['_valid']);

        return [
            'valid' => true,
            'snapshot' => $snapshot,
            'api_draft' => $apiDraft,
            'hints' => $this->ticketingHintsFromOffer($snapshot),
            'meta' => $meta,
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function recordGdsPnrStrategyEvidence(Booking $booking, array $result): void
    {
        try {
            $strategyCode = trim((string) (
                $result['payload_schema']
                ?? $result['selected_payload_style']
                ?? (is_array($this->attemptPassengerRecordsStyleDecision)
                    ? ($this->attemptPassengerRecordsStyleDecision['selected_strategy_code'] ?? '')
                    : '')
            ));
            if ($strategyCode === '' || ! app(SabreGdsPnrCreateStrategyRegistry::class)->isSupported($strategyCode)) {
                return;
            }

            $recorder = app(SabreGdsPnrCreateStrategyEvidenceRecorder::class);
            if (($result['success'] ?? false) === true && trim((string) ($result['pnr'] ?? '')) !== '') {
                $recorder->recordSuccess($booking, $strategyCode, $result);
            } elseif (($result['live_call_attempted'] ?? false) === true) {
                $recorder->recordFailure($booking, $strategyCode, $result);
            }
        } catch (Throwable) {
            // fail-safe: evidence recording must not break booking flow
        }
    }

    /**
     * @return array<string, mixed>|null Live-booking early exit when config forces IATI-like style but eligibility failed.
     */
    protected function passengerRecordsStyleManualReviewGateForLiveAttempt(
        ?int $bookingIdForDiagnostics,
        ?string $bookingRefEarly,
        int $paxCount,
        int $segCount,
        int $connId,
        string $selectedOffer,
        float $fareAmt,
        string $fareCur,
    ): ?array {
        $decision = $this->attemptPassengerRecordsStyleDecision;
        if (! is_array($decision) || ($decision['manual_review_required'] ?? false) !== true) {
            return null;
        }

        $reasonCode = (string) ($decision['iati_like_reason_code'] ?? $decision['reason_code'] ?? 'iati_style_not_eligible');
        $ep = $this->resolveBookingEndpointSummary($connId);
        $contextSummary = array_merge(
            $this->passengerRecordsStyleDecisionDiagnosticSlice($decision),
            [
                'booking_id' => $bookingIdForDiagnostics,
                'booking_reference' => $bookingRefEarly,
                'safe_reason_code' => $reasonCode,
            ],
        );
        $this->logSabreBookingContextSummary($contextSummary);
        $this->logSabrePnrAttemptSummaryFromLiveResult(
            $bookingIdForDiagnostics,
            $bookingRefEarly,
            $contextSummary,
            ['http_status' => null, 'message' => 'IATI-like CPNR style not eligible; deferred to manual review.'],
            false,
            true,
            $reasonCode,
        );

        return array_merge([
            'success' => false,
            'status' => 'needs_review',
            'message' => $this->customerStaffConfirmationBookingMessage(),
            'live_call_attempted' => false,
            'live_call_allowed' => true,
            'passenger_count' => $paxCount,
            'segment_count' => $segCount,
            'supplier_connection_id' => $connId,
            'selected_offer_id' => $selectedOffer,
            'fare_amount' => $fareAmt,
            'fare_currency' => $fareCur,
            'pnr' => null,
            'provider_booking_id' => null,
            'provider_status' => null,
            'http_status' => null,
            'reason_code' => $reasonCode,
            'error_code' => $reasonCode,
            'booking_schema' => $this->effectiveSabreBookingSchema(),
            'payload_schema' => (string) ($decision['fallback_style'] ?? SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1),
            'ticketing_enabled' => false,
            'booking_context_summary' => $contextSummary,
            'passenger_records_style_decision' => $this->passengerRecordsStyleDecisionPublicSlice($decision),
        ], array_intersect_key($ep, array_flip(['endpoint_host', 'endpoint_path'])));
    }

    /**
     * @param  array<string, mixed>  $selection
     * @param  array<string, mixed>  $result
     */
    protected function persistPublicCheckoutStrategyMeta(Booking $booking, array $selection, array $result): void
    {
        $booking->refresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['public_checkout_pnr_strategy'] = [
            'selected_strategy' => $selection['selected_strategy'] ?? null,
            'selection_reason' => $selection['selection_reason'] ?? null,
            'eligible_strategies' => $selection['eligible_strategies'] ?? [],
            'blocked_strategies' => $selection['blocked_strategies'] ?? [],
            'scenario_runner_override_applied' => ($selection['scenario_runner_override_applied'] ?? false) === true ? true : null,
            'endpoint_path' => $result['endpoint_path']
                ?? $result['selected_endpoint_path']
                ?? data_get($result, 'create_payload_safe_summary.endpoint_path'),
            'payload_schema' => $result['payload_schema']
                ?? $result['pnr_strategy_used']
                ?? data_get($result, 'create_payload_safe_summary.payload_schema'),
            'selected_payload_style' => $result['selected_payload_style']
                ?? data_get($result, 'create_payload_safe_summary.selected_payload_style'),
            'pnr_strategy_used' => $result['pnr_strategy_used'] ?? null,
            'evaluated_at' => now()->toIso8601String(),
        ];
        $meta['public_checkout_diagnostics'] = app(PublicCheckoutFareChangeState::class)
            ->checkoutDiagnostics($booking->fresh(['fareBreakdown']), $selection);
        $checkoutOutcome = is_array($meta['sabre_checkout_outcome'] ?? null) ? $meta['sabre_checkout_outcome'] : [];
        $meta['public_checkout_diagnostics']['pnr_attempted'] = ($checkoutOutcome['live_call_attempted'] ?? false) === true
            || ($result['live_call_attempted'] ?? false) === true;
        $meta['public_checkout_diagnostics']['pnr_strategy_used'] = $result['pnr_strategy_used']
            ?? $checkoutOutcome['pnr_strategy_used']
            ?? $checkoutOutcome['payload_schema']
            ?? null;
        $booking->forceFill(['meta' => $meta])->save();
    }

    /**
     * @param  array<string, mixed>  $selection
     * @return array<string, mixed>
     */
    protected function gdsPnrStrategyResultSlice(array $selection): array
    {
        $selected = trim((string) ($selection['selected_strategy'] ?? ''));
        $decision = is_array($this->attemptPassengerRecordsStyleDecision)
            ? $this->attemptPassengerRecordsStyleDecision
            : [];
        $used = trim((string) (
            $decision['selected_payload_style']
            ?? $decision['selected_strategy_code']
            ?? $selected
        ));

        $registry = app(SabreGdsPnrCreateStrategyRegistry::class);
        $endpointPath = trim((string) (
            $decision['selected_endpoint_path']
            ?? ($selected !== '' ? $registry->endpointPathForStrategy($selected) : '')
        ));

        return array_filter([
            'gds_strategy_selection' => $selection !== [] ? $selection : ($decision['gds_strategy_selection'] ?? null),
            'pnr_strategy_selected' => $selected !== '' ? $selected : null,
            'pnr_strategy_used' => $used !== '' ? $used : null,
            'strategy_selection_reason' => (string) ($selection['selection_reason'] ?? $decision['selection_reason'] ?? ''),
            'payload_schema' => $used !== '' ? $used : null,
            'selected_payload_style' => $used !== '' ? $used : null,
            'endpoint_path' => $endpointPath !== '' ? $endpointPath : null,
            'scenario_runner_override_applied' => ($selection['scenario_runner_override_applied'] ?? null) === true ? true : null,
            'eligible_strategies' => is_array($selection['eligible_strategies'] ?? null)
                ? array_values($selection['eligible_strategies'])
                : null,
            'blocked_strategies' => is_array($selection['blocked_strategies'] ?? null)
                ? array_values($selection['blocked_strategies'])
                : null,
        ], static fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array<string, mixed>  $decision
     * @return array<string, mixed>
     */
    protected function passengerRecordsStyleDecisionDiagnosticSlice(array $decision): array
    {
        return array_intersect_key($decision, array_flip([
            'iati_like_selection_considered',
            'iati_like_selected',
            'iati_like_eligible',
            'iati_like_reason_code',
            'selected_payload_style',
            'selected_strategy_code',
            'selected_endpoint_path',
            'selected_endpoint_version',
            'gds_strategy_selection',
            'selection_reason',
            'fallback_payload_style',
            'certified_route_result',
            'cpnr_required_blocks_present',
            'cpnr_required_blocks_missing',
            'gds_compatible',
            'supplier_connection_present',
            'pcc_present',
            'target_city_present',
            'rbd_complete',
            'segment_context_complete',
            'booking_path_requires_revalidation',
            'iati_style_expects_revalidation_waiver_or_refresh',
            'reason_code',
            'reasons',
        ]));
    }

    /**
     * @param  array<string, mixed>  $decision
     * @return array<string, mixed>
     */
    public function passengerRecordsStyleDecisionPublicSlice(array $decision): array
    {
        return $this->passengerRecordsStyleDecisionDiagnosticSlice($decision);
    }

    /** @internal */
    protected function expectedSabrePayloadSchemaHintForFailures(): string
    {
        return $this->effectiveSabreBookingSchema() === 'trip_orders_create_booking'
            ? 'trip_orders_create_booking_v1'
            : SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1;
    }

    public function isBookingEnabled(): bool
    {
        return (bool) config('suppliers.sabre.booking_enabled', false);
    }

    public function isBookingLiveCallEnabled(): bool
    {
        return (bool) config('suppliers.sabre.booking_live_call_enabled', false);
    }

    public function isTicketingEnabled(): bool
    {
        return (bool) config('suppliers.sabre.ticketing_enabled', false);
    }

    public function isPassengerRecordsBlockRiskyItineraryLiveEnabled(): bool
    {
        return (bool) config('suppliers.sabre.passenger_records_block_risky_itinerary_live', true);
    }

    public function isPassengerRecordsAllowVerifiedMultiSegmentEnabled(): bool
    {
        return SabrePassengerRecordsMultiSegmentSellVerifier::isAllowVerifiedMultiSegmentEnabled();
    }

    /**
     * Live Sabre booking/revalidate/PNR HTTP must only run when both flags are true (future wiring).
     */
    public function mayPerformLiveSabreBookingCall(): bool
    {
        return $this->isBookingEnabled() && $this->isBookingLiveCallEnabled();
    }

    /**
     * Whether the offer is structurally acceptable for the Sabre booking path (any carrier / itinerary).
     *
     * @param  array<string, mixed>  $offer
     */
    public function canBookOffer(array $offer): bool
    {
        return $this->validateNormalizedSabreOffer($offer)->success;
    }

    /**
     * Internal booking draft from the selected Sabre fare + passenger/contact input (not the final Sabre API body).
     * Does not log passenger or contact values.
     *
     * @param  array<string, mixed>  $offer  Normalized or search-shaped Sabre offer
     * @param  array<string, mixed>  $passengerData  Expects keys: {@see self::normalizePassengerInput()}
     * @return array<string, mixed>
     */
    public function prepareBookingPayload(array $offer, array $passengerData): array
    {
        $gate = $this->validateNormalizedSabreOffer($offer);
        if (! $gate->success) {
            $code = isset($gate->safe_context['error_code']) && is_string($gate->safe_context['error_code']) && $gate->safe_context['error_code'] !== ''
                ? $gate->safe_context['error_code']
                : 'validation_failed';

            return [
                '_valid' => false,
                'code' => $code,
                'message' => $gate->message,
            ];
        }

        return $this->bookingPayloadBuilder->buildInternalDraft($offer, $passengerData);
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $passengerData
     * @param  array<string, mixed>  $options  C4: {@code certification_full_itinerary_fallback} — allow full-itinerary re-shop confirmation before blocking stale OW segment guard
     * @return array<string, mixed> Safe summary; never includes raw Sabre responses or passport/DOB
     */
    public function createBooking(array $offer, array $passengerData, ?int $bookingIdForDiagnostics = null, array $options = []): array
    {
        $draft = $this->prepareBookingPayload($offer, $passengerData);
        if (($draft['_valid'] ?? false) !== true) {
            $code = (string) ($draft['code'] ?? 'validation_failed');
            $isTiming = $code === 'sabre_invalid_itinerary_timing';
            $segments = is_array($offer['segments'] ?? null) ? $offer['segments'] : [];
            $seg = count($segments);
            $counts = is_array($offer['fare_breakdown']['passenger_counts'] ?? null)
                ? $offer['fare_breakdown']['passenger_counts']
                : [];
            $pax = (int) ($counts['adults'] ?? 0) + (int) ($counts['children'] ?? 0) + (int) ($counts['infants'] ?? 0);
            $endpointExtras = $isTiming ? $this->resolveBookingEndpointSummary((int) ($offer['supplier_connection_id'] ?? 0)) : [];
            $timingExtras = [];
            if ($isTiming) {
                $t = SabreItineraryTimingValidator::analyzeSegmentArrays(array_values($segments));
                $timingExtras = [
                    'failed_time_link_count' => $t['failed_time_link_count'],
                    'invalid_segment_duration_count' => $t['invalid_segment_duration_count'],
                ];
            }

            return array_merge([
                'success' => false,
                'status' => $isTiming ? 'failed' : 'validation_failed',
                'message' => (string) ($draft['message'] ?? 'Sabre offer validation failed.'),
                'live_call_attempted' => false,
                'live_call_allowed' => false,
                'reason_code' => $code,
                'error_code' => $isTiming ? 'sabre_invalid_itinerary_timing' : $code,
                'passenger_count' => $pax,
                'segment_count' => $seg,
            ], $endpointExtras, $timingExtras);
        }

        $routeSelection = $this->resolvePublicCertifiedRouteForAttempt($bookingIdForDiagnostics, $options);
        $routeSelectionForStyle = $this->resolveRouteSelectionForStyleDecision($bookingIdForDiagnostics, $options, $routeSelection);
        if ($routeSelection !== null && ($routeSelection['live_booking_allowed'] ?? false) !== true) {
            if ($this->publicCheckoutGateBypassActive($bookingIdForDiagnostics, $routeSelection, $options)) {
                if ($routeSelectionForStyle === null) {
                    $routeSelectionForStyle = $routeSelection;
                }
            } else {
                return $this->certifiedRouteBlockedResultFromDraft($bookingIdForDiagnostics, $routeSelection, $draft, $offer, $options);
            }
        }
        if ($routeSelectionForStyle !== null) {
            $this->attemptCertifiedRouteSelection = $routeSelectionForStyle;
        }

        $apiDraftEarly = $draft;
        unset($apiDraftEarly['_valid']);
        $this->initializePassengerRecordsStyleDecisionForAttempt($offer, $apiDraftEarly, $routeSelectionForStyle, array_merge($options, [
            'booking_id_for_strategy' => $bookingIdForDiagnostics,
        ]));

        try {

            if (! $this->isOperatorApprovedPnrBypassActive($options)) {
                $freshnessBlock = $this->offerFreshnessBlockBeforePnr($offer, $bookingIdForDiagnostics);
                if ($freshnessBlock !== null) {
                    return $freshnessBlock;
                }
            }

            if (! $this->isBookingEnabled()) {
                return [
                    'success' => false,
                    'status' => 'disabled',
                    'message' => (string) __('Sabre booking is not enabled yet.'),
                    'live_call_attempted' => false,
                    'live_call_allowed' => false,
                    'passenger_count' => count(is_array($draft['passengers'] ?? null) ? $draft['passengers'] : []),
                    'segment_count' => count(is_array($draft['segments'] ?? null) ? $draft['segments'] : []),
                    'supplier_connection_id' => (int) ($draft['supplier_connection_id'] ?? 0),
                    'selected_offer_id' => (string) ($draft['selected_offer_id'] ?? ''),
                    'fare_amount' => (float) data_get($draft, 'fare.amount', 0),
                    'fare_currency' => (string) data_get($draft, 'fare.currency', ''),
                ];
            }

            $paxCount = count(is_array($draft['passengers'] ?? null) ? $draft['passengers'] : []);
            $segCount = count(is_array($draft['segments'] ?? null) ? $draft['segments'] : []);
            $connId = (int) ($draft['supplier_connection_id'] ?? 0);
            $selectedOffer = (string) ($draft['selected_offer_id'] ?? '');
            $fareAmt = (float) data_get($draft, 'fare.amount', 0);
            $fareCur = (string) data_get($draft, 'fare.currency', '');

            if (! $this->isBookingLiveCallEnabled()) {
                $apiDraft = $draft;
                unset($apiDraft['_valid']);
                $envelope = $this->buildLiveBookingEnvelope($apiDraft, $offer, null, $bookingIdForDiagnostics);
                $payloadSafe = $this->bookingPayloadBuilder->summarizeEnvelopeForDiagnostics($envelope);
                $connIdForEp = (int) ($draft['supplier_connection_id'] ?? 0);
                $ep = $this->resolveBookingEndpointSummary($connIdForEp);
                $schema = $this->effectiveSabreBookingSchema();
                $createPayloadSafeSummary = $this->buildCreatePayloadSafeSummaryForLiveAttempt(
                    $envelope,
                    $offer,
                    $apiDraft,
                    $bookingIdForDiagnostics,
                    (string) ($ep['endpoint_path'] ?? ''),
                    $this->resolvePassengerRecordsPayloadStyleForAttempt(),
                );
                $msg = $schema === 'trip_orders_create_booking'
                    ? (string) __('Sabre Trip Orders booking dry-run prepared. No live PNR attempted.')
                    : (string) __('Sabre booking dry-run prepared.');

                return $this->withCreatePayloadSafeSummary([
                    'success' => true,
                    'status' => 'dry_run',
                    'message' => $msg,
                    'live_call_attempted' => false,
                    'live_call_allowed' => false,
                    'passenger_count' => $paxCount,
                    'segment_count' => $segCount,
                    'supplier_connection_id' => $connId,
                    'selected_offer_id' => $selectedOffer,
                    'fare_amount' => $fareAmt,
                    'fare_currency' => $fareCur,
                    'booking_schema' => $schema,
                    'revalidation_attempted' => false,
                    'revalidation_before_booking_enabled' => $this->isRevalidationBeforeBookingEnabled(),
                    'revalidation_skipped_by_config' => false,
                    'revalidation_bypass_enabled' => $this->isAllowCreateBookingWithoutRevalidation(),
                    'ticketing_enabled' => false,
                    'payload_safe_summary' => array_merge($payloadSafe, [
                        'booking_schema' => $schema,
                    ], array_intersect_key($ep, array_flip([
                        'endpoint_host', 'endpoint_path',
                    ]))),
                ], $createPayloadSafeSummary);
            }

            if ($connId < 1) {
                return [
                    'success' => false,
                    'status' => 'failed',
                    'message' => (string) __('A Sabre supplier connection is required for live booking.'),
                    'live_call_attempted' => false,
                    'live_call_allowed' => true,
                    'passenger_count' => $paxCount,
                    'segment_count' => $segCount,
                    'supplier_connection_id' => $connId,
                    'selected_offer_id' => $selectedOffer,
                    'fare_amount' => $fareAmt,
                    'fare_currency' => $fareCur,
                ];
            }

            $connection = SupplierConnection::query()->find($connId);
            if ($connection === null || $connection->provider !== SupplierProvider::Sabre) {
                return [
                    'success' => false,
                    'status' => 'failed',
                    'message' => (string) __('Sabre supplier connection was not found or is not a Sabre connection.'),
                    'live_call_attempted' => false,
                    'live_call_allowed' => true,
                    'passenger_count' => $paxCount,
                    'segment_count' => $segCount,
                    'supplier_connection_id' => $connId,
                    'selected_offer_id' => $selectedOffer,
                    'fare_amount' => $fareAmt,
                    'fare_currency' => $fareCur,
                ];
            }

            $apiDraft = $draft;
            unset($apiDraft['_valid']);

            $bookingRefEarly = null;
            if ($bookingIdForDiagnostics !== null && $bookingIdForDiagnostics > 0) {
                $bookingRefEarly = Booking::query()->whereKey($bookingIdForDiagnostics)->value('booking_reference');
            }
            $this->initializeFreshnessStrategyDecisionForAttempt($offer, $apiDraft, $connection, $bookingIdForDiagnostics, $options);
            $freshnessDecision = is_array($this->attemptFreshnessStrategyDecision)
                ? $this->attemptFreshnessStrategyDecision
                : [];
            $epEarly = $this->resolveBookingEndpointSummary($connId);
            $preRevContext = $this->buildSabreBookingContextDiagnosticSummary($offer, $apiDraft, $connection, [
                'booking_id' => $bookingIdForDiagnostics,
                'booking_reference' => is_string($bookingRefEarly) ? $bookingRefEarly : null,
                'endpoint_path' => (string) ($epEarly['endpoint_path'] ?? ''),
                'booking_schema' => $this->effectiveSabreBookingSchema(),
                'revalidation_required' => ($freshnessDecision['revalidation_required'] ?? $this->isRevalidationBeforeBookingEnabled()) === true,
                'revalidation_attempted' => false,
            ]);
            $this->logSabreBookingContextSummary($preRevContext);

            $certLinkage = is_array($options['certification_fare_linkage'] ?? null)
                ? $options['certification_fare_linkage']
                : [];
            if ($certLinkage !== []) {
                $apiDraft['_fare_linkage'] = array_merge(
                    is_array($apiDraft['_fare_linkage'] ?? null) ? $apiDraft['_fare_linkage'] : [],
                    $certLinkage,
                );
            }

            if (($offer['sabre_segments_synthesized'] ?? false) === true || $segCount < 1) {
                return [
                    'success' => false,
                    'status' => 'failed',
                    'message' => (string) __('Sabre booking requires a complete segment itinerary; re-shop before airline hold.'),
                    'live_call_attempted' => false,
                    'live_call_allowed' => true,
                    'passenger_count' => $paxCount,
                    'segment_count' => $segCount,
                    'supplier_connection_id' => $connId,
                    'selected_offer_id' => $selectedOffer,
                    'fare_amount' => $fareAmt,
                    'fare_currency' => $fareCur,
                    'error_code' => 'sabre_revalidation_gatekeeper_failed',
                    'reason_code' => 'sabre_segments_incomplete',
                    'booking_schema' => $this->effectiveSabreBookingSchema(),
                ];
            }

            if ($this->draftSegmentsMissingBookingClass($apiDraft)) {
                return [
                    'success' => false,
                    'status' => 'failed',
                    'message' => (string) __('Sabre booking is blocked until every segment has a booking class (RBD).'),
                    'live_call_attempted' => false,
                    'live_call_allowed' => true,
                    'passenger_count' => $paxCount,
                    'segment_count' => $segCount,
                    'supplier_connection_id' => $connId,
                    'selected_offer_id' => $selectedOffer,
                    'fare_amount' => $fareAmt,
                    'fare_currency' => $fareCur,
                    'error_code' => 'sabre_revalidation_gatekeeper_failed',
                    'reason_code' => 'missing_booking_class',
                    'booking_schema' => $this->effectiveSabreBookingSchema(),
                ];
            }

            $revEnabled = ($freshnessDecision['revalidation_required'] ?? $this->isRevalidationBeforeBookingEnabled()) === true;
            $allowBypass = $this->isAllowCreateBookingWithoutRevalidation();
            $revalidationOutcome = null;
            $revalidationSkippedByConfig = false;
            $previousRevalidationReasonCode = null;

            $frozenRetry = $this->revalidationFrozenRetryBlockedOutcome(
                $bookingIdForDiagnostics,
                $apiDraft,
                $paxCount,
                $segCount,
                $connId,
                $selectedOffer,
                $fareAmt,
                $fareCur,
            );
            if ($frozenRetry !== null) {
                return $frozenRetry;
            }

            if ($revEnabled) {
                $revalidationOutcome = $this->runRevalidationBeforeBooking(
                    $apiDraft,
                    $connection,
                    null,
                    null,
                    $bookingIdForDiagnostics,
                    is_string($bookingRefEarly) ? $bookingRefEarly : null,
                );
                if (! ($revalidationOutcome['success'] ?? false)) {
                    $this->logSabreRevalidationSummaryFromOutcome(
                        $bookingIdForDiagnostics,
                        is_string($bookingRefEarly) ? $bookingRefEarly : null,
                        $connId,
                        $apiDraft,
                        $connection,
                        $revalidationOutcome,
                        false,
                        null,
                        true,
                        $allowBypass,
                    );
                    if (! $allowBypass) {
                        $revPayloadSummary = is_array($revalidationOutcome['payload_safe_summary'] ?? null)
                            ? $revalidationOutcome['payload_safe_summary']
                            : [];
                        $reasonCode = (string) ($revalidationOutcome['reason_code'] ?? 'sabre_revalidation_failed');
                        $this->persistRevalidationFreezeOnBooking(
                            $bookingIdForDiagnostics,
                            $revalidationOutcome,
                        );
                        $failCtx = array_merge($preRevContext, [
                            'revalidation_attempted' => true,
                            'safe_reason_code' => $reasonCode,
                        ]);

                        return $this->enrichGdsPrePnrRevalidationFailureResult(array_merge([
                            'success' => false,
                            'status' => 'failed',
                            'message' => (string) ($revalidationOutcome['message'] ?? __('Sabre revalidation failed; Trip Orders booking was not attempted.')),
                            'live_call_attempted' => false,
                            'live_call_allowed' => true,
                            'passenger_count' => $paxCount,
                            'segment_count' => $segCount,
                            'supplier_connection_id' => $connId,
                            'selected_offer_id' => $selectedOffer,
                            'fare_amount' => $fareAmt,
                            'fare_currency' => $fareCur,
                            'http_status' => $revalidationOutcome['http_status'] ?? null,
                            'provider_status' => null,
                            'error_code' => 'sabre_revalidation_failed',
                            'reason_code' => $reasonCode,
                            'booking_schema' => $this->effectiveSabreBookingSchema(),
                            'payload_schema' => $this->expectedSabrePayloadSchemaHintForFailures(),
                            'revalidation_attempted' => true,
                            'revalidation_outcome' => 'failed',
                            'revalidation_http_status' => $revalidationOutcome['http_status'] ?? null,
                            'revalidation_duration_ms' => $revalidationOutcome['duration_ms'] ?? null,
                            'revalidation_payload_summary' => $revPayloadSummary,
                            'revalidation_error_digest' => is_array($revalidationOutcome['error_digest'] ?? null) ? $revalidationOutcome['error_digest'] : [],
                            'revalidation_skipped_by_config' => false,
                            'revalidation_bypass_enabled' => false,
                            'revalidation_before_booking_enabled' => true,
                            'ticketing_enabled' => false,
                            'booking_context_summary' => $failCtx,
                        ]), $revalidationOutcome);
                    }
                    $revalidationSkippedByConfig = true;
                    $previousRevalidationReasonCode = (string) ($revalidationOutcome['reason_code'] ?? 'sabre_revalidation_failed');
                    $this->logSabreRevalidationSummaryFromOutcome(
                        $bookingIdForDiagnostics,
                        is_string($bookingRefEarly) ? $bookingRefEarly : null,
                        $connId,
                        $apiDraft,
                        $connection,
                        $revalidationOutcome,
                        true,
                        'revalidation_failed_bypass_enabled',
                        true,
                        $allowBypass,
                    );
                    $epLog = $this->resolveBookingEndpointSummary($connId);
                    $shapeFlags = self::draftFareShapeFlags($apiDraft);
                    Log::notice('sabre.booking.revalidation_skipped', [
                        'booking_id' => $bookingIdForDiagnostics,
                        'provider' => SupplierProvider::Sabre->value,
                        'endpoint_path' => $epLog['endpoint_path'] ?? null,
                        'revalidation_before_booking' => true,
                        'allow_without_revalidation' => true,
                        'ticketing_enabled' => $this->isTicketingEnabled(),
                        'has_fare_basis' => $shapeFlags['has_fare_basis'],
                        'has_booking_class' => $shapeFlags['has_booking_class'],
                        'has_validating_carrier' => $shapeFlags['has_validating_carrier'],
                        'segment_count' => $segCount,
                        'passenger_count' => $paxCount,
                    ]);
                } else {
                    $apiDraft['_fare_linkage'] = is_array($revalidationOutcome['linkage'] ?? null)
                        ? $revalidationOutcome['linkage']
                        : [];
                    $this->logSabreRevalidationSummaryFromOutcome(
                        $bookingIdForDiagnostics,
                        is_string($bookingRefEarly) ? $bookingRefEarly : null,
                        $connId,
                        $apiDraft,
                        $connection,
                        $revalidationOutcome,
                        false,
                        null,
                        true,
                        $allowBypass,
                    );
                }
            } else {
                $revalidationSkippedByConfig = true;
                $this->logSabreRevalidationSummaryFromOutcome(
                    $bookingIdForDiagnostics,
                    is_string($bookingRefEarly) ? $bookingRefEarly : null,
                    $connId,
                    $apiDraft,
                    $connection,
                    ['success' => true, 'endpoint_path' => (string) ($this->resolveBookingEndpointSummary($connId)['endpoint_path'] ?? ''), 'payload_style' => ''],
                    true,
                    $this->prebookingRevalidationSkippedReason(),
                    false,
                    $allowBypass,
                );
                $epLog = $this->resolveBookingEndpointSummary($connId);
                $shapeFlags = self::draftFareShapeFlags($apiDraft);
                Log::notice('sabre.booking.revalidation_skipped', [
                    'booking_id' => $bookingIdForDiagnostics,
                    'provider' => SupplierProvider::Sabre->value,
                    'endpoint_path' => $epLog['endpoint_path'] ?? null,
                    'revalidation_before_booking' => false,
                    'allow_without_revalidation' => $allowBypass,
                    'prebooking_revalidation_skipped_reason' => $this->prebookingRevalidationSkippedReason(),
                    'ticketing_enabled' => $this->isTicketingEnabled(),
                    'has_fare_basis' => $shapeFlags['has_fare_basis'],
                    'has_booking_class' => $shapeFlags['has_booking_class'],
                    'has_validating_carrier' => $shapeFlags['has_validating_carrier'],
                    'segment_count' => $segCount,
                    'passenger_count' => $paxCount,
                ]);
            }

            $prebookingRevalidationSkippedReason = $revalidationSkippedByConfig
                ? $this->prebookingRevalidationSkippedReason()
                : null;

            $b67RevalidationSlice = $this->passengerRecordsVerifiedMultiSegmentRevalidationSlice(
                $segCount,
                $revEnabled,
                $revalidationOutcome,
                $revalidationSkippedByConfig,
                $allowBypass,
                $apiDraft,
            );

            $structuralSegments = is_array($apiDraft['segments'] ?? null) ? array_values($apiDraft['segments']) : [];

            if (($b67RevalidationSlice['passenger_records_multi_segment_revalidation_applied'] ?? false) === true) {
                $apiDraft['segments'] = $this->bookingPayloadBuilder->mergeRevalidatedClassOfServiceIntoSegments(
                    $structuralSegments,
                    is_array($apiDraft['_fare_linkage'] ?? null) ? $apiDraft['_fare_linkage'] : [],
                );
            }

            $routeForControlledBypass = is_array($this->attemptCertifiedRouteSelection)
                ? $this->attemptCertifiedRouteSelection
                : (is_array($routeSelection) ? $routeSelection : []);
            $publicCheckoutBypass = $this->publicCheckoutGateBypassActive(
                $bookingIdForDiagnostics,
                $routeForControlledBypass,
                $options,
            );

            if (! $publicCheckoutBypass) {
                $strategyGate = $this->gdsPnrStrategyNoEligibleGateForLiveAttempt(
                    $bookingIdForDiagnostics,
                    $bookingRefEarly,
                    $paxCount,
                    $segCount,
                    $connId,
                    $selectedOffer,
                    $fareAmt,
                    $fareCur,
                    $options,
                );
                if ($strategyGate !== null) {
                    return $this->mergeControlledStaffPnrOptionsIntoBookingResult($strategyGate, $options);
                }

                $iatiStyleGate = $this->passengerRecordsStyleManualReviewGateForLiveAttempt(
                    $bookingIdForDiagnostics,
                    $bookingRefEarly,
                    $paxCount,
                    $segCount,
                    $connId,
                    $selectedOffer,
                    $fareAmt,
                    $fareCur,
                );
                if ($iatiStyleGate !== null) {
                    return $this->mergeControlledStaffPnrOptionsIntoBookingResult($iatiStyleGate, $options);
                }

                $freshnessGate = $this->passengerRecordsFreshnessManualReviewGateForLiveAttempt(
                    $bookingIdForDiagnostics,
                    $bookingRefEarly,
                    $paxCount,
                    $segCount,
                    $connId,
                    $selectedOffer,
                    $fareAmt,
                    $fareCur,
                );
                if ($freshnessGate !== null) {
                    return $this->mergeControlledStaffPnrOptionsIntoBookingResult($freshnessGate, $options);
                }
            }

            $envelope = $this->buildLiveBookingEnvelope($apiDraft, $offer, $connection, $bookingIdForDiagnostics);
            $contact = is_array($draft['contact'] ?? null) ? $draft['contact'] : [];
            $diagFlags = $this->bookingPayloadBuilder->summarizeEnvelopeForDiagnostics($envelope);
            $epForAttempt = $this->resolveBookingEndpointSummary($connId);
            $createPayloadSafeSummary = $this->buildCreatePayloadSafeSummaryForLiveAttempt(
                $envelope,
                $offer,
                $apiDraft,
                $bookingIdForDiagnostics,
                (string) ($epForAttempt['endpoint_path'] ?? $this->resolvePassengerRecordsEndpointPathForAttempt()),
                $this->resolvePassengerRecordsPayloadStyleForAttempt(),
            );
            $bookingContextSummary = $this->buildSabreBookingContextDiagnosticSummary($offer, $apiDraft, $connection, [
                'booking_id' => $bookingIdForDiagnostics,
                'booking_reference' => is_string($bookingRefEarly) ? $bookingRefEarly : null,
                'endpoint_path' => (string) ($epForAttempt['endpoint_path'] ?? ''),
                'booking_schema' => $this->effectiveSabreBookingSchema(),
                'payload_style' => is_string($diagFlags['payload_style'] ?? null) ? (string) $diagFlags['payload_style'] : null,
                'diag_flags' => $diagFlags,
                'revalidation_required' => $revEnabled,
                'revalidation_attempted' => $revalidationOutcome !== null,
            ]);
            $bookingContextSummary = self::enrichBookingContextSummaryFromGdsStrategy(
                $bookingContextSummary,
                $options,
                is_array($this->attemptFreshnessStrategyDecision) ? $this->attemptFreshnessStrategyDecision : null,
            );
            $this->logSabreBookingContextSummary($bookingContextSummary);
            $fareIntegrityBlock = $this->assessFareSelectionIntegrityGateForLiveAttempt(
                $bookingIdForDiagnostics,
                $offer,
                $apiDraft,
                $diagFlags,
                $paxCount,
                $segCount,
                $connId,
                $selectedOffer,
                $fareAmt,
                $fareCur,
                $epForAttempt,
                $bookingContextSummary,
                $options,
            );
            if ($fareIntegrityBlock !== null && ! $publicCheckoutBypass) {
                return $this->mergeControlledStaffPnrOptionsIntoBookingResult($fareIntegrityBlock, $options);
            }
            $contextGate = $this->assessSabreBookingContextForLiveAttempt($bookingContextSummary, $diagFlags);
            if ($this->isBookingLiveCallEnabled()
                && ($contextGate['blocks_live_call'] ?? false) === true
                && ! $publicCheckoutBypass) {
                $safeReason = (string) ($contextGate['safe_reason_code'] ?? 'sabre_booking_context_incomplete');
                $bookingContextSummary['safe_reason_code'] = $safeReason;
                $this->logSabrePnrAttemptSummaryFromLiveResult(
                    $bookingIdForDiagnostics,
                    is_string($bookingRefEarly) ? $bookingRefEarly : null,
                    $bookingContextSummary,
                    ['http_status' => null, 'message' => 'Sabre booking context incomplete; deferred to manual review.'],
                    false,
                    true,
                    (string) ($contextGate['reason_code'] ?? 'sabre_booking_context_incomplete'),
                );

                return $this->mergeControlledStaffPnrOptionsIntoBookingResult(array_merge([
                    'success' => false,
                    'status' => 'needs_review',
                    'message' => $this->customerStaffConfirmationBookingMessage(),
                    'live_call_attempted' => false,
                    'live_call_allowed' => true,
                    'passenger_count' => $paxCount,
                    'segment_count' => $segCount,
                    'supplier_connection_id' => $connId,
                    'selected_offer_id' => $selectedOffer,
                    'fare_amount' => $fareAmt,
                    'fare_currency' => $fareCur,
                    'pnr' => null,
                    'provider_booking_id' => null,
                    'provider_status' => null,
                    'http_status' => null,
                    'reason_code' => (string) ($contextGate['reason_code'] ?? 'sabre_booking_context_incomplete'),
                    'error_code' => 'sabre_booking_context_incomplete',
                    'booking_schema' => $this->effectiveSabreBookingSchema(),
                    'payload_schema' => $diagFlags['payload_schema'] ?? null,
                    'ticketing_enabled' => false,
                    'booking_context_summary' => $bookingContextSummary,
                ], array_intersect_key($epForAttempt, array_flip([
                    'endpoint_host', 'endpoint_path',
                ]))),
                    $options,
                );
            }
            $revalidationResultContext = array_merge(
                $revalidationOutcome !== null ? [
                    'revalidation_attempted' => true,
                    'revalidation_outcome' => ($revalidationOutcome['success'] ?? false) ? 'ok' : 'failed',
                    'revalidation_http_status' => $revalidationOutcome['http_status'] ?? null,
                    'revalidation_duration_ms' => $revalidationOutcome['duration_ms'] ?? null,
                    'revalidation_linkage_digest' => is_array($revalidationOutcome['linkage_digest'] ?? null) ? $revalidationOutcome['linkage_digest'] : [],
                ] : ['revalidation_attempted' => false],
                self::liveBookingRevalidationAuditSlice(
                    $revEnabled,
                    $allowBypass,
                    $revalidationSkippedByConfig,
                    $previousRevalidationReasonCode,
                    $prebookingRevalidationSkippedReason,
                    (string) ($epForAttempt['endpoint_path'] ?? ''),
                    $this->effectiveSabreBookingSchema(),
                    $diagFlags,
                    $paxCount,
                    $segCount,
                ),
                is_array($this->attemptFreshnessStrategyDecision)
                    ? $this->freshnessStrategyDiagnosticSlice($this->attemptFreshnessStrategyDecision)
                    : [],
            );

            $b65Eligibility = $this->passengerRecordsMultiSegmentEligibilitySlice(
                $offer,
                $apiDraft,
                $b67RevalidationSlice,
                $structuralSegments,
            );

            if ($this->effectiveSabreBookingSchema() === 'create_passenger_name_record'
                && $this->isPassengerRecordsBlockRiskyItineraryLiveEnabled()) {
                $guardSlice = $this->passengerRecordsRiskyItineraryGuardSlice($offer, $segCount, $b65Eligibility);
                if ($guardSlice !== null) {
                    $bookingForGuard = ($bookingIdForDiagnostics !== null && $bookingIdForDiagnostics > 0)
                        ? Booking::query()->find($bookingIdForDiagnostics)
                        : null;
                    $guardPolicy = app(SabrePassengerRecordsItineraryGuardPolicy::class);
                    $guardDecision = $guardPolicy->resolve(
                        $bookingForGuard,
                        $offer,
                        $options,
                        $guardSlice,
                        $segCount,
                        $diagFlags,
                        $this->isTicketingEnabled(),
                        (string) ($epForAttempt['endpoint_path'] ?? ''),
                    );
                    $guardSafeSummary = $guardPolicy->safeSummarySlice($guardDecision);

                    if (($guardDecision['guard_bypassed'] ?? false) === true) {
                        Log::notice('sabre.booking.passenger_records_itinerary_guard_bypassed', [
                            'booking_id' => $bookingIdForDiagnostics,
                            'provider' => SupplierProvider::Sabre->value,
                            'segment_count' => $segCount,
                            'guard_trigger' => $guardSlice['guard_trigger'],
                            'guard_bypass_reason' => $guardDecision['guard_bypass_reason'] ?? SabrePassengerRecordsItineraryGuardPolicy::BYPASS_REASON,
                        ]);
                        $revalidationResultContext = array_merge(
                            $revalidationResultContext,
                            [
                                'passenger_records_itinerary_guard_bypassed' => true,
                            ],
                            $guardSafeSummary,
                            $guardSlice,
                            $b65Eligibility,
                        );
                    } elseif ($this->isPnrOnlyPassengerRecordsLiveCreateEnabled()) {
                        Log::notice('sabre.booking.passenger_records_itinerary_advisory', [
                            'booking_id' => $bookingIdForDiagnostics,
                            'provider' => SupplierProvider::Sabre->value,
                            'segment_count' => $segCount,
                            'guard_trigger' => $guardSlice['guard_trigger'],
                            'segment_order_corrected' => $guardSlice['segment_order_corrected'],
                            'passenger_records_multi_segment_eligible' => (bool) ($b65Eligibility['passenger_records_multi_segment_eligible'] ?? false),
                        ]);
                        $revalidationResultContext = array_merge(
                            $revalidationResultContext,
                            [
                                'passenger_records_itinerary_advisory' => true,
                                'passenger_records_guard_waived_reason' => 'pnr_only_ticketing_disabled',
                            ],
                            $guardSlice,
                            $b65Eligibility,
                        );
                    } else {
                        Log::notice('sabre.booking.passenger_records_itinerary_guard', [
                            'booking_id' => $bookingIdForDiagnostics,
                            'provider' => SupplierProvider::Sabre->value,
                            'segment_count' => $segCount,
                            'guard_trigger' => $guardSlice['guard_trigger'],
                            'segment_order_corrected' => $guardSlice['segment_order_corrected'],
                            'passenger_records_multi_segment_eligible' => (bool) ($b65Eligibility['passenger_records_multi_segment_eligible'] ?? false),
                        ]);

                        return array_merge([
                            'success' => false,
                            'status' => 'needs_review',
                            'message' => 'Passenger Records live create blocked for multi-segment or corrected-order Sabre itinerary; manual supplier booking required.',
                            'live_call_attempted' => false,
                            'live_call_allowed' => true,
                            'passenger_count' => $paxCount,
                            'segment_count' => $segCount,
                            'supplier_connection_id' => $connId,
                            'selected_offer_id' => $selectedOffer,
                            'fare_amount' => $fareAmt,
                            'fare_currency' => $fareCur,
                            'pnr' => null,
                            'provider_booking_id' => null,
                            'provider_status' => null,
                            'http_status' => null,
                            'reason_code' => 'sabre_passenger_records_itinerary_guard',
                            'error_code' => 'sabre_passenger_records_itinerary_guard',
                            'booking_schema' => $this->effectiveSabreBookingSchema(),
                            'payload_schema' => $diagFlags['payload_schema'] ?? SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1,
                            'ticketing_enabled' => false,
                            'pnr_attempted' => false,
                            'public_auto_pnr_attempted' => false,
                        ], $guardSafeSummary, $guardSlice, $b65Eligibility, $revalidationResultContext, array_intersect_key($epForAttempt, array_flip([
                            'endpoint_host', 'endpoint_path',
                        ])));
                    }
                }
            }

            if (SabreBookingPayloadBuilder::isTraditionalPnrPassengerRecordsWireStyle((string) ($diagFlags['payload_schema'] ?? ''))
                && (($diagFlags['wire_traditional_pnr_contract_valid'] ?? false) !== true)) {
                $bad = is_array($diagFlags['wire_invalid_traditional_pnr_contract_keys'] ?? null)
                    ? $diagFlags['wire_invalid_traditional_pnr_contract_keys']
                    : [];
                $customerSafe = $this->bookingPayloadBuilder->buildTraditionalPnrPayloadValidationCustomerSafeMessage(
                    array_map('strval', $bad),
                );
                $parts = [];
                if ($bad !== []) {
                    $parts[] = implode(', ', array_map('strval', array_slice($bad, 0, 24)));
                }
                $safeMsg = implode(' | ', array_filter($parts, static fn (string $s): bool => $s !== ''));

                return array_merge([
                    'success' => false,
                    'status' => 'payload_validation_failed',
                    'message' => $customerSafe !== ''
                        ? $customerSafe
                        : ($safeMsg !== ''
                            ? $safeMsg
                            : (string) __('Traditional Passenger Records wire failed validation before live POST.')),
                    'customer_safe_message' => $customerSafe,
                    'live_call_attempted' => false,
                    'live_call_allowed' => true,
                    'passenger_count' => $paxCount,
                    'segment_count' => $segCount,
                    'supplier_connection_id' => $connId,
                    'selected_offer_id' => $selectedOffer,
                    'fare_amount' => $fareAmt,
                    'fare_currency' => $fareCur,
                    'pnr' => null,
                    'provider_booking_id' => null,
                    'provider_status' => null,
                    'http_status' => null,
                    'reason_code' => 'sabre_booking_payload_validation_failed',
                    'error_code' => 'sabre_booking_payload_validation_failed',
                    'booking_schema' => $this->effectiveSabreBookingSchema(),
                    'payload_schema' => $diagFlags['payload_schema'] ?? null,
                    'ticketing_enabled' => false,
                ], [
                    'wire_traditional_pnr_contract_valid' => false,
                    'wire_invalid_traditional_pnr_contract_keys' => array_slice(array_map('strval', $bad), 0, 32),
                ], $revalidationResultContext,
                    array_intersect_key($diagFlags, array_flip([
                        'wire_has_create_passenger_name_record_rq', 'wire_traditional_pnr_contract_valid',
                        'wire_segment_count', 'wire_passenger_count', 'booking_transport',
                        'wire_air_price_passenger_type_contract_valid',
                        'has_fare_basis', 'has_validating_carrier', 'has_booking_class',
                        'manual_ticketing_marker_present', 'ticketing_time_limit_present',
                        'pnr_time_limit_marker_present', 'ticket_issuance_disabled_ok',
                        'ticketing_enabled_required_for_pnr', 'fare_basis_present', 'validating_carrier_present',
                    ])), self::extractLinkageMissingFlags($diagFlags));
            }
            if (($diagFlags['payload_schema'] ?? '') === SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS) {
                $strippedForSchema = $this->bookingPayloadBuilder->stripOtaInternalKeysFromBookingWire($envelope);
                $cpnrSchemaSummary = app(SabreCpnrIatiWireSchemaValidator::class)->validateCpnrEnvelope($strippedForSchema);
                if (($cpnrSchemaSummary['cpnr_schema_validation_failed'] ?? false) === true) {
                    $safeMsg = 'Sabre booking validation failed: '
                        .substr((string) ($cpnrSchemaSummary['cpnr_schema_validation_message_summary'] ?? 'IATI CPNR AirPrice schema validation failed.'), 0, 200);
                    if (($cpnrSchemaSummary['cpnr_schema_validation_pointer'] ?? null) !== null) {
                        $safeMsg .= ' pointer: '.substr((string) $cpnrSchemaSummary['cpnr_schema_validation_pointer'], 0, 160);
                    }

                    return array_merge([
                        'success' => false,
                        'status' => 'payload_validation_failed',
                        'message' => $safeMsg,
                        'live_call_attempted' => false,
                        'live_call_allowed' => true,
                        'passenger_count' => $paxCount,
                        'segment_count' => $segCount,
                        'supplier_connection_id' => $connId,
                        'selected_offer_id' => $selectedOffer,
                        'fare_amount' => $fareAmt,
                        'fare_currency' => $fareCur,
                        'pnr' => null,
                        'provider_booking_id' => null,
                        'provider_status' => null,
                        'http_status' => null,
                        'reason_code' => 'sabre_booking_validation_failed',
                        'error_code' => 'sabre_booking_validation_failed',
                        'booking_schema' => $this->effectiveSabreBookingSchema(),
                        'payload_schema' => $diagFlags['payload_schema'] ?? null,
                        'ticketing_enabled' => false,
                        'application_error_digest_available' => false,
                    ], $cpnrSchemaSummary, $revalidationResultContext, array_intersect_key($epForAttempt, array_flip([
                        'endpoint_host', 'endpoint_path',
                    ])));
                }
            }
            if (($diagFlags['payload_schema'] ?? '') === SabreBookingPayloadBuilder::PASSENGER_RECORDS_V2_5_GDS) {
                $strippedForSchema = $this->bookingPayloadBuilder->stripOtaInternalKeysFromBookingWire($envelope);
                $cpnrSchemaSummary = app(SabrePassengerRecordsV25WireSchemaValidator::class)->validateCpnrEnvelope($strippedForSchema);
                $v25QualifierDigest = $this->bookingPayloadBuilder->summarizeV25AirPricePricingQualifiersStructuralDigest(
                    $strippedForSchema,
                    $this->v25BrandContextForQualifierDigest($apiDraft),
                );
                if (($cpnrSchemaSummary['cpnr_schema_validation_failed'] ?? false) === true) {
                    $safeMsg = 'Sabre booking validation failed: '
                        .substr((string) ($cpnrSchemaSummary['cpnr_schema_validation_message_summary'] ?? 'Passenger Records v2.5 GDS AirPrice schema validation failed.'), 0, 200);
                    if (($cpnrSchemaSummary['cpnr_schema_validation_pointer'] ?? null) !== null) {
                        $safeMsg .= ' pointer: '.substr((string) $cpnrSchemaSummary['cpnr_schema_validation_pointer'], 0, 160);
                    }

                    return array_merge([
                        'success' => false,
                        'status' => 'payload_validation_failed',
                        'message' => $safeMsg,
                        'customer_safe_message' => $this->v25AirPriceOptionalQualifierSchemaCustomerMessage(),
                        'live_call_attempted' => false,
                        'live_call_allowed' => true,
                        'passenger_count' => $paxCount,
                        'segment_count' => $segCount,
                        'supplier_connection_id' => $connId,
                        'selected_offer_id' => $selectedOffer,
                        'fare_amount' => $fareAmt,
                        'fare_currency' => $fareCur,
                        'pnr' => null,
                        'provider_booking_id' => null,
                        'provider_status' => null,
                        'http_status' => null,
                        'reason_code' => 'sabre_booking_validation_failed',
                        'error_code' => 'sabre_booking_validation_failed',
                        'safe_reason_code' => SabreBookingPayloadBuilder::V25_AIRPRICE_OPTIONAL_QUALIFIER_SCHEMA_ERROR,
                        'booking_schema' => $this->effectiveSabreBookingSchema(),
                        'payload_schema' => $diagFlags['payload_schema'] ?? null,
                        'ticketing_enabled' => false,
                        'application_error_digest_available' => false,
                        'v25_airprice_pricing_qualifiers_digest' => $v25QualifierDigest,
                        'pnr_attempted' => false,
                    ], $cpnrSchemaSummary, $revalidationResultContext, array_intersect_key($epForAttempt, array_flip([
                        'endpoint_host', 'endpoint_path',
                    ])));
                }
            }
            if (($diagFlags['has_trip_orders_schema'] ?? false) === true
                && (($diagFlags['wire_agency_phone_ok'] ?? true) === false)) {
                return array_merge([
                    'success' => false,
                    'status' => 'payload_validation_failed',
                    'message' => 'agency_phone_missing',
                    'live_call_attempted' => false,
                    'live_call_allowed' => true,
                    'passenger_count' => $paxCount,
                    'segment_count' => $segCount,
                    'supplier_connection_id' => $connId,
                    'selected_offer_id' => $selectedOffer,
                    'fare_amount' => $fareAmt,
                    'fare_currency' => $fareCur,
                    'pnr' => null,
                    'provider_booking_id' => null,
                    'provider_status' => null,
                    'http_status' => null,
                    'reason_code' => 'sabre_booking_payload_validation_failed',
                    'error_code' => 'sabre_booking_payload_validation_failed',
                    'booking_schema' => $this->effectiveSabreBookingSchema(),
                    'payload_schema' => $diagFlags['payload_schema'] ?? null,
                    'ticketing_enabled' => false,
                ], self::tripOrdersTravelerPayloadAuditSlice($diagFlags), $revalidationResultContext, self::extractLinkageMissingFlags($diagFlags));
            }
            if (($diagFlags['has_trip_orders_schema'] ?? false) === true
                && (
                    (($diagFlags['wire_traveler_required_fields_valid'] ?? true) === false)
                    || (($diagFlags['wire_payload_null_free'] ?? true) === false)
                    || (($diagFlags['wire_contract_valid'] ?? true) === false)
                    || (($diagFlags['wire_segment_required_fields_valid'] ?? true) === false)
                )) {
                $invalidTraveler = is_array($diagFlags['wire_invalid_traveler_field_keys'] ?? null)
                    ? $diagFlags['wire_invalid_traveler_field_keys']
                    : [];
                $invalidContract = is_array($diagFlags['wire_invalid_contract_keys'] ?? null)
                    ? $diagFlags['wire_invalid_contract_keys']
                    : [];
                $invalidSeg = is_array($diagFlags['wire_invalid_segment_field_keys'] ?? null)
                    ? $diagFlags['wire_invalid_segment_field_keys']
                    : [];
                $nullPaths = is_array($diagFlags['wire_null_paths'] ?? null)
                    ? $diagFlags['wire_null_paths']
                    : [];
                $parts = [];
                if ($invalidContract !== []) {
                    $parts[] = implode(', ', array_map('strval', array_slice($invalidContract, 0, 24)));
                }
                if ($invalidSeg !== []) {
                    $parts[] = implode(', ', array_map('strval', array_slice($invalidSeg, 0, 24)));
                }
                if ($nullPaths !== []) {
                    $parts[] = 'null_paths: '.implode(', ', array_map('strval', array_slice($nullPaths, 0, 16)));
                }
                if ($invalidTraveler !== []) {
                    $parts[] = implode(', ', array_map('strval', array_slice($invalidTraveler, 0, 24)));
                }
                $safeMsg = implode(' | ', array_filter($parts, static fn (string $s): bool => $s !== ''));

                return array_merge([
                    'success' => false,
                    'status' => 'payload_validation_failed',
                    'message' => $safeMsg !== ''
                        ? $safeMsg
                        : (string) __('Trip Orders wire payload failed validation before live POST.'),
                    'live_call_attempted' => false,
                    'live_call_allowed' => true,
                    'passenger_count' => $paxCount,
                    'segment_count' => $segCount,
                    'supplier_connection_id' => $connId,
                    'selected_offer_id' => $selectedOffer,
                    'fare_amount' => $fareAmt,
                    'fare_currency' => $fareCur,
                    'pnr' => null,
                    'provider_booking_id' => null,
                    'provider_status' => null,
                    'http_status' => null,
                    'reason_code' => 'sabre_booking_payload_validation_failed',
                    'error_code' => 'sabre_booking_payload_validation_failed',
                    'booking_schema' => $this->effectiveSabreBookingSchema(),
                    'payload_schema' => $diagFlags['payload_schema'] ?? null,
                    'ticketing_enabled' => false,
                ], self::tripOrdersTravelerPayloadAuditSlice($diagFlags), $revalidationResultContext, self::extractLinkageMissingFlags($diagFlags));
            }

            $certificationFlow = ($options['certification_full_itinerary_fallback'] ?? false) === true;
            if ($bookingIdForDiagnostics !== null && ! $certificationFlow) {
                $bookingForOfferAcceptance = Booking::query()->find($bookingIdForDiagnostics);
                if ($bookingForOfferAcceptance !== null && SabreOfferRefreshAcceptance::requiresAcceptance($bookingForOfferAcceptance)) {
                    return array_merge([
                        'success' => false,
                        'status' => 'needs_review',
                        'message' => SabreOfferRefreshAcceptance::ADMIN_MESSAGE,
                        'live_call_attempted' => false,
                        'live_call_allowed' => true,
                        'passenger_count' => $paxCount,
                        'segment_count' => $segCount,
                        'supplier_connection_id' => $connId,
                        'selected_offer_id' => $selectedOffer,
                        'fare_amount' => $fareAmt,
                        'fare_currency' => $fareCur,
                        'error_code' => SabreOfferRefreshAcceptance::ERROR_CODE_REQUIRES_ACCEPTANCE,
                        'reason_code' => SabreOfferRefreshAcceptance::ERROR_CODE_REQUIRES_ACCEPTANCE,
                        'booking_schema' => $this->effectiveSabreBookingSchema(),
                        'ticketing_enabled' => false,
                    ], self::offerRefreshAcceptanceSummarySlice($bookingForOfferAcceptance), $revalidationResultContext, array_intersect_key($epForAttempt, array_flip([
                        'endpoint_host', 'endpoint_path',
                    ])));
                }
            }

            $freshShopGuard = $this->evaluatePassengerRecordsFreshShopGuard(
                $offer,
                $connection,
                $bookingIdForDiagnostics,
                $options,
                $diagFlags,
            );
            if (($freshShopGuard['block'] ?? false) === true) {
                $staleReport = is_array($freshShopGuard['stale_segment_report'] ?? null)
                    ? $freshShopGuard['stale_segment_report']
                    : [];
                $staleMsg = (string) __('This flight is no longer available at the selected schedule/class. Please search again or contact staff.');
                Log::notice('sabre.booking.passenger_records_stale_shop_segment', [
                    'booking_id' => $bookingIdForDiagnostics,
                    'stale_segment_index' => $staleReport['index'] ?? null,
                    'stale_segment_route' => $staleReport['route'] ?? null,
                    'probable_issue' => $staleReport['probable_issue'] ?? null,
                    'allowed_by_full_itinerary_confirmation' => false,
                ]);

                return array_merge([
                    'success' => false,
                    'status' => 'needs_review',
                    'message' => $staleMsg,
                    'live_call_attempted' => false,
                    'live_call_allowed' => true,
                    'passenger_count' => $paxCount,
                    'segment_count' => $segCount,
                    'supplier_connection_id' => $connId,
                    'selected_offer_id' => $selectedOffer,
                    'fare_amount' => $fareAmt,
                    'fare_currency' => $fareCur,
                    'pnr' => null,
                    'provider_booking_id' => null,
                    'provider_status' => null,
                    'http_status' => null,
                    'reason_code' => 'sabre_passenger_records_stale_shop_segment',
                    'error_code' => 'sabre_passenger_records_stale_shop_segment',
                    'booking_schema' => $this->effectiveSabreBookingSchema(),
                    'payload_schema' => $diagFlags['payload_schema'] ?? SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1,
                    'ticketing_enabled' => false,
                    'stale_segment_index' => (int) ($staleReport['index'] ?? 0),
                    'stale_segment_route' => (string) ($staleReport['route'] ?? ''),
                    'stale_segment_flight' => (string) ($staleReport['flight_number'] ?? ''),
                    'probable_issue' => (string) ($staleReport['probable_issue'] ?? ''),
                    'fresh_shop_guard_result' => is_array($freshShopGuard['fresh_shop_guard_result'] ?? null)
                        ? $freshShopGuard['fresh_shop_guard_result']
                        : [],
                    'full_itinerary_guard_reason' => is_array($freshShopGuard['fresh_shop_guard_result'] ?? null)
                        ? (string) ($freshShopGuard['fresh_shop_guard_result']['full_itinerary_guard_reason'] ?? '')
                        : '',
                ], $b65Eligibility, $revalidationResultContext, $this->offerRefreshAcceptanceSummarySliceForBookingId($bookingIdForDiagnostics), array_intersect_key($epForAttempt, array_flip([
                    'endpoint_host', 'endpoint_path',
                ])));
            }
            $freshShopGuardResultForSuccess = is_array($freshShopGuard['fresh_shop_guard_result'] ?? null)
                ? $freshShopGuard['fresh_shop_guard_result']
                : null;

            $controlledF9jRetryRecorded = false;
            $controlledF9lSchemaRecoveryRecorded = false;
            $controlledF9qFinalRetryRecorded = false;
            if (($options['controlled_f9l_schema_recovery_eligible'] ?? false) === true
                && is_numeric($bookingIdForDiagnostics)
                && (int) $bookingIdForDiagnostics > 0) {
                $bookingForF9l = Booking::query()->find((int) $bookingIdForDiagnostics);
                if ($bookingForF9l !== null) {
                    $this->controlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate->recordUsage($bookingForF9l);
                    $controlledF9lSchemaRecoveryRecorded = true;
                }
            } elseif (($options['controlled_f9q_final_retry_eligible'] ?? false) === true
                && is_numeric($bookingIdForDiagnostics)
                && (int) $bookingIdForDiagnostics > 0) {
                $bookingForF9q = Booking::query()->find((int) $bookingIdForDiagnostics);
                if ($bookingForF9q !== null) {
                    $this->controlledFinalPnrRetryAllowanceGate->recordUsage($bookingForF9q);
                    $controlledF9qFinalRetryRecorded = true;
                }
            } elseif (($options['controlled_f9j_retry_eligible'] ?? false) === true
                && is_numeric($bookingIdForDiagnostics)
                && (int) $bookingIdForDiagnostics > 0) {
                $bookingForF9j = Booking::query()->find((int) $bookingIdForDiagnostics);
                $meaningfulAttemptForF9j = is_numeric($options['controlled_f9j_meaningful_attempt_id'] ?? null)
                    ? SupplierBookingAttempt::query()->find((int) $options['controlled_f9j_meaningful_attempt_id'])
                    : null;
                if ($bookingForF9j !== null && $meaningfulAttemptForF9j !== null) {
                    $this->controlledPnrRetryAfterAirpriceVcFixAllowanceGate->recordUsage(
                        $bookingForF9j,
                        $meaningfulAttemptForF9j,
                    );
                    $controlledF9jRetryRecorded = true;
                }
            }

            Log::info('sabre.booking.createbooking_attempt', [
                'booking_id' => $bookingIdForDiagnostics,
                'provider' => SupplierProvider::Sabre->value,
                'endpoint_host' => $epForAttempt['endpoint_host'] ?? null,
                'endpoint_path' => $epForAttempt['endpoint_path'] ?? null,
                'booking_schema' => $this->effectiveSabreBookingSchema(),
                'revalidation_skipped_by_config' => $revalidationSkippedByConfig,
                'ticketing_enabled' => $this->isTicketingEnabled(),
                'segment_count' => $segCount,
                'passenger_count' => $paxCount,
            ]);
            $contextCompletionBlock = $this->enforcePublicAutoPnrContextCompletionBeforeLiveCreate(
                $bookingIdForDiagnostics,
                $segCount,
                $options,
            );
            if ($contextCompletionBlock !== null) {
                return array_merge(
                    $contextCompletionBlock,
                    $revalidationResultContext,
                    array_intersect_key($epForAttempt, array_flip(['endpoint_host', 'endpoint_path'])),
                );
            }
            $this->logSabrePnrAttemptSummaryFromLiveResult(
                $bookingIdForDiagnostics,
                is_string($bookingRefEarly) ? $bookingRefEarly : null,
                $bookingContextSummary,
                ['http_status' => null],
                true,
                false,
                'sabre_booking_live_post_pending',
            );
            $passengerRecordsEndpointPath = $this->resolvePassengerRecordsEndpointPathForAttempt();
            $endpointPersistence = $this->passengerRecordsEndpointPersistenceSlice($passengerRecordsEndpointPath);
            $live = $this->bookingClient->createPassengerRecordBooking(
                $connection,
                $envelope,
                array_merge([
                    'booking_id' => $bookingIdForDiagnostics,
                    'supplier_connection_id' => $connId,
                    'passenger_count' => $paxCount,
                    'segment_count' => $segCount,
                    'has_contact_email' => trim((string) ($contact['email'] ?? '')) !== '',
                    'has_contact_phone' => trim((string) ($contact['phone'] ?? '')) !== '',
                ], $this->operationalAllowNnDecisionDiagnosticSlice(), $diagFlags),
                $passengerRecordsEndpointPath,
            );

            $actualFromLive = trim((string) (is_array($live['booking_diagnostics'] ?? null)
                ? ($live['booking_diagnostics']['endpoint_path'] ?? '')
                : ''));
            if ($actualFromLive !== '') {
                $endpointPersistence = $this->passengerRecordsEndpointPersistenceSlice($actualFromLive);
            }

            $diagFlat = self::flattenBookingDiagnostics(is_array($live['booking_diagnostics'] ?? null) ? $live['booking_diagnostics'] : []);
            $v25LiveSlice = $this->v25PassengerRecordsLiveDiagnosticsSlice(
                $envelope,
                $apiDraft,
                $diagFlat,
                (string) ($diagFlags['payload_schema'] ?? ''),
            );
            $contextResultSlice = ['booking_context_summary' => $bookingContextSummary];

            if (($live['error_code'] ?? '') === 'sabre_booking_application_error') {
                $this->logSabrePnrAttemptSummaryFromLiveResult(
                    $bookingIdForDiagnostics,
                    is_string($bookingRefEarly) ? $bookingRefEarly : null,
                    $bookingContextSummary,
                    $live,
                    true,
                    true,
                    'sabre_booking_application_error',
                );

                return array_merge(
                    $this->withCreatePayloadSafeSummary(array_merge([
                        'success' => false,
                        'status' => 'needs_review',
                        'message' => (string) ($live['safe_message'] ?? __('Sabre returned a response requiring staff review. No ticket has been issued.')),
                        'live_call_attempted' => true,
                        'live_call_allowed' => true,
                        'passenger_count' => $paxCount,
                        'segment_count' => $segCount,
                        'supplier_connection_id' => $connId,
                        'selected_offer_id' => $selectedOffer,
                        'fare_amount' => $fareAmt,
                        'fare_currency' => $fareCur,
                        'pnr' => null,
                        'provider_booking_id' => null,
                        'provider_status' => $live['provider_status'] ?? null,
                        'http_status' => $live['http_status'] ?? null,
                        'reason_code' => (string) ($live['reason_code'] ?? 'sabre_booking_application_error'),
                        'error_code' => 'sabre_booking_application_error',
                        'response_safe_keys' => is_array($live['response_safe_keys'] ?? null) ? $live['response_safe_keys'] : [],
                        'booking_schema' => $this->effectiveSabreBookingSchema(),
                        'payload_schema' => $diagFlags['payload_schema'] ?? null,
                    ], $contextResultSlice, $diagFlat, $revalidationResultContext, self::extractLinkageMissingFlags($diagFlags), $endpointPersistence, self::passengerRecordsApplicationDigestSliceFromResult($live)), $createPayloadSafeSummary),
                    $this->controlledRetryRecordedSlices($controlledF9jRetryRecorded, $controlledF9lSchemaRecoveryRecorded, $controlledF9qFinalRetryRecorded),
                );
            }

            if (($live['success'] ?? false) && (($live['status'] ?? '') === 'needs_review')) {
                $this->logSabrePnrAttemptSummaryFromLiveResult(
                    $bookingIdForDiagnostics,
                    is_string($bookingRefEarly) ? $bookingRefEarly : null,
                    $bookingContextSummary,
                    $live,
                    true,
                    true,
                    (string) ($live['reason_code'] ?? 'sabre_booking_success_missing_locator'),
                );

                return array_merge(
                    $this->withCreatePayloadSafeSummary(array_merge([
                        'success' => true,
                        'status' => 'needs_review',
                        'message' => (string) ($live['safe_message'] ?? __('Sabre booking endpoint returned success but no PNR/locator was found. Staff review required.')),
                        'live_call_attempted' => true,
                        'live_call_allowed' => true,
                        'passenger_count' => $paxCount,
                        'segment_count' => $segCount,
                        'supplier_connection_id' => $connId,
                        'selected_offer_id' => $selectedOffer,
                        'fare_amount' => $fareAmt,
                        'fare_currency' => $fareCur,
                        'pnr' => null,
                        'provider_booking_id' => $live['provider_booking_id'] ?? null,
                        'provider_status' => $live['provider_status'] ?? null,
                        'http_status' => $live['http_status'] ?? null,
                        'reason_code' => (string) ($live['reason_code'] ?? 'sabre_booking_success_missing_locator'),
                        'response_safe_keys' => is_array($live['response_safe_keys'] ?? null) ? $live['response_safe_keys'] : [],
                        'booking_schema' => $this->effectiveSabreBookingSchema(),
                        'payload_schema' => $diagFlags['payload_schema'] ?? null,
                    ], $contextResultSlice, $diagFlat, $revalidationResultContext, $endpointPersistence), $createPayloadSafeSummary),
                    $this->controlledRetryRecordedSlices($controlledF9jRetryRecorded, $controlledF9lSchemaRecoveryRecorded, $controlledF9qFinalRetryRecorded),
                );
            }

            if (! ($live['success'] ?? false)) {
                $errorCode = (string) ($live['error_code'] ?? 'sabre_booking_http_failed');
                $this->logSabrePnrAttemptSummaryFromLiveResult(
                    $bookingIdForDiagnostics,
                    is_string($bookingRefEarly) ? $bookingRefEarly : null,
                    $bookingContextSummary,
                    $live,
                    true,
                    false,
                    $errorCode,
                );

                $schemaHostGateway = app(SabreCpnrIatiWireSchemaValidator::class)->outcomeLooksLikeCpnrSchemaValidationFailure(
                    $errorCode,
                    (string) ($live['safe_message'] ?? ''),
                    false,
                );
                $v25CustomerMessage = SabreBookingPayloadBuilder::v25OptionalQualifierCustomerMessageFromOutcome($v25LiveSlice);

                return array_merge(
                    $this->withCreatePayloadSafeSummary(array_merge([
                        'success' => false,
                        'status' => 'failed',
                        'message' => (string) ($live['safe_message'] ?? __('Sabre booking failed.')),
                        'customer_safe_message' => $v25CustomerMessage,
                        'live_call_attempted' => (bool) ($live['live_call_attempted'] ?? true),
                        'live_call_allowed' => true,
                        'passenger_count' => $paxCount,
                        'segment_count' => $segCount,
                        'supplier_connection_id' => $connId,
                        'selected_offer_id' => $selectedOffer,
                        'fare_amount' => $fareAmt,
                        'fare_currency' => $fareCur,
                        'http_status' => $live['http_status'] ?? null,
                        'provider_status' => $live['provider_status'] ?? null,
                        'error_code' => $errorCode,
                        'reason_code' => (string) ($live['reason_code'] ?? $errorCode),
                        'safe_validation_excerpts' => is_array($diagFlat['safe_validation_excerpts'] ?? null)
                            ? array_slice($diagFlat['safe_validation_excerpts'], 0, 6)
                            : [],
                        'safe_validation_excerpts_structured' => is_array($diagFlat['safe_validation_excerpts_structured'] ?? null)
                            ? array_slice($diagFlat['safe_validation_excerpts_structured'], 0, 6)
                            : [],
                        'booking_schema' => $this->effectiveSabreBookingSchema(),
                        'payload_schema' => $diagFlags['payload_schema'] ?? null,
                        'cpnr_schema_validation_failed_host_gateway' => $schemaHostGateway ? true : null,
                    ], $v25LiveSlice, $contextResultSlice, $diagFlat, $revalidationResultContext, self::extractLinkageMissingFlags($diagFlags), $endpointPersistence), $createPayloadSafeSummary),
                    $this->controlledRetryRecordedSlices($controlledF9jRetryRecorded, $controlledF9lSchemaRecoveryRecorded, $controlledF9qFinalRetryRecorded),
                );
            }

            $this->logSabrePnrAttemptSummaryFromLiveResult(
                $bookingIdForDiagnostics,
                is_string($bookingRefEarly) ? $bookingRefEarly : null,
                $bookingContextSummary,
                $live,
                true,
                false,
                (string) ($live['reason_code'] ?? 'sabre_booking_success'),
            );

            return array_merge(
                $this->withCreatePayloadSafeSummary(array_merge([
                    'success' => true,
                    'status' => 'pending_payment_or_ticketing',
                    'message' => (string) ($live['safe_message'] ?? __('PNR created. Ticketing is pending/manual.')),
                    'live_call_attempted' => true,
                    'live_call_allowed' => true,
                    'passenger_count' => $paxCount,
                    'segment_count' => $segCount,
                    'supplier_connection_id' => $connId,
                    'selected_offer_id' => $selectedOffer,
                    'fare_amount' => $fareAmt,
                    'fare_currency' => $fareCur,
                    'pnr' => $live['pnr'] ?? null,
                    'provider_booking_id' => $live['provider_booking_id'] ?? null,
                    'provider_status' => $live['provider_status'] ?? null,
                    'http_status' => $live['http_status'] ?? null,
                    'reason_code' => (string) ($live['reason_code'] ?? 'sabre_booking_success'),
                    'booking_schema' => $this->effectiveSabreBookingSchema(),
                    'payload_schema' => $diagFlags['payload_schema'] ?? null,
                ], $contextResultSlice, $b65Eligibility, $revalidationResultContext, $endpointPersistence, $freshShopGuardResultForSuccess !== null
                ? ['fresh_shop_guard_result' => $freshShopGuardResultForSuccess]
                : []), $createPayloadSafeSummary),
                $this->controlledRetryRecordedSlices($controlledF9jRetryRecorded, $controlledF9lSchemaRecoveryRecorded, $controlledF9qFinalRetryRecorded),
            );
        } finally {
            $this->attemptCertifiedRouteSelection = null;
            $this->attemptPassengerRecordsStyleDecision = null;
            $this->attemptOperationalAllowNnDecision = null;
            $this->attemptFreshnessStrategyDecision = null;
        }
    }

    /**
     * @param  array<string, mixed>  $apiDraft  Internal draft without {@code _valid}
     * @return array{has_fare_basis: bool, has_booking_class: bool, has_validating_carrier: bool}
     */
    /**
     * @return array<string, mixed>
     */
    protected static function offerRefreshAcceptanceSummarySlice(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];

        return [
            'offer_refresh_requires_customer_confirmation' => ($meta[SabreOfferRefreshAcceptance::META_REQUIRES_CONFIRMATION] ?? false) === true,
            'offer_refresh_accepted' => ($meta[SabreOfferRefreshAcceptance::META_ACCEPTED] ?? false) === true,
            'offer_refresh_price_changed' => ($meta[SabreOfferRefreshAcceptance::META_PRICE_CHANGED] ?? false) === true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function offerRefreshAcceptanceSummarySliceForBookingId(?int $bookingId): array
    {
        if ($bookingId === null || $bookingId < 1) {
            return [];
        }
        $booking = Booking::query()->find($bookingId);

        return $booking !== null ? self::offerRefreshAcceptanceSummarySlice($booking) : [];
    }

    protected static function draftFareShapeFlags(array $apiDraft): array
    {
        $hasFb = false;
        $hasBc = false;
        $segs = is_array($apiDraft['segments'] ?? null) ? $apiDraft['segments'] : [];
        foreach ($segs as $s) {
            if (! is_array($s)) {
                continue;
            }
            if (trim((string) ($s['fare_basis_code'] ?? '')) !== '') {
                $hasFb = true;
            }
            if (trim((string) ($s['booking_class'] ?? $s['class_of_service'] ?? '')) !== '') {
                $hasBc = true;
            }
        }
        $hasVc = trim((string) ($apiDraft['validating_carrier'] ?? '')) !== '';

        return [
            'has_fare_basis' => $hasFb,
            'has_booking_class' => $hasBc,
            'has_validating_carrier' => $hasVc,
        ];
    }

    /**
     * Sprint 1A: safe PCC fingerprint (never logs full PCC).
     *
     * @return array{pcc_present: bool, pcc_last2: ?string, pcc_hash: ?string}
     */
    protected function safePccFingerprintFromDraft(array $apiDraft, ?SupplierConnection $connection = null): array
    {
        $pcc = $this->bookingPayloadBuilder->resolveSabrePseudoCityCodeForTripOrdersWire($apiDraft);
        if ($pcc === '' && $connection !== null) {
            $cred = is_array($connection->credentials) ? $connection->credentials : [];
            $settings = is_array($connection->settings) ? $connection->settings : [];
            foreach (['pcc', 'PCC', 'pseudo_city_code', 'pseudoCityCode'] as $key) {
                $v = trim((string) ($cred[$key] ?? data_get($settings, $key, '')));
                if ($v !== '') {
                    $pcc = strtoupper(substr($v, 0, 16));
                    break;
                }
            }
        }

        if ($pcc === '') {
            return ['pcc_present' => false, 'pcc_last2' => null, 'pcc_hash' => null];
        }

        return [
            'pcc_present' => true,
            'pcc_last2' => strlen($pcc) >= 2 ? substr($pcc, -2) : $pcc,
            'pcc_hash' => substr(hash('sha256', $pcc), 0, 12),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return array{
     *     rbd_total_segments: int,
     *     rbd_present_count: int,
     *     rbd_missing_count: int,
     *     fare_basis_present_count: int,
     *     fare_basis_missing_count: int,
     *     marketing_carrier_count: int,
     *     operating_carrier_count: int
     * }
     */
    protected static function segmentBookableContextCoverage(array $segments): array
    {
        $rbdPresent = 0;
        $fbPresent = 0;
        $marketing = [];
        $operating = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $rbd = trim((string) ($seg['booking_class'] ?? $seg['class_of_service'] ?? ''));
            if ($rbd !== '') {
                $rbdPresent++;
            }
            $fb = trim((string) ($seg['fare_basis_code'] ?? $seg['fareBasisCode'] ?? ''));
            if ($fb !== '') {
                $fbPresent++;
            }
            $m = strtoupper(trim((string) ($seg['airline_code'] ?? $seg['carrier'] ?? $seg['marketing_carrier'] ?? '')));
            if ($m !== '') {
                $marketing[$m] = true;
            }
            $o = strtoupper(trim((string) ($seg['operating_airline_code'] ?? $seg['operating_carrier'] ?? '')));
            if ($o !== '') {
                $operating[$o] = true;
            }
        }
        $total = count(array_filter($segments, static fn ($s): bool => is_array($s)));

        return [
            'rbd_total_segments' => $total,
            'rbd_present_count' => $rbdPresent,
            'rbd_missing_count' => max(0, $total - $rbdPresent),
            'fare_basis_present_count' => $fbPresent,
            'fare_basis_missing_count' => max(0, $total - $fbPresent),
            'marketing_carrier_count' => count($marketing),
            'operating_carrier_count' => count($operating),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return array{
     *     segment_context_total: int,
     *     segment_context_complete_count: int,
     *     segment_context_incomplete_count: int
     * }
     */
    protected static function segmentStructuralContextCoverage(array $segments): array
    {
        $total = 0;
        $complete = 0;
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $total++;
            $origin = strtoupper(trim((string) ($seg['origin'] ?? '')));
            $dest = strtoupper(trim((string) ($seg['destination'] ?? '')));
            $dep = trim((string) ($seg['departure_at'] ?? $seg['depart_at'] ?? ''));
            if ($origin !== '' && $dest !== '' && $dep !== '') {
                $complete++;
            }
        }

        return [
            'segment_context_total' => $total,
            'segment_context_complete_count' => $complete,
            'segment_context_incomplete_count' => max(0, $total - $complete),
        ];
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $apiDraft
     * @param  array<string, mixed>  $extras  booking_id, booking_reference, endpoint_path, booking_schema, payload_style, diagFlags, certified_route, revalidation flags
     * @return array<string, mixed>
     */
    public function buildSabreBookingContextDiagnosticSummary(
        array $offer,
        array $apiDraft,
        ?SupplierConnection $connection = null,
        array $extras = [],
    ): array {
        $connId = (int) ($apiDraft['supplier_connection_id'] ?? $offer['supplier_connection_id'] ?? 0);
        $offerConnId = (int) ($offer['supplier_connection_id'] ?? 0);
        $pcc = $this->safePccFingerprintFromDraft($apiDraft, $connection);
        $segs = is_array($apiDraft['segments'] ?? null) ? array_values($apiDraft['segments']) : [];
        $coverage = array_merge(
            self::segmentBookableContextCoverage($segs),
            self::segmentStructuralContextCoverage($segs),
        );
        $handoff = is_array($offer['sabre_booking_context'] ?? null) ? $offer['sabre_booking_context'] : [];
        if ($handoff === []) {
            $handoff = is_array(data_get($offer, 'raw_payload.sabre_booking_context'))
                ? data_get($offer, 'raw_payload.sabre_booking_context')
                : [];
        }
        $schema = (string) ($extras['booking_schema'] ?? $this->effectiveSabreBookingSchema());
        $diagFlags = is_array($extras['diag_flags'] ?? null) ? $extras['diag_flags'] : [];
        $targetCityPresent = ($diagFlags['wire_has_target_city'] ?? false) === true
            || ($pcc['pcc_present'] ?? false) === true;

        $raw = is_array($offer['raw_payload'] ?? null) ? $offer['raw_payload'] : [];
        $tripType = trim((string) (
            $extras['trip_type']
            ?? data_get($offer, 'search_criteria.trip_type')
            ?? data_get($raw, 'trip_type')
            ?? ''
        ));
        $validating = strtoupper(trim((string) ($apiDraft['validating_carrier'] ?? $offer['validating_carrier'] ?? '')));
        if ($validating !== '') {
            $validating = substr($validating, 0, 8);
        }

        $snapshotSource = 'selected_offer';
        if ($offerConnId < 1 && $connId > 0) {
            $snapshotSource = 'draft_connection_only';
        } elseif ($offerConnId > 0 && $connId > 0 && $offerConnId !== $connId) {
            $snapshotSource = 'connection_mismatch';
        } elseif ($offerConnId < 1 && $connId < 1) {
            $snapshotSource = 'missing_connection';
        }

        $channel = self::inferSabreDistributionChannel($offer);
        $brandKeys = self::collectSafeBrandishKeys($offer);
        $fareOptionKey = trim((string) (
            data_get($raw, 'fare_option_key')
            ?? data_get($raw, 'sabre_shop_identifiers.fare_option_key')
            ?? data_get($offer, 'fare_option_key')
            ?? ''
        ));

        $summary = array_merge([
            'booking_id' => $extras['booking_id'] ?? null,
            'booking_reference' => isset($extras['booking_reference']) ? (string) $extras['booking_reference'] : null,
            'provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => $connId > 0 ? $connId : null,
            'supplier_connection_present' => $connId > 0,
            'supplier_connection_resolved' => $connection !== null,
            'offer_supplier_connection_id' => $offerConnId > 0 ? $offerConnId : null,
            'offer_snapshot_source' => $snapshotSource,
            'pcc_present' => (bool) ($pcc['pcc_present'] ?? false),
            'pcc_last2' => $pcc['pcc_last2'] ?? null,
            'pcc_hash' => $pcc['pcc_hash'] ?? null,
            'target_city_present' => $targetCityPresent,
            'endpoint_path' => isset($extras['endpoint_path']) ? (string) $extras['endpoint_path'] : null,
            'payload_style' => isset($extras['payload_style']) ? (string) $extras['payload_style'] : (is_string($diagFlags['payload_style'] ?? null) ? (string) $diagFlags['payload_style'] : null),
            'booking_schema' => $schema !== '' ? $schema : null,
            'booking_mode' => (string) config('suppliers.sabre.booking_mode', 'pnr_only'),
            'trip_type' => $tripType !== '' ? $tripType : null,
            'segment_count' => count($segs),
            'distribution_channel' => $channel,
            'validating_carrier' => $validating !== '' ? $validating : null,
            'has_brand_code' => $brandKeys !== [],
            'has_fare_option_key' => $fareOptionKey !== '',
            'handoff_ready_for_booking_payload' => ($handoff['ready_for_booking_payload'] ?? false) === true,
            'handoff_has_revalidation_linkage' => ($handoff['has_revalidation_linkage'] ?? false) === true,
            'handoff_segment_slice_count' => isset($handoff['segment_slice_count']) ? (int) $handoff['segment_slice_count'] : null,
            'handoff_pricing_information_index' => isset($handoff['pricing_information_index']) ? (int) $handoff['pricing_information_index'] : null,
            'handoff_brand_code_present' => trim((string) ($handoff['brand_code'] ?? '')) !== '',
            'revalidation_required' => (bool) ($extras['revalidation_required'] ?? $this->isRevalidationBeforeBookingEnabled()),
            'revalidation_attempted' => (bool) ($extras['revalidation_attempted'] ?? false),
            'certified_route_result' => is_array($extras['certified_route_result'] ?? null)
                ? $extras['certified_route_result']
                : (is_array($this->attemptCertifiedRouteSelection) ? [
                    'route_status' => $this->attemptCertifiedRouteSelection['route_status'] ?? null,
                    'endpoint_path' => $this->attemptCertifiedRouteSelection['endpoint_path'] ?? null,
                    'payload_style' => $this->attemptCertifiedRouteSelection['payload_style'] ?? null,
                ] : null),
        ], $coverage);

        foreach ([
            'wire_has_air_book' => 'air_book_present',
            'wire_has_air_price' => 'air_price_present',
            'wire_has_root_air_price' => 'root_air_price_present',
            'wire_has_end_transaction' => 'end_transaction_present',
            'wire_has_received_from' => 'received_from_present',
            'wire_ticketing_enabled' => 'ticketing_block_present',
            'wire_has_contact' => 'contact_block_present',
            'wire_has_contactInfo' => 'contact_info_block_present',
            'wire_has_customerInfo' => 'customer_info_block_present',
        ] as $diagKey => $outKey) {
            if (array_key_exists($diagKey, $diagFlags)) {
                $summary[$outKey] = (bool) $diagFlags[$diagKey];
            }
        }

        foreach ([
            'is_iati_like_cpnr_style',
            'endpoint_version',
            'target_city_present',
            'airbook_present',
            'airprice_present',
            'end_transaction_present',
            'received_from_present',
            'ticketing_present',
            'docs_block_present',
            'ctce_block_present',
            'ctcm_block_present',
            'secure_flight_present',
            'brand_code_present',
            'passenger_type_pricing_present',
        ] as $iatiKey) {
            if (array_key_exists($iatiKey, $diagFlags)) {
                $summary[$iatiKey] = $diagFlags[$iatiKey];
            }
        }
        if (! array_key_exists('endpoint_version', $summary) && is_string($diagFlags['endpoint_version'] ?? null)) {
            $summary['endpoint_version'] = $diagFlags['endpoint_version'];
        }
        if (($summary['is_iati_like_cpnr_style'] ?? false) === true && ! array_key_exists('endpoint_version', $summary)) {
            $summary['endpoint_version'] = '2.4.0';
        }

        if (isset($extras['safe_reason_code']) && is_string($extras['safe_reason_code']) && $extras['safe_reason_code'] !== '') {
            $summary['safe_reason_code'] = $extras['safe_reason_code'];
        }

        if (is_array($this->attemptPassengerRecordsStyleDecision)) {
            $summary = array_merge($summary, $this->passengerRecordsStyleDecisionDiagnosticSlice($this->attemptPassengerRecordsStyleDecision));
            if (! array_key_exists('payload_style', $summary) || trim((string) ($summary['payload_style'] ?? '')) === '') {
                $summary['payload_style'] = $this->attemptPassengerRecordsStyleDecision['selected_payload_style'] ?? null;
            }
            $selectedEp = trim((string) ($this->attemptPassengerRecordsStyleDecision['selected_endpoint_path'] ?? ''));
            if ($selectedEp !== '') {
                $summary['endpoint_path'] = $selectedEp;
                $summary['selected_endpoint_path'] = $selectedEp;
            }
        }

        if (is_array($this->attemptFreshnessStrategyDecision)) {
            $summary = array_merge($summary, $this->freshnessStrategyDiagnosticSlice($this->attemptFreshnessStrategyDecision));
        }

        return array_filter($summary, static fn ($v) => $v !== null);
    }

    /**
     * @param  array<string, mixed>  $contextSummary
     * @param  array<string, mixed>  $diagFlags
     * @return array{blocks_live_call: bool, reason_code: string, safe_reason_code: string}
     */
    protected function assessSabreBookingContextForLiveAttempt(array $contextSummary, array $diagFlags = []): array
    {
        $reasons = [];
        if (($contextSummary['supplier_connection_present'] ?? false) !== true) {
            $reasons[] = 'missing_supplier_connection_id';
        }
        if (($contextSummary['supplier_connection_resolved'] ?? false) !== true) {
            $reasons[] = 'supplier_connection_unresolved';
        }
        $schema = (string) ($contextSummary['booking_schema'] ?? '');
        $traditional = $schema === 'create_passenger_name_record'
            || (($diagFlags['payload_schema'] ?? '') === SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1);
        $wireContractInvalid = ($diagFlags['wire_traditional_pnr_contract_valid'] ?? true) === false;
        if ($traditional && $wireContractInvalid) {
            if (($contextSummary['pcc_present'] ?? false) !== true) {
                $reasons[] = 'pcc_missing';
            }
            if (($contextSummary['target_city_present'] ?? false) !== true) {
                $reasons[] = 'target_city_missing';
            }
        }
        $segTotal = (int) ($contextSummary['rbd_total_segments'] ?? 0);
        $rbdPresent = (int) ($contextSummary['rbd_present_count'] ?? 0);
        if ($traditional && $segTotal > 0 && $rbdPresent < 1) {
            $reasons[] = 'rbd_missing_all_segments';
        }
        $structTotal = (int) ($contextSummary['segment_context_total'] ?? 0);
        $structComplete = (int) ($contextSummary['segment_context_complete_count'] ?? 0);
        if ($structTotal > 0 && $structComplete < $structTotal) {
            $reasons[] = 'segment_context_incomplete';
        }
        if (($contextSummary['offer_snapshot_source'] ?? '') === 'connection_mismatch') {
            $reasons[] = 'offer_connection_mismatch';
        }

        if ($reasons === []) {
            return [
                'blocks_live_call' => false,
                'reason_code' => '',
                'safe_reason_code' => '',
            ];
        }

        return [
            'blocks_live_call' => true,
            'reason_code' => 'sabre_booking_context_incomplete',
            'safe_reason_code' => implode(',', array_slice($reasons, 0, 8)),
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    protected function logSabreBookingContextSummary(array $summary): void
    {
        Log::info('sabre.booking.context_summary', SensitiveDataRedactor::redact($summary));
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    protected function logSabreBookingRevalidationSummary(array $summary): void
    {
        Log::info('sabre.booking.revalidation_summary', SensitiveDataRedactor::redact($summary));
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    protected function logSabreBookingPnrAttemptSummary(array $summary): void
    {
        Log::info('sabre.booking.pnr_attempt_summary', SensitiveDataRedactor::redact($summary));
    }

    /**
     * @param  array<string, mixed>  $result  {@see createBooking()} output
     * @return array<string, mixed>
     */
    protected static function bookingContextDiagnosticSliceFromResult(array $result): array
    {
        $summary = is_array($result['booking_context_summary'] ?? null) ? $result['booking_context_summary'] : [];
        if ($summary === []) {
            return [];
        }

        return ['booking_context_summary' => $summary];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>|null  $freshnessDecision
     * @return array<string, mixed>
     */
    protected static function enrichBookingContextSummaryFromGdsStrategy(
        array $summary,
        array $options,
        ?array $freshnessDecision = null,
    ): array {
        $selection = is_array($options['gds_strategy_selection'] ?? null)
            ? $options['gds_strategy_selection']
            : [];
        if ($selection !== []) {
            $summary['gds_strategy_selection'] = array_intersect_key($selection, array_flip([
                'selected_strategy',
                'selection_reason',
                'eligible_strategies',
                'blocked_strategies',
                'scenario_runner_override_applied',
                'strategy_option',
            ]));
        }
        if (($selection['scenario_runner_override_applied'] ?? false) === true) {
            $summary['scenario_runner_override_applied'] = true;
        }
        if (is_array($freshnessDecision)) {
            $freshnessSource = trim((string) ($freshnessDecision['freshness_source'] ?? ''));
            if ($freshnessSource !== '') {
                $summary['freshness_source'] = $freshnessSource;
            }
            $skipReason = trim((string) ($freshnessDecision['revalidation_skip_reason'] ?? ''));
            if ($skipReason !== '') {
                $summary['revalidation_skipped_reason'] = $skipReason;
            }
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected static function gdsStrategySelectionAttemptSliceFromResult(array $result): array
    {
        $selection = is_array($result['gds_strategy_selection'] ?? null)
            ? $result['gds_strategy_selection']
            : [];
        if ($selection === []) {
            return array_filter([
                'scenario_runner_override_applied' => ($result['scenario_runner_override_applied'] ?? false) === true ? true : null,
            ], static fn ($v) => $v !== null);
        }

        return array_filter([
            'gds_strategy_selection' => array_intersect_key($selection, array_flip([
                'selected_strategy',
                'selection_reason',
                'eligible_strategies',
                'blocked_strategies',
                'scenario_runner_override_applied',
                'strategy_option',
            ])),
            'scenario_runner_override_applied' => ($selection['scenario_runner_override_applied'] ?? $result['scenario_runner_override_applied'] ?? false) === true
                ? true
                : null,
        ], static fn ($v) => $v !== null && $v !== []);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected static function autoPnrContextCompletionCheckoutSlice(array $result, ?Booking $booking = null): array
    {
        $completion = is_array($result['auto_pnr_context_completion'] ?? null)
            ? $result['auto_pnr_context_completion']
            : [];
        if ($completion === [] && $booking !== null) {
            $stored = data_get(is_array($booking->meta) ? $booking->meta : [], SabreGdsAutoPnrContextCompletionService::META_KEY, []);
            $completion = is_array($stored) ? $stored : [];
        }
        if ($completion === []) {
            return [];
        }

        return app(SabreGdsAutoPnrContextCompletionService::class)->checkoutPersistSlice($completion);
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected function appendAutoPnrContextCompletionToAttemptSummary(array $summary, array $result): array
    {
        $slice = self::autoPnrContextCompletionCheckoutSlice($result);

        return $slice === [] ? $summary : array_merge($summary, $slice);
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected function appendBookingContextToAttemptSummary(array $summary, array $result): array
    {
        $slice = self::bookingContextDiagnosticSliceFromResult($result);
        $summary = $slice === [] ? $summary : array_merge($summary, $slice);

        return array_merge(
            $summary,
            self::gdsStrategySelectionAttemptSliceFromResult($result),
            $this->appendAutoPnrContextCompletionToAttemptSummary([], $result),
        );
    }

    /**
     * Public GDS auto-PNR must complete per-segment branded context before Passenger Records live HTTP.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>|null Block result when completion cannot allow create; null to proceed.
     */
    protected function enforcePublicAutoPnrContextCompletionBeforeLiveCreate(
        ?int $bookingIdForDiagnostics,
        int $segCount,
        array $options,
    ): ?array {
        if (! $this->shouldEnforcePublicAutoPnrContextCompletion($bookingIdForDiagnostics, $options)) {
            return null;
        }

        $booking = Booking::query()->withCount('passengers')->find((int) $bookingIdForDiagnostics);
        if ($booking === null) {
            return null;
        }

        $contextCompletionService = app(SabreGdsAutoPnrContextCompletionService::class);
        $completion = is_array($options['auto_pnr_context_completion'] ?? null)
            ? $options['auto_pnr_context_completion']
            : [];

        if (($completion['auto_pnr_context_completion_attempted'] ?? false) !== true) {
            $stored = $contextCompletionService->readStoredCompletion($booking);
            if (($stored['auto_pnr_context_completion_attempted'] ?? false) === true) {
                $completion = array_merge($completion, $stored);
            } else {
                $completion = $contextCompletionService->completeForBooking($booking);
            }
        }

        if (! $contextCompletionService->publicCreateAllowed($segCount, $completion)) {
            $blockReason = trim((string) ($completion['public_auto_pnr_block_reason'] ?? ''));
            if ($blockReason === '') {
                $blockReason = SabreGdsAutoPnrContextCompletionService::REASON_CONTEXT_COMPLETION_FAILED;
            }
            $contextCompletionService->persistCompletionDiagnostics($booking->fresh(), $completion);

            return [
                'success' => false,
                'status' => 'needs_review',
                'message' => $this->customerStaffConfirmationBookingMessage(),
                'live_call_attempted' => false,
                'live_call_allowed' => true,
                'passenger_count' => (int) ($booking->passengers_count ?? 0),
                'segment_count' => $segCount,
                'error_code' => $blockReason,
                'reason_code' => $blockReason,
                'pnr_attempted' => false,
                'manual_review_required' => true,
                'auto_pnr_context_completion' => $completion,
                'public_auto_pnr_attempted' => false,
                'public_auto_pnr_block_reason' => $blockReason,
                'booking_schema' => $this->effectiveSabreBookingSchema(),
            ];
        }

        if (($completion['public_auto_pnr_attempt_ready'] ?? false) === true) {
            $stored = $contextCompletionService->readStoredCompletion($booking->fresh());
            if (trim((string) ($stored['auto_pnr_context_completion_status'] ?? '')) === '') {
                $contextCompletionService->persistCompletedContext($booking->fresh(), $completion);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function shouldEnforcePublicAutoPnrContextCompletion(?int $bookingIdForDiagnostics, array $options): bool
    {
        if ($bookingIdForDiagnostics === null || $bookingIdForDiagnostics < 1) {
            return false;
        }
        if ($this->isAdminManualStrategyFallbackActive($options)) {
            return false;
        }
        if (($options['certification_full_itinerary_fallback'] ?? false) === true) {
            return false;
        }
        if (($options['controlled_f9j_retry_eligible'] ?? false) === true
            || ($options['controlled_f9l_schema_recovery_eligible'] ?? false) === true
            || ($options['controlled_f9q_final_retry_eligible'] ?? false) === true) {
            return false;
        }
        if ($this->effectiveSabreBookingSchema() !== 'create_passenger_name_record') {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    protected static function inferSabreDistributionChannel(array $offer): ?string
    {
        $raw = is_array($offer['raw_payload'] ?? null) ? $offer['raw_payload'] : [];
        foreach (['distributionModel', 'distribution_model', 'contentSource', 'pricing_source', 'source'] as $key) {
            $v = strtolower(trim((string) ($raw[$key] ?? $offer[$key] ?? '')));
            if ($v !== '' && str_contains($v, 'ndc')) {
                return 'ndc';
            }
        }

        return 'gds';
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return list<string>
     */
    protected static function collectSafeBrandishKeys(array $offer): array
    {
        $raw = is_array($offer['raw_payload'] ?? null) ? $offer['raw_payload'] : [];
        $keys = [];
        foreach (['brand_code', 'brandCode', 'brand_id', 'brandId'] as $k) {
            if (trim((string) ($raw[$k] ?? $offer[$k] ?? '')) !== '') {
                $keys[] = $k;
            }
        }
        $branded = is_array($offer['branded_fares'] ?? null) ? $offer['branded_fares'] : [];
        if ($branded !== []) {
            $keys[] = 'branded_fares';
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param  array<string, mixed>  $diagFlags
     */
    protected function logSabreRevalidationSummaryFromOutcome(
        ?int $bookingId,
        ?string $bookingReference,
        int $supplierConnectionId,
        array $apiDraft,
        ?SupplierConnection $connection,
        array $revalidationOutcome,
        bool $skipped,
        ?string $skipReason,
        bool $revEnabled,
        bool $allowBypass,
    ): void {
        $pcc = $this->safePccFingerprintFromDraft($apiDraft, $connection);
        $blocks = false;
        if ($skipped) {
            $blocks = false;
        } elseif (! ($revalidationOutcome['success'] ?? false)) {
            $blocks = ! $allowBypass && $revEnabled;
        }

        $errDigest = is_array($revalidationOutcome['error_digest'] ?? null) ? $revalidationOutcome['error_digest'] : [];
        $safeErr = '';
        if ($errDigest !== []) {
            $codes = is_array($errDigest['error_codes'] ?? null) ? $errDigest['error_codes'] : [];
            $msgs = is_array($errDigest['error_messages'] ?? null) ? $errDigest['error_messages'] : [];
            $safeErr = substr(implode('; ', array_filter([
                $codes !== [] ? implode(',', array_map('strval', array_slice($codes, 0, 4))) : '',
                $msgs !== [] ? implode('; ', array_map('strval', array_slice($msgs, 0, 2))) : '',
            ])), 0, 240);
        }

        $summary = [
            'booking_id' => $bookingId,
            'booking_reference' => $bookingReference,
            'supplier_connection_id' => $supplierConnectionId > 0 ? $supplierConnectionId : null,
            'pcc_present' => (bool) ($pcc['pcc_present'] ?? false),
            'endpoint_path' => (string) ($revalidationOutcome['endpoint_path'] ?? $this->effectiveRevalidatePathSuffix(null)),
            'payload_style' => (string) ($revalidationOutcome['payload_style'] ?? ''),
            'attempted' => ! $skipped,
            'skipped' => $skipped,
            'skip_reason' => $skipReason,
            'success' => (bool) ($revalidationOutcome['success'] ?? false),
            'http_status' => $revalidationOutcome['http_status'] ?? null,
            'sabre_error_code' => is_string($revalidationOutcome['reason_code'] ?? null)
                ? (string) $revalidationOutcome['reason_code']
                : null,
            'safe_error_message_summary' => $safeErr !== '' ? $safeErr : null,
            'blocks_booking' => $blocks,
        ];
        if (is_array($this->attemptFreshnessStrategyDecision)) {
            $summary = array_merge($summary, $this->freshnessStrategyDiagnosticSlice($this->attemptFreshnessStrategyDecision));
        }
        $this->logSabreBookingRevalidationSummary($summary);
    }

    /**
     * @param  array<string, mixed>  $contextSummary
     * @param  array<string, mixed>  $liveResult
     */
    protected function logSabrePnrAttemptSummaryFromLiveResult(
        ?int $bookingId,
        ?string $bookingReference,
        array $contextSummary,
        array $liveResult,
        bool $pnrAttempted,
        bool $manualReview,
        ?string $reasonCode,
    ): void {
        $pnr = trim((string) ($liveResult['pnr'] ?? ''));
        $this->logSabreBookingPnrAttemptSummary(array_merge($contextSummary, [
            'booking_id' => $bookingId,
            'booking_reference' => $bookingReference,
            'pnr_attempted' => $pnrAttempted,
            'pnr_created' => $pnr !== '',
            'pnr_present' => $pnr !== '',
            'response_http_status' => $liveResult['http_status'] ?? null,
            'sabre_error_code' => isset($liveResult['error_code']) ? (string) $liveResult['error_code'] : (isset($liveResult['reason_code']) ? (string) $liveResult['reason_code'] : null),
            'safe_error_message_summary' => isset($liveResult['safe_message'])
                ? substr((string) $liveResult['safe_message'], 0, 240)
                : (isset($liveResult['message']) ? substr((string) $liveResult['message'], 0, 240) : null),
            'manual_review' => $manualReview,
            'reason_code' => $reasonCode,
        ]));
    }

    /**
     * @param  array<string, mixed>  $diagFlags  {@see SabreBookingPayloadBuilder::summarizeEnvelopeForDiagnostics()}
     * @return array<string, mixed>
     */
    protected static function liveBookingRevalidationAuditSlice(
        bool $revEnabled,
        bool $allowBypass,
        bool $revalidationSkippedByConfig,
        ?string $previousRevalidationReasonCode,
        ?string $prebookingRevalidationSkippedReason,
        string $endpointPath,
        string $bookingSchema,
        array $diagFlags,
        int $passengerCount,
        int $segmentCount,
    ): array {
        $out = [
            'revalidation_skipped_by_config' => $revalidationSkippedByConfig,
            'revalidation_bypass_enabled' => $allowBypass,
            'revalidation_before_booking_enabled' => $revEnabled,
            'ticketing_enabled' => false,
            'endpoint_path' => $endpointPath !== '' ? $endpointPath : null,
            'booking_schema' => $bookingSchema,
            'has_fare_basis' => (bool) ($diagFlags['has_fare_basis'] ?? false),
            'has_booking_class' => (bool) ($diagFlags['has_booking_class'] ?? false),
            'has_validating_carrier' => (bool) ($diagFlags['has_validating_carrier'] ?? false),
            'passenger_count' => $passengerCount,
            'segment_count' => $segmentCount,
        ];
        if ($previousRevalidationReasonCode !== null && $previousRevalidationReasonCode !== '') {
            $out['previous_revalidation_reason_code'] = $previousRevalidationReasonCode;
        }
        if ($prebookingRevalidationSkippedReason !== null && $prebookingRevalidationSkippedReason !== '') {
            $out['prebooking_revalidation_skipped_reason'] = $prebookingRevalidationSkippedReason;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $result  Output of {@see createBooking()}
     * @return array<string, mixed>
     */
    protected static function bookingRevalidationAuditForMeta(array $result): array
    {
        return array_filter(
            array_intersect_key($result, array_flip([
                'revalidation_skipped_by_config',
                'revalidation_bypass_enabled',
                'revalidation_before_booking_enabled',
                'prebooking_revalidation_skipped_reason',
                'ticketing_enabled',
                'previous_revalidation_reason_code',
                'has_fare_basis',
                'has_booking_class',
                'has_validating_carrier',
                'segment_count',
                'passenger_count',
            ])),
            static fn ($v) => $v !== null,
        );
    }

    /**
     * Slice the linkage flag keys present in a {@see createBooking()} result so attempt safe_summary persists exactly
     * which Trip Orders linkage fields were available at booking-time.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected static function linkageFlagSliceFromResult(array $result): array
    {
        $keys = [
            'has_fare_basis', 'has_fare_reference', 'has_price_quote_reference', 'has_offer_reference',
            'has_revalidation_reference', 'has_itinerary_reference', 'has_validating_carrier',
            'has_revalidated_fare', 'has_revalidated_currency', 'has_end_transaction', 'has_commit_or_end_transaction',
            'missing_linkage_flags',
            'revalidation_attempted', 'revalidation_outcome', 'revalidation_http_status', 'revalidation_duration_ms',
            'revalidation_linkage_digest',
            'revalidation_skipped_by_config', 'revalidation_bypass_enabled', 'revalidation_before_booking_enabled',
            'ticketing_enabled', 'previous_revalidation_reason_code', 'has_booking_class', 'segment_count', 'passenger_count',
            'booking_schema', 'endpoint_path',
            'payload_style', 'has_flight_offer', 'has_flight_details', 'has_required_booking_product_object',
            'has_segments_inside_flight_offer', 'has_segments_inside_flight_details',
            'wire_root_keys', 'wire_has_flight_offer_at_root', 'wire_has_flight_details_at_root',
            'wire_has_required_product_at_root', 'wire_has_required_booking_product_nested',
            'wire_flight_offer_path', 'wire_flight_details_path', 'wire_segment_count',
            'wire_has_passengers', 'wire_has_contact', 'wire_has_contactInfo', 'wire_contact_field_style', 'wire_has_contact_email', 'wire_has_contact_phone',
            'wire_has_customer_contact_phone', 'wire_has_agency_phone', 'wire_agency_phone_field_style', 'wire_agency_phone_paths', 'wire_agency_phone_redacted', 'wire_agency_phone_ok', 'wire_has_POS', 'wire_has_pos', 'wire_has_agency_block', 'wire_has_travelAgency', 'wire_has_customerInfo', 'wire_pcc_present', 'wire_agency_config_phone_present', 'wire_agency_country_config_present', 'wire_phone_use_type_values_sanitized', 'wire_phone_location_values_sanitized',
            'wire_has_payment_or_hold_mode', 'wire_ticketing_enabled',
            'wire_has_hotel_at_root', 'wire_has_car_at_root',
            'wire_gender_values_sanitized', 'wire_gender_enum_valid',
            'wire_has_remarks', 'wire_remarks_count',
            'wire_traveler_field_style', 'wire_has_givenName', 'wire_has_given_name',
            'wire_has_passengerCode', 'wire_has_passengerTypeCode',
        ];

        return array_intersect_key($result, array_flip($keys));
    }

    /**
     * For Sabre HTTP failures and application errors, surface the Trip Orders linkage flags (has_fare_basis,
     * has_fare_reference, etc.) at the top level of the returned result so admin / safe_summary can persist exactly
     * which mandatory linkage fields were absent in the booking payload.
     *
     * @param  array<string, mixed>  $diagFlags  Output of {@see SabreBookingPayloadBuilder::summarizeEnvelopeForDiagnostics()}
     * @return array<string, mixed>
     */
    protected static function extractLinkageMissingFlags(array $diagFlags): array
    {
        $keys = [
            'has_fare_basis', 'has_fare_reference', 'has_price_quote_reference', 'has_offer_reference',
            'has_revalidation_reference', 'has_itinerary_reference', 'has_validating_carrier',
            'has_revalidated_fare', 'has_revalidated_currency', 'has_end_transaction', 'has_commit_or_end_transaction',
        ];
        $out = [];
        foreach ($keys as $k) {
            if (array_key_exists($k, $diagFlags)) {
                $out[$k] = (bool) $diagFlags[$k];
            }
        }
        $missing = [];
        foreach (['has_fare_basis', 'has_fare_reference', 'has_price_quote_reference', 'has_offer_reference', 'has_revalidation_reference'] as $k) {
            if (array_key_exists($k, $diagFlags) && ! $diagFlags[$k]) {
                $missing[] = $k;
            }
        }
        if (($diagFlags['has_trip_orders_schema'] ?? false) === true) {
            foreach (['has_end_transaction'] as $k) {
                if (array_key_exists($k, $diagFlags) && ! $diagFlags[$k]) {
                    $missing[] = $k;
                }
            }
        }
        if ($missing !== []) {
            $out['missing_linkage_flags'] = $missing;
        }
        foreach (['has_flight_offer', 'has_flight_details', 'has_required_booking_product_object', 'has_segments_inside_flight_offer', 'has_segments_inside_flight_details'] as $fk) {
            if (array_key_exists($fk, $diagFlags)) {
                $out[$fk] = (bool) $diagFlags[$fk];
            }
        }
        if (array_key_exists('payload_style', $diagFlags) && is_string($diagFlags['payload_style']) && trim($diagFlags['payload_style']) !== '') {
            $out['payload_style'] = trim($diagFlags['payload_style']);
        }
        foreach ([
            'wire_root_keys', 'wire_has_flight_offer_at_root', 'wire_has_flight_details_at_root',
            'wire_has_hotel_at_root', 'wire_has_car_at_root', 'wire_has_required_product_at_root',
            'wire_has_required_booking_product_nested', 'wire_flight_offer_path', 'wire_flight_details_path',
            'wire_segment_count', 'wire_has_passengers', 'wire_has_contact', 'wire_has_contactInfo', 'wire_contact_field_style', 'wire_has_contact_email', 'wire_has_contact_phone',
            'wire_has_customer_contact_phone', 'wire_has_agency_phone', 'wire_agency_phone_field_style', 'wire_agency_phone_paths', 'wire_agency_phone_redacted', 'wire_agency_phone_ok', 'wire_has_POS', 'wire_has_pos', 'wire_has_agency_block', 'wire_has_travelAgency', 'wire_has_customerInfo', 'wire_pcc_present', 'wire_agency_config_phone_present', 'wire_agency_country_config_present', 'wire_phone_use_type_values_sanitized', 'wire_phone_location_values_sanitized',
            'wire_has_payment_or_hold_mode',
            'wire_ticketing_enabled', 'wire_gender_values_sanitized', 'wire_gender_enum_valid',
            'wire_has_remarks', 'wire_remarks_count',
            'wire_traveler_field_style', 'wire_has_givenName', 'wire_has_given_name',
            'wire_has_passengerCode', 'wire_has_passengerTypeCode',
        ] as $wk) {
            if (array_key_exists($wk, $diagFlags)) {
                $out[$wk] = $diagFlags[$wk];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>
     */
    protected function ticketingHintsFromOffer(array $offer): array
    {
        $raw = is_array($offer['raw_payload'] ?? null) ? $offer['raw_payload'] : [];
        $out = [];
        $tl = data_get($raw, 'payment_requirements.payment_required_by') ?? ($offer['offer_expires_at'] ?? null);
        if (is_string($tl) && trim($tl) !== '') {
            $out['time_limit_iso'] = trim($tl);
        }

        return $out;
    }

    protected function resolveSabreCheckoutAttemptSource(array $result): string
    {
        $source = trim((string) ($result['source'] ?? ''));

        return $source !== '' ? $source : 'sabre_public_checkout';
    }

    /**
     * Persists safe Sabre checkout outcome, optional supplier_booking_attempts, and live PNR columns for public flow.
     *
     * @param  array<string, mixed>  $result  Output of {@see createBooking()}
     */
    public function finalizePublicCheckoutSabreStorage(Booking $booking, array $result): void
    {
        $booking->refresh();
        $attemptSource = $this->resolveSabreCheckoutAttemptSource($result);
        $lifecycle = app(SabreGdsAutoPnrLifecycleService::class);
        $lifecycle->recordOfferRefreshed($booking);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $completionAttemptSlice = self::autoPnrContextCompletionCheckoutSlice($result, $booking);
        if ($completionAttemptSlice !== []) {
            $result = array_merge($result, $completionAttemptSlice);
        }
        $checkoutOutcome = array_merge([
            'status' => (string) ($result['status'] ?? ''),
            'recorded_at' => now()->toIso8601String(),
            'live_call_attempted' => (bool) ($result['live_call_attempted'] ?? false),
            'http_status' => $result['http_status'] ?? null,
            'booking_schema' => $result['booking_schema'] ?? null,
            'payload_schema' => self::resolvePayloadSchemaForSummary($result),
            'error_code' => $result['error_code'] ?? null,
            'pnr_strategy_selected' => $result['pnr_strategy_selected'] ?? data_get($result, 'gds_strategy_selection.selected_strategy'),
            'pnr_strategy_used' => $result['pnr_strategy_used'] ?? self::resolvePayloadSchemaForSummary($result),
            'pnr_block_reason_code' => $result['pnr_block_reason_code'] ?? ($result['error_code'] ?? null),
            'scenario_runner_override_applied' => ($result['scenario_runner_override_applied'] ?? data_get($result, 'gds_strategy_selection.scenario_runner_override_applied')) === true
                ? true
                : null,
        ], $this->sabreCheckoutOutcomeDigestSlice($result), $this->passengerRecordsMultiSegmentOutcomeSlice($result), self::bookingRevalidationAuditForMeta($result), self::bookingContextDiagnosticSliceFromResult($result), $this->gdsCheckoutOperationalDiagnosticSlice($result, $booking), $completionAttemptSlice, self::gdsStrategySelectionAttemptSliceFromResult($result));
        $checkoutOutcome = $lifecycle->reconcileCheckoutOutcomeRevalidationFlags($booking, $checkoutOutcome);
        if ($completionAttemptSlice !== []) {
            $handoff = is_array($meta['sabre_booking_context'] ?? null) ? $meta['sabre_booking_context'] : [];
            $meta['sabre_booking_context'] = app(SabreGdsAutoPnrContextCompletionService::class)
                ->mergeCompletionIntoSabreBookingContext($handoff, $completionAttemptSlice);
            $meta[SabreGdsAutoPnrContextCompletionService::META_KEY] = $completionAttemptSlice;
            $booking->forceFill(['meta' => $meta]);
        }
        if (self::shouldAttachSabreHostClassification($result)) {
            $context = self::buildSabreHostClassificationContextFromResult($result);
            $checkoutOutcome['sabre_host_classification'] = SabreHostErrorClassifier::buildPersistedSlice(
                $context,
                array_intersect_key($result, array_flip([
                    'live_call_attempted',
                    'booking_schema',
                    'payload_schema',
                    'segment_count',
                    'passenger_count',
                ])),
            );
            $hostFingerprint = SabreHostRejectionFingerprint::buildForPersistence(
                $booking,
                $checkoutOutcome['sabre_host_classification'],
            );
            if (is_array($hostFingerprint) && $hostFingerprint !== []) {
                $checkoutOutcome['sabre_host_rejection_fingerprint'] = $hostFingerprint;
            }
            $this->persistSabreHostSellDiagnostics($booking, $result, $checkoutOutcome);
        }
        $mixedProof = is_array($result['mixed_carrier_preflight_proof'] ?? null)
            ? $result['mixed_carrier_preflight_proof']
            : (is_array($meta[SabreGdsMixedCarrierFareBasisPayloadPreflight::META_PREFLIGHT_PROOF_KEY] ?? null)
                ? $meta[SabreGdsMixedCarrierFareBasisPayloadPreflight::META_PREFLIGHT_PROOF_KEY]
                : []);
        if ($mixedProof !== []) {
            $meta[SabreGdsMixedCarrierFareBasisPayloadPreflight::META_PREFLIGHT_PROOF_KEY] = $mixedProof;
            $checkoutOutcome = array_merge($checkoutOutcome, array_intersect_key(
                $mixedProof,
                array_flip(app(SabreGdsMixedCarrierFareBasisPayloadPreflight::class)->attemptProofKeys()),
            ));
        }
        $meta['sabre_checkout_outcome'] = $checkoutOutcome;
        $meta['sabre_provider_snapshot'] = array_filter([
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => $result['supplier_connection_id'] ?? data_get($meta, 'supplier_connection_id'),
            'selected_offer_id' => $result['selected_offer_id'] ?? null,
            'passenger_count' => $result['passenger_count'] ?? null,
            'segment_count' => $result['segment_count'] ?? null,
            'total_amount' => $result['fare_amount'] ?? null,
            'currency' => $result['fare_currency'] ?? null,
            'provider_status' => $result['provider_status'] ?? null,
            'pnr' => $result['pnr'] ?? null,
            'supplier_api_booking_id' => $result['provider_booking_id'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $cid = $meta['supplier_connection_id'] ?? $result['supplier_connection_id'] ?? null;
        $cid = is_numeric($cid) ? (int) $cid : null;
        $attemptConnectionId = ($cid !== null && $cid > 0) ? $cid : null;

        $status = (string) ($result['status'] ?? '');

        $bookingColumnPatch = [];

        if ($status === 'dry_run' && ($result['success'] ?? false)) {
            $ep = $this->resolveEndpointSummaryPreferringBookingResult($result, (int) ($attemptConnectionId ?? 0));
            $payloadSummary = is_array($result['payload_safe_summary'] ?? null) ? $result['payload_safe_summary'] : [];
            SupplierBookingAttempt::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $attemptConnectionId,
                'provider' => SupplierProvider::Sabre->value,
                'action' => 'create_pnr',
                'status' => 'dry_run',
                'safe_summary' => array_merge([
                    'source' => $attemptSource,
                    'passenger_count' => (int) ($result['passenger_count'] ?? 0),
                    'segment_count' => (int) ($result['segment_count'] ?? 0),
                    'live_call_attempted' => false,
                    'booking_schema' => (string) ($result['booking_schema'] ?? $this->effectiveSabreBookingSchema()),
                ], self::bookingRevalidationAuditForMeta($result), $payloadSummary, self::createPayloadAndStructureSliceFromResult($result), self::autoPnrContextCompletionCheckoutSlice($result, $booking), array_intersect_key($ep, array_flip([
                    'endpoint_host', 'endpoint_path', 'timeout_seconds', 'connect_timeout_seconds',
                ]))),
                'attempted_by' => null,
                'attempted_at' => now(),
                'completed_at' => now(),
            ]);
        } elseif (($result['reason_code'] ?? '') === 'sabre_passenger_records_pnr_unconfirmed_segment_nn' && $status === 'needs_review') {
            $ep = $this->resolveEndpointSummaryPreferringBookingResult($result, (int) ($attemptConnectionId ?? 0));
            $pnr = trim((string) ($result['pnr'] ?? ''));
            SupplierBookingAttempt::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $attemptConnectionId,
                'provider' => SupplierProvider::Sabre->value,
                'action' => 'create_pnr',
                'status' => 'needs_review',
                'error_code' => 'sabre_booking_application_error',
                'error_message' => (string) ($result['safe_message'] ?? $result['message'] ?? 'Sabre PNR created with unconfirmed NN segment status.'),
                'supplier_reference' => $pnr !== '' ? substr($pnr, 0, 191) : null,
                'safe_summary' => SensitiveDataRedactor::redact(array_merge([
                    'source' => $attemptSource,
                    'http_status' => $result['http_status'] ?? null,
                    'endpoint_path' => $ep['endpoint_path'] ?? null,
                    'booking_schema' => (string) ($result['booking_schema'] ?? $this->effectiveSabreBookingSchema()),
                    'payload_schema' => self::resolvePayloadSchemaForSummary($result),
                    'ticketing_disabled' => true,
                    'ticketing_pending' => true,
                    'pnr' => $pnr !== '' ? strtoupper(substr($pnr, 0, 32)) : null,
                    'reason_code' => 'sabre_passenger_records_pnr_unconfirmed_segment_nn',
                    'airline_segment_status' => 'NN',
                    'segment_status_unconfirmed' => true,
                    'passenger_count' => (int) ($result['passenger_count'] ?? 0),
                    'segment_count' => (int) ($result['segment_count'] ?? 0),
                ], self::bookingRevalidationAuditForMeta($result), self::createPayloadAndStructureSliceFromResult($result), array_intersect_key($ep, array_flip([
                    'endpoint_host', 'endpoint_path', 'timeout_seconds', 'connect_timeout_seconds',
                ])))),
                'attempted_by' => null,
                'attempted_at' => now(),
                'completed_at' => now(),
            ]);
            if ($pnr !== '') {
                $bookingColumnPatch['pnr'] = strtoupper(substr($pnr, 0, 32));
                $bookingColumnPatch['supplier_reference'] = substr($pnr, 0, 191);
            }
            $bookingColumnPatch['supplier_booking_status'] = 'manual_review';
        } elseif (($result['error_code'] ?? '') === 'sabre_booking_application_error' && $status === 'needs_review') {
            $ep = $this->resolveEndpointSummaryPreferringBookingResult($result, (int) ($attemptConnectionId ?? 0));
            $safeKeys = is_array($result['response_safe_keys'] ?? null) ? array_slice($result['response_safe_keys'], 0, 48) : [];
            $digestKeys = [
                'response_error_count', 'response_error_codes', 'response_error_messages',
                'response_error_fields', 'response_error_paths', 'response_missing_fields',
                'request_id', 'request_correlation_id', 'trace_id',
                'timestamp', 'response_top_level_message', 'response_top_level_status',
            ];
            $digestSlice = array_intersect_key($result, array_flip($digestKeys));
            $linkageSlice = self::linkageFlagSliceFromResult($result);
            SupplierBookingAttempt::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $attemptConnectionId,
                'provider' => SupplierProvider::Sabre->value,
                'action' => 'create_pnr',
                'status' => 'needs_review',
                'error_code' => 'sabre_booking_application_error',
                'error_message' => (string) ($result['message'] ?? 'Sabre application-level booking error (HTTP 200).'),
                'safe_summary' => SensitiveDataRedactor::redact(array_merge(
                    $this->sabreBookingApplicationErrorAttemptSafeSummary(
                        $result,
                        $ep,
                        $digestSlice,
                        $attemptSource,
                        $safeKeys,
                    ),
                    self::bookingRevalidationAuditForMeta($result),
                    $linkageSlice,
                    array_intersect_key($ep, array_flip(['timeout_seconds', 'connect_timeout_seconds'])),
                )),
                'attempted_by' => null,
                'attempted_at' => now(),
                'completed_at' => now(),
            ]);
            $bookingColumnPatch['supplier_booking_status'] = 'manual_review';
            $this->persistPassengerRecordsApplicationFailureMeta($booking, $result);
        } elseif (($result['error_code'] ?? '') === 'sabre_passenger_records_itinerary_guard' && $status === 'needs_review') {
            $ep = $this->resolveEndpointSummaryPreferringBookingResult($result, (int) ($attemptConnectionId ?? 0));
            SupplierBookingAttempt::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $attemptConnectionId,
                'provider' => SupplierProvider::Sabre->value,
                'action' => 'create_pnr',
                'status' => 'needs_review',
                'error_code' => 'sabre_passenger_records_itinerary_guard',
                'error_message' => (string) ($result['message'] ?? 'Passenger Records live create blocked for risky Sabre itinerary.'),
                'safe_summary' => SensitiveDataRedactor::redact(array_merge([
                    'source' => $attemptSource,
                    'live_call_attempted' => false,
                    'http_status' => null,
                    'booking_schema' => (string) ($result['booking_schema'] ?? $this->effectiveSabreBookingSchema()),
                    'payload_schema' => self::resolvePayloadSchemaForSummary($result),
                    'ticketing_disabled' => true,
                    'ticketing_pending' => true,
                    'passenger_count' => (int) ($result['passenger_count'] ?? 0),
                    'segment_count' => (int) ($result['segment_count'] ?? 0),
                    'guard_trigger' => (string) ($result['guard_trigger'] ?? ''),
                    'segment_order_corrected' => (bool) ($result['segment_order_corrected'] ?? false),
                    'pnr_attempted' => false,
                    'public_auto_pnr_attempted' => false,
                ], app(SabrePassengerRecordsItineraryGuardPolicy::class)->safeSummarySlice($result), self::bookingRevalidationAuditForMeta($result), array_intersect_key($ep, array_flip([
                    'endpoint_host', 'endpoint_path', 'timeout_seconds', 'connect_timeout_seconds',
                ])))),
                'attempted_by' => null,
                'attempted_at' => now(),
                'completed_at' => now(),
            ]);
            $bookingColumnPatch['supplier_booking_status'] = 'manual_review';
        } elseif (($result['error_code'] ?? '') === ComplexItineraryPolicy::ERROR_CODE && $status === 'needs_review') {
            $ep = $this->resolveEndpointSummaryPreferringBookingResult($result, (int) ($attemptConnectionId ?? 0));
            SupplierBookingAttempt::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $attemptConnectionId,
                'provider' => SupplierProvider::Sabre->value,
                'action' => 'create_pnr',
                'status' => 'needs_review',
                'error_code' => ComplexItineraryPolicy::ERROR_CODE,
                'error_message' => (string) ($result['message'] ?? ComplexItineraryPolicy::publicCheckoutNotice()),
                'safe_summary' => SensitiveDataRedactor::redact(array_merge([
                    'source' => $attemptSource,
                    'live_call_attempted' => false,
                    'http_status' => null,
                    'booking_schema' => (string) ($result['booking_schema'] ?? $this->effectiveSabreBookingSchema()),
                    'payload_schema' => self::resolvePayloadSchemaForSummary($result),
                    'ticketing_disabled' => true,
                    'ticketing_pending' => true,
                    'passenger_count' => (int) ($result['passenger_count'] ?? 0),
                    'segment_count' => (int) ($result['segment_count'] ?? 0),
                    'supplier_pnr_deferred_reason' => ComplexItineraryPolicy::DEFER_REASON,
                ], array_intersect_key($ep, array_flip([
                    'endpoint_host', 'endpoint_path', 'timeout_seconds', 'connect_timeout_seconds',
                ])))),
                'attempted_by' => null,
                'attempted_at' => now(),
                'completed_at' => now(),
            ]);
            $bookingColumnPatch['supplier_booking_status'] = 'manual_review';
            $meta['supplier_pnr_deferred_reason'] = ComplexItineraryPolicy::DEFER_REASON;
            $meta['defer_supplier_booking_to_manual_review'] = true;
            $bookingColumnPatch['meta'] = $meta;
        } elseif (($result['error_code'] ?? '') === 'sabre_booking_context_incomplete' && $status === 'needs_review') {
            $ep = $this->resolveEndpointSummaryPreferringBookingResult($result, (int) ($attemptConnectionId ?? 0));
            SupplierBookingAttempt::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $attemptConnectionId,
                'provider' => SupplierProvider::Sabre->value,
                'action' => 'create_pnr',
                'status' => 'needs_review',
                'error_code' => 'sabre_booking_context_incomplete',
                'error_message' => (string) ($result['message'] ?? 'Sabre booking context incomplete; manual supplier booking required.'),
                'safe_summary' => $this->appendBookingContextToAttemptSummary(SensitiveDataRedactor::redact(array_merge([
                    'source' => $attemptSource,
                    'live_call_attempted' => false,
                    'http_status' => null,
                    'booking_schema' => (string) ($result['booking_schema'] ?? $this->effectiveSabreBookingSchema()),
                    'payload_schema' => self::resolvePayloadSchemaForSummary($result),
                    'ticketing_disabled' => true,
                    'ticketing_pending' => true,
                    'passenger_count' => (int) ($result['passenger_count'] ?? 0),
                    'segment_count' => (int) ($result['segment_count'] ?? 0),
                    'safe_reason_code' => (string) data_get($result, 'booking_context_summary.safe_reason_code', ''),
                ], self::bookingRevalidationAuditForMeta($result), array_intersect_key($ep, array_flip([
                    'endpoint_host', 'endpoint_path', 'timeout_seconds', 'connect_timeout_seconds',
                ])))), $result),
                'attempted_by' => null,
                'attempted_at' => now(),
                'completed_at' => now(),
            ]);
            $bookingColumnPatch['supplier_booking_status'] = 'manual_review';
        } elseif (($result['error_code'] ?? '') === 'sabre_passenger_records_stale_shop_segment' && $status === 'needs_review') {
            $ep = $this->resolveEndpointSummaryPreferringBookingResult($result, (int) ($attemptConnectionId ?? 0));
            SupplierBookingAttempt::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $attemptConnectionId,
                'provider' => SupplierProvider::Sabre->value,
                'action' => 'create_pnr',
                'status' => 'needs_review',
                'error_code' => 'sabre_passenger_records_stale_shop_segment',
                'error_message' => (string) ($result['message'] ?? 'Stale Sabre shop segment before Passenger Records create.'),
                'safe_summary' => SensitiveDataRedactor::redact(array_merge([
                    'source' => $attemptSource,
                    'live_call_attempted' => false,
                    'http_status' => null,
                    'booking_schema' => (string) ($result['booking_schema'] ?? $this->effectiveSabreBookingSchema()),
                    'payload_schema' => self::resolvePayloadSchemaForSummary($result),
                    'ticketing_disabled' => true,
                    'ticketing_pending' => true,
                    'passenger_count' => (int) ($result['passenger_count'] ?? 0),
                    'segment_count' => (int) ($result['segment_count'] ?? 0),
                    'stale_segment_index' => isset($result['stale_segment_index']) ? (int) $result['stale_segment_index'] : null,
                    'stale_segment_route' => isset($result['stale_segment_route']) ? (string) $result['stale_segment_route'] : '',
                    'stale_segment_flight' => isset($result['stale_segment_flight']) ? (string) $result['stale_segment_flight'] : '',
                    'probable_issue' => isset($result['probable_issue']) ? (string) $result['probable_issue'] : '',
                ], self::bookingRevalidationAuditForMeta($result), array_intersect_key($ep, array_flip([
                    'endpoint_host', 'endpoint_path', 'timeout_seconds', 'connect_timeout_seconds',
                ])))),
                'attempted_by' => null,
                'attempted_at' => now(),
                'completed_at' => now(),
            ]);
            $bookingColumnPatch['supplier_booking_status'] = 'manual_review';
        } elseif ($status === 'needs_review' && ($result['success'] ?? false)) {
            $ep = $this->resolveEndpointSummaryPreferringBookingResult($result, (int) ($attemptConnectionId ?? 0));
            $bid = trim((string) ($result['provider_booking_id'] ?? ''));
            $safeKeys = is_array($result['response_safe_keys'] ?? null) ? array_slice($result['response_safe_keys'], 0, 48) : [];
            $digestKeys = [
                'response_error_count', 'response_error_codes', 'response_error_messages',
                'response_error_fields', 'response_error_paths', 'response_missing_fields',
                'request_id', 'request_correlation_id', 'trace_id',
                'timestamp', 'response_top_level_message', 'response_top_level_status',
            ];
            $digestSlice = array_intersect_key($result, array_flip($digestKeys));
            $linkageSlice = self::linkageFlagSliceFromResult($result);
            SupplierBookingAttempt::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $attemptConnectionId,
                'provider' => SupplierProvider::Sabre->value,
                'action' => 'create_pnr',
                'status' => 'needs_review',
                'supplier_reference' => $bid !== '' ? substr($bid, 0, 191) : null,
                'safe_summary' => array_merge([
                    'source' => $attemptSource,
                    'live_call_attempted' => true,
                    'http_status' => $result['http_status'] ?? null,
                    'payload_schema' => self::resolvePayloadSchemaForSummary($result),
                    'booking_schema' => (string) ($result['booking_schema'] ?? $this->effectiveSabreBookingSchema()),
                    'ticketing_disabled' => true,
                    'ticketing_pending' => true,
                    'response_safe_keys' => $safeKeys,
                    'pnr' => null,
                    'passenger_count' => (int) ($result['passenger_count'] ?? 0),
                    'segment_count' => (int) ($result['segment_count'] ?? 0),
                ], self::bookingRevalidationAuditForMeta($result), $digestSlice, $linkageSlice, self::passengerRecordsEndpointSliceFromResult($result), array_intersect_key($ep, array_flip([
                    'endpoint_host', 'endpoint_path', 'timeout_seconds', 'connect_timeout_seconds',
                ]))),
                'attempted_by' => null,
                'attempted_at' => now(),
                'completed_at' => now(),
            ]);
            $bookingColumnPatch['supplier_booking_status'] = 'manual_review';
            if ($bid !== '') {
                $bookingColumnPatch['supplier_api_booking_id'] = substr($bid, 0, 191);
                $bookingColumnPatch['supplier_reference'] = substr($bid, 0, 191);
            }
        } elseif ($status === 'pending_payment_or_ticketing' && ($result['success'] ?? false)) {
            $this->persistLiveSabrePnrOnBooking($booking, $result, null);
            SupplierBookingAttempt::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $attemptConnectionId,
                'provider' => SupplierProvider::Sabre->value,
                'action' => 'create_pnr',
                'status' => 'success',
                'supplier_reference' => trim((string) ($result['provider_booking_id'] ?? $result['pnr'] ?? '')) !== ''
                    ? trim((string) ($result['provider_booking_id'] ?? $result['pnr']))
                    : null,
                'safe_summary' => $this->appendBookingContextToAttemptSummary(array_merge([
                    'source' => $attemptSource,
                    'pnr' => $result['pnr'] ?? null,
                    'http_status' => $result['http_status'] ?? null,
                    'provider_status' => $result['provider_status'] ?? null,
                    'payload_schema' => self::resolvePayloadSchemaForSummary($result),
                    'booking_schema' => $result['booking_schema'] ?? null,
                    'ticketing_disabled' => true,
                    'ticketing_pending' => true,
                    'live_call_attempted' => true,
                    'segment_count' => (int) ($result['segment_count'] ?? 0),
                    'passenger_count' => (int) ($result['passenger_count'] ?? 0),
                ], self::bookingRevalidationAuditForMeta($result), self::linkageFlagSliceFromResult($result), self::passengerRecordsEndpointSliceFromResult($result), $completionAttemptSlice, array_intersect_key(
                    $this->resolveEndpointSummaryPreferringBookingResult($result, (int) ($attemptConnectionId ?? 0)),
                    array_flip(['endpoint_host', 'endpoint_path', 'timeout_seconds', 'connect_timeout_seconds'])
                )), $result),
                'attempted_by' => null,
                'attempted_at' => now(),
                'completed_at' => now(),
            ]);
        } elseif ($status === 'failed' && in_array((string) ($result['error_code'] ?? ''), ['sabre_revalidation_failed', 'sabre_revalidation_gatekeeper_failed', 'sabre_gds_fare_validation_failed'], true)) {
            $ep = $this->resolveEndpointSummaryPreferringBookingResult($result, (int) ($attemptConnectionId ?? 0));
            $revSummary = is_array($result['revalidation_payload_summary'] ?? null) ? $result['revalidation_payload_summary'] : [];
            $revErrorDigest = is_array($result['revalidation_error_digest'] ?? null) ? $result['revalidation_error_digest'] : [];
            $linkageSlice = self::linkageFlagSliceFromResult($result);
            $gdsDiag = $this->gdsCheckoutOperationalDiagnosticSlice($result, $booking);
            $selectedStyle = trim((string) (
                $result['selected_payload_style']
                ?? $result['payload_schema']
                ?? $result['pnr_strategy_used']
                ?? ''
            ));
            $errorCode = (string) ($result['error_code'] ?? 'sabre_revalidation_failed');
            $customerMessage = (string) ($result['customer_safe_message'] ?? $result['message'] ?? 'Sabre revalidation failed.');
            SupplierBookingAttempt::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $attemptConnectionId,
                'provider' => SupplierProvider::Sabre->value,
                'action' => 'create_pnr',
                'status' => 'failed',
                'error_code' => $errorCode,
                'error_message' => $customerMessage,
                'safe_summary' => SensitiveDataRedactor::redact(array_merge([
                    'source' => $attemptSource,
                    'live_call_attempted' => false,
                    'pnr_attempted' => false,
                    'segment_count' => (int) ($result['segment_count'] ?? 0),
                    'passenger_count' => (int) ($result['passenger_count'] ?? 0),
                    'revalidation_attempted' => true,
                    'revalidation_outcome' => 'failed',
                    'revalidation_http_status' => $result['revalidation_http_status'] ?? null,
                    'revalidation_endpoint_path' => $result['revalidation_endpoint_path'] ?? null,
                    'revalidation_duration_ms' => $result['revalidation_duration_ms'] ?? null,
                    'revalidation_reason_code' => (string) ($result['revalidation_reason_code'] ?? $result['reason_code'] ?? $errorCode),
                    'revalidation_payload_summary' => $revSummary,
                    'revalidation_error_digest' => $revErrorDigest,
                    'booking_schema' => $this->effectiveSabreBookingSchema(),
                    'payload_schema' => self::resolvePayloadSchemaForSummary($result) ?? $this->expectedSabrePayloadSchemaHintForFailures(),
                    'selected_payload_style' => $selectedStyle !== '' ? $selectedStyle : null,
                ], $gdsDiag, $linkageSlice, array_intersect_key($ep, array_flip([
                    'endpoint_host', 'endpoint_path', 'timeout_seconds', 'connect_timeout_seconds',
                ])))),
                'attempted_by' => null,
                'attempted_at' => now(),
                'completed_at' => now(),
            ]);
            $bookingColumnPatch['supplier_booking_status'] = 'manual_review';
        } elseif ($status === 'failed' && (($result['error_code'] ?? '') === 'sabre_invalid_itinerary_timing')) {
            SupplierBookingAttempt::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $attemptConnectionId,
                'provider' => SupplierProvider::Sabre->value,
                'action' => 'create_pnr',
                'status' => 'failed',
                'error_code' => 'sabre_invalid_itinerary_timing',
                'error_message' => (string) ($result['message'] ?? 'Selected itinerary timing is invalid. Please choose another fare.'),
                'safe_summary' => array_merge([
                    'source' => $attemptSource,
                    'live_call_attempted' => false,
                    'segment_count' => (int) ($result['segment_count'] ?? 0),
                    'passenger_count' => (int) ($result['passenger_count'] ?? 0),
                    'failed_time_link_count' => (int) ($result['failed_time_link_count'] ?? 0),
                    'invalid_segment_duration_count' => (int) ($result['invalid_segment_duration_count'] ?? 0),
                ], array_intersect_key($result, array_flip([
                    'endpoint_host', 'endpoint_path', 'exception_class', 'http_status',
                    'duration_ms', 'timeout_seconds', 'connect_timeout_seconds',
                ]))),
                'attempted_by' => null,
                'attempted_at' => now(),
                'completed_at' => now(),
            ]);
        } elseif ($status === 'failed' && ($result['live_call_attempted'] ?? false)) {
            $errCode = (string) ($result['error_code'] ?? 'sabre_booking_http_failed');
            $bookingColumnPatch['supplier_booking_status'] = 'manual_review';
            SupplierBookingAttempt::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $attemptConnectionId,
                'provider' => SupplierProvider::Sabre->value,
                'action' => 'create_pnr',
                'status' => 'failed',
                'error_code' => $errCode,
                'error_message' => (string) ($result['message'] ?? 'Sabre booking failed.'),
                'safe_summary' => array_merge([
                    'source' => $attemptSource,
                    'live_call_attempted' => true,
                    'segment_count' => (int) ($result['segment_count'] ?? 0),
                    'passenger_count' => (int) ($result['passenger_count'] ?? 0),
                    'reason_code' => (string) ($result['reason_code'] ?? $errCode),
                    'has_booking_class' => (bool) ($result['has_booking_class'] ?? false),
                    'has_fare_basis' => (bool) ($result['has_fare_basis'] ?? false),
                    'has_end_transaction' => (bool) ($result['has_end_transaction'] ?? false),
                ], self::bookingRevalidationAuditForMeta($result), self::linkageFlagSliceFromResult($result), self::passengerRecordsEndpointSliceFromResult($result), $completionAttemptSlice, array_intersect_key($result, array_flip([
                    'endpoint_host', 'duration_ms', 'exception_class', 'http_status',
                    'timeout_seconds', 'connect_timeout_seconds', 'safe_validation_excerpts',
                    'payload_schema', 'booking_schema',
                ]))),
                'attempted_by' => null,
                'attempted_at' => now(),
                'completed_at' => now(),
            ]);
        }

        if (($result['status'] ?? '') === 'needs_review'
            && ($result['live_call_attempted'] ?? false) !== true
            && ! array_key_exists('supplier_booking_status', $bookingColumnPatch)) {
            $bookingColumnPatch['supplier_booking_status'] = 'manual_review';
        }

        $booking->forceFill(array_merge(['meta' => $meta], $bookingColumnPatch))->save();
    }

    /**
     * D5 / S1B: Safe one-shot PNR itinerary sync after successful PNR persistence (no customer email; never throws).
     */
    public function maybeAutoSyncPnrItineraryAfterPublicCheckout(Booking $booking): void
    {
        app(SabreGdsAutoPnrLifecycleService::class)->maybeAutoSyncPnrItineraryAfterPnrCreate($booking);
    }

    /**
     * D2F-C2: gate passive host classification on live failed/review outcomes only.
     *
     * @param  array<string, mixed>  $result
     */
    protected static function shouldAttachSabreHostClassification(array $result): bool
    {
        if (($result['live_call_attempted'] ?? false) !== true) {
            return false;
        }

        $errorCode = strtolower(trim((string) ($result['error_code'] ?? '')));
        if ($errorCode === SabreCertifiedRouteSelector::ERROR_CODE_PENDING) {
            return false;
        }

        $status = (string) ($result['status'] ?? '');
        if ($status === 'pending_payment_or_ticketing' && ($result['success'] ?? false) === true) {
            return false;
        }

        return in_array($status, ['failed', 'needs_review'], true);
    }

    /**
     * D2F-C2: whitelisted diagnostic slice for {@see SabreHostErrorClassifier} — no PII/raw payload.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected static function buildSabreHostClassificationContextFromResult(array $result): array
    {
        $whitelist = [
            'error_code',
            'reason_code',
            'http_status',
            'response_error_messages',
            'application_error_messages',
            'messages',
            'message',
            'airline_segment_status',
            'halt_on_status_received',
            'probable_issue',
            'host_warning_messages_truncated',
            'application_status',
            'application_digest_status',
        ];

        $context = array_intersect_key($result, array_flip($whitelist));
        $digest = is_array($result['passenger_records_application_digest'] ?? null)
            ? $result['passenger_records_application_digest']
            : [];
        if ($digest !== []) {
            $context = array_merge(
                $context,
                app(SabrePassengerRecordsApplicationResultDigest::class)->hostClassificationContextFromDigest($digest, $context),
            );
        }
        foreach (['response_error_messages', 'application_error_messages', 'messages', 'message'] as $key) {
            if (($context[$key] ?? null) === null && array_key_exists($key, $digest)) {
                $context[$key] = $digest[$key];
            }
        }
        if (! isset($context['response_error_messages']) || ! is_array($context['response_error_messages']) || $context['response_error_messages'] === []) {
            $fromDigest = self::hostClassificationMessagesFromApplicationDigest($digest);
            if ($fromDigest !== []) {
                $context['response_error_messages'] = $fromDigest;
            }
        }
        $warningLines = [];
        foreach ([$result, is_array($result['booking_diagnostics'] ?? null) ? $result['booking_diagnostics'] : []] as $src) {
            if (! is_array($src)) {
                continue;
            }
            foreach ((array) ($src['host_warning_messages_truncated'] ?? []) as $line) {
                if (is_string($line) && trim($line) !== '') {
                    $warningLines[] = trim($line);
                }
            }
            foreach ((array) ($src['response_error_messages'] ?? []) as $line) {
                if (is_string($line) && trim($line) !== '') {
                    $warningLines[] = trim($line);
                }
            }
        }
        if ($warningLines !== []) {
            $context['response_error_messages'] = array_values(array_unique(array_merge(
                is_array($context['response_error_messages'] ?? null) ? $context['response_error_messages'] : [],
                $warningLines,
            )));
        }

        return $context;
    }

    /**
     * @param  array<string, mixed>  $digest
     * @return list<string>
     */
    protected static function hostClassificationMessagesFromApplicationDigest(array $digest): array
    {
        $messages = [];
        foreach (['errors', 'warnings', 'messages'] as $bucket) {
            foreach ((array) ($digest[$bucket] ?? []) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $msg = trim((string) ($row['message'] ?? $row['content'] ?? ''));
                if ($msg !== '') {
                    $messages[] = $msg;
                }
            }
        }

        return array_values(array_unique($messages));
    }

    /**
     * SABRE-GDS-HOST-SELL-DIAGNOSTICS: persist safe host sell diagnostics + fingerprint registry (no raw payloads).
     *
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $checkoutOutcome
     */
    protected function persistSabreHostSellDiagnostics(Booking $booking, array $result, array $checkoutOutcome): void
    {
        try {
            if (($result['live_call_attempted'] ?? false) !== true) {
                return;
            }

            $classification = is_array($checkoutOutcome['sabre_host_classification'] ?? null)
                ? $checkoutOutcome['sabre_host_classification']
                : SabreHostSellClassifier::buildPersistedSlice(
                    self::buildSabreHostClassificationContextFromResult($result),
                    array_intersect_key($result, array_flip([
                        'live_call_attempted', 'booking_schema', 'payload_schema', 'segment_count', 'passenger_count',
                        'pnr_present_in_response_body', 'halt_on_status_received',
                    ])),
                );

            $diagnostics = SabreHostSellResponseCollector::collect($booking, $result, $classification);
            if ($diagnostics === []) {
                return;
            }

            $meta = is_array($booking->meta) ? $booking->meta : [];
            if (! isset($meta['failed_offer_snapshot_for_reshop']) && is_array($meta['flight_offer_snapshot'] ?? null)) {
                $meta['failed_offer_snapshot_for_reshop'] = $meta['flight_offer_snapshot'];
            }

            $fingerprint = SabreHostSellFingerprint::buildAndRegister($booking, $diagnostics);
            if (is_array($fingerprint) && $fingerprint !== []) {
                $diagnostics['fingerprint_hash'] = $fingerprint['fingerprint_hash'] ?? null;
                $diagnostics['occurrence_count'] = $fingerprint['occurrence_count'] ?? 1;
            }

            $meta['sabre_host_sell_diagnostics'] = $diagnostics;
            $booking->forceFill(['meta' => $meta])->save();
            $this->recordGdsPnrStrategyEvidence($booking, $result);
        } catch (Throwable $e) {
            Log::warning('sabre_host_sell_diagnostics_persist_failed', [
                'booking_id' => $booking->id,
                'exception' => $e::class,
                'message' => Str::limit($e->getMessage(), 120, ''),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $result  Successful live {@see createBooking()} result
     */
    public function persistLiveSabrePnrOnBooking(Booking $booking, array $result, ?User $actor): void
    {
        if (((string) ($booking->pnr ?? '')) !== '') {
            return;
        }

        $pnr = isset($result['pnr']) && is_string($result['pnr']) ? strtoupper(trim($result['pnr'])) : '';
        $apiBookingId = isset($result['provider_booking_id']) && is_string($result['provider_booking_id'])
            ? trim($result['provider_booking_id'])
            : '';
        $apiBookingIdStored = $apiBookingId !== '' ? substr($apiBookingId, 0, 191) : null;
        $supplierRefStored = $apiBookingIdStored ?? ($pnr !== '' ? substr($pnr, 0, 191) : null);

        $cid = (int) ($result['supplier_connection_id'] ?? 0);
        $connection = $cid > 0 ? SupplierConnection::query()->find($cid) : null;

        DB::transaction(function () use ($booking, $pnr, $apiBookingIdStored, $supplierRefStored, $connection, $actor, $result): void {
            $booking->forceFill([
                'pnr' => $pnr !== '' ? substr($pnr, 0, 32) : null,
                'supplier_api_booking_id' => $apiBookingIdStored,
                'supplier_reference' => $supplierRefStored,
                'supplier_booking_status' => 'pending_payment_or_ticketing',
                'supplier_booking_created_at' => now(),
            ])->save();

            $safeSummary = SensitiveDataRedactor::redact([
                'supplier_offer_id' => $result['selected_offer_id'] ?? null,
                'passenger_count' => $result['passenger_count'] ?? null,
                'fare_amount' => $result['fare_amount'] ?? null,
                'fare_currency' => $result['fare_currency'] ?? null,
                'http_status' => $result['http_status'] ?? null,
                'provider_status' => $result['provider_status'] ?? null,
            ]);

            SupplierBooking::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $connection?->id,
                'provider' => SupplierProvider::Sabre->value,
                'supplier_api_booking_id' => $apiBookingIdStored,
                'supplier_reference' => $supplierRefStored,
                'pnr' => $pnr !== '' ? substr($pnr, 0, 32) : null,
                'status' => 'pending_ticketing',
                'raw_summary' => is_array($safeSummary) ? $safeSummary : [],
                'created_by' => $actor?->id,
                'created_at_supplier' => now(),
            ]);
        });

        $booking->refresh();
        app(SabreGdsAutoPnrLifecycleService::class)->persistPnrCreateArtifacts($booking, $result);
        app(SabreGdsAutoPnrLifecycleService::class)->maybeAutoSyncPnrItineraryAfterPnrCreate($booking);
        $this->recordGdsPnrStrategyEvidence($booking, $result);
    }

    /**
     * BF7-J-OPS-FIX2: Persist PNR from allow-NN operational create when segments remain NN (manual_review only).
     *
     * @param  array<string, mixed>  $result
     */
    public function persistLiveSabrePnrManualReviewOnBooking(Booking $booking, array $result, ?User $actor): void
    {
        if (((string) ($booking->pnr ?? '')) !== '') {
            return;
        }

        $pnr = isset($result['pnr']) && is_string($result['pnr']) ? strtoupper(trim($result['pnr'])) : '';
        if ($pnr === '') {
            return;
        }

        $apiBookingId = isset($result['provider_booking_id']) && is_string($result['provider_booking_id'])
            ? trim($result['provider_booking_id'])
            : '';
        $apiBookingIdStored = $apiBookingId !== '' ? substr($apiBookingId, 0, 191) : null;
        $supplierRefStored = $apiBookingIdStored ?? substr($pnr, 0, 191);

        $cid = (int) ($result['supplier_connection_id'] ?? 0);
        $connection = $cid > 0 ? SupplierConnection::query()->find($cid) : null;

        DB::transaction(function () use ($booking, $pnr, $apiBookingIdStored, $supplierRefStored, $connection, $actor): void {
            $booking->forceFill([
                'pnr' => substr($pnr, 0, 32),
                'supplier_api_booking_id' => $apiBookingIdStored,
                'supplier_reference' => $supplierRefStored,
                'supplier_booking_status' => 'manual_review',
                'supplier_booking_created_at' => now(),
            ])->save();

            SupplierBooking::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $connection?->id,
                'provider' => SupplierProvider::Sabre->value,
                'supplier_api_booking_id' => $apiBookingIdStored,
                'supplier_reference' => $supplierRefStored,
                'pnr' => substr($pnr, 0, 32),
                'status' => 'created',
                'raw_summary' => SensitiveDataRedactor::redact([
                    'segment_status_unconfirmed' => true,
                    'reason_code' => 'sabre_passenger_records_pnr_unconfirmed_segment_nn',
                ]),
                'created_by' => $actor?->id,
                'created_at_supplier' => now(),
            ]);
        });
    }

    /**
     * Safe public-checkout path: validates offer, persists attempt/outcome meta; may perform live Sabre revalidation
     * and Trip Orders {@code createBooking} when booking + live-call flags are enabled.
     *
     * @return array<string, mixed> Same shape as {@see createBooking()}
     */
    public function runPublicReviewDryRun(Booking $booking): array
    {
        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown', 'supplierBookings']);

        $contextCompletionService = app(SabreGdsAutoPnrContextCompletionService::class);
        $completion = $contextCompletionService->completeForBooking($booking);

        if (($completion['needs_exact_refresh'] ?? false) === true
            && (bool) config('suppliers.sabre.refresh_offer_before_public_pnr', true)) {
            try {
                $refresh = $this->offerRefresh->refresh($booking, true);
                $refreshError = trim((string) ($refresh['error'] ?? ''));
                $refreshStatus = trim((string) ($refresh['refresh_status'] ?? $refresh['status'] ?? ''));
                $exactRefreshResult = $refreshError !== '' ? $refreshError : ($refreshStatus !== '' ? $refreshStatus : 'refreshed');
                $booking->refresh();
                $completion = $contextCompletionService->completeForBooking($booking->fresh(), [
                    'exact_refresh_attempted' => true,
                    'exact_refresh_result' => $exactRefreshResult,
                ]);
            } catch (\Throwable $e) {
                $completion = $contextCompletionService->completeForBooking($booking->fresh(), [
                    'exact_refresh_attempted' => true,
                    'exact_refresh_result' => 'offer_refresh_failed',
                ]);
            }
        }

        if (SabreOfferRefreshAcceptance::requiresAcceptance($booking->fresh())) {
            $completion['public_auto_pnr_attempt_ready'] = false;
            $completion['public_auto_pnr_block_reason'] = SabreOfferRefreshAcceptance::ERROR_CODE_REQUIRES_ACCEPTANCE;
            $contextCompletionService->persistCompletionDiagnostics($booking->fresh(), $completion);

            $blockedResult = [
                'success' => false,
                'status' => 'needs_review',
                'message' => SabreOfferRefreshAcceptance::ADMIN_MESSAGE,
                'live_call_attempted' => false,
                'live_call_allowed' => $this->isBookingLiveCallEnabled(),
                'error_code' => SabreOfferRefreshAcceptance::ERROR_CODE_REQUIRES_ACCEPTANCE,
                'reason_code' => SabreOfferRefreshAcceptance::ERROR_CODE_REQUIRES_ACCEPTANCE,
                'pnr_attempted' => false,
                'manual_review_required' => true,
                'auto_pnr_context_completion' => $completion,
                'public_auto_pnr_attempted' => false,
                'public_auto_pnr_block_reason' => SabreOfferRefreshAcceptance::ERROR_CODE_REQUIRES_ACCEPTANCE,
            ];
            $this->finalizePublicCheckoutSabreStorage($booking->fresh(), $blockedResult);

            return $blockedResult;
        }

        if (($completion['public_auto_pnr_attempt_ready'] ?? false) !== true) {
            $blockReason = trim((string) ($completion['public_auto_pnr_block_reason'] ?? ''));
            if ($blockReason === '') {
                $blockReason = SabreGdsAutoPnrContextCompletionService::REASON_CONTEXT_COMPLETION_FAILED;
            }
            $contextCompletionService->persistCompletionDiagnostics($booking->fresh(), $completion);
            $blockedResult = [
                'success' => false,
                'status' => 'needs_review',
                'message' => $this->customerStaffConfirmationBookingMessage(),
                'live_call_attempted' => false,
                'live_call_allowed' => $this->isBookingLiveCallEnabled(),
                'error_code' => $blockReason,
                'reason_code' => $blockReason,
                'pnr_attempted' => false,
                'manual_review_required' => true,
                'auto_pnr_context_completion' => $completion,
                'public_auto_pnr_attempted' => false,
                'public_auto_pnr_block_reason' => $blockReason,
            ];
            $this->finalizePublicCheckoutSabreStorage($booking->fresh(), $blockedResult);

            return $blockedResult;
        }

        $contextCompletionService->persistCompletedContext($booking->fresh(), $completion);
        $booking->refresh();

        $operationalReadinessService = app(SabreOperationalPnrReadiness::class);
        $operationalReadiness = $operationalReadinessService->evaluate($booking);
        $operationalBypass = $operationalReadinessService->wouldAttemptPnr($booking);

        $readinessService = app(SabreVerifiedAutoPnrReadiness::class);
        $readiness = $readinessService->evaluate($booking);

        $strategySelection = app(SabreGdsPnrCreateStrategySelector::class)->selectForBooking($booking);

        $selectedStrategy = trim((string) ($strategySelection['selected_strategy'] ?? ''));
        if ($selectedStrategy === '' && $this->isBookingLiveCallEnabled()) {
            $publicAutoBlockReason = trim((string) ($strategySelection['public_auto_block_reason'] ?? ''));
            $errorCode = $publicAutoBlockReason !== ''
                ? $publicAutoBlockReason
                : 'sabre_gds_no_eligible_pnr_strategy';
            $blockingConditions = array_values(array_unique(array_filter(array_merge(
                [$errorCode],
                [(string) ($strategySelection['reason_code'] ?? SabreGdsPnrCreateStrategySelector::REASON_NO_ELIGIBLE)],
                is_array($strategySelection['blocked_strategies'] ?? null) ? $strategySelection['blocked_strategies'] : [],
            ))));
            $blockedResult = array_merge($this->gdsPnrStrategyResultSlice($strategySelection), [
                'success' => false,
                'status' => 'needs_review',
                'message' => $this->customerStaffConfirmationBookingMessage(),
                'live_call_attempted' => false,
                'live_call_allowed' => $this->isBookingLiveCallEnabled(),
                'error_code' => $errorCode,
                'reason_code' => $errorCode,
                'pnr_attempted' => false,
                'blocking_conditions' => $blockingConditions,
                'gds_strategy_selection' => $strategySelection,
                'manual_review_required' => true,
                'public_auto_certified' => ($strategySelection['public_auto_certified'] ?? false) === true,
                'public_auto_block_reason' => $publicAutoBlockReason !== '' ? $publicAutoBlockReason : null,
                'auto_pnr_context_completion' => $completion,
                'public_auto_pnr_attempted' => false,
                'public_auto_pnr_block_reason' => $publicAutoBlockReason !== '' ? $publicAutoBlockReason : $errorCode,
            ]);
            $this->finalizePublicCheckoutSabreStorage($booking, $blockedResult);
            $this->persistPublicCheckoutStrategyMeta($booking, $strategySelection, $blockedResult);
            $operationalReadinessService->persistCheckoutMeta(
                $booking->fresh(),
                $operationalReadinessService->evaluate($booking->fresh(['supplierBookings'])),
                false,
                'deferred',
                'sabre_gds_no_eligible_pnr_strategy',
            );
            $readinessService->persistCheckoutMeta($booking->fresh(), $readiness, false, 'deferred');

            return $blockedResult;
        }

        $pnrFlagGate = app(SupplierPnrFlagGate::class);
        if (! $pnrFlagGate->sabrePnrCreateAllowed() && $this->isBookingLiveCallEnabled()) {
            $blockedResult = [
                'success' => false,
                'status' => 'needs_review',
                'message' => $this->customerStaffConfirmationBookingMessage(),
                'live_call_attempted' => false,
                'live_call_allowed' => false,
                'error_code' => 'pnr_create_disabled',
                'reason_code' => 'pnr_create_disabled',
                'gds_strategy_selection' => $strategySelection,
                'pnr_strategy_selected' => $strategySelection['selected_strategy'] ?? null,
                'pnr_block_reason_code' => 'pnr_create_disabled',
            ];
            $this->finalizePublicCheckoutSabreStorage($booking, $blockedResult);
            $this->persistPublicCheckoutStrategyMeta($booking, $strategySelection, $blockedResult);
            $operationalReadinessService->persistCheckoutMeta(
                $booking->fresh(),
                $operationalReadinessService->evaluate($booking->fresh(['supplierBookings'])),
                false,
                'deferred',
                'pnr_create_disabled',
            );
            $readinessService->persistCheckoutMeta($booking->fresh(), $readiness, false, 'deferred');

            return $blockedResult;
        }

        $readinessService->persistCheckoutMeta($booking, $readiness, false, 'deferred');
        $operationalReadinessService->persistCheckoutMeta($booking, $operationalReadiness, false, 'deferred');
        $booking->refresh();

        $createOptions = [
            'allow_operational_public_auto_pnr' => $operationalBypass,
            'allow_verified_public_auto_pnr' => false,
            'gds_pnr_strategy_code' => $selectedStrategy,
            'gds_strategy_selection' => $strategySelection,
            'auto_pnr_context_completion' => $completion,
        ];

        if (config('suppliers.sabre.certified_route_selector_public_checkout_enabled', true)) {
            $routeSelection = $this->certifiedRouteSelector->selectForBooking($booking);
            if (($routeSelection['live_booking_allowed'] ?? false) !== true
                && ! $this->publicCheckoutGateBypassActive($booking->id, $routeSelection, $createOptions)) {
                $result = $this->certifiedRouteBlockedResult($booking, $routeSelection);
                $this->finalizePublicCheckoutSabreStorage($booking, $result);
                $this->persistCertifiedRouteDeferMeta($booking, $routeSelection);
                $readinessService->persistCheckoutMeta(
                    $booking->fresh(),
                    $readiness,
                    false,
                    'deferred',
                );
                $operationalReadinessService->persistCheckoutMeta(
                    $booking->fresh(),
                    $operationalReadiness,
                    false,
                    'deferred',
                );

                return $result;
            }
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = [];
        if (is_array($meta['normalized_offer_snapshot'] ?? null)) {
            $snapshot = $meta['normalized_offer_snapshot'];
        } elseif (is_array($meta['validated_offer_snapshot'] ?? null)) {
            $snapshot = $meta['validated_offer_snapshot'];
        } elseif (is_array($meta['flight_offer_snapshot'] ?? null)) {
            $snapshot = $meta['flight_offer_snapshot'];
        }

        $snapshot = $this->mergePublicReviewSabreSnapshotFromBooking($booking, $snapshot);

        $result = $this->createBooking(
            $snapshot,
            $this->passengerDataFromBooking($booking),
            $booking->id,
            $createOptions,
        );
        $result = array_merge($result, $this->gdsPnrStrategyResultSlice($strategySelection), [
            'auto_pnr_context_completion' => $completion,
            'public_auto_pnr_attempted' => ($result['live_call_attempted'] ?? false) === true,
            'public_auto_pnr_block_reason' => ($result['live_call_attempted'] ?? false) === true
                ? null
                : ($result['error_code'] ?? $result['reason_code'] ?? null),
        ]);
        $this->persistPublicCheckoutStrategyMeta($booking, $strategySelection, $result);
        $this->finalizePublicCheckoutSabreStorage($booking, $result);

        $attemptResult = $this->operationalPublicAutoPnrAttemptResultFromCreateOutcome($result, $operationalBypass);
        $failureReasonCode = ($operationalBypass && $attemptResult === 'failed')
            ? $this->resolveOperationalAutoPnrFailureReasonFromCreateResult($result)
            : null;

        if ($operationalBypass && $attemptResult === 'failed') {
            $result['message'] = SabreOperationalPnrReadiness::CUSTOMER_FAILURE_NOTICE;
        }

        $operationalReadinessService->persistCheckoutMeta(
            $booking->fresh(),
            $operationalReadinessService->evaluate($booking->fresh(['supplierBookings'])),
            $operationalBypass,
            $attemptResult,
            $failureReasonCode,
        );

        $readinessService->persistCheckoutMeta(
            $booking->fresh(),
            $readiness,
            false,
            'deferred',
        );

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function verifiedPublicAutoPnrAttemptResultFromCreateOutcome(array $result, bool $verifiedBypass): string
    {
        if (! $verifiedBypass) {
            return 'deferred';
        }

        $status = (string) ($result['status'] ?? '');
        if ($status === 'pending_payment_or_ticketing' && ($result['success'] ?? false)) {
            return 'created';
        }

        if (in_array($status, ['needs_review', 'failed', 'validation_failed'], true) || ! ($result['success'] ?? false)) {
            return 'failed';
        }

        return 'deferred';
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function operationalPublicAutoPnrAttemptResultFromCreateOutcome(array $result, bool $operationalBypass): string
    {
        if (! $operationalBypass) {
            return 'deferred';
        }

        $status = (string) ($result['status'] ?? '');
        if ($status === 'pending_payment_or_ticketing' && ($result['success'] ?? false)) {
            return 'created';
        }

        if (in_array($status, ['needs_review', 'failed', 'validation_failed'], true) || ! ($result['success'] ?? false)) {
            return 'failed';
        }

        return 'deferred';
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function resolveOperationalAutoPnrFailureReasonFromCreateResult(array $result): ?string
    {
        $errorCode = (string) ($result['error_code'] ?? 'sabre_booking_application_error');
        if ($errorCode === '') {
            return 'sabre_booking_application_error';
        }

        return $errorCode;
    }

    /**
     * BF7-J-OPS-FIX1: Persist safe operational admin/staff attempt metadata after createBooking.
     *
     * @param  array<string, mixed>  $result
     */
    protected function persistOperationalStaffAutoPnrMeta(Booking $booking, array $result): void
    {
        $operationalReadinessService = app(SabreOperationalPnrReadiness::class);
        $readiness = $operationalReadinessService->evaluate(
            $booking->fresh(['passengers', 'contact', 'supplierBookings']),
        );
        $attemptResult = $this->operationalStaffAutoPnrAttemptResultFromCreateOutcome($result);
        $failureReasonCode = $attemptResult === 'failed'
            ? $this->resolveOperationalAutoPnrFailureReasonFromCreateResult($result)
            : null;

        $operationalReadinessService->persistCheckoutMeta(
            $booking->fresh(['supplierBookings']),
            $readiness,
            true,
            $attemptResult,
            $failureReasonCode,
        );
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function operationalStaffAutoPnrAttemptResultFromCreateOutcome(array $result): string
    {
        $status = (string) ($result['status'] ?? '');
        if ($status === 'pending_payment_or_ticketing' && ($result['success'] ?? false)) {
            return 'created';
        }

        if (in_array($status, ['needs_review', 'failed', 'validation_failed'], true) || ! ($result['success'] ?? false)) {
            return 'failed';
        }

        if (($result['live_call_attempted'] ?? false) === true || $status === 'dry_run') {
            return 'attempted';
        }

        return 'deferred';
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function resolveVerifiedAutoPnrFailureReasonFromCreateResult(array $result): ?string
    {
        $errorCode = (string) ($result['error_code'] ?? 'sabre_booking_application_error');
        $safeSummary = array_filter([
            'response_error_messages' => $result['response_error_messages'] ?? null,
            'response_error_codes' => $result['response_error_codes'] ?? null,
            'create_segment_count' => $result['create_segment_count'] ?? null,
            'create_segments_summary' => $result['create_segments_summary'] ?? null,
            'create_air_price_present' => $result['create_air_price_present'] ?? null,
            'auto_pnr_pricing_context_ready' => $result['auto_pnr_pricing_context_ready'] ?? true,
        ], static fn ($v) => $v !== null);

        $classification = SabrePnrFailureClassifier::classify(
            $errorCode !== '' ? $errorCode : null,
            $safeSummary,
        );

        if (($classification['classification'] ?? '') === SabrePnrFailureClassifier::CLASSIFICATION_FARE_RBD_CARRIER_NOT_SELLABLE) {
            return SabreVerifiedAutoPnrReadiness::VERIFIED_AUTO_PNR_TERMINAL_FAILURE_REASON;
        }

        return null;
    }

    /**
     * Local inspect: sanitized Sabre booking envelope shape for a booking (no raw payload, no PII values in return).
     *
     * @return array<string, mixed>
     */
    public function inspectBookingPayloadShapeForCommand(Booking $booking): array
    {
        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $p = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($p !== SupplierProvider::Sabre->value) {
            return [
                'booking_id' => $booking->id,
                'provider' => $p,
                'error' => 'booking_not_sabre',
            ];
        }

        $snapshot = [];
        if (is_array($meta['normalized_offer_snapshot'] ?? null)) {
            $snapshot = $meta['normalized_offer_snapshot'];
        } elseif (is_array($meta['validated_offer_snapshot'] ?? null)) {
            $snapshot = $meta['validated_offer_snapshot'];
        } elseif (is_array($meta['flight_offer_snapshot'] ?? null)) {
            $snapshot = $meta['flight_offer_snapshot'];
        }
        $snapshot = $this->mergePublicReviewSabreSnapshotFromBooking($booking, $snapshot);

        $connId = (int) ($meta['supplier_connection_id'] ?? 0);
        $ep = $this->resolveBookingEndpointSummary($connId);

        $gate = $this->validateNormalizedSabreOffer($snapshot);
        if (! $gate->success) {
            $segs = is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : [];
            $flags = $this->inspectPassengerContactFlags($booking, $segs);

            return array_merge([
                'booking_id' => $booking->id,
                'provider' => SupplierProvider::Sabre->value,
                'endpoint_host' => $ep['endpoint_host'],
                'endpoint_path' => $ep['endpoint_path'],
                'segment_count' => count($segs),
                'passenger_count' => $booking->passengers->count(),
                'validation_ok' => false,
                'booking_schema' => $this->effectiveSabreBookingSchema(),
                'booking_transport' => 'rest_json',
                'booking_mode' => (string) config('suppliers.sabre.booking_mode', 'pnr_only'),
                'ticketing_enabled' => false,
                'validation_error_code' => is_string($gate->safe_context['error_code'] ?? null)
                    ? (string) $gate->safe_context['error_code']
                    : 'validation_failed',
            ], $flags, $this->segmentInspectSummariesFromRows($segs));
        }

        $draft = $this->prepareBookingPayload($snapshot, $this->passengerDataFromBooking($booking));
        if (($draft['_valid'] ?? false) !== true) {
            $segs = is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : [];
            $flags = $this->inspectPassengerContactFlags($booking, $segs);

            return array_merge([
                'booking_id' => $booking->id,
                'provider' => SupplierProvider::Sabre->value,
                'endpoint_host' => $ep['endpoint_host'],
                'endpoint_path' => $ep['endpoint_path'],
                'segment_count' => count($segs),
                'passenger_count' => $booking->passengers->count(),
                'validation_ok' => false,
                'booking_schema' => $this->effectiveSabreBookingSchema(),
                'booking_transport' => 'rest_json',
                'booking_mode' => (string) config('suppliers.sabre.booking_mode', 'pnr_only'),
                'ticketing_enabled' => false,
                'validation_error_code' => (string) ($draft['code'] ?? 'validation_failed'),
            ], $flags, $this->segmentInspectSummariesFromRows($segs));
        }

        $apiDraft = $draft;
        unset($apiDraft['_valid']);
        $schema = $this->effectiveSabreBookingSchema();
        $envelope = $this->buildLiveBookingEnvelope($apiDraft, $snapshot);
        $diag = $this->bookingPayloadBuilder->summarizeEnvelopeForDiagnostics($envelope);
        $segs = $schema === 'trip_orders_create_booking'
            ? (is_array(data_get($envelope, 'createBooking.itinerary.segments')) ? data_get($envelope, 'createBooking.itinerary.segments') : [])
            : (is_array($envelope['itinerary']['segments'] ?? null) ? $envelope['itinerary']['segments'] : []);
        if ($schema === 'trip_orders_create_booking' && (! is_array($segs) || $segs === [])) {
            $cb = is_array(data_get($envelope, 'createBooking')) ? data_get($envelope, 'createBooking') : [];
            foreach (['flightOffer.segments', 'flightDetails.segments', 'flightOffer.itinerary.segments', 'flightDetails.itinerary.segments'] as $path) {
                $try = data_get($cb, $path);
                if (is_array($try) && $try !== []) {
                    $segs = $try;
                    break;
                }
            }
        }

        $validationOk = (bool) ($diag['validation_ok'] ?? true);
        $conn = $connId > 0 ? SupplierConnection::query()->find($connId) : null;
        $routeSelection = $this->certifiedRouteSelector->selectForBooking($booking);
        $styleDecision = $this->decidePassengerRecordsPayloadStyle($snapshot, $apiDraft, $conn, $routeSelection);
        $freshnessDecision = $this->decideSabreBookingFreshnessStrategy($snapshot, $apiDraft, $conn, $styleDecision, $booking);
        $contextDiag = $this->buildSabreBookingContextDiagnosticSummary($snapshot, $apiDraft, $conn, [
            'booking_id' => $booking->id,
            'booking_reference' => $booking->reference_code,
            'endpoint_path' => (string) ($ep['endpoint_path'] ?? ''),
            'booking_schema' => $schema,
            'payload_style' => is_string($diag['payload_style'] ?? null) ? (string) $diag['payload_style'] : null,
            'diag_flags' => $diag,
            'trip_type' => trim((string) data_get($meta, 'search_criteria.trip_type', '')),
        ]);

        return array_merge([
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'validation_ok' => $validationOk,
            'endpoint_host' => $ep['endpoint_host'],
            'endpoint_path' => $ep['endpoint_path'],
            'booking_schema' => $schema,
            'booking_transport' => (string) ($diag['booking_transport'] ?? 'rest_json'),
            'booking_mode' => (string) config('suppliers.sabre.booking_mode', 'pnr_only'),
            'freshness_strategy_decision_json' => $freshnessDecision,
        ], $contextDiag, $this->freshnessStrategyDiagnosticSlice($freshnessDecision), $diag, $this->segmentInspectSummariesFromRows(is_array($segs) ? $segs : []), [
            'ticketing_enabled' => (bool) config('suppliers.sabre.ticketing_enabled', false),
        ]);
    }

    /**
     * F9H: Read-only controlled Passenger Records payload digest (rebuilds same wire as controlled-create; no HTTP, no DB mutation).
     *
     * @return array<string, mixed>
     */
    public function inspectControlledPnrPayloadDigestForBooking(Booking $booking): array
    {
        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $p = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));

        $base = [
            'booking_id' => $booking->id,
            'booking_reference' => (string) ($booking->reference_code ?? ''),
            'pnr_present' => trim((string) ($booking->pnr ?? '')) !== '',
            'supplier_reference_present' => trim((string) ($booking->supplier_reference ?? '')) !== '',
            'live_supplier_call_attempted' => false,
            'pnr_create_attempted' => false,
            'ticketing_attempted' => false,
            'cancellation_attempted' => false,
        ];

        if ($p !== SupplierProvider::Sabre->value) {
            return array_merge($base, [
                'digest_status' => 'not_sabre',
                'provider' => $p,
                'recommended_next_action' => 'Booking is not on Sabre.',
                'payload_digest_available' => false,
            ]);
        }

        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null)
            ? $meta['normalized_offer_snapshot']
            : (is_array($meta['validated_offer_snapshot'] ?? null)
                ? $meta['validated_offer_snapshot']
                : (is_array($meta['flight_offer_snapshot'] ?? null) ? $meta['flight_offer_snapshot'] : []));
        $snapshot = $this->mergePublicReviewSabreSnapshotFromBooking($booking, $snapshot);

        $gate = $this->validateNormalizedSabreOffer($snapshot);
        if (! $gate->success) {
            return array_merge($base, [
                'digest_status' => 'validation_failed',
                'validation_error_code' => is_string($gate->safe_context['error_code'] ?? null)
                    ? (string) $gate->safe_context['error_code']
                    : 'validation_failed',
                'recommended_next_action' => 'Fix offer snapshot validation before inspecting Passenger Records wire.',
                'payload_digest_available' => false,
            ]);
        }

        $passengerData = $this->passengerDataFromBooking($booking);
        $draft = $this->prepareBookingPayload($snapshot, $passengerData);
        if (($draft['_valid'] ?? false) !== true) {
            return array_merge($base, [
                'digest_status' => 'draft_invalid',
                'validation_error_code' => (string) ($draft['code'] ?? 'validation_failed'),
                'recommended_next_action' => 'Fix booking draft validation before inspecting Passenger Records wire.',
                'payload_digest_available' => false,
            ]);
        }

        $apiDraft = $draft;
        unset($apiDraft['_valid']);

        $prevCertifiedRoute = $this->attemptCertifiedRouteSelection;
        $prevStyleDecision = $this->attemptPassengerRecordsStyleDecision;
        $prevAllowNn = $this->attemptOperationalAllowNnDecision;

        try {
            $connId = (int) ($meta['supplier_connection_id'] ?? $apiDraft['supplier_connection_id'] ?? 0);
            $connection = $connId > 0 ? SupplierConnection::query()->find($connId) : null;
            if ($connection !== null && $connection->provider !== SupplierProvider::Sabre) {
                $connection = null;
            }

            $routeSelection = $this->certifiedRouteSelector->selectForBooking($booking);
            $storedCertified = is_array($meta['certified_route_selection'] ?? null)
                ? $meta['certified_route_selection']
                : null;
            if ($storedCertified !== null && $storedCertified !== []) {
                $routeSelection = array_merge($routeSelection ?? [], $storedCertified);
            }
            if ($routeSelection !== null && $routeSelection !== []) {
                $this->attemptCertifiedRouteSelection = $routeSelection;
            }

            $this->initializePassengerRecordsStyleDecisionForAttempt($snapshot, $apiDraft, $routeSelection, [
                'booking_id_for_strategy' => $booking->id,
            ]);

            $envelope = $this->buildLiveBookingEnvelope($apiDraft, $snapshot, $connection, $booking->id);
            $wire = $this->bookingPayloadBuilder->stripOtaInternalKeysFromBookingWire($envelope);
            $cpnr = is_array($wire['CreatePassengerNameRecordRQ'] ?? null) ? $wire['CreatePassengerNameRecordRQ'] : [];

            $contextDigest = app(SabreControlledPnrContextDigest::class);
            $pricingReadiness = $this->assessAutoPnrPricingContextReadinessForBooking($booking);
            $safeRefresh = app(SabreSafeRefreshContext::class)->assess($meta);
            $freshnessMeta = app(SabreOfferFreshness::class)->buildOfferFreshnessMeta($snapshot, null, $meta, true);
            $freshnessStatus = (string) ($freshnessMeta['freshness_status'] ?? '');

            $selectedSegments = $this->selectedContextSegmentsForPayloadDigest($apiDraft, $snapshot);
            $digestContext = [
                'endpoint_path' => $this->resolvePassengerRecordsEndpointPathForAttempt(),
                'payload_style' => $this->resolvePassengerRecordsPayloadStyleForAttempt(),
                'payload_schema' => $this->effectiveSabreBookingSchema(),
                'version' => is_scalar($cpnr['version'] ?? null) ? (string) $cpnr['version'] : null,
                'passenger_count' => $booking->passengers->count(),
                'selected_context_segments' => $selectedSegments,
                'api_draft' => $apiDraft,
                'booking_meta' => $meta,
                'validating_carrier' => $apiDraft['validating_carrier'] ?? $snapshot['validating_carrier'] ?? null,
                'brand_code' => $this->resolveBrandCodeForPayloadDigest($snapshot, $apiDraft),
                'brand_name' => data_get($snapshot, 'brand_name') ?? data_get($snapshot, 'fare_family_name'),
                'validated_offer_brand_code' => $this->resolveBrandCodeForPayloadDigest(
                    is_array($meta['validated_offer_snapshot'] ?? null) ? $meta['validated_offer_snapshot'] : $snapshot,
                    $apiDraft,
                ),
                'accepted_fare_change_brand_code' => $this->resolveAcceptedFareChangeBrandCodeForPayloadDigest($meta),
                'missing_revalidation_linkage' => ($pricingReadiness['has_revalidation_linkage_complete'] ?? false) !== true
                    && ($safeRefresh['safe_refresh_context_complete'] ?? false) !== true,
                'legacy_revalidation_signal_used' => $contextDigest->hasLegacySuccessRevalidationSignal($meta)
                    && ($pricingReadiness['has_revalidation_linkage_complete'] ?? false) !== true,
                'stale_offer_context' => in_array($freshnessStatus, ['stale', 'expired'], true),
            ];

            $digest = app(SabrePassengerRecordsPayloadDigest::class)->digest($wire, $digestContext);
            $selectedSummary = $this->selectedContextSummaryForPayloadDigest($selectedSegments, $digestContext);

            return array_merge($base, $digest, [
                'digest_status' => 'ok',
                'selected_context_summary' => $selectedSummary,
                'payload_digest_available' => true,
            ], app(SabrePassengerRecordsPayloadDigest::class)->commandSummaryFromDigest($digest));
        } catch (Throwable $e) {
            return array_merge($base, [
                'digest_status' => 'wire_build_failed',
                'wire_build_error' => substr($e->getMessage(), 0, 120),
                'recommended_next_action' => 'Passenger Records wire could not be rebuilt safely — check booking context and certified route.',
                'payload_digest_available' => false,
            ]);
        } finally {
            $this->attemptCertifiedRouteSelection = $prevCertifiedRoute;
            $this->attemptPassengerRecordsStyleDecision = $prevStyleDecision;
            $this->attemptOperationalAllowNnDecision = $prevAllowNn;
        }
    }

    /**
     * Read-only rebuild of safe PNR structure snapshots from booking meta (no HTTP, no DB mutation).
     *
     * @return array<string, mixed>
     */
    public function rebuildPnrAttemptStructureSnapshots(
        Booking $booking,
        string $payloadStyle,
        string $endpointPath,
        string $source = 'regenerated_from_booking_meta',
    ): array {
        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null)
            ? $meta['normalized_offer_snapshot']
            : (is_array($meta['validated_offer_snapshot'] ?? null)
                ? $meta['validated_offer_snapshot']
                : (is_array($meta['flight_offer_snapshot'] ?? null) ? $meta['flight_offer_snapshot'] : []));
        $snapshot = $this->mergePublicReviewSabreSnapshotFromBooking($booking, $snapshot);
        $draft = $this->bookingPayloadBuilder->buildInternalDraft($snapshot, $this->passengerDataFromBooking($booking));
        if (($draft['_valid'] ?? false) !== true) {
            return ['structure_snapshot_source' => 'regeneration_draft_invalid'];
        }

        $envelope = $this->bookingPayloadBuilder->buildPassengerRecordsCpnrWireForStyle($draft, [], $payloadStyle);

        return app(SabrePnrAttemptStructureSnapshot::class)->buildFromWire($envelope, [
            'endpoint_path' => $endpointPath,
            'payload_schema' => $payloadStyle,
            'selected_payload_style' => $payloadStyle,
            'structure_snapshot_source' => $source,
        ]);
    }

    /**
     * @param  array<string, mixed>  $apiDraft
     * @param  array<string, mixed>  $offer
     * @return list<array<string, mixed>>
     */
    protected function selectedContextSegmentsForPayloadDigest(array $apiDraft, array $offer): array
    {
        $segs = is_array($apiDraft['segments'] ?? null) ? array_values($apiDraft['segments']) : [];
        if ($segs === []) {
            $segs = is_array($offer['segments'] ?? null) ? array_values($offer['segments']) : [];
        }
        $out = [];
        foreach ($segs as $i => $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $out[] = array_filter([
                'index' => $i,
                'marketing_carrier' => strtoupper(trim((string) (
                    $seg['marketing_carrier'] ?? $seg['carrier'] ?? $seg['marketing_airline'] ?? ''
                ))) ?: null,
                'flight_number' => trim((string) ($seg['flight_number'] ?? '')) ?: null,
                'origin' => strtoupper(trim((string) ($seg['origin'] ?? $seg['departure_airport'] ?? ''))) ?: null,
                'destination' => strtoupper(trim((string) ($seg['destination'] ?? $seg['arrival_airport'] ?? ''))) ?: null,
                'booking_class' => strtoupper(trim((string) (
                    $seg['booking_class'] ?? $seg['rbd'] ?? $seg['res_book_desig_code'] ?? ''
                ))) ?: null,
                'fare_basis_code' => trim((string) ($seg['fare_basis_code'] ?? $seg['fare_basis'] ?? '')) ?: null,
                'departure_datetime' => trim((string) ($seg['departure_datetime'] ?? $seg['departure_at'] ?? '')) ?: null,
            ], static fn ($v) => $v !== null && $v !== '');
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $apiDraft
     */
    protected function resolveBrandCodeForPayloadDigest(array $offer, array $apiDraft): ?string
    {
        $candidates = [
            data_get($apiDraft, '_sabre_booking_context.brand_code'),
            data_get($apiDraft, '_sabre_booking_context.selected_fare_family_option.brand_code'),
            data_get($offer, 'brand_code'),
            data_get($offer, 'fare_family_code'),
            data_get($offer, 'raw_payload.brand_code'),
        ];
        foreach ($candidates as $c) {
            if (is_string($c) && trim($c) !== '') {
                return strtoupper(substr(trim($c), 0, 32));
            }
        }

        return null;
    }

    protected function v25AirPriceOptionalQualifierSchemaCustomerMessage(): string
    {
        return (string) __(SabreBookingPayloadBuilder::V25_GDS_OPTIONAL_QUALIFIER_CUSTOMER_MESSAGE);
    }

    /**
     * @param  array<string, mixed>  $apiDraft
     * @return array{brand_code?: string, selected_brand_code?: string}
     */
    protected function v25BrandContextForQualifierDigest(array $apiDraft): array
    {
        $ctx = is_array($apiDraft['_sabre_booking_context'] ?? null) ? $apiDraft['_sabre_booking_context'] : [];
        $brand = strtoupper(trim((string) (
            $ctx['selected_brand_code']
            ?? $ctx['brand_code']
            ?? ''
        )));

        return $brand !== '' ? ['brand_code' => $brand, 'selected_brand_code' => $brand] : [];
    }

    /**
     * V25-CPNR: Safe post-HTTP / attempt diagnostics for Passenger Records v2.5 GDS (no raw payload / PII).
     *
     * @param  array<string, mixed>  $envelope
     * @param  array<string, mixed>  $apiDraft
     * @param  array<string, mixed>  $diagFlat
     * @return array<string, mixed>
     */
    protected function v25PassengerRecordsLiveDiagnosticsSlice(
        array $envelope,
        array $apiDraft,
        array $diagFlat,
        string $payloadStyle,
    ): array {
        if (! SabreBookingPayloadBuilder::isPassengerRecordsV25GdsWireStyle($payloadStyle)) {
            return [];
        }

        $stripped = $this->bookingPayloadBuilder->stripOtaInternalKeysFromBookingWire($envelope);
        $digest = $this->bookingPayloadBuilder->summarizeV25AirPricePricingQualifiersStructuralDigest(
            $stripped,
            $this->v25BrandContextForQualifierDigest($apiDraft),
        );

        $structured = is_array($diagFlat['safe_validation_excerpts_structured'] ?? null)
            ? array_slice($diagFlat['safe_validation_excerpts_structured'], 0, 6)
            : [];
        $excerptBuilder = app(SabrePassengerRecordsHttpValidationExcerptBuilder::class);
        $schemaSummary = $excerptBuilder->extractCpnrSchemaValidationSummary($structured);
        $schemaReason = $excerptBuilder->classifyV25AirPriceOptionalQualifierSchemaReason($structured)
            ?? $excerptBuilder->classifyV25BrandQualifierHostReason($structured);
        $customerMessage = $schemaReason === SabreBookingPayloadBuilder::V25_AIRPRICE_OPTIONAL_QUALIFIER_SCHEMA_ERROR
            ? $this->v25AirPriceOptionalQualifierSchemaCustomerMessage()
            : null;

        return array_filter([
            'v25_airprice_pricing_qualifiers_digest' => $digest,
            'cpnr_schema_validation_pointer' => $schemaSummary['cpnr_schema_validation_pointer'] ?? null,
            'cpnr_schema_validation_message_summary' => $schemaSummary['cpnr_schema_validation_message_summary'] ?? null,
            'cpnr_schema_validation_stage' => $schemaSummary['cpnr_schema_validation_stage'] ?? null,
            'safe_reason_code' => $schemaReason,
            'v25_brand_qualifier_host_reason' => $excerptBuilder->classifyV25BrandQualifierHostReason($structured),
            'customer_safe_message' => $customerMessage,
            'pnr_attempted' => true,
            'selected_payload_style' => SabreBookingPayloadBuilder::PASSENGER_RECORDS_V2_5_GDS,
            'ticket_issuance_attempted' => false,
            'airticket_attempted' => false,
        ], static fn ($v) => $v !== null && $v !== []);
    }

    /**
     * F9I: Brand code from accepted fare-change context for payload digest comparison (safe fields only).
     *
     * @param  array<string, mixed>  $meta
     */
    protected function resolveAcceptedFareChangeBrandCodeForPayloadDigest(array $meta): ?string
    {
        $acceptance = is_array($meta['controlled_pnr_fare_change_acceptance'] ?? null)
            ? $meta['controlled_pnr_fare_change_acceptance']
            : [];
        if (($acceptance['accepted'] ?? false) !== true) {
            return null;
        }

        $validatedSnapshot = is_array($meta['validated_offer_snapshot'] ?? null)
            ? $meta['validated_offer_snapshot']
            : [];
        $candidates = [
            data_get($acceptance, 'accepted_brand_code'),
            data_get($meta, 'selected_fare_family_option.brand_code'),
            data_get($validatedSnapshot, 'brand_code'),
            data_get($validatedSnapshot, 'fare_family_code'),
            data_get($validatedSnapshot, 'raw_payload.brand_code'),
        ];
        foreach ($candidates as $c) {
            if (is_string($c) && trim($c) !== '') {
                return strtoupper(substr(trim($c), 0, 32));
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $selectedSegments
     * @param  array<string, mixed>  $digestContext
     * @return array<string, mixed>
     */
    protected function selectedContextSummaryForPayloadDigest(array $selectedSegments, array $digestContext): array
    {
        $routeParts = [];
        foreach ($selectedSegments as $seg) {
            $o = (string) ($seg['origin'] ?? '');
            $d = (string) ($seg['destination'] ?? '');
            if ($routeParts === [] && $o !== '') {
                $routeParts[] = $o;
            }
            if ($d !== '') {
                $routeParts[] = $d;
            }
        }

        return array_filter([
            'validating_carrier' => $digestContext['validating_carrier'] ?? null,
            'brand_code' => $digestContext['brand_code'] ?? null,
            'route_chain' => $routeParts !== [] ? implode('-', $routeParts) : null,
            'segment_count' => count($selectedSegments),
            'rbd_by_segment' => array_values(array_filter(array_map(
                static fn ($s) => is_array($s) ? ($s['booking_class'] ?? null) : null,
                $selectedSegments,
            ))),
            'fare_basis_by_segment' => array_values(array_filter(array_map(
                static fn ($s) => is_array($s) ? ($s['fare_basis_code'] ?? null) : null,
                $selectedSegments,
            ))),
        ], static fn ($v) => $v !== null && $v !== [] && $v !== '');
    }

    /**
     * Redacted Trip Orders {@code createBooking} structural preview for local inspect (B22; no traveler/contact values).
     *
     * @return array<string, mixed>
     */
    public function previewRedactedTripOrdersCreateBookingForCommand(Booking $booking, ?string $payloadStyleOverride = null): array
    {
        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $p = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($p !== SupplierProvider::Sabre->value) {
            return [
                'booking_id' => $booking->id,
                'provider' => $p,
                'error' => 'booking_not_sabre',
            ];
        }
        $snapshot = [];
        if (is_array($meta['normalized_offer_snapshot'] ?? null)) {
            $snapshot = $meta['normalized_offer_snapshot'];
        } elseif (is_array($meta['validated_offer_snapshot'] ?? null)) {
            $snapshot = $meta['validated_offer_snapshot'];
        } elseif (is_array($meta['flight_offer_snapshot'] ?? null)) {
            $snapshot = $meta['flight_offer_snapshot'];
        }
        $snapshot = $this->mergePublicReviewSabreSnapshotFromBooking($booking, $snapshot);
        $gate = $this->validateNormalizedSabreOffer($snapshot);
        if (! $gate->success) {
            return [
                'booking_id' => $booking->id,
                'provider' => SupplierProvider::Sabre->value,
                'error' => 'validation_failed',
                'validation_error_code' => is_string($gate->safe_context['error_code'] ?? null)
                    ? (string) $gate->safe_context['error_code']
                    : 'validation_failed',
            ];
        }
        $draft = $this->prepareBookingPayload($snapshot, $this->passengerDataFromBooking($booking));
        if (($draft['_valid'] ?? false) !== true) {
            return [
                'booking_id' => $booking->id,
                'provider' => SupplierProvider::Sabre->value,
                'error' => 'draft_invalid',
                'validation_error_code' => (string) ($draft['code'] ?? 'validation_failed'),
            ];
        }
        $apiDraft = $draft;
        unset($apiDraft['_valid']);
        $hints = $this->ticketingHintsFromOffer($snapshot);
        $trimPo = $payloadStyleOverride !== null ? trim((string) $payloadStyleOverride) : '';
        if ($trimPo !== '' && SabreBookingPayloadBuilder::isTraditionalPnrPassengerRecordsWireStyle($trimPo)) {
            return $this->previewRedactedTraditionalPnrForCommand($booking, $trimPo);
        }
        if ($this->effectiveSabreBookingSchema() === 'trip_orders_create_booking'
            && $payloadStyleOverride !== null && trim($payloadStyleOverride) !== '') {
            $envelope = $this->bookingPayloadBuilder->buildTripOrdersCreateBookingEnvelope(
                $apiDraft,
                $hints,
                $this->bookingPayloadBuilder->normalizeCreatebookingPayloadStyle(trim($payloadStyleOverride)),
            );
        } else {
            $envelope = $this->buildLiveBookingEnvelope($apiDraft, $snapshot);
        }

        return array_merge([
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
        ], $this->bookingPayloadBuilder->previewRedactedTripOrdersCreateBookingShape($envelope));
    }

    /**
     * B23: final Trip Orders {@code createBooking} wire POST body (after {@code _ota*} strip), redacted for safe inspect/file output.
     *
     * @return array<string, mixed>
     */
    public function previewTripOrdersWireJsonForInspectCommand(Booking $booking, ?string $payloadStyleOverride = null): array
    {
        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $p = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($p !== SupplierProvider::Sabre->value) {
            return [
                'booking_id' => $booking->id,
                'provider' => $p,
                'error' => 'booking_not_sabre',
            ];
        }
        $snapshot = [];
        if (is_array($meta['normalized_offer_snapshot'] ?? null)) {
            $snapshot = $meta['normalized_offer_snapshot'];
        } elseif (is_array($meta['validated_offer_snapshot'] ?? null)) {
            $snapshot = $meta['validated_offer_snapshot'];
        } elseif (is_array($meta['flight_offer_snapshot'] ?? null)) {
            $snapshot = $meta['flight_offer_snapshot'];
        }
        $snapshot = $this->mergePublicReviewSabreSnapshotFromBooking($booking, $snapshot);
        $gate = $this->validateNormalizedSabreOffer($snapshot);
        if (! $gate->success) {
            return [
                'booking_id' => $booking->id,
                'provider' => SupplierProvider::Sabre->value,
                'error' => 'validation_failed',
                'validation_error_code' => is_string($gate->safe_context['error_code'] ?? null)
                    ? (string) $gate->safe_context['error_code']
                    : 'validation_failed',
            ];
        }
        $draft = $this->prepareBookingPayload($snapshot, $this->passengerDataFromBooking($booking));
        if (($draft['_valid'] ?? false) !== true) {
            return [
                'booking_id' => $booking->id,
                'provider' => SupplierProvider::Sabre->value,
                'error' => 'draft_invalid',
                'validation_error_code' => (string) ($draft['code'] ?? 'validation_failed'),
            ];
        }
        $trimOverride = $payloadStyleOverride !== null && trim((string) $payloadStyleOverride) !== ''
            ? trim((string) $payloadStyleOverride)
            : null;
        if ($trimOverride !== null && SabreBookingPayloadBuilder::isTraditionalPnrPassengerRecordsWireStyle($trimOverride)) {
            $apiDraft = $draft;
            unset($apiDraft['_valid']);
            $hints = $this->ticketingHintsFromOffer($snapshot);
            $raw = $this->bookingPayloadBuilder->buildPassengerRecordsCpnrWireForStyle($apiDraft, $hints, $trimOverride);
            $wire = $this->bookingPayloadBuilder->stripOtaInternalKeysFromBookingWire($raw);
            $redacted = $this->bookingPayloadBuilder->redactTraditionalPnrWireJsonForPreview($wire);
            $wireDiag = $this->bookingPayloadBuilder->summarizeTraditionalPnrWirePostBody($wire, $meta, $trimOverride);

            return array_merge([
                'booking_id' => $booking->id,
                'provider' => SupplierProvider::Sabre->value,
                'payload_style' => $trimOverride,
                'redacted_wire_request_body' => $redacted,
            ], $wireDiag);
        }
        if ($this->effectiveSabreBookingSchema() !== 'trip_orders_create_booking') {
            return [
                'booking_id' => $booking->id,
                'provider' => SupplierProvider::Sabre->value,
                'error' => 'not_trip_orders_booking_schema',
            ];
        }
        $apiDraft = $draft;
        unset($apiDraft['_valid']);
        $hints = $this->ticketingHintsFromOffer($snapshot);
        $style = $payloadStyleOverride !== null && trim($payloadStyleOverride) !== ''
            ? $this->bookingPayloadBuilder->normalizeCreatebookingPayloadStyle(trim($payloadStyleOverride))
            : $this->bookingPayloadBuilder->resolveCreatebookingPayloadStyle();
        $envelope = $this->bookingPayloadBuilder->buildTripOrdersCreateBookingEnvelope($apiDraft, $hints, $style);
        $wire = $this->bookingPayloadBuilder->tripOrdersFinalWirePostBodyFromEnvelope($envelope);
        $redacted = $this->bookingPayloadBuilder->redactTripOrdersWireJsonForPreview($wire);
        $wireDiag = $this->bookingPayloadBuilder->summarizeTripOrdersWirePostBodyForEnvelope($envelope);

        return array_merge([
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'payload_style' => $style,
            'redacted_wire_request_body' => $redacted,
        ], $wireDiag);
    }

    /**
     * B75: Local/testing-only Passenger Records CPNR AirBook segment sell diagnostics (0411 / FLIGHT NOOP triage). No booking side effects.
     *
     * @return array<string, mixed>
     */
    public function inspectPassengerRecordsAirBookSegmentSellDiagnosticsForCommand(Booking $booking, ?string $manualNote = null): array
    {
        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $p = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($p !== SupplierProvider::Sabre->value) {
            return [
                'booking_id' => $booking->id,
                'provider' => $p,
                'error' => 'booking_not_sabre',
            ];
        }
        $snapshot = [];
        if (is_array($meta['normalized_offer_snapshot'] ?? null)) {
            $snapshot = $meta['normalized_offer_snapshot'];
        } elseif (is_array($meta['validated_offer_snapshot'] ?? null)) {
            $snapshot = $meta['validated_offer_snapshot'];
        } elseif (is_array($meta['flight_offer_snapshot'] ?? null)) {
            $snapshot = $meta['flight_offer_snapshot'];
        }
        $snapshot = $this->mergePublicReviewSabreSnapshotFromBooking($booking, $snapshot);
        $gate = $this->validateNormalizedSabreOffer($snapshot);
        if (! $gate->success) {
            return [
                'booking_id' => $booking->id,
                'provider' => SupplierProvider::Sabre->value,
                'error' => 'validation_failed',
                'validation_error_code' => is_string($gate->safe_context['error_code'] ?? null)
                    ? (string) $gate->safe_context['error_code']
                    : 'validation_failed',
            ];
        }
        $draft = $this->prepareBookingPayload($snapshot, $this->passengerDataFromBooking($booking));
        if (($draft['_valid'] ?? false) !== true) {
            return [
                'booking_id' => $booking->id,
                'provider' => SupplierProvider::Sabre->value,
                'error' => 'draft_invalid',
                'validation_error_code' => (string) ($draft['code'] ?? 'validation_failed'),
            ];
        }
        $apiDraft = $draft;
        unset($apiDraft['_valid']);
        $hints = $this->ticketingHintsFromOffer($snapshot);
        $raw = $this->bookingPayloadBuilder->buildTraditionalPnrCreatePassengerNameRecordV1Wire($apiDraft, $hints);
        $wire = $this->bookingPayloadBuilder->stripOtaInternalKeysFromBookingWire($raw);
        $wireDiag = $this->bookingPayloadBuilder->summarizeTraditionalPnrWirePostBody($wire, $meta);
        $snapshotSegs = array_values(is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : []);
        $segSell = $this->bookingPayloadBuilder->traditionalPnrAirBookSegmentSellDiagnostics($wire, $snapshotSegs);

        $connId = (int) ($meta['supplier_connection_id'] ?? 0);
        $ep = $this->resolveBookingEndpointSummary($connId);

        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::Sabre->value)
            ->orderByDesc('id')
            ->first();

        $lastAttempt = null;
        if ($attempt !== null) {
            $safe = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
            $msgSrc = is_array($safe['response_error_messages'] ?? null) ? $safe['response_error_messages'] : [];
            $msgOut = [];
            foreach (array_slice($msgSrc, 0, 8) as $m) {
                if (! is_string($m)) {
                    continue;
                }
                $t = trim($m);
                if ($t === '') {
                    continue;
                }
                $msgOut[] = strlen($t) > 260 ? substr($t, 0, 260) : $t;
            }
            $lastAttempt = [
                'attempt_id' => $attempt->id,
                'action' => (string) $attempt->action,
                'status' => (string) $attempt->status,
                'http_status' => $safe['http_status'] ?? null,
                'application_results_status' => $safe['application_results_status'] ?? null,
                'response_error_codes' => is_array($safe['response_error_codes'] ?? null) ? array_slice($safe['response_error_codes'], 0, 16) : [],
                'response_error_messages' => $msgOut,
                'host_warning_sabre_codes' => is_array($safe['host_warning_sabre_codes'] ?? null)
                    ? array_slice($safe['host_warning_sabre_codes'], 0, 16)
                    : [],
                'host_warning_modules' => is_array($safe['host_warning_modules'] ?? null)
                    ? array_slice($safe['host_warning_modules'], 0, 16)
                    : [],
                'host_warning_messages_truncated' => is_array($safe['host_warning_messages_truncated'] ?? null)
                    ? array_slice($safe['host_warning_messages_truncated'], 0, 8)
                    : [],
            ];
        }

        $invalidKeys = $wireDiag['wire_invalid_traditional_pnr_contract_keys'] ?? [];
        if (! is_array($invalidKeys)) {
            $invalidKeys = [];
        }

        return array_merge([
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'payload_style' => SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1,
            'endpoint_path' => $ep['endpoint_path'] ?? null,
            'endpoint_host' => $ep['endpoint_host'] ?? null,
            'ticketing_enabled' => (bool) config('suppliers.sabre.ticketing_enabled', false),
            'booking_schema' => $this->effectiveSabreBookingSchema(),
            'wire_contract_valid' => (bool) ($wireDiag['wire_traditional_pnr_contract_valid'] ?? false),
            'wire_invalid_traditional_pnr_contract_keys' => array_values(array_slice($invalidKeys, 0, 32)),
            'manual_note' => $manualNote,
            'v24_vs_v25_same_failure_note' => $this->b75PassengerRecordsV24V25EndpointCompareNote($booking->id),
            'last_supplier_attempt' => $lastAttempt,
        ], $segSell);
    }

    /**
     * OTA-BRANDED-FARE-CONTEXT-AND-UI-CONSISTENCY-1: read-only GDS PNR payload integrity summary (no raw payload / PII).
     *
     * @return array<string, mixed>
     */
    public function inspectGdsPnrPayloadIntegrityForCommand(Booking $booking): array
    {
        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown']);
        $rawMeta = is_array($booking->meta) ? $booking->meta : [];
        $meta = $rawMeta;
        $snapshot = $this->mergePublicReviewSabreSnapshotFromBooking($booking, $this->offerSnapshotFromBookingMeta($meta));

        $validator = new FareSelectionIntegrityValidator;
        $payloadDigest = $this->buildGdsPnrPayloadIntegrityDigest($booking, $meta, $snapshot);
        $integrity = $validator->validate($meta, $snapshot, $snapshot, $payloadDigest);

        $reconciledMeta = BookingSupplierConfirmationNoticeResolver::reconcileSabreBrandedFareMeta($rawMeta);
        $payloadDigestReconciled = $this->buildGdsPnrPayloadIntegrityDigest($booking, $reconciledMeta, $snapshot);

        $handoff = is_array($rawMeta['sabre_booking_context'] ?? null) ? $rawMeta['sabre_booking_context'] : [];

        $selected = is_array($rawMeta['selected_fare_family_option'] ?? null) ? $rawMeta['selected_fare_family_option'] : [];

        $metaConn = (int) ($rawMeta['supplier_connection_id'] ?? 0);
        $snapConn = (int) ($snapshot['supplier_connection_id'] ?? 0);
        $connectionSticky = $metaConn > 0 && ($snapConn === 0 || $metaConn === $snapConn);

        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::Sabre->value)
            ->orderByDesc('id')
            ->first();
        $previousFailed = false;
        $safeRetryRequiresAdmin = false;
        if ($attempt !== null) {
            $status = strtolower((string) $attempt->status);
            $previousFailed = in_array($status, ['failed', 'manual_review', 'needs_review'], true);
            $safe = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
            $msgs = is_array($safe['response_error_messages'] ?? null) ? $safe['response_error_messages'] : [];
            foreach ($msgs as $msg) {
                if (is_string($msg) && stripos($msg, 'FORMAT') !== false) {
                    $previousFailed = true;
                    break;
                }
            }
            $safeRetryRequiresAdmin = $previousFailed || trim((string) ($booking->pnr ?? '')) === '';
        }

        $haltCodes = [];
        if (($payloadDigest['halt_on_status_codes'] ?? null) !== null) {
            $haltCodes = is_array($payloadDigest['halt_on_status_codes']) ? $payloadDigest['halt_on_status_codes'] : [];
        }
        $nnNotSelfBlocking = ! in_array('NN', $haltCodes, true);

        $admin = is_array($integrity['admin_summary'] ?? null) ? $integrity['admin_summary'] : [];

        return array_filter([
            'booking_id' => $booking->id,
            'booking_reference' => (string) ($booking->booking_reference ?? ''),
            'connection_sticky' => $connectionSticky,
            'selected_brand_context_consistent' => (bool) ($integrity['consistent'] ?? false),
            'selected_brand_code' => strtoupper(trim((string) ($selected['brand_code'] ?? ''))) ?: null,
            'booking_context_brand_code' => strtoupper(trim((string) ($handoff['selected_brand_code'] ?? $handoff['brand_code'] ?? ''))) ?: null,
            'selected_fare_basis' => $this->firstSegmentFareBasis($selected),
            'booking_context_fare_basis' => $this->firstSegmentFareBasis($handoff),
            'selected_baggage' => trim((string) ($selected['baggage_summary'] ?? $selected['baggage'] ?? '')) ?: null,
            'booking_context_baggage' => trim((string) ($handoff['baggage'] ?? '')) ?: null,
            'selected_total' => isset($selected['displayed_price']) && is_numeric($selected['displayed_price'])
                ? (float) $selected['displayed_price']
                : (isset($rawMeta['selected_fare_total']) ? (float) $rawMeta['selected_fare_total'] : null),
            'booking_context_total' => isset($handoff['selected_price_total']) && is_numeric($handoff['selected_price_total'])
                ? (float) $handoff['selected_price_total']
                : (isset($rawMeta['revalidated_fare_total']) ? (float) $rawMeta['revalidated_fare_total'] : null),
            'pnr_payload_segments_complete' => (bool) ($payloadDigestReconciled['pnr_payload_segments_complete'] ?? $payloadDigest['pnr_payload_segments_complete'] ?? false),
            'blank_segment_rows_present' => (bool) ($payloadDigestReconciled['blank_segment_rows_present'] ?? $payloadDigest['blank_segment_rows_present'] ?? false),
            'fare_basis_present' => (bool) ($payloadDigestReconciled['fare_basis_present'] ?? $payloadDigest['fare_basis_present'] ?? false),
            'validating_carrier_present' => (bool) ($payloadDigestReconciled['validating_carrier_present'] ?? $payloadDigest['validating_carrier_present'] ?? false),
            'nn_not_self_blocking' => $nnNotSelfBlocking,
            'previous_attempt_failed' => $previousFailed,
            'safe_retry_requires_admin_confirmation' => $safeRetryRequiresAdmin,
            'mismatch_fields' => $integrity['mismatch_fields'] ?? [],
            'integrity_reason_code' => $integrity['reason_code'] ?? null,
            'connection_sticky_detail' => $admin['connection_sticky'] ?? $connectionSticky,
        ], static fn (mixed $v): bool => $v !== null);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function offerSnapshotFromBookingMeta(array $meta): array
    {
        if (is_array($meta['normalized_offer_snapshot'] ?? null)) {
            return $meta['normalized_offer_snapshot'];
        }
        if (is_array($meta['validated_offer_snapshot'] ?? null)) {
            return $meta['validated_offer_snapshot'];
        }

        return is_array($meta['flight_offer_snapshot'] ?? null) ? $meta['flight_offer_snapshot'] : [];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    protected function buildGdsPnrPayloadIntegrityDigest(Booking $booking, array $meta, array $snapshot): array
    {
        $gate = $this->validateNormalizedSabreOffer($snapshot);
        if (! $gate->success) {
            return ['pnr_payload_segments_complete' => false];
        }

        $draft = $this->prepareBookingPayload($snapshot, $this->passengerDataFromBooking($booking));
        if (($draft['_valid'] ?? false) !== true) {
            return ['pnr_payload_segments_complete' => false];
        }

        $apiDraft = $draft;
        unset($apiDraft['_valid']);
        $hints = $this->ticketingHintsFromOffer($snapshot);
        $raw = $this->bookingPayloadBuilder->buildTraditionalPnrCreatePassengerNameRecordV1Wire($apiDraft, $hints);
        $wire = $this->bookingPayloadBuilder->stripOtaInternalKeysFromBookingWire($raw);
        $wireDiag = $this->bookingPayloadBuilder->summarizeTraditionalPnrWirePostBody($wire, $meta);
        $snapshotSegs = array_values(is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : []);
        $segSell = $this->bookingPayloadBuilder->traditionalPnrAirBookSegmentSellDiagnostics($wire, $snapshotSegs);

        $haltCodes = $this->bookingPayloadBuilder->extractHaltOnStatusCodesFromCpnr(
            is_array($wire['CreatePassengerNameRecordRQ'] ?? null) ? $wire['CreatePassengerNameRecordRQ'] : []
        );

        $handoff = is_array($meta['sabre_booking_context'] ?? null) ? $meta['sabre_booking_context'] : [];
        $fbList = is_array($handoff['fare_basis_codes_by_segment'] ?? null) ? $handoff['fare_basis_codes_by_segment'] : [];

        $sellRows = is_array($segSell['segments'] ?? null) ? $segSell['segments'] : [];
        $blankCount = 0;
        $completeCount = 0;
        foreach ($sellRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $origin = trim((string) ($row['origin'] ?? ''));
            $dest = trim((string) ($row['destination'] ?? ''));
            $mkt = trim((string) ($row['marketing_airline'] ?? ''));
            $fn = trim((string) ($row['flight_number'] ?? ''));
            if ($origin === '' || $dest === '' || $mkt === '' || $fn === '') {
                $blankCount++;
            } else {
                $completeCount++;
            }
        }
        $expectedSegs = max(count($snapshotSegs), count($sellRows));

        return [
            'brand_code' => $handoff['selected_brand_code'] ?? $handoff['brand_code'] ?? null,
            'fare_basis_codes_by_segment' => $fbList,
            'pnr_payload_segments_complete' => $expectedSegs > 0 && $completeCount >= $expectedSegs && $blankCount === 0,
            'blank_segment_rows_present' => $blankCount > 0,
            'fare_basis_present' => $this->firstSegmentFareBasis($handoff) !== '',
            'validating_carrier_present' => trim((string) ($snapshot['validating_carrier'] ?? data_get($handoff, 'validating_carrier', ''))) !== '',
            'halt_on_status_codes' => $haltCodes,
            'wire_contract_valid' => (bool) ($wireDiag['wire_traditional_pnr_contract_valid'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function firstSegmentFareBasis(array $row): ?string
    {
        $list = is_array($row['fare_basis_codes_by_segment'] ?? null) ? $row['fare_basis_codes_by_segment'] : [];
        if ($list !== [] && trim((string) $list[0]) !== '') {
            return strtoupper(trim((string) $list[0]));
        }
        $single = trim((string) ($row['fare_basis'] ?? ''));

        return $single !== '' ? strtoupper($single) : null;
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $apiDraft
     * @param  array<string, mixed>  $diagFlags
     * @param  array<string, mixed>  $bookingContextSummary
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $epForAttempt
     * @return array<string, mixed>|null
     */
    protected function assessFareSelectionIntegrityGateForLiveAttempt(
        ?int $bookingIdForDiagnostics,
        array $offer,
        array $apiDraft,
        array $diagFlags,
        int $paxCount,
        int $segCount,
        int $connId,
        string $selectedOffer,
        float $fareAmt,
        string $fareCur,
        array $epForAttempt,
        array $bookingContextSummary,
        array $options = [],
    ): ?array {
        if ($bookingIdForDiagnostics === null || $bookingIdForDiagnostics <= 0) {
            return null;
        }
        if (! $this->isBookingLiveCallEnabled()) {
            return null;
        }

        $booking = Booking::query()->find($bookingIdForDiagnostics);
        if ($booking === null) {
            return null;
        }

        $meta = BookingSupplierConfirmationNoticeResolver::reconcileSabreBrandedFareMeta(
            is_array($booking->meta) ? $booking->meta : []
        );
        $snapshot = $this->mergePublicReviewSabreSnapshotFromBooking($booking, $offer);
        $digest = $this->buildGdsPnrPayloadIntegrityDigest($booking, $meta, $snapshot);
        $integrity = (new FareSelectionIntegrityValidator)->validate($meta, $snapshot, $snapshot, $digest);

        if (($integrity['blocks_pnr_creation'] ?? false) !== true) {
            return null;
        }

        $mismatchFields = is_array($integrity['mismatch_fields'] ?? null) ? $integrity['mismatch_fields'] : [];
        $adminSummary = is_array($integrity['admin_summary'] ?? null) ? $integrity['admin_summary'] : [];

        Log::warning('branded_fare_context_mismatch_blocked', [
            'booking_id' => $bookingIdForDiagnostics,
            'mismatch_fields' => array_slice($mismatchFields, 0, 12),
            'admin_summary' => SensitiveDataRedactor::redact($adminSummary),
        ]);

        $metaPatch = $meta;
        $metaPatch['fare_selection_integrity'] = [
            'consistent' => false,
            'reason_code' => FareSelectionIntegrityValidator::REASON_MISMATCH,
            'mismatch_fields' => $mismatchFields,
            'admin_summary' => $adminSummary,
            'checked_at' => now()->toIso8601String(),
        ];
        $metaPatch['defer_supplier_booking_to_manual_review'] = true;
        $metaPatch['supplier_pnr_deferred_reason'] = FareSelectionIntegrityValidator::REASON_MISMATCH;
        $booking->forceFill([
            'meta' => $metaPatch,
            'supplier_booking_status' => 'manual_review',
        ])->save();

        $bookingContextSummary['fare_selection_integrity_blocked'] = true;
        $bookingContextSummary['fare_selection_mismatch_fields'] = $mismatchFields;

        $this->logSabrePnrAttemptSummaryFromLiveResult(
            $bookingIdForDiagnostics,
            (string) ($booking->booking_reference ?? ''),
            $bookingContextSummary,
            ['http_status' => null, 'message' => 'Branded fare context mismatch; deferred to manual review.'],
            false,
            true,
            FareSelectionIntegrityValidator::REASON_MISMATCH,
        );

        return array_merge([
            'success' => false,
            'status' => 'needs_review',
            'message' => $this->customerStaffConfirmationBookingMessage(),
            'live_call_attempted' => false,
            'live_call_allowed' => true,
            'passenger_count' => $paxCount,
            'segment_count' => $segCount,
            'supplier_connection_id' => $connId,
            'selected_offer_id' => $selectedOffer,
            'fare_amount' => $fareAmt,
            'fare_currency' => $fareCur,
            'pnr' => null,
            'provider_booking_id' => null,
            'provider_status' => null,
            'http_status' => null,
            'reason_code' => FareSelectionIntegrityValidator::REASON_MISMATCH,
            'error_code' => FareSelectionIntegrityValidator::REASON_MISMATCH,
            'manual_review_required' => true,
            'booking_context_summary' => $bookingContextSummary,
            'fare_selection_integrity' => [
                'mismatch_fields' => $mismatchFields,
                'admin_summary' => $adminSummary,
            ],
        ], array_intersect_key($epForAttempt, array_flip(['endpoint_host', 'endpoint_path'])));
    }

    /**
     * B78: Local/testing-only fare / pricing / carrier context on the stored offer snapshot vs traditional CPNR root
     * {@code AirPrice} shape (no live POST, no raw Sabre response, no PCC).
     *
     * @return array<string, mixed>
     */
    /**
     * C5: Safe assessment of whether stored shop context has explicit BFM pricing/offer linkage for auto revalidate/PNR pricing.
     *
     * @return array<string, mixed>
     */
    public function assessAutoPnrPricingContextReadinessForBooking(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = [];
        if (is_array($meta['normalized_offer_snapshot'] ?? null)) {
            $snapshot = $meta['normalized_offer_snapshot'];
        } elseif (is_array($meta['validated_offer_snapshot'] ?? null)) {
            $snapshot = $meta['validated_offer_snapshot'];
        } elseif (is_array($meta['flight_offer_snapshot'] ?? null)) {
            $snapshot = $meta['flight_offer_snapshot'];
        }
        if ($snapshot === []) {
            return [
                'has_selected_passenger_info' => false,
                'has_pricing_information_ref' => false,
                'has_offer_reference' => false,
                'has_itinerary_reference' => false,
                'has_fare_component_refs' => false,
                'has_fare_component_desc_refs' => false,
                'has_validating_carrier' => false,
                'has_fare_basis_codes' => false,
                'has_revalidation_linkage_complete' => false,
                'auto_pnr_pricing_context_ready' => false,
                'missing_pricing_context_fields' => ['offer_snapshot'],
            ];
        }

        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown']);
        $snapshot = $this->mergePublicReviewSabreSnapshotFromBooking($booking, $snapshot);

        return app(SabreStoredPricingContextDigest::class)->assessReadiness($snapshot);
    }

    public function inspectPassengerRecordsFareContextDiagnosticsForCommand(Booking $booking): array
    {
        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $p = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($p !== SupplierProvider::Sabre->value) {
            return [
                'booking_id' => $booking->id,
                'error' => 'booking_not_sabre',
            ];
        }
        $snapshot = [];
        if (is_array($meta['normalized_offer_snapshot'] ?? null)) {
            $snapshot = $meta['normalized_offer_snapshot'];
        } elseif (is_array($meta['validated_offer_snapshot'] ?? null)) {
            $snapshot = $meta['validated_offer_snapshot'];
        } elseif (is_array($meta['flight_offer_snapshot'] ?? null)) {
            $snapshot = $meta['flight_offer_snapshot'];
        }
        $snapshot = $this->mergePublicReviewSabreSnapshotFromBooking($booking, $snapshot);
        $segments = array_values(is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : []);
        $raw = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];
        $ctx = is_array($raw['sabre_shop_context'] ?? null) ? $raw['sabre_shop_context'] : [];
        $ids = is_array($raw['sabre_shop_identifiers'] ?? null) ? $raw['sabre_shop_identifiers'] : [];

        $vc = $this->b78ResolveValidatingCarrierSnapshot($snapshot, $ctx, $ids);
        $validatingCarrierPresent = $vc !== '';

        $fareBasisPerSeg = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                $fareBasisPerSeg[] = false;

                continue;
            }
            $fb = strtoupper(trim((string) ($seg['fare_basis_code'] ?? $seg['fareBasisCode'] ?? '')));
            $fareBasisPerSeg[] = $fb !== '';
        }
        $fareBasisValues = $this->b78CollectFareBasisValuesSanitized($snapshot, $segments, $ctx, $ids);

        $pricingInformationPresent = $this->b78SnapshotHasPricingInformationScalars($ctx, $ids);
        $fcRefs = is_array($ctx['fare_component_refs'] ?? null) ? $ctx['fare_component_refs'] : [];
        $fcdRefs = is_array($ctx['fare_component_desc_refs'] ?? null) ? $ctx['fare_component_desc_refs'] : [];
        if (! array_is_list($fcRefs)) {
            $fcRefs = [];
        }
        if (! array_is_list($fcdRefs)) {
            $fcdRefs = [];
        }
        $fareComponentsPresent = $fcRefs !== [] || $fcdRefs !== [];
        $fareComponentSegmentCount = max(count($fcRefs), count($fcdRefs));

        $brandCodes = $this->b78CollectBrandishFromSnapshot($snapshot);
        $brandCandidatesPresent = $brandCodes !== [];

        $gate = $this->validateNormalizedSabreOffer($snapshot);
        $wireBuilt = false;
        $airpriceOqPresent = false;
        $oqKeys = [];
        $ptCodes = [];
        if ($gate->success) {
            $draft = $this->prepareBookingPayload($snapshot, $this->passengerDataFromBooking($booking));
            if (($draft['_valid'] ?? false) === true) {
                $apiDraft = $draft;
                unset($apiDraft['_valid']);
                $hints = $this->ticketingHintsFromOffer($snapshot);
                $rawWire = $this->bookingPayloadBuilder->buildTraditionalPnrCreatePassengerNameRecordV1Wire($apiDraft, $hints);
                $wire = $this->bookingPayloadBuilder->stripOtaInternalKeysFromBookingWire($rawWire);
                $extracted = $this->b78ExtractRootAirPriceOptionalQualifierSummary($wire);
                $airpriceOqPresent = $extracted['airprice_optional_qualifiers_present'];
                $oqKeys = $extracted['current_airprice_qualifier_keys'];
                $ptCodes = $extracted['passenger_type_codes_sanitized'];
                $wireBuilt = true;
            }
        }

        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::Sabre->value)
            ->orderByDesc('id')
            ->first();

        $missing = [];
        if (! $validatingCarrierPresent) {
            $missing[] = 'missing_validating_carrier';
        }
        if ($fareBasisPerSeg !== [] && ! in_array(true, $fareBasisPerSeg, true)) {
            $missing[] = 'missing_fare_basis_all_segments';
        }
        if (! $pricingInformationPresent) {
            $missing[] = 'missing_pricing_information';
        }
        if (! $fareComponentsPresent) {
            $missing[] = 'missing_fare_components';
        }
        if ($wireBuilt && ! $airpriceOqPresent) {
            $missing[] = 'missing_airprice_optional_qualifiers';
        }
        if (! $gate->success) {
            $missing[] = 'offer_gate_failed';
        }
        if ($gate->success && ! $wireBuilt) {
            $missing[] = 'cpnr_draft_unavailable';
        }

        sort($missing);

        return [
            'booking_id' => $booking->id,
            'segment_count' => count($segments),
            'validating_carrier_present' => $validatingCarrierPresent,
            'validating_carrier_sanitized' => $vc !== '' ? $vc : null,
            'fare_basis_present_per_segment' => $fareBasisPerSeg,
            'fare_basis_values_sanitized' => $fareBasisValues,
            'pricing_information_present' => $pricingInformationPresent,
            'fare_components_present' => $fareComponentsPresent,
            'fare_component_segment_count' => $fareComponentSegmentCount,
            'passenger_type_codes_sanitized' => $ptCodes,
            'brand_candidates_present' => $brandCandidatesPresent,
            'brand_codes_sanitized' => $brandCodes,
            'airprice_optional_qualifiers_present' => $airpriceOqPresent,
            'current_airprice_qualifier_keys' => $oqKeys,
            'missing_context_flags' => array_values(array_unique($missing)),
            'last_supplier_attempt_error' => $this->b78ExtractNoFaresClassErrorFromAttempt($attempt),
        ];
    }

    /**
     * BF7-A: Sanitized AirPrice Brand qualifier diagnostics for a booking (local/testing; no HTTP).
     *
     * @return array<string, mixed>
     */
    public function inspectPassengerRecordsAirPriceBrandDiagnosticsForCommand(Booking $booking, ?string $styleOverride = null): array
    {
        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $p = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($p !== SupplierProvider::Sabre->value) {
            return [
                'booking_id' => $booking->id,
                'error' => 'booking_not_sabre',
            ];
        }

        $snapshot = [];
        if (is_array($meta['normalized_offer_snapshot'] ?? null)) {
            $snapshot = $meta['normalized_offer_snapshot'];
        } elseif (is_array($meta['validated_offer_snapshot'] ?? null)) {
            $snapshot = $meta['validated_offer_snapshot'];
        } elseif (is_array($meta['flight_offer_snapshot'] ?? null)) {
            $snapshot = $meta['flight_offer_snapshot'];
        }
        $snapshot = $this->mergePublicReviewSabreSnapshotFromBooking($booking, $snapshot);

        $style = trim((string) ($styleOverride ?? ''));
        if ($style === '') {
            $styleSel = $this->inspectPassengerRecordsStyleSelectionForCommand($booking);
            $style = trim((string) ($styleSel['selected_style'] ?? SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1));
            if ($style === '' || isset($styleSel['error'])) {
                $style = SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS;
            }
        }

        $gate = $this->validateNormalizedSabreOffer($snapshot);
        if (! $gate->success) {
            return [
                'booking_id' => $booking->id,
                'payload_style' => $style,
                'error' => 'offer_validation_failed',
                'selected_fare_family_brand_code' => $this->bookingPayloadBuilder
                    ->selectedFareFamilyBrandCodeFromBookingMetaForInspect($meta),
            ];
        }

        $draft = $this->prepareBookingPayload($snapshot, $this->passengerDataFromBooking($booking));
        if (($draft['_valid'] ?? false) !== true) {
            return [
                'booking_id' => $booking->id,
                'payload_style' => $style,
                'error' => 'cpnr_draft_unavailable',
                'selected_fare_family_brand_code' => $this->bookingPayloadBuilder
                    ->selectedFareFamilyBrandCodeFromBookingMetaForInspect($meta),
            ];
        }

        $apiDraft = $draft;
        unset($apiDraft['_valid']);
        $hints = $this->ticketingHintsFromOffer($snapshot);
        $rawWire = $this->bookingPayloadBuilder->buildPassengerRecordsCpnrWireForStyle($apiDraft, $hints, $style);
        $wire = $this->bookingPayloadBuilder->stripOtaInternalKeysFromBookingWire($rawWire);

        return array_merge(
            ['booking_id' => $booking->id],
            $this->bookingPayloadBuilder->summarizeAirPriceBrandQualifierForInspect(
                $apiDraft,
                $wire,
                $style,
                $meta,
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $wire
     * @return array{airprice_optional_qualifiers_present: bool, current_airprice_qualifier_keys: list<string>, passenger_type_codes_sanitized: list<string>}
     */
    protected function b78ExtractRootAirPriceOptionalQualifierSummary(array $wire): array
    {
        $cpnr = is_array($wire['CreatePassengerNameRecordRQ'] ?? null) ? $wire['CreatePassengerNameRecordRQ'] : [];
        $ap = $cpnr['AirPrice'] ?? null;
        $oqKeys = [];
        $ptCodes = [];
        $oqPresent = false;
        if (is_array($ap) && $ap !== [] && array_is_list($ap)) {
            $first = is_array($ap[0] ?? null) ? $ap[0] : [];
            $pri = is_array($first['PriceRequestInformation'] ?? null) ? $first['PriceRequestInformation'] : [];
            $oq = is_array($pri['OptionalQualifiers'] ?? null) ? $pri['OptionalQualifiers'] : [];
            $oqPresent = $oq !== [];
            $oqKeys = array_keys($oq);
            sort($oqKeys);
            $oqKeys = array_values(array_slice($oqKeys, 0, 24));
            $pq = is_array($oq['PricingQualifiers'] ?? null) ? $oq['PricingQualifiers'] : [];
            $ptRows = $pq['PassengerType'] ?? [];
            $list = [];
            if (is_array($ptRows)) {
                $list = array_is_list($ptRows) ? $ptRows : [$ptRows];
            }
            foreach ($list as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $c = strtoupper(trim((string) ($row['Code'] ?? '')));
                if ($c !== '') {
                    $ptCodes[] = substr($c, 0, 8);
                }
            }
        }
        $ptCodes = array_values(array_unique(array_slice($ptCodes, 0, 12)));

        return [
            'airprice_optional_qualifiers_present' => $oqPresent,
            'current_airprice_qualifier_keys' => $oqKeys,
            'passenger_type_codes_sanitized' => $ptCodes,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $ids
     */
    protected function b78ResolveValidatingCarrierSnapshot(array $snapshot, array $ctx, array $ids): string
    {
        foreach ([
            (string) ($snapshot['validating_carrier'] ?? ''),
            (string) ($ctx['validating_carrier'] ?? ''),
            (string) ($ids['validating_carrier_code'] ?? ''),
        ] as $cand) {
            $t = strtoupper(trim($cand));
            if ($t !== '') {
                return substr($t, 0, 8);
            }
        }

        return '';
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $ids
     * @return list<string>
     */
    protected function b78CollectFareBasisValuesSanitized(array $snapshot, array $segments, array $ctx, array $ids): array
    {
        $out = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            foreach (['fare_basis_code', 'fareBasisCode'] as $k) {
                $t = strtoupper(trim((string) ($seg[$k] ?? '')));
                if ($t !== '') {
                    $out[] = substr($t, 0, 24);
                }
            }
        }
        $fb = is_array($snapshot['fare_breakdown'] ?? null) ? $snapshot['fare_breakdown'] : [];
        $fbcFb = $fb['fare_basis_codes'] ?? null;
        if (is_array($fbcFb)) {
            foreach ($fbcFb as $c) {
                $t = strtoupper(trim((string) $c));
                if ($t !== '') {
                    $out[] = substr($t, 0, 24);
                }
            }
        }
        $fbcCtx = $ctx['fare_basis_codes'] ?? null;
        if (is_array($fbcCtx)) {
            foreach ($fbcCtx as $c) {
                $t = strtoupper(trim((string) $c));
                if ($t !== '') {
                    $out[] = substr($t, 0, 24);
                }
            }
        }
        $first = trim((string) ($ids['fare_basis_first'] ?? ''));
        if ($first !== '') {
            $out[] = strtoupper(substr($first, 0, 24));
        }
        $csv = trim((string) ($ids['fare_basis_codes_csv'] ?? ''));
        if ($csv !== '') {
            foreach (array_slice(array_map('trim', explode(',', $csv)), 0, 12) as $part) {
                $t = strtoupper($part);
                if ($t !== '') {
                    $out[] = substr($t, 0, 24);
                }
            }
        }

        $out = array_values(array_unique(array_filter($out, static fn (string $s): bool => $s !== '')));

        return array_slice($out, 0, 16);
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $ids
     */
    protected function b78SnapshotHasPricingInformationScalars(array $ctx, array $ids): bool
    {
        foreach (['pricing_information_ref', 'pricing_information_id'] as $k) {
            if (trim((string) ($ctx[$k] ?? '')) !== '') {
                return true;
            }
        }
        foreach ($ids as $k => $v) {
            if (! is_string($k) || ! str_starts_with($k, 'pricing_')) {
                continue;
            }
            if (is_scalar($v) && trim((string) $v) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return list<string>
     */
    protected function b78CollectBrandishFromSnapshot(array $snapshot): array
    {
        $acc = [];
        $this->b78WalkBrandishNodes($snapshot, $acc, 0, 6);

        $acc = array_values(array_unique(array_filter(array_map(
            static fn (string $s): string => strtoupper(substr(trim($s), 0, 32)),
            $acc
        ), static fn (string $s): bool => $s !== '')));

        return array_slice($acc, 0, 16);
    }

    /**
     * @param  array<string, mixed>|mixed  $node
     * @param  list<string>  $acc
     */
    protected function b78WalkBrandishNodes(mixed $node, array &$acc, int $depth, int $maxDepth): void
    {
        if ($depth > $maxDepth || ! is_array($node)) {
            return;
        }
        foreach ($node as $k => $v) {
            if (! is_string($k) || $this->b78DiagKeyLooksSensitive($k)) {
                continue;
            }
            if (stripos($k, 'brand') !== false && is_string($v)) {
                $t = trim($v);
                if ($t !== '' && strlen($t) <= 48 && ! $this->b78ScalarLooksLikeContact($t)) {
                    $acc[] = $t;
                }
            }
            if (is_array($v)) {
                $this->b78WalkBrandishNodes($v, $acc, $depth + 1, $maxDepth);
            }
        }
    }

    protected function b78DiagKeyLooksSensitive(string $key): bool
    {
        $l = strtolower($key);
        foreach (['email', 'phone', 'passport', 'document', 'nationality', 'address', 'contact',
            'first_name', 'last_name', 'given', 'surname', 'dob', 'birth', 'password', 'token',
            'secret', 'pcc'] as $frag) {
            if (str_contains($l, $frag)) {
                return true;
            }
        }

        return false;
    }

    protected function b78ScalarLooksLikeContact(string $value): bool
    {
        if (str_contains($value, '@')) {
            return true;
        }
        if (preg_match('/^\+?[0-9]{6,}$/', $value) === 1) {
            return true;
        }

        return false;
    }

    protected function b78ExtractNoFaresClassErrorFromAttempt(?SupplierBookingAttempt $attempt): ?string
    {
        if ($attempt === null) {
            return null;
        }
        $haystacks = [];
        $em = trim((string) $attempt->error_message);
        if ($em !== '') {
            $haystacks[] = $em;
        }
        $safe = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        foreach (['response_error_messages', 'host_warning_messages_truncated', 'host_warning_messages'] as $k) {
            $arr = $safe[$k] ?? null;
            if (! is_array($arr)) {
                continue;
            }
            foreach ($arr as $m) {
                if (is_string($m) && trim($m) !== '') {
                    $haystacks[] = $m;
                }
            }
        }
        foreach ($haystacks as $h) {
            if (stripos($h, 'NO FARES') !== false || stripos($h, 'FARES/RBD/CARRIER') !== false) {
                $t = trim($h);

                return strlen($t) > 260 ? substr($t, 0, 260) : $t;
            }
        }

        return null;
    }

    /**
     * B75: Summarize stored {@code compare_booking_endpoint} attempts for Passenger Records v2.4 vs v2.5 (safe fields only).
     */
    protected function b75PassengerRecordsV24V25EndpointCompareNote(int $bookingId): string
    {
        $rows = SupplierBookingAttempt::query()
            ->where('booking_id', $bookingId)
            ->where('action', 'compare_booking_endpoint')
            ->where('provider', SupplierProvider::Sabre->value)
            ->orderBy('id')
            ->get(['id', 'safe_summary']);

        $v24 = null;
        $v25 = null;
        foreach ($rows as $row) {
            $safe = is_array($row->safe_summary) ? $row->safe_summary : [];
            $path = strtolower((string) ($safe['endpoint_path'] ?? ''));
            if (! str_contains($path, 'passenger/records')) {
                continue;
            }
            if (str_contains($path, 'v2.4.0') && $v24 === null) {
                $v24 = $safe;
            }
            if (str_contains($path, 'v2.5.0') && $v25 === null) {
                $v25 = $safe;
            }
        }

        if ($v24 === null && $v25 === null) {
            return 'no_compare_booking_endpoint_passenger_records_rows';
        }
        if ($v24 === null || $v25 === null) {
            return 'compare_booking_endpoint_missing_one_of_v24_or_v25';
        }

        $sameHttp = ($v24['http_status'] ?? null) === ($v25['http_status'] ?? null);
        $c24 = implode(',', (array) ($v24['response_error_codes'] ?? []));
        $c25 = implode(',', (array) ($v25['response_error_codes'] ?? []));
        $m24 = implode('|', array_slice((array) ($v24['response_error_messages'] ?? []), 0, 2));
        $m25 = implode('|', array_slice((array) ($v25['response_error_messages'] ?? []), 0, 2));

        if ($sameHttp && $c24 === $c25 && $m24 === $m25) {
            return 'v24_and_v25_same_http_status_and_truncated_error_signals_in_compare_attempts';
        }

        return 'v24_and_v25_differ_in_compare_attempts';
    }

    /**
     * Append one entry to the local JSON report for createBooking style compares (safe fields only; no raw bodies).
     *
     * @param  array<string, mixed>  $entry
     */
    protected function appendCompareCreatebookingStyleReportFile(Booking $booking, array $entry): void
    {
        $path = storage_path('app/sabre-createbooking-style-compare-booking-'.$booking->id.'.json');
        $existingAttempts = [];
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded) && is_array($decoded['attempts'] ?? null)) {
                $existingAttempts = $decoded['attempts'];
            }
        }
        $existingAttempts[] = array_merge(['recorded_at' => now()->toIso8601String()], $entry);
        file_put_contents($path, json_encode([
            'booking_id' => $booking->id,
            'attempts' => $existingAttempts,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  array<string, mixed>  $live  {@see SabreBookingClient::createPassengerRecordBooking()}
     * @param  array<string, mixed>  $diag  {@see SabreBookingPayloadBuilder::summarizeEnvelopeForDiagnostics()}
     * @param  array<string, mixed>  $shapeHints  e.g. pnr_present, supplier_reference_present from compare row
     */
    protected function recordCompareCreatebookingStyleAttempt(
        Booking $booking,
        SupplierConnection $connection,
        string $style,
        array $live,
        array $diag,
        array $shapeHints,
    ): int {
        $diagFlat = self::flattenBookingDiagnostics(is_array($live['booking_diagnostics'] ?? null) ? $live['booking_diagnostics'] : []);
        $ep = $this->resolveBookingEndpointSummary($connection->id);
        $phoneCls = $this->agencyPhoneMissingClassifierForTripOrdersCompareRow($style, $diag, $live, $booking->id);
        $safeSummary = SensitiveDataRedactor::redact(array_merge([
            'source' => 'sabre_compare_createbooking_styles',
            'payload_style' => $style,
            'http_status' => $live['http_status'] ?? null,
            'error_code' => $live['error_code'] ?? null,
            'reason_code' => $live['reason_code'] ?? null,
            'live_call_attempted' => (bool) ($live['live_call_attempted'] ?? false),
            'pnr_present' => (bool) ($shapeHints['pnr_present'] ?? false),
            'supplier_reference_present' => (bool) ($shapeHints['supplier_reference_present'] ?? false),
            'ticketing_disabled' => true,
            'agency_phone_error_cleared' => $this->agencyPhoneMissingClearedFromLiveCompareDigest($live),
        ], $phoneCls, array_intersect_key($diag, array_flip([
            'wire_root_keys', 'wire_has_flight_offer_at_root', 'wire_has_flight_details_at_root',
            'wire_has_required_product_at_root', 'wire_segment_count', 'wire_flight_offer_segment_count',
            'wire_flight_details_segment_count', 'wire_traveler_count', 'wire_fare_basis_count', 'wire_booking_class_count',
            'wire_has_validating_carrier', 'wire_has_amount', 'wire_has_currency',
            'wire_gender_values_sanitized', 'wire_gender_enum_valid',
            'wire_has_remarks', 'wire_remarks_count',
            'wire_has_contact', 'wire_has_contactInfo', 'wire_contact_field_style', 'wire_has_contact_email', 'wire_has_contact_phone',
            'wire_has_customer_contact_phone', 'wire_has_agency_phone', 'wire_agency_phone_field_style', 'wire_agency_phone_paths', 'wire_agency_phone_redacted', 'wire_agency_phone_ok', 'wire_has_POS', 'wire_has_pos', 'wire_has_agency_block', 'wire_has_travelAgency', 'wire_has_customerInfo', 'wire_pcc_present', 'wire_agency_config_phone_present', 'wire_agency_country_config_present', 'wire_phone_use_type_values_sanitized', 'wire_phone_location_values_sanitized',
            'wire_traveler_field_style', 'wire_has_givenName', 'wire_has_given_name',
            'wire_has_passengerCode', 'wire_has_passengerTypeCode',
            'wire_traveler_required_fields_valid', 'wire_invalid_traveler_field_keys',
            'wire_segment_field_style', 'wire_segment_required_fields_valid', 'wire_invalid_segment_field_keys',
            'wire_null_path_count', 'wire_null_paths', 'wire_required_null_paths',
            'wire_has_any_nulls', 'wire_nulls_safe_to_omit', 'wire_payload_null_free',
            'wire_contract_valid', 'wire_invalid_contract_keys',
        ])), self::tripOrdersTravelerPayloadAuditSlice($diag), array_intersect_key($diagFlat, array_flip([
            'response_error_count', 'response_error_codes', 'response_error_messages', 'response_error_fields',
            'response_error_paths', 'response_missing_fields', 'response_top_level_keys', 'response_top_level_message', 'response_top_level_status',
            'response_top_level_error_code', 'response_top_level_type', 'response_additional_messages',
            'request_id', 'request_correlation_id', 'trace_id', 'timestamp', 'safe_validation_excerpts',
        ])), array_intersect_key($ep, array_flip(['endpoint_host', 'endpoint_path']))));

        $attempt = SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connection->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'compare_trip_orders_createbooking_style',
            'status' => ($live['success'] ?? false) ? 'success' : 'failed',
            'error_code' => isset($live['error_code']) && is_string($live['error_code']) && $live['error_code'] !== ''
                ? substr($live['error_code'], 0, 191) : null,
            'error_message' => isset($live['safe_message']) && is_string($live['safe_message'])
                ? substr(trim($live['safe_message']), 0, 2000) : null,
            'supplier_reference' => null,
            'safe_summary' => $safeSummary,
            'attempted_by' => null,
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        $this->appendCompareCreatebookingStyleReportFile($booking, array_intersect_key($safeSummary, array_flip([
            'payload_style', 'http_status', 'error_code', 'reason_code', 'response_error_codes', 'response_error_messages',
            'response_error_fields', 'response_error_paths', 'response_missing_fields',
            'response_top_level_keys', 'response_top_level_error_code', 'wire_root_keys', 'wire_has_required_product_at_root',
            'wire_traveler_count', 'wire_flight_offer_segment_count', 'wire_flight_details_segment_count',
            'wire_gender_values_sanitized', 'wire_gender_enum_valid',
            'wire_has_remarks', 'wire_remarks_count',
            'wire_has_contact', 'wire_has_contactInfo', 'wire_contact_field_style', 'wire_has_contact_email', 'wire_has_contact_phone',
            'wire_has_customer_contact_phone', 'wire_has_agency_phone', 'wire_agency_phone_field_style', 'wire_agency_phone_paths', 'wire_agency_phone_redacted', 'wire_agency_phone_ok', 'wire_has_POS', 'wire_has_pos', 'wire_has_agency_block', 'wire_has_travelAgency', 'wire_has_customerInfo', 'wire_pcc_present', 'wire_agency_config_phone_present', 'wire_agency_country_config_present', 'wire_phone_use_type_values_sanitized', 'wire_phone_location_values_sanitized',
            'wire_traveler_field_style', 'wire_has_givenName', 'wire_has_given_name',
            'wire_has_passengerCode', 'wire_has_passengerTypeCode',
            'wire_traveler_required_fields_valid', 'wire_invalid_traveler_field_keys',
            'wire_segment_field_style', 'wire_segment_required_fields_valid', 'wire_invalid_segment_field_keys',
            'agency_phone_error', 'agency_phone_body_variants_tested', 'likely_profile_level_agency_phone_issue', 'suggested_next_path', 'agency_phone_setup_note',
            'agency_phone_error_cleared', 'agency_phone_config_present', 'wire_has_agency_phone', 'traditional_pnr_endpoints_forbidden',
        ])));

        return (int) $attempt->id;
    }

    /**
     * B37: When live compare digest contains no {@code AGENCY_PHONE_MISSING} token, the style may have cleared that Sabre error class.
     */
    protected function agencyPhoneMissingClearedFromLiveCompareDigest(array $live): bool
    {
        $bd = is_array($live['booking_diagnostics'] ?? null) ? $live['booking_diagnostics'] : [];
        $flat = self::flattenBookingDiagnostics($bd);
        $hay = [];
        foreach (['response_error_messages', 'response_error_codes', 'response_additional_messages'] as $dk) {
            foreach ((array) ($flat[$dk] ?? []) as $m) {
                if (is_string($m) && $m !== '') {
                    $hay[] = $m;
                }
            }
            foreach ((array) ($live[$dk] ?? []) as $m) {
                if (is_string($m) && $m !== '') {
                    $hay[] = $m;
                }
            }
        }
        foreach ($hay as $m) {
            if (stripos($m, 'AGENCY_PHONE_MISSING') !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * B38: True when Sabre digest (live compare) includes {@code AGENCY_PHONE_MISSING}.
     */
    protected function liveCompareDigestContainsAgencyPhoneMissing(array $live): bool
    {
        return ! $this->agencyPhoneMissingClearedFromLiveCompareDigest($live);
    }

    /**
     * B38: Safe classifier rows for Trip Orders compare output / attempt {@code safe_summary} (no PII).
     *
     * @param  array<string, mixed>  $diag  {@see SabreBookingPayloadBuilder::summarizeEnvelopeForDiagnostics()} for Trip Orders envelope
     * @param  array<string, mixed>|null  $live  Live {@see SabreBookingClient::createPassengerRecordBooking()} result when sent; otherwise null
     * @return array<string, mixed>
     */
    protected function agencyPhoneMissingClassifierForTripOrdersCompareRow(string $style, array $diag, ?array $live, ?int $bookingId = null): array
    {
        $hasError = $live !== null && $this->liveCompareDigestContainsAgencyPhoneMissing($live);
        $variantsTested = in_array($style, SabreBookingPayloadBuilder::AGENCY_PHONE_BODY_VARIANT_COMPARE_STYLES, true)
            || in_array($style, SabreBookingPayloadBuilder::TRIP_ORDERS_V2_AGENCY_PHONE_CERTIFICATION_STYLES, true);
        $hasAgencyPhoneValue = (bool) ($diag['has_agency_phone_value'] ?? false)
            || (bool) ($diag['wire_has_agency_phone'] ?? false);
        $likelyProfile = $hasError && $hasAgencyPhoneValue;
        $agencyPhoneConfigPresent = trim((string) config('suppliers.sabre.agency_phone', '')) !== ''
            || (bool) ($diag['wire_agency_config_phone_present'] ?? false);
        $blockerClass = null;
        if ($hasError) {
            $blockerClass = $hasAgencyPhoneValue ? 'pcc_profile_agency_phone_missing' : 'payload_shape_unresolved';
        } elseif ($variantsTested) {
            $blockerClass = 'agency_phone_cleared';
        }
        $out = [
            'agency_phone_missing' => $hasError,
            'agency_phone_error' => $hasError,
            'agency_phone_body_variants_tested' => $variantsTested,
            'likely_profile_level_agency_phone_issue' => $likelyProfile,
            'agency_phone_blocker_class' => $blockerClass,
            'agency_phone_config_present' => $agencyPhoneConfigPresent,
            'wire_has_agency_phone' => $hasAgencyPhoneValue,
            'has_agency_phone_value' => $hasAgencyPhoneValue,
            'traditional_pnr_endpoints_forbidden' => $bookingId !== null && $this->traditionalPnrPassengerEndpointsAllForbiddenLatest((int) $bookingId),
            'suggested_next_path' => $hasError ? 'traditional_pnr_fallback' : null,
        ];
        if ($blockerClass === 'pcc_profile_agency_phone_missing') {
            $out['recommended_sabre_support_message'] = 'Trip Orders createBooking returns AGENCY_PHONE_MISSING while request JSON includes agency office phone (agencyContactInfo variants tested: phone, phoneNumber, phones[]). Please confirm PCC/TJR agency office phone is configured for our certification PCC; body fields may be ignored when the host profile is empty.';
            $out['agency_phone_setup_note'] = $out['recommended_sabre_support_message'];
        } elseif ($hasError) {
            $out['agency_phone_setup_note'] = 'AGENCY_PHONE_MISSING with no agency phone detected on wire — try another certification phone shape or set SABRE_AGENCY_PHONE.';
        }

        return $out;
    }

    /**
     * B22/B23: compare Trip Orders {@code createBooking} payload styles (shape; optional {@code --send} live POST per style).
     *
     * @param  ?non-empty-string  $styleFilter  When set, only this style is evaluated (must be in {@see SabreBookingPayloadBuilder::TRIP_ORDERS_CREATEBOOKING_COMPARE_STYLES}).
     * @return list<array<string, mixed>>
     */
    public function compareTripOrdersCreateBookingStylesForCommand(Booking $booking, bool $sendAttempt = false, ?string $styleFilter = null): array
    {
        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $p = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($p !== SupplierProvider::Sabre->value) {
            return [[
                'style' => 'n/a',
                'http_status' => 'not_attempted',
                'status' => 'booking_not_sabre',
                'error_message' => 'booking_not_sabre',
                'pnr_present' => false,
                'supplier_reference_present' => false,
                'createBooking_root_keys' => [],
            ]];
        }
        $snapshot = [];
        if (is_array($meta['normalized_offer_snapshot'] ?? null)) {
            $snapshot = $meta['normalized_offer_snapshot'];
        } elseif (is_array($meta['validated_offer_snapshot'] ?? null)) {
            $snapshot = $meta['validated_offer_snapshot'];
        } elseif (is_array($meta['flight_offer_snapshot'] ?? null)) {
            $snapshot = $meta['flight_offer_snapshot'];
        }
        $snapshot = $this->mergePublicReviewSabreSnapshotFromBooking($booking, $snapshot);
        $gate = $this->validateNormalizedSabreOffer($snapshot);
        if (! $gate->success) {
            return [[
                'style' => 'n/a',
                'http_status' => 'not_attempted',
                'status' => 'validation_failed',
                'error_message' => (string) ($gate->safe_context['error_code'] ?? 'validation_failed'),
                'pnr_present' => false,
                'supplier_reference_present' => false,
                'createBooking_root_keys' => [],
            ]];
        }
        $draft = $this->prepareBookingPayload($snapshot, $this->passengerDataFromBooking($booking));
        if (($draft['_valid'] ?? false) !== true) {
            return [[
                'style' => 'n/a',
                'http_status' => 'not_attempted',
                'status' => 'draft_invalid',
                'error_message' => (string) ($draft['code'] ?? 'validation_failed'),
                'pnr_present' => false,
                'supplier_reference_present' => false,
                'createBooking_root_keys' => [],
            ]];
        }
        $apiDraft = $draft;
        unset($apiDraft['_valid']);
        $hints = $this->ticketingHintsFromOffer($snapshot);
        $allCompareStyles = SabreBookingPayloadBuilder::TRIP_ORDERS_CREATEBOOKING_COMPARE_STYLES;
        if ($styleFilter !== null && trim($styleFilter) !== '') {
            $want = trim($styleFilter);
            if (! in_array($want, $allCompareStyles, true)) {
                return [[
                    'style' => $want,
                    'http_status' => 'not_attempted',
                    'status' => 'invalid_style',
                    'error_message' => 'Invalid --style "'.$want.'". Allowed styles: '.implode(', ', $allCompareStyles).'.',
                    'pnr_present' => false,
                    'supplier_reference_present' => false,
                    'createBooking_root_keys' => [],
                    'wire_root_keys' => [],
                    'wire_has_flight_offer_at_root' => false,
                    'wire_has_flight_details_at_root' => false,
                    'wire_has_required_product_at_root' => false,
                    'has_flight_offer' => false,
                    'has_flight_details' => false,
                    'has_required_booking_product_object' => false,
                ]];
            }
            $styles = [$want];
        } else {
            $styles = $allCompareStyles;
        }
        $conn = null;
        if ($sendAttempt) {
            $cid = (int) data_get($booking->meta, 'supplier_connection_id', 0);
            $cand = $cid > 0 ? SupplierConnection::query()->find($cid) : null;
            if ($cand !== null && $cand->provider === SupplierProvider::Sabre) {
                $conn = $cand;
            }
        }
        $paxCount = count(is_array($apiDraft['passengers'] ?? null) ? $apiDraft['passengers'] : []);
        $segCount = count(is_array($apiDraft['segments'] ?? null) ? $apiDraft['segments'] : []);
        $contactArr = is_array($apiDraft['contact'] ?? null) ? $apiDraft['contact'] : [];
        $rows = [];
        foreach ($styles as $style) {
            $liveForClassifier = null;
            $envelope = $this->bookingPayloadBuilder->buildTripOrdersCreateBookingEnvelope($apiDraft, $hints, $style);
            $diag = $this->bookingPayloadBuilder->summarizeEnvelopeForDiagnostics($envelope);
            $cb = is_array($envelope['createBooking'] ?? null) ? $envelope['createBooking'] : [];
            $shapeKeys = $cb !== [] ? array_slice(array_keys($cb), 0, 48) : array_slice($diag['wire_root_keys'] ?? [], 0, 48);
            $row = [
                'style' => $style,
                'http_status' => 'not_attempted',
                'status' => (($diag['validation_ok'] ?? true) && ($diag['has_required_booking_product_object'] ?? false)) ? 'payload_inspect_ok' : 'payload_inspect_failed',
                'error_message' => ($diag['validation_ok'] ?? true) ? '' : 'trip_orders_payload_validation_failed',
                'pnr_present' => false,
                'supplier_reference_present' => false,
                'createBooking_root_keys' => $shapeKeys,
                'wire_root_keys' => $diag['wire_root_keys'] ?? [],
                'wire_has_flight_offer_at_root' => (bool) ($diag['wire_has_flight_offer_at_root'] ?? false),
                'wire_has_flight_details_at_root' => (bool) ($diag['wire_has_flight_details_at_root'] ?? false),
                'wire_has_required_product_at_root' => (bool) ($diag['wire_has_required_product_at_root'] ?? false),
                'has_flight_offer' => (bool) ($diag['has_flight_offer'] ?? false),
                'has_flight_details' => (bool) ($diag['has_flight_details'] ?? false),
                'has_required_booking_product_object' => (bool) ($diag['has_required_booking_product_object'] ?? false),
                'agency_phone_error_cleared' => false,
            ];
            foreach ($diag as $dk => $dv) {
                if (! is_string($dk)) {
                    continue;
                }
                if (str_starts_with($dk, 'traveler_')
                    || in_array($dk, [
                        'wire_traveler_field_style', 'wire_has_givenName', 'wire_has_given_name',
                        'wire_has_passengerCode', 'wire_has_passengerTypeCode',
                        'wire_has_contact', 'wire_has_contactInfo', 'wire_contact_field_style', 'wire_has_contact_email', 'wire_has_contact_phone',
                        'wire_has_customer_contact_phone', 'wire_has_agency_phone', 'wire_agency_phone_field_style', 'wire_agency_phone_paths', 'wire_agency_phone_redacted', 'wire_agency_phone_ok', 'wire_has_POS', 'wire_has_pos', 'wire_has_agency_block', 'wire_has_travelAgency', 'wire_has_customerInfo', 'wire_pcc_present', 'wire_agency_config_phone_present', 'wire_agency_country_config_present', 'wire_phone_use_type_values_sanitized', 'wire_phone_location_values_sanitized',
                        'wire_traveler_required_fields_valid', 'wire_invalid_traveler_field_keys',
                        'wire_segment_field_style', 'wire_segment_required_fields_valid', 'wire_invalid_segment_field_keys',
                        'wire_null_path_count', 'wire_null_paths', 'wire_required_null_paths',
                        'wire_has_any_nulls', 'wire_nulls_safe_to_omit', 'wire_payload_null_free',
                        'wire_contract_valid', 'wire_invalid_contract_keys',
                    ], true)) {
                    $row[$dk] = $dv;
                }
            }
            if ($sendAttempt) {
                if ($conn === null) {
                    $row['http_status'] = 'not_sent';
                    $row['error_message'] = 'no_sabre_supplier_connection';
                    $row['status'] = 'send_skipped';
                } elseif (($diag['wire_agency_phone_ok'] ?? true) === false) {
                    $row['http_status'] = 'not_sent';
                    $row['status'] = 'payload_validation_failed';
                    $row['error_message'] = 'agency_phone_missing';
                } elseif (($diag['wire_traveler_required_fields_valid'] ?? true) === false
                    || (($diag['wire_payload_null_free'] ?? true) === false)
                    || (($diag['wire_contract_valid'] ?? true) === false)
                    || (($diag['wire_segment_required_fields_valid'] ?? true) === false)) {
                    $row['http_status'] = 'not_sent';
                    $row['status'] = 'payload_validation_failed';
                    $keys = is_array($diag['wire_invalid_traveler_field_keys'] ?? null)
                        ? $diag['wire_invalid_traveler_field_keys']
                        : [];
                    $ckeys = is_array($diag['wire_invalid_contract_keys'] ?? null)
                        ? $diag['wire_invalid_contract_keys']
                        : [];
                    $skeys = is_array($diag['wire_invalid_segment_field_keys'] ?? null)
                        ? $diag['wire_invalid_segment_field_keys']
                        : [];
                    $npaths = is_array($diag['wire_null_paths'] ?? null)
                        ? $diag['wire_null_paths']
                        : [];
                    $row['error_message'] = substr(implode(' | ', array_filter([
                        $ckeys !== [] ? implode(', ', array_map('strval', array_slice($ckeys, 0, 16))) : '',
                        $skeys !== [] ? implode(', ', array_map('strval', array_slice($skeys, 0, 16))) : '',
                        $npaths !== [] ? 'null_paths: '.implode(', ', array_map('strval', array_slice($npaths, 0, 12))) : '',
                        $keys !== [] ? implode(', ', array_map('strval', array_slice($keys, 0, 16))) : '',
                    ], static fn (string $s): bool => $s !== '')), 0, 240);
                } elseif ($this->mayPerformLiveSabreBookingCall()) {
                    $blindWarning = null;
                    if (in_array($style, SabreBookingPayloadBuilder::AGENCY_PHONE_BODY_VARIANT_COMPARE_STYLES, true)
                        && $this->countAgencyPhoneBodyVariantFailuresForBooking($booking) >= 5) {
                        $blindWarning = 'Repeated agency phone body variants failed; this may be PCC/profile-level. Continue only if intentionally testing a new schema-backed shape.';
                    }
                    $live = $this->bookingClient->createPassengerRecordBooking($conn, $envelope, array_merge([
                        'booking_id' => $booking->id,
                        'supplier_connection_id' => $conn->id,
                        'passenger_count' => $paxCount,
                        'segment_count' => $segCount,
                        'has_contact_email' => trim((string) ($contactArr['email'] ?? '')) !== '',
                        'has_contact_phone' => trim((string) ($contactArr['phone'] ?? '')) !== '',
                    ], $diag));
                    $liveForClassifier = $live;
                    $hs = $live['http_status'] ?? null;
                    $row['http_status'] = $hs === null ? 'null' : (string) $hs;
                    $row['error_message'] = substr(trim((string) ($live['safe_message'] ?? '')), 0, 240);
                    $row['pnr_present'] = trim((string) ($live['pnr'] ?? '')) !== '';
                    $ref = trim((string) ($live['provider_booking_id'] ?? ''));
                    $row['supplier_reference_present'] = $ref !== '';
                    $row['status'] = ($live['success'] ?? false) ? 'live_ok' : 'live_failed';
                    $bd = is_array($live['booking_diagnostics'] ?? null) ? $live['booking_diagnostics'] : [];
                    $flatDigest = self::flattenBookingDiagnostics($bd);
                    foreach ([
                        'response_error_codes', 'response_error_messages', 'response_error_fields',
                        'response_error_paths', 'response_missing_fields', 'response_top_level_keys', 'response_top_level_error_code',
                        'response_top_level_type', 'response_additional_messages',
                    ] as $ek) {
                        if (array_key_exists($ek, $flatDigest)) {
                            $row[$ek] = $flatDigest[$ek];
                        }
                    }
                    $row['agency_phone_error_cleared'] = $this->agencyPhoneMissingClearedFromLiveCompareDigest($live);
                    foreach ([
                        'wire_flight_offer_segment_count', 'wire_flight_details_segment_count', 'wire_traveler_count',
                        'wire_fare_basis_count', 'wire_booking_class_count', 'wire_has_validating_carrier',
                        'wire_has_amount', 'wire_has_currency',
                        'wire_gender_values_sanitized', 'wire_gender_enum_valid',
                        'wire_has_remarks', 'wire_remarks_count',
                        'wire_has_contact', 'wire_has_contactInfo', 'wire_contact_field_style', 'wire_has_contact_email', 'wire_has_contact_phone',
                        'wire_has_customer_contact_phone', 'wire_has_agency_phone', 'wire_agency_phone_field_style', 'wire_agency_phone_paths', 'wire_agency_phone_redacted', 'wire_agency_phone_ok', 'wire_has_POS', 'wire_has_pos', 'wire_has_agency_block', 'wire_has_travelAgency', 'wire_has_customerInfo', 'wire_pcc_present', 'wire_agency_config_phone_present', 'wire_agency_country_config_present', 'wire_phone_use_type_values_sanitized', 'wire_phone_location_values_sanitized',
                        'wire_traveler_field_style', 'wire_has_givenName', 'wire_has_given_name',
                        'wire_has_passengerCode', 'wire_has_passengerTypeCode',
                    ] as $wk) {
                        if (array_key_exists($wk, $diag)) {
                            $row[$wk] = $diag[$wk];
                        }
                    }
                    $row['payload_style'] = $style;
                    $row['error_code'] = $live['error_code'] ?? null;
                    if (isset($diag['inspect_warning_wire_root_incomplete']) && is_string($diag['inspect_warning_wire_root_incomplete'])) {
                        $row['inspect_warning_wire_root_incomplete'] = $diag['inspect_warning_wire_root_incomplete'];
                    }
                    $row['supplier_booking_attempt_id'] = $this->recordCompareCreatebookingStyleAttempt($booking, $conn, $style, $live, $diag, [
                        'pnr_present' => $row['pnr_present'],
                        'supplier_reference_present' => $row['supplier_reference_present'],
                    ]);
                    if ($blindWarning !== null) {
                        $row['blind_agency_phone_variant_warning'] = $blindWarning;
                    }
                } else {
                    $row['http_status'] = 'not_sent';
                    $row['error_message'] = 'booking_or_live_call_disabled';
                    $row['status'] = 'send_skipped';
                }
            }
            $row = array_merge($row, $this->agencyPhoneMissingClassifierForTripOrdersCompareRow($style, $diag, $liveForClassifier, $booking->id));
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * B38: Redacted traditional {@code CreatePassengerNameRecordRQ} wire preview (inspect only; no HTTP). **B79:** optional
     * {@code traditionalPayloadStyle} selects AirPrice validating-carrier compare wire for sandbox parity experiments (never live default).
     *
     * @return array<string, mixed>
     */
    public function previewRedactedTraditionalPnrForCommand(Booking $booking, ?string $traditionalPayloadStyle = null): array
    {
        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $p = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($p !== SupplierProvider::Sabre->value) {
            return [
                'booking_id' => $booking->id,
                'provider' => $p,
                'error' => 'booking_not_sabre',
            ];
        }
        $snapshot = [];
        if (is_array($meta['normalized_offer_snapshot'] ?? null)) {
            $snapshot = $meta['normalized_offer_snapshot'];
        } elseif (is_array($meta['validated_offer_snapshot'] ?? null)) {
            $snapshot = $meta['validated_offer_snapshot'];
        } elseif (is_array($meta['flight_offer_snapshot'] ?? null)) {
            $snapshot = $meta['flight_offer_snapshot'];
        }
        $snapshot = $this->mergePublicReviewSabreSnapshotFromBooking($booking, $snapshot);
        $gate = $this->validateNormalizedSabreOffer($snapshot);
        if (! $gate->success) {
            return [
                'booking_id' => $booking->id,
                'provider' => SupplierProvider::Sabre->value,
                'error' => 'validation_failed',
            ];
        }
        $draft = $this->prepareBookingPayload($snapshot, $this->passengerDataFromBooking($booking));
        if (($draft['_valid'] ?? false) !== true) {
            return [
                'booking_id' => $booking->id,
                'provider' => SupplierProvider::Sabre->value,
                'error' => 'draft_invalid',
            ];
        }
        $apiDraft = $draft;
        unset($apiDraft['_valid']);
        $hints = $this->ticketingHintsFromOffer($snapshot);
        $tpStyle = $traditionalPayloadStyle ?? SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1;
        if (! SabreBookingPayloadBuilder::isTraditionalPnrPassengerRecordsWireStyle($tpStyle)) {
            $tpStyle = SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1;
        }
        $raw = $tpStyle === SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1_AIRPRICE_VALIDATING_CARRIER_COMPARE_V1
            ? $this->bookingPayloadBuilder->buildTraditionalPnrCreatePassengerNameRecordV1AirpriceValidatingCarrierCompareWire($apiDraft, $hints)
            : $this->bookingPayloadBuilder->buildTraditionalPnrCreatePassengerNameRecordV1Wire($apiDraft, $hints);

        return array_merge([
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'ticketing_enabled' => (bool) config('suppliers.sabre.ticketing_enabled', false),
        ], $this->bookingPayloadBuilder->previewRedactedTraditionalPnrCreatePassengerNameRecordV1Wire($raw, $tpStyle));
    }

    /**
     * Inspect-only: compare OTA traditional CPNR wire key paths to a frozen IATI operational GDS template (no HTTP, no PII values).
     *
     * @return array<string, mixed>
     */
    public function inspectTraditionalCpnrIatiStructureDiffForCommand(Booking $booking): array
    {
        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $p = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($p !== SupplierProvider::Sabre->value) {
            return [
                'booking_id' => $booking->id,
                'provider' => $p,
                'error' => 'booking_not_sabre',
            ];
        }
        $snapshot = [];
        if (is_array($meta['normalized_offer_snapshot'] ?? null)) {
            $snapshot = $meta['normalized_offer_snapshot'];
        } elseif (is_array($meta['validated_offer_snapshot'] ?? null)) {
            $snapshot = $meta['validated_offer_snapshot'];
        } elseif (is_array($meta['flight_offer_snapshot'] ?? null)) {
            $snapshot = $meta['flight_offer_snapshot'];
        }
        $snapshot = $this->mergePublicReviewSabreSnapshotFromBooking($booking, $snapshot);
        $gate = $this->validateNormalizedSabreOffer($snapshot);
        if (! $gate->success) {
            return [
                'booking_id' => $booking->id,
                'provider' => SupplierProvider::Sabre->value,
                'error' => 'validation_failed',
            ];
        }
        $draft = $this->prepareBookingPayload($snapshot, $this->passengerDataFromBooking($booking));
        if (($draft['_valid'] ?? false) !== true) {
            return [
                'booking_id' => $booking->id,
                'provider' => SupplierProvider::Sabre->value,
                'error' => 'draft_invalid',
            ];
        }
        $apiDraft = $draft;
        unset($apiDraft['_valid']);
        $hints = $this->ticketingHintsFromOffer($snapshot);
        $wire = $this->bookingPayloadBuilder->buildTraditionalPnrCreatePassengerNameRecordV1Wire($apiDraft, $hints);
        $stripped = $this->bookingPayloadBuilder->stripOtaInternalKeysFromBookingWire($wire);
        $cpnr = is_array($stripped['CreatePassengerNameRecordRQ'] ?? null) ? $stripped['CreatePassengerNameRecordRQ'] : [];
        if ($cpnr === []) {
            return [
                'booking_id' => $booking->id,
                'provider' => SupplierProvider::Sabre->value,
                'error' => 'missing_create_passenger_name_record_rq',
            ];
        }

        $diag = SabreTraditionalCpnrIatiWireStructureDiagnostic::analyze($cpnr);
        $wireSummary = $this->bookingPayloadBuilder->summarizeTraditionalPnrWirePostBody($stripped, $meta);

        $connId = (int) ($meta['supplier_connection_id'] ?? 0);
        $connection = $connId > 0 ? SupplierConnection::query()->find($connId) : null;
        if ($connection !== null && $connection->provider !== SupplierProvider::Sabre) {
            $connection = null;
        }
        $routeSelection = $this->certifiedRouteSelector->selectForBooking($booking);
        $styleDecision = $this->decidePassengerRecordsPayloadStyle($snapshot, $apiDraft, $connection, $routeSelection);

        return array_merge([
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
        ], $diag, [
            'ota_wire_contract_summary' => $wireSummary,
            'passenger_records_style_decision' => $this->passengerRecordsStyleDecisionPublicSlice($styleDecision),
        ]);
    }

    /**
     * Inspect-only: Sprint 2B Passenger Records payload style selection (no HTTP).
     *
     * @return array<string, mixed>
     */
    public function inspectPassengerRecordsStyleSelectionForCommand(Booking $booking): array
    {
        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $p = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($p !== SupplierProvider::Sabre->value) {
            return [
                'booking_id' => $booking->id,
                'provider' => $p,
                'error' => 'booking_not_sabre',
            ];
        }
        $snapshot = [];
        if (is_array($meta['normalized_offer_snapshot'] ?? null)) {
            $snapshot = $meta['normalized_offer_snapshot'];
        } elseif (is_array($meta['validated_offer_snapshot'] ?? null)) {
            $snapshot = $meta['validated_offer_snapshot'];
        } elseif (is_array($meta['flight_offer_snapshot'] ?? null)) {
            $snapshot = $meta['flight_offer_snapshot'];
        }
        $snapshot = $this->mergePublicReviewSabreSnapshotFromBooking($booking, $snapshot);
        $gate = $this->validateNormalizedSabreOffer($snapshot);
        if (! $gate->success) {
            return [
                'booking_id' => $booking->id,
                'provider' => SupplierProvider::Sabre->value,
                'error' => 'validation_failed',
            ];
        }
        $draft = $this->prepareBookingPayload($snapshot, $this->passengerDataFromBooking($booking));
        if (($draft['_valid'] ?? false) !== true) {
            return [
                'booking_id' => $booking->id,
                'provider' => SupplierProvider::Sabre->value,
                'error' => 'draft_invalid',
            ];
        }
        $apiDraft = $draft;
        unset($apiDraft['_valid']);
        $connId = (int) ($meta['supplier_connection_id'] ?? $apiDraft['supplier_connection_id'] ?? 0);
        $connection = $connId > 0 ? SupplierConnection::query()->find($connId) : null;
        if ($connection !== null && $connection->provider !== SupplierProvider::Sabre) {
            $connection = null;
        }
        $routeSelection = $this->certifiedRouteSelector->selectForBooking($booking);
        $decision = $this->decidePassengerRecordsPayloadStyle($snapshot, $apiDraft, $connection, $routeSelection);
        $freshnessDecision = $this->decideSabreBookingFreshnessStrategy($snapshot, $apiDraft, $connection, $decision, $booking);

        return array_merge([
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'booking_schema' => $this->effectiveSabreBookingSchema(),
            'currently_selected_style' => $decision['selected_style'] ?? null,
            'currently_selected_endpoint' => $decision['selected_endpoint_path'] ?? null,
            'eligible_for_iati_like_cpnr' => $decision['iati_like_eligible'] ?? false,
            'not_eligible_reason' => $decision['iati_like_reason_code'] ?? ($decision['reason_code'] ?? null),
            'freshness_strategy_decision_json' => $freshnessDecision,
        ], $this->passengerRecordsStyleDecisionPublicSlice($decision), $this->freshnessStrategyDiagnosticSlice($freshnessDecision));
    }

    /**
     * B40/B41: Local-only capability snapshot from booking state + persisted {@code supplier_booking_attempts} (no live HTTP).
     *
     * @return array<string, mixed>
     */
    public function bookingCapabilityReportForCommand(Booking $booking): array
    {
        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $p = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($p !== SupplierProvider::Sabre->value) {
            return [
                'booking_not_sabre' => true,
                'search_available' => false,
                'trip_orders_endpoint_available' => false,
                'trip_orders_latest_error' => null,
                'trip_orders_likely_profile_level_agency_phone_issue' => false,
                'traditional_pnr_preview_valid' => false,
                'traditional_endpoint_entitlement' => [],
                'traditional_pnr_endpoints_forbidden' => false,
                'traditional_pnr_unknown_endpoint_count' => 0,
                'traditional_pnr_forbidden_endpoint_count' => 0,
                'ticketing_enabled' => $this->isTicketingEnabled(),
                'recommended_next_action' => 'N/A — booking is not on Sabre.',
            ];
        }

        $connId = (int) data_get($meta, 'supplier_connection_id', 0);
        $snap = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $segs = is_array($snap['segments'] ?? null) ? $snap['segments'] : [];
        $searchAvailable = $segs !== [];
        if (! $searchAvailable && $connId > 0) {
            $lastSearch = SupplierDiagnosticLog::query()
                ->where('supplier_connection_id', $connId)
                ->where('provider', SupplierProvider::Sabre->value)
                ->where('action', 'search')
                ->orderByDesc('id')
                ->first();
            if ($lastSearch !== null) {
                $searchAvailable = $lastSearch->status === 'success';
            }
        }

        $tripPathConfigured = str_contains((string) config('suppliers.sabre.booking_path', ''), '/v1/trip/orders/createBooking')
            || $this->effectiveSabreBookingSchema() === 'trip_orders_create_booking';

        $attempts = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::Sabre->value)
            ->orderByDesc('attempted_at')
            ->limit(120)
            ->get();

        $tripOrdersReachable = false;
        $latestTripSs = null;
        foreach ($attempts as $att) {
            if (! $this->attemptTouchesTripOrdersCreateBooking($att)) {
                continue;
            }
            $ss = is_array($att->safe_summary) ? $att->safe_summary : [];
            if ($latestTripSs === null) {
                $latestTripSs = $ss;
            }
            $http = (string) ($ss['http_status'] ?? '');
            if (in_array($http, ['200', '201', '422'], true)) {
                $tripOrdersReachable = true;
            }
        }

        $tripOrdersLatestError = null;
        if ($latestTripSs !== null && $this->safeSummaryHasAgencyPhoneMissingToken($latestTripSs)) {
            $tripOrdersLatestError = 'AGENCY_PHONE_MISSING';
        }

        $tripOrdersLikelyProfile = false;
        if ($latestTripSs !== null) {
            if (($latestTripSs['likely_profile_level_agency_phone_issue'] ?? false) === true) {
                $tripOrdersLikelyProfile = true;
            } elseif ($tripOrdersLatestError !== null) {
                $cfgPhone = trim((string) config('suppliers.sabre.agency_phone', '')) !== '';
                $tripOrdersLikelyProfile = (($latestTripSs['wire_has_agency_phone'] ?? false) === true)
                    || (($latestTripSs['agency_phone_config_present'] ?? false) === true)
                    || $cfgPhone;
            }
        }

        $traditionalPaths = [
            '/v2/passengers/create',
            '/v2/passenger/create',
            '/v2.5.0/passenger/records',
            '/v2.4.0/passenger/records',
            '/v2.5.0/passenger/records?mode=create',
            '/v2.4.0/passenger/records?mode=create',
            '/v2.3.0/passenger/records?mode=create',
        ];
        $unknownNotTested = 'unknown_not_tested_after_b40';
        $traditionalEntitlement = [];
        foreach ($traditionalPaths as $pathKey) {
            $traditionalEntitlement[$pathKey] = $unknownNotTested;
        }
        $seenPath = [];
        foreach ($attempts as $att) {
            if ($att->action !== 'compare_booking_endpoint') {
                continue;
            }
            $ss = is_array($att->safe_summary) ? $att->safe_summary : [];
            $ep = (string) ($ss['endpoint_path'] ?? '');
            if ($ep === '' || ! in_array($ep, $traditionalPaths, true)) {
                continue;
            }
            if (isset($seenPath[$ep])) {
                continue;
            }
            $seenPath[$ep] = true;
            $hs = (string) ($ss['http_status'] ?? '');
            if ($hs === '403') {
                $traditionalEntitlement[$ep] = 'forbidden';
            } elseif ($hs === '200' || $hs === '201' || $hs === '422') {
                $traditionalEntitlement[$ep] = 'reachable_http_'.$hs;
            } elseif ($hs !== '' && $hs !== 'not_sent' && $hs !== '0') {
                $traditionalEntitlement[$ep] = 'http_'.$hs;
            }
        }

        $twInspect = $this->previewTripOrdersWireJsonForInspectCommand(
            $booking,
            SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1,
        );
        $traditionalPreviewValid = $this->traditionalPnrWireInspectPreviewMatchesContract($twInspect);

        $traditionalPnrEndpointsForbidden = $this->traditionalPnrPassengerEndpointsAllForbiddenLatest($booking->id);
        $unknownCount = 0;
        $forbiddenCount = 0;
        foreach ($traditionalPaths as $pathKey) {
            $st = (string) ($traditionalEntitlement[$pathKey] ?? '');
            if ($st === $unknownNotTested) {
                $unknownCount++;
            }
            if ($st === 'forbidden') {
                $forbiddenCount++;
            }
        }

        $recommended = 'Fix Sabre agency/PCC phone profile or obtain exact Trip Orders agency phone contract; current credentials are not entitled for passenger record endpoints.';
        $modeCreateKey = '/v2.5.0/passenger/records?mode=create';
        $modeCreateEnt = (string) ($traditionalEntitlement[$modeCreateKey] ?? $unknownNotTested);
        $modeCreateNon403 = $modeCreateEnt !== $unknownNotTested && $modeCreateEnt !== 'forbidden';
        if ($modeCreateNon403) {
            $recommended = 'Passenger Records POST /v2.5.0/passenger/records?mode=create returned a non-403 HTTP outcome on compare; continue contract validation on cert/sandbox before production.';
        }
        if ($tripOrdersLatestError === 'AGENCY_PHONE_MISSING' && $traditionalPnrEndpointsForbidden) {
            $recommended = 'Trip Orders is blocked by AGENCY_PHONE_MISSING; traditional Passenger/PNR endpoints are forbidden for current credentials. Booking is blocked until Sabre PCC/profile phone or correct booking entitlement/path is fixed.';
            if ($modeCreateNon403) {
                $recommended = 'Trip Orders is blocked by AGENCY_PHONE_MISSING, but Passenger Records /v2.5.0/passenger/records?mode=create is reachable (non-403) for current credentials — prefer that Binham-style path for PNR creation while Trip Orders phone profile is fixed.';
            }
        }

        $report = [
            'search_available' => $searchAvailable,
            'trip_orders_endpoint_available' => $tripPathConfigured || $tripOrdersReachable,
            'trip_orders_latest_error' => $tripOrdersLatestError,
            'trip_orders_likely_profile_level_agency_phone_issue' => $tripOrdersLikelyProfile,
            'traditional_pnr_preview_valid' => $traditionalPreviewValid,
            'traditional_endpoint_entitlement' => $traditionalEntitlement,
            'traditional_pnr_endpoints_forbidden' => $traditionalPnrEndpointsForbidden,
            'traditional_pnr_unknown_endpoint_count' => $unknownCount,
            'traditional_pnr_forbidden_endpoint_count' => $forbiddenCount,
            'ticketing_enabled' => $this->isTicketingEnabled(),
            'recommended_next_action' => $recommended,
        ];

        $discoveryPath = storage_path('app/sabre-booking-endpoint-discovery.json');
        $expanded = $this->expandedEndpointDiscoverySummaryFromStoredReportPath($discoveryPath);
        if ($expanded !== null) {
            $report['expanded_endpoint_discovery_summary'] = $expanded;
        }

        return $report;
    }

    /**
     * B42: Ordered Sabre REST paths probed by {@code sabre:discover-booking-endpoints} (POST {@code {}} only).
     *
     * @return list<string>
     */
    public static function bookingEndpointDiscoveryProbePaths(): array
    {
        $raw = [
            '/v1/trip/orders/createBooking',
            '/v1/trip/orders',
            '/v1/trip/orders/create',
            '/v1/trip/orders/book',
            '/v1/bookings/create',
            '/v1/bookings',
            '/v2/bookings/create',
            '/v2/bookings',
            '/v2/passengers/create',
            '/v2/passenger/create',
            '/v2.5.0/passenger/records',
            '/v2.5.0/passenger/records?mode=create',
            '/v2.4.0/passenger/records?mode=create',
            '/v2.3.0/passenger/records?mode=create',
            '/v2.5.0/passenger/records?mode=update',
            '/v1.1.0/passenger/records?mode=update',
            '/v2.4.0/passenger/records',
            '/v2.3.0/passenger/records',
            '/v2.5.0/passenger/name/records',
            '/v2/passenger/name/records',
            '/v1/passenger/records',
            '/v1/passenger/name/records',
            '/v2.5.0/create/passenger/name/record',
            '/v2.5.0/create-passenger-name-record',
            '/v2/create-passenger-name-record',
            '/v1/create-passenger-name-record',
            '/v2.5.0/CreatePassengerNameRecord',
            '/v1/CreatePassengerNameRecord',
            '/v1/reservations/create',
            '/v1/reservations',
            '/v2/reservations/create',
            '/v2/reservations',
            '/v1/pnr/create',
            '/v1/pnr',
            '/v2/pnr/create',
            '/v2/pnr',
            '/v1/trip/orders/getBooking',
            '/v1/trip/orders/modifyBooking',
            '/v1/trip/orders/cancelBooking',
            '/v1/reservations/retrieve',
            '/v1/reservation',
        ];
        $seen = [];
        $out = [];
        foreach ($raw as $p) {
            $norm = $p !== '' && $p[0] === '/' ? $p : '/'.$p;
            if (isset($seen[$norm])) {
                continue;
            }
            $seen[$norm] = true;
            $out[] = $norm;
        }

        return $out;
    }

    /**
     * @return array{non_create_endpoint: bool, likely_create_endpoint: bool}
     */
    public static function discoveryEndpointFlags(string $path): array
    {
        $pathOnly = $path;
        $query = '';
        if (str_contains($path, '?')) {
            $parts = explode('?', $path, 2);
            $pathOnly = $parts[0];
            $query = $parts[1] ?? '';
        }
        $ql = strtolower($query);
        if (str_contains($ql, 'mode=update')) {
            return [
                'non_create_endpoint' => true,
                'likely_create_endpoint' => false,
            ];
        }

        $nonCreateExact = [
            '/v1/trip/orders/getBooking',
            '/v1/trip/orders/modifyBooking',
            '/v1/trip/orders/cancelBooking',
            '/v1/reservations/retrieve',
            '/v1/reservation',
        ];
        $collectionExact = [
            '/v1/trip/orders',
            '/v1/bookings',
            '/v2/bookings',
            '/v1/reservations',
            '/v2/reservations',
            '/v1/pnr',
            '/v2/pnr',
        ];
        $nonCreate = in_array($pathOnly, $nonCreateExact, true);
        $likelyCreate = ! $nonCreate && ! in_array($pathOnly, $collectionExact, true);

        return [
            'non_create_endpoint' => $nonCreate,
            'likely_create_endpoint' => $likelyCreate,
        ];
    }

    /**
     * B42: Map HTTP outcome + transport failure to discovery {@code access_result} labels.
     */
    public static function discoveryAccessResultForProbe(int $httpStatus, ?string $transportError): string
    {
        if ($transportError === 'timeout') {
            return 'timeout';
        }
        if ($transportError === 'network') {
            return 'network_error';
        }
        if ($httpStatus === 0) {
            return 'unknown';
        }
        if (in_array($httpStatus, [200, 201], true)) {
            return 'ready';
        }
        if (in_array($httpStatus, [400, 422], true)) {
            return 'reachable_validation_error';
        }
        if ($httpStatus === 403) {
            return 'forbidden';
        }
        if ($httpStatus === 404) {
            return 'not_found';
        }
        if ($httpStatus === 405) {
            return 'method_not_allowed';
        }

        return 'unknown';
    }

    public static function discoverySoapHintMessage(): string
    {
        return 'REST booking endpoints unavailable or blocked. Sabre PNR creation may require SOAP CreatePassengerNameRecord / PassengerDetails entitlement or PCC profile fix.';
    }

    /**
     * True when every PNR-family probe path is blocked (no {@code ready} / {@code reachable_validation_error}).
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public static function discoveryShouldEmitSoapHint(array $rows): bool
    {
        $family = [];
        foreach ($rows as $row) {
            $p = (string) ($row['endpoint_path'] ?? '');
            if ($p === '') {
                continue;
            }
            if (self::discoveryRowIsPnrFamilyPath($p)) {
                $family[] = (string) ($row['access_result'] ?? 'unknown');
            }
        }
        if ($family === []) {
            return false;
        }
        foreach ($family as $ar) {
            if ($ar === 'ready' || $ar === 'reachable_validation_error') {
                return false;
            }
        }

        return true;
    }

    protected static function discoveryRowIsPnrFamilyPath(string $path): bool
    {
        if (str_contains($path, '/v1/trip/orders/createBooking')) {
            return true;
        }
        if ($path === '/v1/trip/orders/create' || $path === '/v1/trip/orders/book') {
            return true;
        }
        if (str_contains($path, '/bookings/create')) {
            return true;
        }
        if (str_contains($path, '/passenger') || str_contains($path, 'PassengerNameRecord') || str_contains($path, 'create-passenger-name-record')) {
            return true;
        }
        if (str_contains($path, '/pnr')) {
            return true;
        }
        if (str_contains($path, '/reservations/create')) {
            return true;
        }
        if (str_contains($path, '/create/passenger/name/record')) {
            return true;
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public static function expandedEndpointDiscoverySummaryFromRows(array $rows): array
    {
        $total = count($rows);
        $ready = 0;
        $val = 0;
        $forbidden = 0;
        $nf = 0;
        $candidates = [];
        foreach ($rows as $row) {
            $ar = (string) ($row['access_result'] ?? '');
            if ($ar === 'ready') {
                $ready++;
            }
            if ($ar === 'reachable_validation_error') {
                $val++;
            }
            if ($ar === 'forbidden') {
                $forbidden++;
            }
            if ($ar === 'not_found') {
                $nf++;
            }
            $p = (string) ($row['endpoint_path'] ?? '');
            $likely = $p !== '' ? self::discoveryEndpointFlags($p)['likely_create_endpoint'] : false;
            if ($likely && ($ar === 'ready' || $ar === 'reachable_validation_error')) {
                if ($p !== '') {
                    $candidates[] = $p;
                }
            }
        }

        return [
            'total_tested' => $total,
            'ready_count' => $ready,
            'validation_error_count' => $val,
            'forbidden_count' => $forbidden,
            'not_found_count' => $nf,
            'possible_create_candidates' => array_values(array_unique($candidates)),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function expandedEndpointDiscoverySummaryFromStoredReportPath(string $absolutePath): ?array
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return null;
        }
        $raw = file_get_contents($absolutePath);
        if ($raw === false || trim($raw) === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return null;
        }
        if (isset($decoded['expanded_endpoint_discovery_summary']) && is_array($decoded['expanded_endpoint_discovery_summary'])) {
            $sum = $decoded['expanded_endpoint_discovery_summary'];
            if (isset($sum['total_tested']) && is_int($sum['total_tested'])) {
                return $sum;
            }
        }
        $endpoints = $decoded['endpoints'] ?? null;
        if (! is_array($endpoints) || $endpoints === []) {
            return null;
        }

        return self::expandedEndpointDiscoverySummaryFromRows($endpoints);
    }

    /**
     * B42: OAuth once, then sequential POST {@code {}} probes (no passenger data, no real booking payload).
     *
     * @return list<array<string, mixed>>
     */
    public function discoverBookingEndpointsProbeForConnection(SupplierConnection $connection): array
    {
        $token = $this->sabreClient->getAccessToken($connection);
        $base = rtrim((string) ($connection->base_url ?: config('suppliers.sabre.default_base_url')), '/');
        $timeouts = $this->sabreClient->httpTimeoutSettings();
        $timeout = $timeouts['timeout_seconds'];
        $connectTimeout = $timeouts['connect_timeout_seconds'];
        $rows = [];
        foreach (self::bookingEndpointDiscoveryProbePaths() as $path) {
            $url = $base.$path;
            $httpStatus = 0;
            $transport = null;
            $arr = [];
            try {
                $response = Http::withToken($token)
                    ->acceptJson()
                    ->timeout($timeout)
                    ->connectTimeout($connectTimeout)
                    ->withBody('{}', 'application/json')
                    ->post($url);
                $httpStatus = $response->status();
                $json = $response->json();
                $arr = is_array($json) ? $json : [];
            } catch (ConnectionException $e) {
                $transport = self::discoveryTransportLabelFromConnectionException($e);
            } catch (Throwable) {
                $transport = 'network';
            }
            $access = self::discoveryAccessResultForProbe($httpStatus, $transport);
            $flags = self::discoveryEndpointFlags($path);
            $digest = ($httpStatus > 0 && ! in_array($httpStatus, [200, 201], true))
                ? $this->bookingClient->digestBookingResponseJsonForProbe($arr)
                : [];
            $codes = isset($digest['response_error_codes']) && is_array($digest['response_error_codes'])
                ? $digest['response_error_codes'] : [];
            $topCode = isset($digest['response_top_level_error_code']) && is_string($digest['response_top_level_error_code'])
                ? $digest['response_top_level_error_code'] : '';
            $safeCode = '';
            if ($topCode !== '') {
                $safeCode = substr($topCode, 0, 120);
            } elseif (isset($codes[0]) && is_string($codes[0]) && $codes[0] !== '') {
                $safeCode = substr($codes[0], 0, 120);
            }
            $messages = isset($digest['response_error_messages']) && is_array($digest['response_error_messages'])
                ? $digest['response_error_messages'] : [];
            $topMsg = isset($digest['response_top_level_message']) && is_string($digest['response_top_level_message'])
                ? $digest['response_top_level_message'] : '';
            $safeMsg = '';
            if ($topMsg !== '') {
                $safeMsg = substr($topMsg, 0, 200);
            } elseif (isset($messages[0]) && is_string($messages[0]) && $messages[0] !== '') {
                $safeMsg = substr($messages[0], 0, 200);
            }
            $row = [
                'endpoint_path' => $path,
                'method' => 'POST',
                'http_status' => $httpStatus,
                'access_result' => $access,
                'likely_create_endpoint' => $flags['likely_create_endpoint'],
                'non_create_endpoint' => $flags['non_create_endpoint'],
                'entitlement_hint' => self::discoveryEntitlementHint($access, $httpStatus, $safeCode),
                'safe_error_code' => $safeCode,
                'safe_error_message_truncated' => $safeMsg,
            ];
            $rows[] = SensitiveDataRedactor::redact($row);
        }

        return $rows;
    }

    protected static function discoveryTransportLabelFromConnectionException(ConnectionException $e): string
    {
        $m = strtolower($e->getMessage());
        if (str_contains($m, 'timed out') || str_contains($m, 'timeout') || str_contains($m, 'curl error 28')) {
            return 'timeout';
        }

        return 'network';
    }

    protected static function discoveryEntitlementHint(string $accessResult, int $httpStatus, string $safeErrorCode): string
    {
        return match ($accessResult) {
            'forbidden' => 'http_403_entitlement_or_access_denied',
            'not_found' => 'http_404_path_not_recognized',
            'method_not_allowed' => 'http_405_wrong_verb_or_surface',
            'ready' => 'http_2xx_probe_ack_endpoint_live',
            'reachable_validation_error' => $safeErrorCode !== ''
                ? 'http_4xx_contract_reachable;code='.substr($safeErrorCode, 0, 40)
                : 'http_4xx_contract_reachable',
            'timeout' => 'transport_timeout',
            'network_error' => 'transport_connection_failed',
            default => $httpStatus > 0 ? 'http_status_unclassified' : 'http_status_zero_or_transport',
        };
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function buildBookingEndpointDiscoveryReportPayload(SupplierConnection $connection, array $rows): array
    {
        $summary = self::expandedEndpointDiscoverySummaryFromRows($rows);
        $payload = [
            'generated_at' => now()->toIso8601String(),
            'connection_id' => $connection->id,
            'probe_http_body' => '{}',
            'ticketing_enabled_config' => (bool) config('suppliers.sabre.ticketing_enabled', false),
            'endpoints' => $rows,
            'expanded_endpoint_discovery_summary' => $summary,
        ];
        if (self::discoveryShouldEmitSoapHint($rows)) {
            $payload['sabre_rest_probe_soap_hint'] = self::discoverySoapHintMessage();
        }

        return SensitiveDataRedactor::redact($payload);
    }

    /**
     * B41: True when {@see self::previewTripOrdersWireJsonForInspectCommand()} traditional branch matches
     * {@code sabre:inspect-booking-payload --wire-preview-json --style=traditional_pnr_create_passenger_name_record_v1} contract (no HTTP).
     *
     * @param  array<string, mixed>  $preview
     */
    protected function traditionalPnrWireInspectPreviewMatchesContract(array $preview): bool
    {
        if (isset($preview['error'])) {
            return false;
        }
        foreach ([
            'wire_has_create_passenger_name_record_rq',
            'wire_has_travel_itinerary_add_info',
            'wire_has_customer_info',
            'wire_has_person_name',
            'wire_has_contact_numbers',
            'wire_has_air_book',
            'wire_has_flight_segment',
            'wire_has_post_processing',
            'wire_has_halt_on_air_price_error',
            'wire_has_air_price',
            'wire_has_root_air_price',
            'wire_has_received_from',
            'wire_has_email',
        ] as $k) {
            if (($preview[$k] ?? false) !== true) {
                return false;
            }
        }
        if (($preview['wire_post_processing_has_end_transaction_rq'] ?? true) !== false) {
            return false;
        }
        if (($preview['wire_post_processing_has_end_transaction'] ?? false) !== true) {
            return false;
        }
        if (($preview['wire_post_processing_has_redisplay_reservation'] ?? false) !== true) {
            return false;
        }
        if (($preview['wire_root_air_price_type'] ?? '') !== 'array') {
            return false;
        }
        if (($preview['wire_root_air_price_retain_present'] ?? false) !== true) {
            return false;
        }
        foreach ([
            'wire_airbook_has_air_price',
            'wire_airbook_has_price_quote_information',
            'wire_airbook_has_fare_breakdown_summary',
        ] as $k) {
            if (($preview[$k] ?? true) !== false) {
                return false;
            }
        }
        if (($preview['wire_has_halt_on_air_book_error'] ?? true) !== false) {
            return false;
        }
        if (($preview['wire_ticketing_enabled'] ?? true) !== false) {
            return false;
        }
        foreach ([
            'wire_flight_segment_has_cabin_code',
            'wire_flight_segment_has_class_of_service',
            'wire_flight_segment_has_fare_basis_code',
            'wire_flight_segment_has_number',
        ] as $k) {
            if (($preview[$k] ?? true) !== false) {
                return false;
            }
        }
        if (($preview['wire_flight_segment_has_res_book_desig_code'] ?? false) !== true) {
            return false;
        }
        if (($preview['wire_flight_segment_number_in_party_valid'] ?? false) !== true) {
            return false;
        }
        if (($preview['wire_remarks_count'] ?? 0) > 0) {
            if (($preview['wire_remark_type_enum_valid'] ?? false) !== true) {
                return false;
            }
        }
        if (($preview['wire_special_service_has_service'] ?? false) !== false) {
            return false;
        }
        if (($preview['wire_agency_info_has_telephone'] ?? false) !== false) {
            return false;
        }
        if (($preview['wire_customer_person_name_array_valid'] ?? false) !== true) {
            return false;
        }
        if (($preview['wire_customer_email_type_valid'] ?? false) !== true) {
            return false;
        }
        if (($preview['wire_air_price_passenger_type_contract_valid'] ?? false) !== true) {
            return false;
        }
        if (($preview['wire_traditional_pnr_contract_valid'] ?? false) !== true) {
            return false;
        }

        $airbookRetryRedisplay = (bool) config('suppliers.sabre.traditional_cpnr_airbook_retry_redisplay', false);
        if ($airbookRetryRedisplay) {
            foreach ([
                'wire_airbook_retry_redisplay_enabled',
                'wire_airbook_has_retry_rebook',
                'wire_airbook_has_redisplay_reservation',
                'wire_airbook_retry_redisplay_numeric_contract_valid',
                'wire_airbook_retry_rebook_contract_valid',
            ] as $k) {
                if (($preview[$k] ?? false) !== true) {
                    return false;
                }
            }
        } else {
            if (($preview['wire_airbook_retry_redisplay_enabled'] ?? true) !== false) {
                return false;
            }
            foreach ([
                'wire_airbook_has_retry_rebook',
                'wire_airbook_has_redisplay_reservation',
                'wire_airbook_retry_rebook_num_attempts_present',
                'wire_airbook_retry_rebook_wait_interval_present',
                'wire_airbook_redisplay_num_attempts_present',
                'wire_airbook_redisplay_wait_interval_present',
            ] as $k) {
                if (($preview[$k] ?? true) !== false) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $ss  Attempt {@code safe_summary} slice
     */
    protected function safeSummaryHasAgencyPhoneMissingToken(array $ss): bool
    {
        if (($ss['agency_phone_error'] ?? false) === true) {
            return true;
        }
        foreach (['response_error_messages', 'response_error_codes', 'response_additional_messages'] as $k) {
            foreach ((array) ($ss[$k] ?? []) as $m) {
                if (is_string($m) && stripos($m, 'AGENCY_PHONE_MISSING') !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function attemptTouchesTripOrdersCreateBooking(SupplierBookingAttempt $att): bool
    {
        if ($att->action === 'compare_trip_orders_createbooking_style') {
            return true;
        }
        if ($att->action === 'create_pnr') {
            $ss = is_array($att->safe_summary) ? $att->safe_summary : [];
            $ep = (string) ($ss['endpoint_path'] ?? '');
            if (str_contains($ep, 'trip/orders/createBooking')) {
                return true;
            }
            if (($ss['booking_schema'] ?? '') === 'trip_orders_create_booking') {
                return true;
            }
        }

        return false;
    }

    protected function traditionalPnrPassengerEndpointsAllForbiddenLatest(int $bookingId): bool
    {
        $paths = [
            '/v2/passengers/create',
            '/v2/passenger/create',
            '/v2.5.0/passenger/records',
            '/v2.4.0/passenger/records',
        ];
        $attempts = SupplierBookingAttempt::query()
            ->where('booking_id', $bookingId)
            ->where('provider', SupplierProvider::Sabre->value)
            ->where('action', 'compare_booking_endpoint')
            ->orderByDesc('attempted_at')
            ->limit(120)
            ->get();
        $latestByPath = [];
        foreach ($attempts as $att) {
            $ss = is_array($att->safe_summary) ? $att->safe_summary : [];
            $ep = (string) ($ss['endpoint_path'] ?? '');
            if ($ep === '' || ! in_array($ep, $paths, true)) {
                continue;
            }
            if (! array_key_exists($ep, $latestByPath)) {
                $latestByPath[$ep] = (string) ($ss['http_status'] ?? '');
            }
        }
        foreach ($paths as $p) {
            if (! isset($latestByPath[$p]) || $latestByPath[$p] !== '403') {
                return false;
            }
        }

        return true;
    }

    public function countAgencyPhoneBodyVariantFailuresForBooking(Booking $booking): int
    {
        $variants = SabreBookingPayloadBuilder::AGENCY_PHONE_BODY_VARIANT_COMPARE_STYLES;
        $attempts = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('action', 'compare_trip_orders_createbooking_style')
            ->orderByDesc('attempted_at')
            ->limit(200)
            ->get();
        $n = 0;
        foreach ($attempts as $a) {
            $ss = is_array($a->safe_summary) ? $a->safe_summary : [];
            $style = (string) ($ss['payload_style'] ?? '');
            if (! in_array($style, $variants, true)) {
                continue;
            }
            if (($ss['agency_phone_error'] ?? false) === true) {
                $n++;

                continue;
            }
            foreach ((array) ($ss['response_error_messages'] ?? []) as $m) {
                if (is_string($m) && stripos($m, 'AGENCY_PHONE_MISSING') !== false) {
                    $n++;
                    break;
                }
            }
        }

        return $n;
    }

    /**
     * P5: Safe audit for mixed/interline alternative booking paths (no HTTP, no raw payloads).
     *
     * @return array<string, mixed>
     */
    public function alternativeBookingPathAuditForCommand(Booking $booking): array
    {
        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown', 'agency']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $cert = app(SabrePnrCertificationSupport::class);
        $readiness = $cert->buildReadiness($booking);
        $pricing = $this->assessAutoPnrPricingContextReadinessForBooking($booking);
        $capability = $this->bookingCapabilityReportForCommand($booking);
        $configuredPath = (string) config('suppliers.sabre.booking_path', '');
        $effectiveSchema = $this->effectiveSabreBookingSchema();
        $tripOrdersConfigured = str_contains($configuredPath, '/v1/trip/orders/createBooking')
            || $effectiveSchema === 'trip_orders_create_booking';
        $agencyPhone = trim((string) config('suppliers.sabre.agency_phone', ''));
        $iati = $this->inspectTraditionalCpnrIatiStructureDiffForCommand($booking);

        return [
            'booking_id' => $booking->id,
            'configured_booking_path' => $configuredPath,
            'effective_booking_schema' => $effectiveSchema,
            'trip_orders_configured' => $tripOrdersConfigured,
            'why_trip_orders_create_booking_not_default' => $tripOrdersConfigured
                ? 'SABRE_BOOKING_PATH already targets Trip Orders createBooking.'
                : 'Production/certification default uses SABRE_BOOKING_PATH='.($configuredPath !== '' ? $configuredPath : '(empty)')
                .' (Passenger Records / traditional CPNR), not /v1/trip/orders/createBooking.',
            'trip_orders_latest_error' => $capability['trip_orders_latest_error'] ?? null,
            'trip_orders_likely_profile_level_agency_phone_issue' => ($capability['trip_orders_likely_profile_level_agency_phone_issue'] ?? false) === true,
            'agency_phone_config_present' => $agencyPhone !== '',
            'agency_phone_fields_for_trip_orders' => [
                'SABRE_AGENCY_PHONE',
                'SABRE_AGENCY_PHONE_COUNTRY_CODE',
                'SABRE_AGENCY_PHONE_TYPE',
                'SABRE_AGENCY_PHONE_LOCATION',
                'SABRE_AGENCY_POS_PHONE_USE_TYPE',
                'SABRE_AGENCY_NAME',
                'SABRE_AGENCY_CITY',
                'SABRE_AGENCY_COUNTRY',
            ],
            'agency_profile_note' => 'AGENCY_PHONE_MISSING often reflects Sabre PCC/TJR office profile phone, not only JSON body fields.',
            'readiness' => $readiness,
            'pricing_context_ready' => ($pricing['auto_pnr_pricing_context_ready'] ?? false) === true,
            'missing_pricing_context_fields' => is_array($pricing['missing_pricing_context_fields'] ?? null)
                ? array_values($pricing['missing_pricing_context_fields'])
                : [],
            'offer_refresh_acceptance_required' => SabreOfferRefreshAcceptance::requiresAcceptance($booking),
            'passengers_create_vs_passenger_records' => '/v2/passengers/create uses the same traditional CPNR wire as Passenger Records; v2.5 mode=create is the Binham/IATI REST path with ApplicationResults.',
            'iati_cpnr_structure_diff' => [
                'paths_only_in_iati_template_count' => (int) ($iati['paths_only_in_iati_template_count'] ?? 0),
                'paths_only_in_ota_wire_count' => (int) ($iati['paths_only_in_ota_wire_count'] ?? 0),
                'ota_wire_contract_valid' => (bool) data_get($iati, 'ota_wire_contract_summary.wire_traditional_pnr_contract_valid', false),
            ],
            'path_candidates' => array_map(
                static fn (array $pair): array => ['endpoint_path' => $pair[0], 'payload_style' => $pair[1]],
                $this->bookingEndpointCompareMatrixPairs(false, 'p5'),
            ),
            'traditional_endpoint_entitlement' => is_array($capability['traditional_endpoint_entitlement'] ?? null)
                ? $capability['traditional_endpoint_entitlement']
                : [],
            'recommended_next_action' => (string) ($capability['recommended_next_action'] ?? ''),
        ];
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    protected function bookingEndpointCompareMatrixPairs(bool $skipTripOrders, ?string $matrixProfile): array
    {
        if ($matrixProfile === 'p5') {
            $pairs = [];
            if (! $skipTripOrders) {
                foreach (SabreBookingPayloadBuilder::BOOKING_ENDPOINT_COMPARE_TRIP_ORDERS_P5_STYLES as $st) {
                    $pairs[] = ['/v1/trip/orders/createBooking', $st];
                }
            }
            $pairs[] = [
                '/v2/passengers/create',
                SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1,
            ];
            foreach (SabreBookingPayloadBuilder::BOOKING_ENDPOINT_COMPARE_PASSENGER_RECORDS_P4_STYLES as $st) {
                $pairs[] = ['/v2.5.0/passenger/records?mode=create', $st];
            }

            return $pairs;
        }

        /** @var list<array{0: string, 1: string}> $pairs */
        $tradEndpoints = [
            '/v2/passengers/create',
            '/v2/passenger/create',
            '/v2.5.0/passenger/records',
            '/v2.4.0/passenger/records',
            '/v2.5.0/passenger/records?mode=create',
            '/v2.4.0/passenger/records?mode=create',
            '/v2.3.0/passenger/records?mode=create',
        ];
        $p4PassengerRecordsEndpoints = [
            '/v2.5.0/passenger/records?mode=create',
            '/v2.4.0/passenger/records?mode=create',
        ];
        $pairs = [];
        foreach ($tradEndpoints as $pth) {
            $styles = in_array($pth, $p4PassengerRecordsEndpoints, true)
                ? SabreBookingPayloadBuilder::BOOKING_ENDPOINT_COMPARE_PASSENGER_RECORDS_P4_STYLES
                : [SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1];
            foreach ($styles as $st) {
                $pairs[] = [$pth, $st];
            }
        }
        if (! $skipTripOrders) {
            $pairs[] = ['/v1/trip/orders/createBooking', SabreBookingPayloadBuilder::BOOKING_ENDPOINT_COMPARE_TRIP_ORDERS_STYLE];
        }

        return $pairs;
    }

    /**
     * B38: Matrix of booking endpoint × payload style (inspect-only by default; optional single {@code --send}).
     * B44/B45: Passenger Records HTTP 400 merges {@code ApplicationResults} + REST top-level error fields into probe digest; {@code --send} rows + {@code safe_summary} include {@code response_top_level_*}, {@code response_timestamp_present}, {@code request_body_non_empty} (no raw bodies).
     *
     * @return list<array<string, mixed>>
     */
    public function compareBookingEndpointsForCommand(
        Booking $booking,
        bool $send,
        bool $skipTripOrders,
        ?string $endpointOpt,
        ?string $styleOpt,
        ?string $matrixProfile = null,
        bool $revalidateBeforeSend = false,
    ): array {
        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $p = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($p !== SupplierProvider::Sabre->value) {
            return [['endpoint_path' => 'n/a', 'payload_style' => 'n/a', 'status' => 'booking_not_sabre', 'http_status' => 'not_sent', 'access_result' => 'inspect_only']];
        }
        if ($send && ($endpointOpt === null || trim($endpointOpt) === '' || $styleOpt === null || trim($styleOpt) === '')) {
            return [['endpoint_path' => 'n/a', 'payload_style' => 'n/a', 'status' => 'send_requires_endpoint_and_style', 'http_status' => 'not_sent', 'access_result' => 'inspect_only', 'error_messages' => ['pass --endpoint=/path and --style=name with --send']]];
        }
        $snapshot = SabreOfferRefreshAcceptance::authoritativeOfferSnapshot($meta);
        $snapshot = $this->mergePublicReviewSabreSnapshotFromBooking($booking, $snapshot);
        $gate = $this->validateNormalizedSabreOffer($snapshot);
        if (! $gate->success) {
            return [['endpoint_path' => 'n/a', 'payload_style' => 'n/a', 'status' => 'validation_failed', 'http_status' => 'not_sent', 'access_result' => 'inspect_only']];
        }
        $draft = $this->prepareBookingPayload($snapshot, $this->passengerDataFromBooking($booking));
        if (($draft['_valid'] ?? false) !== true) {
            return [['endpoint_path' => 'n/a', 'payload_style' => 'n/a', 'status' => 'draft_invalid', 'http_status' => 'not_sent', 'access_result' => 'inspect_only']];
        }
        $apiDraft = $draft;
        unset($apiDraft['_valid']);
        $hints = $this->ticketingHintsFromOffer($snapshot);
        $paxCount = count(is_array($apiDraft['passengers'] ?? null) ? $apiDraft['passengers'] : []);
        $segCount = count(is_array($apiDraft['segments'] ?? null) ? $apiDraft['segments'] : []);
        $contactArr = is_array($apiDraft['contact'] ?? null) ? $apiDraft['contact'] : [];

        $pairs = $this->bookingEndpointCompareMatrixPairs($skipTripOrders, $matrixProfile);

        $normalizePath = static function (string $path): string {
            $path = trim($path);
            if ($path === '') {
                return '';
            }

            return $path[0] === '/' ? $path : '/'.$path;
        };

        if ($send) {
            $ep = $normalizePath(trim((string) $endpointOpt));
            $st = trim((string) $styleOpt);
            $allowed = false;
            foreach ($pairs as [$pth, $pst]) {
                if ($pth === $ep && $pst === $st) {
                    $allowed = true;
                    break;
                }
            }
            if (! $allowed) {
                return [[
                    'endpoint_path' => $ep,
                    'payload_style' => $st,
                    'status' => 'invalid_send_combo',
                    'http_status' => 'not_sent',
                    'access_result' => 'inspect_only',
                    'error_messages' => ['endpoint and style must match an allowed matrix pair'],
                ]];
            }
            if (! $this->mayPerformLiveSabreBookingCall()) {
                return [[
                    'endpoint_path' => $ep,
                    'payload_style' => $st,
                    'status' => 'send_skipped',
                    'http_status' => 'not_sent',
                    'access_result' => 'inspect_only',
                    'error_messages' => ['sabre_booking_or_live_call_disabled'],
                ]];
            }
            $cid = (int) data_get($booking->meta, 'supplier_connection_id', 0);
            $conn = $cid > 0 ? SupplierConnection::query()->find($cid) : null;
            if ($conn === null || $conn->provider !== SupplierProvider::Sabre) {
                return [[
                    'endpoint_path' => $ep,
                    'payload_style' => $st,
                    'status' => 'send_skipped',
                    'http_status' => 'not_sent',
                    'access_result' => 'inspect_only',
                    'error_messages' => ['no_sabre_supplier_connection'],
                ]];
            }
            $revalidateAttempted = false;
            $revalidateSuccess = false;
            if ($revalidateBeforeSend && str_contains($ep, 'trip/orders')) {
                $revalidateAttempted = true;
                $revalidateContext = $this->runCertificationRevalidateFirst($booking);
                $revalidateSuccess = ($revalidateContext['success'] ?? false) === true;
                $linkage = is_array($revalidateContext['linkage'] ?? null) ? $revalidateContext['linkage'] : [];
                if ($linkage !== []) {
                    $apiDraft['_fare_linkage'] = $linkage;
                }
            }
            $wire = $this->buildWireForBookingEndpointCompare($apiDraft, $hints, $st);
            if (SabreBookingPayloadBuilder::isTraditionalPnrPassengerRecordsWireStyle($st)) {
                $tDiag = $this->bookingPayloadBuilder->summarizeTraditionalPnrWirePostBody($wire, $meta, $st);
                if (($tDiag['wire_traditional_pnr_contract_valid'] ?? false) !== true) {
                    return [[
                        'endpoint_path' => $ep,
                        'payload_style' => $st,
                        'status' => 'payload_validation_failed',
                        'http_status' => 'not_sent',
                        'access_result' => 'inspect_only',
                        'error_messages' => ['traditional_pnr_wire_validation_failed'],
                        'wire_invalid_traditional_pnr_contract_keys' => is_array($tDiag['wire_invalid_traditional_pnr_contract_keys'] ?? null)
                            ? $tDiag['wire_invalid_traditional_pnr_contract_keys']
                            : [],
                    ]];
                }
            }
            if (SabreBookingPayloadBuilder::isTripOrdersCreatebookingCompareStyle($st)) {
                $diag = $this->bookingPayloadBuilder->summarizeEnvelopeForDiagnostics(
                    $this->bookingPayloadBuilder->buildTripOrdersCreateBookingEnvelope($apiDraft, $hints, $st)
                );
                if (($diag['wire_agency_phone_ok'] ?? true) === false
                    || (($diag['wire_traveler_required_fields_valid'] ?? true) === false)
                    || (($diag['wire_payload_null_free'] ?? true) === false)
                    || (($diag['wire_contract_valid'] ?? true) === false)
                    || (($diag['wire_segment_required_fields_valid'] ?? true) === false)) {
                    return [[
                        'endpoint_path' => $ep,
                        'payload_style' => $st,
                        'status' => 'payload_validation_failed',
                        'http_status' => 'not_sent',
                        'access_result' => 'inspect_only',
                        'error_messages' => ['trip_orders_payload_validation_failed'],
                        'revalidate_first_attempted' => $revalidateAttempted,
                        'revalidate_first_success' => $revalidateSuccess,
                    ]];
                }
            }
            $httpStatus = 0;
            $json = [];
            $extraHeaders = [];
            if (stripos($ep, 'passenger/records') !== false) {
                $extraHeaders['Conversation-ID'] = 'ota-'.$booking->id.'-'.time();
            }
            try {
                $response = $this->sabreClient->postAuthenticatedJson($conn, $ep, $wire, $extraHeaders);
                $httpStatus = $response->status();
                $json = $response->json();
            } catch (ConnectionException) {
                $httpStatus = 0;
                $json = [];
            }
            $arr = is_array($json) ? $json : [];
            $digest = $arr !== [] ? $this->bookingClient->digestBookingResponseJsonForProbe($arr) : [];
            $msgs = array_slice(array_map('strval', (array) ($digest['response_error_messages'] ?? [])), 0, 24);
            $codesOut = array_slice(array_map('strval', (array) ($digest['response_error_codes'] ?? [])), 0, 24);
            $fieldsOut = array_slice(array_map('strval', (array) ($digest['response_error_fields'] ?? [])), 0, 24);
            $pathsOut = array_slice(array_map('strval', (array) ($digest['response_error_paths'] ?? [])), 0, 32);
            $topKeys = array_slice(array_map('strval', (array) ($digest['response_top_level_keys'] ?? [])), 0, 48);
            $pnr = $this->bookingClient->extractPnrLocatorFromBookingJson($arr);
            $orderRef = $this->bookingClient->extractSupplierOrderReferenceFromBookingJson($arr);
            $access = SabreCheckBookingEndpointsCommand::accessResultForStatus($httpStatus);
            $reqBodyNonEmpty = $wire !== [];
            $rootKeys = array_values(array_filter(array_keys($wire), static fn ($k) => is_string($k) && $k !== ''));
            $tDiag = SabreBookingPayloadBuilder::isTraditionalPnrPassengerRecordsWireStyle($st)
                ? $this->bookingPayloadBuilder->summarizeTraditionalPnrWirePostBody($wire, $meta, $st)
                : [];
            $tripSlice = SabreBookingPayloadBuilder::isTripOrdersCreatebookingCompareStyle($st)
                ? $this->compareBookingEndpointTripOrdersWireSlice($apiDraft, $hints, $st)
                : [];
            $tripEnvelopeDiag = SabreBookingPayloadBuilder::isTripOrdersCreatebookingCompareStyle($st)
                ? $this->bookingPayloadBuilder->summarizeEnvelopeForDiagnostics(
                    $this->bookingPayloadBuilder->buildTripOrdersCreateBookingEnvelope($apiDraft, $hints, $st)
                )
                : [];
            $pnrCreated = $pnr !== '';
            $expiryIso = null;
            $expirySource = null;
            if ($pnrCreated) {
                $expiryParsed = app(SabrePnrCertificationSupport::class)->extractExpiryFromCreateResult(
                    array_merge($digest, ['pnr' => $pnr])
                );
                $expiryIso = $expiryParsed['iso'] ?? null;
                $expirySource = $expiryParsed['source'] ?? null;
            }
            $row = array_merge([
                'endpoint_path' => $ep,
                'payload_style' => $st,
                'revalidate_first_attempted' => $revalidateAttempted,
                'revalidate_first_success' => $revalidateSuccess,
                'supplier_pnr_expires_at' => $expiryIso,
                'supplier_pnr_expiry_source' => $expirySource,
                'status' => 'live_attempted',
                'http_status' => (string) $httpStatus,
                'available' => $httpStatus > 0,
                'access_result' => $access,
                'error_messages' => $msgs,
                'response_error_codes' => $codesOut,
                'response_error_messages' => $msgs,
                'response_error_fields' => $fieldsOut,
                'response_error_paths' => $pathsOut,
                'response_top_level_keys' => $topKeys,
                'response_top_level_error_code' => isset($digest['response_top_level_error_code']) && is_string($digest['response_top_level_error_code'])
                    ? $digest['response_top_level_error_code']
                    : null,
                'response_top_level_message' => isset($digest['response_top_level_message']) && is_string($digest['response_top_level_message'])
                    ? $digest['response_top_level_message']
                    : null,
                'response_top_level_status' => isset($digest['response_top_level_status']) && is_string($digest['response_top_level_status'])
                    ? $digest['response_top_level_status']
                    : null,
                'response_top_level_type' => isset($digest['response_top_level_type']) && is_string($digest['response_top_level_type'])
                    ? $digest['response_top_level_type']
                    : null,
                'response_timestamp_present' => (bool) ($digest['response_timestamp_present'] ?? false),
                'application_results_status' => isset($digest['application_results_status']) && is_string($digest['application_results_status'])
                    ? $digest['application_results_status']
                    : null,
                'application_results_incomplete' => (bool) ($digest['application_results_incomplete'] ?? false),
                'host_warning_modules' => array_slice(array_map('strval', (array) ($digest['host_warning_modules'] ?? [])), 0, 16),
                'host_warning_sabre_codes' => array_slice(array_map('strval', (array) ($digest['host_warning_sabre_codes'] ?? [])), 0, 16),
                'host_warning_messages_truncated' => array_slice(array_map('strval', (array) ($digest['host_warning_messages_truncated'] ?? [])), 0, 16),
                'passenger_records_error_digest_present' => (bool) ($digest['passenger_records_error_digest_present'] ?? false),
                'request_body_non_empty' => $reqBodyNonEmpty,
                'request_body_root_keys' => array_slice($rootKeys, 0, 16),
                'wire_has_create_passenger_name_record_rq' => (bool) ($tDiag['wire_has_create_passenger_name_record_rq'] ?? false),
                'wire_contract_valid' => SabreBookingPayloadBuilder::isTripOrdersCreatebookingCompareStyle($st)
                    ? (bool) ($tripSlice['wire_contract_valid'] ?? false)
                    : (bool) ($tDiag['wire_traditional_pnr_contract_valid'] ?? false),
                'wire_traditional_pnr_contract_valid' => (bool) ($tDiag['wire_traditional_pnr_contract_valid'] ?? false),
                'pnr_created' => $pnrCreated,
                'pnr' => $pnrCreated ? $pnr : null,
                'p4_candidate_for_production' => $pnrCreated,
                'wire_segment_count' => (int) ($tDiag['wire_segment_count'] ?? $segCount),
                'wire_passenger_count' => (int) ($tDiag['wire_passenger_count'] ?? $paxCount),
                'pnr_present' => $pnr !== '',
                'supplier_reference_present' => trim($orderRef) !== '',
                'ticketing_disabled' => true,
                'passenger_count' => $paxCount,
                'segment_count' => $segCount,
                'wire_iati_paths_only_in_iati_template_count' => (int) ($tDiag['wire_iati_paths_only_in_iati_template_count'] ?? 0),
                'wire_iati_paths_only_in_ota_wire_count' => (int) ($tDiag['wire_iati_paths_only_in_ota_wire_count'] ?? 0),
                'wire_iati_email_row_key_union_ota' => is_array($tDiag['wire_iati_email_row_key_union_ota'] ?? null)
                    ? array_slice($tDiag['wire_iati_email_row_key_union_ota'], 0, 16)
                    : [],
                'wire_iati_email_row_key_union_iati' => is_array($tDiag['wire_iati_email_row_key_union_iati'] ?? null)
                    ? array_slice($tDiag['wire_iati_email_row_key_union_iati'], 0, 16)
                    : [],
                'wire_segment_sell_context_all_required_present' => (bool) ($tDiag['wire_segment_sell_context_all_required_present'] ?? false),
                'wire_offer_snapshot_present' => (bool) ($tDiag['wire_offer_snapshot_present'] ?? false),
                'wire_offer_has_brand_candidates' => (bool) ($tDiag['wire_offer_has_brand_candidates'] ?? false),
            ], $tripSlice);
            if (SabreBookingPayloadBuilder::isTripOrdersCreatebookingCompareStyle($st) && $tripEnvelopeDiag !== []) {
                $phoneDiag = array_merge($tripEnvelopeDiag, is_array($tripSlice['payload_summary'] ?? null) ? $tripSlice['payload_summary'] : []);
                $row = array_merge($row, $this->agencyPhoneMissingClassifierForTripOrdersCompareRow($st, $phoneDiag, $digest !== [] ? $digest : null, $booking->id));
            }
            if ($httpStatus === 403) {
                $row['entitlement_hint'] = 'Endpoint reachable but credential not entitled or wrong product path.';
            }
            if (! $pnrCreated) {
                $hostBlob = strtoupper(implode(' ', array_merge($msgs, $codesOut)));
                if (str_contains($hostBlob, 'NO FARES') || str_contains($hostBlob, 'NO FARE')) {
                    $row['classification'] = 'pnr_requires_manual_sabre_pricing';
                }
            }

            SupplierBookingAttempt::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $conn->id,
                'provider' => SupplierProvider::Sabre->value,
                'action' => 'compare_booking_endpoint',
                'status' => $httpStatus === 403 ? 'forbidden' : 'attempted',
                'error_code' => null,
                'error_message' => null,
                'supplier_reference' => null,
                'safe_summary' => SensitiveDataRedactor::redact([
                    'source' => 'sabre_compare_booking_endpoints',
                    'endpoint_path' => $ep,
                    'payload_style' => $st,
                    'http_status' => (string) $httpStatus,
                    'access_result' => $access,
                    'ticketing_disabled' => true,
                    'request_body_non_empty' => $reqBodyNonEmpty,
                    'wire_top_level_keys' => array_slice($rootKeys, 0, 16),
                    'has_create_passenger_name_record_rq' => (bool) ($tDiag['wire_has_create_passenger_name_record_rq'] ?? false),
                    'wire_has_create_passenger_name_record_rq' => (bool) ($tDiag['wire_has_create_passenger_name_record_rq'] ?? false),
                    'response_top_level_keys' => $topKeys,
                    'response_top_level_error_code' => $row['response_top_level_error_code'] ?? null,
                    'response_top_level_message' => $row['response_top_level_message'] ?? null,
                    'response_top_level_status' => $row['response_top_level_status'] ?? null,
                    'response_top_level_type' => $row['response_top_level_type'] ?? null,
                    'response_timestamp_present' => (bool) ($digest['response_timestamp_present'] ?? false),
                    'response_error_codes' => $codesOut,
                    'response_error_messages' => $msgs,
                    'application_results_status' => $row['application_results_status'] ?? null,
                    'application_results_incomplete' => (bool) ($row['application_results_incomplete'] ?? false),
                    'host_warning_modules' => $row['host_warning_modules'] ?? [],
                    'host_warning_sabre_codes' => $row['host_warning_sabre_codes'] ?? [],
                    'host_warning_messages_truncated' => $row['host_warning_messages_truncated'] ?? [],
                    'passenger_records_error_digest_present' => (bool) ($digest['passenger_records_error_digest_present'] ?? false),
                    'wire_iati_paths_only_in_iati_template_count' => (int) ($tDiag['wire_iati_paths_only_in_iati_template_count'] ?? 0),
                    'wire_iati_paths_only_in_ota_wire_count' => (int) ($tDiag['wire_iati_paths_only_in_ota_wire_count'] ?? 0),
                    'wire_iati_email_row_key_union_ota' => is_array($tDiag['wire_iati_email_row_key_union_ota'] ?? null)
                        ? array_slice($tDiag['wire_iati_email_row_key_union_ota'], 0, 16)
                        : [],
                    'wire_iati_email_row_key_union_iati' => is_array($tDiag['wire_iati_email_row_key_union_iati'] ?? null)
                        ? array_slice($tDiag['wire_iati_email_row_key_union_iati'], 0, 16)
                        : [],
                    'wire_segment_sell_context_all_required_present' => (bool) ($tDiag['wire_segment_sell_context_all_required_present'] ?? false),
                    'wire_offer_snapshot_present' => (bool) ($tDiag['wire_offer_snapshot_present'] ?? false),
                    'wire_offer_has_brand_candidates' => (bool) ($tDiag['wire_offer_has_brand_candidates'] ?? false),
                    'entitlement_hint' => $row['entitlement_hint'] ?? null,
                ]),
                'attempted_by' => null,
                'attempted_at' => now(),
                'completed_at' => now(),
            ]);

            return [$row];
        }

        $rows = [];
        foreach ($pairs as [$path, $style]) {
            $path = $normalizePath($path);
            $wire = $this->buildWireForBookingEndpointCompare($apiDraft, $hints, $style);
            $keys = array_slice(array_keys($wire), 0, 32);
            $tradDiag = $this->compareBookingEndpointTraditionalWireSlice($wire, $meta, $style);
            $tripDiag = SabreBookingPayloadBuilder::isTripOrdersCreatebookingCompareStyle($style)
                ? $this->compareBookingEndpointTripOrdersWireSlice($apiDraft, $hints, $style)
                : [];
            $rows[] = array_merge([
                'endpoint_path' => $path,
                'payload_style' => $style,
                'status' => 'inspect_only',
                'http_status' => 'not_sent',
                'application_results_status' => null,
                'available' => false,
                'access_result' => 'inspect_only',
                'error_messages' => [],
                'response_error_codes' => [],
                'response_error_messages' => [],
                'pnr_created' => false,
                'pnr' => null,
                'pnr_present' => false,
                'supplier_reference_present' => false,
                'ticketing_disabled' => true,
                'wire_top_level_keys' => $keys,
                'passenger_count' => $paxCount,
                'segment_count' => $segCount,
            ], $tradDiag, $tripDiag);
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $apiDraft
     * @param  array<string, mixed>  $hints
     * @return array<string, mixed>
     */
    protected function compareBookingEndpointTripOrdersWireSlice(array $apiDraft, array $hints, string $style): array
    {
        $envelope = $this->bookingPayloadBuilder->buildTripOrdersCreateBookingEnvelope($apiDraft, $hints, $style);
        $diag = $this->bookingPayloadBuilder->summarizeEnvelopeForDiagnostics($envelope);
        $payloadSummary = $this->bookingPayloadBuilder->summarizeTripOrdersCertificationPayloadSummary($envelope);

        return [
            'wire_contract_valid' => (bool) ($diag['wire_contract_valid'] ?? false),
            'wire_has_flight_offer' => (bool) ($diag['wire_has_flight_offer'] ?? false),
            'wire_has_flight_details' => (bool) ($diag['wire_has_flight_details'] ?? false),
            'wire_has_shop_context' => (bool) ($diag['wire_has_shop_context'] ?? false),
            'wire_has_fare_linkage' => (bool) ($diag['wire_has_fare_linkage'] ?? false),
            'wire_agency_phone_ok' => (bool) ($diag['wire_agency_phone_ok'] ?? false),
            'wire_segment_required_fields_valid' => (bool) ($diag['wire_segment_required_fields_valid'] ?? false),
            'payload_summary' => $payloadSummary,
        ];
    }

    /**
     * P4: Safe traditional CPNR wire contract slice for compare matrix rows (no raw bodies / PII).
     *
     * @param  array<string, mixed>  $wire
     * @param  array<string, mixed>  $bookingMeta
     * @return array<string, mixed>
     */
    protected function compareBookingEndpointTraditionalWireSlice(array $wire, array $bookingMeta, string $style): array
    {
        if (! SabreBookingPayloadBuilder::isTraditionalPnrPassengerRecordsWireStyle($style)) {
            return [
                'wire_contract_valid' => false,
                'wire_traditional_pnr_contract_valid' => false,
            ];
        }
        $tDiag = $this->bookingPayloadBuilder->summarizeTraditionalPnrWirePostBody($wire, $bookingMeta, $style);
        $valid = ($tDiag['wire_traditional_pnr_contract_valid'] ?? false) === true;
        $invalid = is_array($tDiag['wire_invalid_traditional_pnr_contract_keys'] ?? null)
            ? $tDiag['wire_invalid_traditional_pnr_contract_keys']
            : [];

        return [
            'wire_contract_valid' => $valid,
            'wire_traditional_pnr_contract_valid' => $valid,
            'wire_invalid_traditional_pnr_contract_keys' => array_slice(array_map('strval', $invalid), 0, 12),
            'wire_has_root_air_price' => (bool) ($tDiag['wire_has_root_air_price'] ?? false),
            'wire_airprice_has_validating_carrier' => (bool) ($tDiag['wire_airprice_has_validating_carrier'] ?? false),
            'wire_airbook_retry_rebook_contract_valid' => (bool) ($tDiag['wire_airbook_retry_rebook_contract_valid'] ?? false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildWireForBookingEndpointCompare(array $apiDraft, array $hints, string $style): array
    {
        if (SabreBookingPayloadBuilder::isTripOrdersCreatebookingCompareStyle($style)) {
            $envelope = $this->bookingPayloadBuilder->buildTripOrdersCreateBookingEnvelope($apiDraft, $hints, $style);

            return $this->bookingPayloadBuilder->tripOrdersFinalWirePostBodyFromEnvelope($envelope);
        }
        if ($style === SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1_AIRPRICE_VALIDATING_CARRIER_COMPARE_V1) {
            $raw = $this->bookingPayloadBuilder->buildTraditionalPnrCreatePassengerNameRecordV1AirpriceValidatingCarrierCompareWire($apiDraft, $hints);

            return $this->bookingPayloadBuilder->stripOtaInternalKeysFromBookingWire($raw);
        }
        if ($style === SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1_AIRPRICE_PER_SEGMENT_FARE_BASIS_COMPARE_V1) {
            $raw = $this->bookingPayloadBuilder->buildTraditionalPnrCreatePassengerNameRecordV1AirpricePerSegmentFareBasisCompareWire($apiDraft, $hints);

            return $this->bookingPayloadBuilder->stripOtaInternalKeysFromBookingWire($raw);
        }
        if ($style === SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1_AIRBOOK_RETRY_REBOOK_REDISPLAY_COMPARE_V1) {
            $raw = $this->bookingPayloadBuilder->buildTraditionalPnrCreatePassengerNameRecordV1AirbookRetryRebookRedisplayCompareWire($apiDraft, $hints);

            return $this->bookingPayloadBuilder->stripOtaInternalKeysFromBookingWire($raw);
        }
        if (SabreBookingPayloadBuilder::isTraditionalPnrPassengerRecordsWireStyle($style)) {
            $raw = $this->bookingPayloadBuilder->buildPassengerRecordsCpnrWireForStyle($apiDraft, $hints, $style);

            return $this->bookingPayloadBuilder->stripOtaInternalKeysFromBookingWire($raw);
        }

        return [];
    }

    /**
     * @param  list<array<string, mixed>>  $segmentRows
     * @return array<string, mixed>
     */
    protected function segmentInspectSummariesFromRows(array $segmentRows): array
    {
        $out = [];
        foreach ($segmentRows as $i => $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $pfx = 'segment_'.($i + 1).'_';
            $bc = trim((string) ($seg['booking_class'] ?? $seg['class_of_service'] ?? ''));
            $fb = trim((string) ($seg['fare_basis_code'] ?? ''));
            $out[$pfx.'airline'] = strtoupper(trim((string) ($seg['carrier'] ?? $seg['airline_code'] ?? $seg['marketing_carrier'] ?? $seg['marketing_airline'] ?? '')));
            $out[$pfx.'flight_number'] = trim((string) ($seg['flight_number'] ?? $seg['flight_no'] ?? ''));
            $out[$pfx.'origin'] = strtoupper(trim((string) ($seg['origin'] ?? '')));
            $out[$pfx.'destination'] = strtoupper(trim((string) ($seg['destination'] ?? '')));
            $out[$pfx.'departure_at'] = (string) ($seg['departure_at'] ?? $seg['depart_at'] ?? $seg['departure_datetime'] ?? '');
            $out[$pfx.'booking_class_present'] = $bc !== '' ? 'yes' : 'no';
            $out[$pfx.'fare_basis_present'] = $fb !== '' ? 'yes' : 'no';
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $segmentRows
     * @return array<string, bool>
     */
    protected function inspectPassengerContactFlags(Booking $booking, array $segmentRows): array
    {
        $contact = $booking->contact;
        $hasPassport = false;
        foreach ($booking->passengers as $pax) {
            if (is_string($pax->passport_number) && trim($pax->passport_number) !== '') {
                $hasPassport = true;
                break;
            }
        }
        $hasBookingClass = false;
        $hasFareBasis = false;
        foreach ($segmentRows as $s) {
            if (! is_array($s)) {
                continue;
            }
            if (trim((string) ($s['booking_class'] ?? $s['class_of_service'] ?? '')) !== '') {
                $hasBookingClass = true;
            }
            if (trim((string) ($s['fare_basis_code'] ?? '')) !== '') {
                $hasFareBasis = true;
            }
        }

        return [
            'has_contact_email' => $contact !== null && trim((string) $contact->email) !== '',
            'has_contact_phone' => $contact !== null && trim((string) $contact->phone) !== '',
            'has_passport_doc' => $hasPassport,
            'has_booking_class' => $hasBookingClass,
            'has_fare_basis' => $hasFareBasis,
            'has_ticketing_instruction' => false,
            'has_end_transaction' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    protected function mergePublicReviewSabreSnapshotFromBooking(Booking $booking, array $snapshot, ?array $metaOverride = null): array
    {
        $meta = BookingSupplierConfirmationNoticeResolver::reconcileSabreBrandedFareMeta(
            $metaOverride ?? (is_array($booking->meta) ? $booking->meta : []),
        );
        $criteria = is_array($meta['search_criteria'] ?? null) ? $meta['search_criteria'] : [];
        $origin = strtoupper(trim((string) ($criteria['origin'] ?? '')));
        $destination = strtoupper(trim((string) ($criteria['destination'] ?? '')));
        $departDate = trim((string) ($criteria['depart_date'] ?? ($booking->travel_date?->format('Y-m-d') ?? '')));
        $departAt = $departDate !== '' ? $departDate.'T12:00:00Z' : now()->toIso8601String();

        $snapshot['supplier_provider'] = SupplierProvider::Sabre->value;

        $connFromMeta = (int) ($meta['supplier_connection_id'] ?? 0);
        $connFromSnapshot = (int) ($snapshot['supplier_connection_id'] ?? 0);
        if ($connFromMeta > 0) {
            $snapshot['supplier_connection_id'] = $connFromMeta;
        } elseif ($connFromSnapshot > 0) {
            $snapshot['supplier_connection_id'] = $connFromSnapshot;
        }

        $offerId = trim((string) ($snapshot['supplier_offer_id'] ?? $snapshot['offer_id'] ?? $snapshot['id'] ?? ''));
        if ($offerId !== '') {
            $snapshot['supplier_offer_id'] = $offerId;
            $snapshot['offer_id'] = $snapshot['offer_id'] ?? $offerId;
        }

        $fareIn = is_array($snapshot['fare_breakdown'] ?? null) ? $snapshot['fare_breakdown'] : [];
        $supplierTotal = (float) ($fareIn['supplier_total'] ?? 0);
        $currency = trim((string) ($fareIn['currency'] ?? $snapshot['currency'] ?? ''));

        $fbc = $booking->fareBreakdown;
        if ($supplierTotal <= 0.0 && $fbc !== null && (float) $fbc->total > 0.0) {
            $supplierTotal = (float) $fbc->total;
        }
        if ($currency === '' && $fbc !== null && trim((string) $fbc->currency) !== '') {
            $currency = trim((string) $fbc->currency);
        }

        $counts = is_array($fareIn['passenger_counts'] ?? null) ? $fareIn['passenger_counts'] : [];
        if ((int) ($counts['adults'] ?? 0) < 1) {
            $metaCounts = is_array($meta['passenger_counts'] ?? null) ? $meta['passenger_counts'] : [];
            $adults = (int) ($metaCounts['adults'] ?? 0);
            if ($adults < 1) {
                $adults = max(1, $booking->passengers->where('passenger_type', 'adult')->count());
            }
            $counts = [
                'adults' => $adults,
                'children' => (int) ($metaCounts['children'] ?? $booking->passengers->where('passenger_type', 'child')->count()),
                'infants' => (int) ($metaCounts['infants'] ?? $booking->passengers->where('passenger_type', 'infant')->count()),
            ];
        }

        $snapshot['fare_breakdown'] = array_merge($fareIn, [
            'supplier_total' => $supplierTotal > 0 ? $supplierTotal : (float) ($fareIn['supplier_total'] ?? 0),
            'currency' => $currency !== '' ? $currency : 'USD',
            'passenger_counts' => $counts,
        ]);

        $cm = trim((string) ($meta['confirmation_method'] ?? $meta['booking_method'] ?? ''));
        if ($cm !== '') {
            $snapshot['checkout_payment_mode'] = $cm;
        }

        $segments = is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : [];
        if ($segments === [] && $origin !== '' && $destination !== '') {
            $snapshot['sabre_segments_synthesized'] = true;
            $snapshot['segments'] = [];
        }

        $metaHandoff = is_array($meta['sabre_booking_context'] ?? null) ? $meta['sabre_booking_context'] : [];
        $existingContext = is_array($snapshot['sabre_booking_context'] ?? null)
            ? $snapshot['sabre_booking_context']
            : (is_array(data_get($snapshot, 'raw_payload.sabre_booking_context'))
                ? $snapshot['raw_payload']['sabre_booking_context']
                : []);
        $context = $metaHandoff !== [] ? $metaHandoff : $existingContext;

        $fareOptionKey = trim((string) ($meta['fare_option_key'] ?? ''));
        $selectedIntent = is_array($meta['selected_fare_family_option'] ?? null)
            ? $meta['selected_fare_family_option']
            : null;
        $sanitizedSelectedFare = $this->bookingPayloadBuilder->sanitizeSelectedFareFamilyForSabreContext(
            $selectedIntent,
            $fareOptionKey !== '' ? $fareOptionKey : null,
        );
        if ($sanitizedSelectedFare !== []) {
            $context = $this->bookingPayloadBuilder->mergeSelectedFareFamilyIntoSabreBookingContext(
                $context,
                $sanitizedSelectedFare,
            );
        }

        if ($context !== []) {
            $snapshot['sabre_booking_context'] = $context;
            $raw = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];
            $raw['sabre_booking_context'] = $context;
            $snapshot['raw_payload'] = $raw;
        }
        if ($fareOptionKey !== '') {
            $raw = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];
            $raw['fare_option_key'] = $fareOptionKey;
            $snapshot['raw_payload'] = $raw;
            $snapshot['fare_option_key'] = $fareOptionKey;
        }
        $snapshot = app(SabreFlightSearchNormalizer::class)->ensureSabreBookingContextOnCachedOffer($snapshot);

        return $snapshot;
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveBooking(string $reference): array
    {
        $ref = trim($reference);
        if ($ref === '') {
            return [
                'success' => false,
                'status' => 'validation_failed',
                'message' => 'Missing booking reference.',
                'live_call_attempted' => false,
            ];
        }

        if (! $this->mayPerformLiveSabreBookingCall()) {
            return [
                'success' => false,
                'status' => 'dry_run',
                'message' => 'Sabre retrieve booking is not available while live calls are disabled.',
                'reference' => $ref,
                'live_call_attempted' => false,
            ];
        }

        return [
            'success' => false,
            'status' => 'pending_implementation',
            'message' => 'Sabre retrieve booking is not implemented yet (dry-run only).',
            'reference' => $ref,
            'live_call_attempted' => false,
        ];
    }

    /**
     * Phase 3G-Cancel-R1: Gated live cancel for a Booking row (portal workflow entry).
     *
     * @return array<string, mixed>
     */
    public function cancelBookingForBooking(Booking $booking): array
    {
        return app(SabreBookingCancelService::class)->cancelForBooking($booking);
    }

    /**
     * Resolve booking by reference code, PNR, or numeric id then cancel via {@see cancelBookingForBooking()}.
     *
     * @return array<string, mixed>
     */
    public function cancelBooking(string $reference): array
    {
        $ref = trim($reference);
        if ($ref === '') {
            return [
                'success' => false,
                'status' => 'validation_failed',
                'message' => 'Missing booking reference.',
                'live_call_attempted' => false,
                'safe_summary_category' => SabreBookingCancelService::CATEGORY_CANCEL_PAYLOAD_MISSING,
            ];
        }

        $booking = Booking::query()
            ->where('reference_code', $ref)
            ->orWhere('pnr', strtoupper($ref))
            ->when(is_numeric($ref), fn ($q) => $q->orWhere('id', (int) $ref))
            ->orderByDesc('id')
            ->first();

        if ($booking === null) {
            return [
                'success' => false,
                'status' => 'not_found',
                'message' => 'Booking could not be found for cancellation.',
                'reference' => $ref,
                'live_call_attempted' => false,
                'safe_summary_category' => SabreBookingCancelService::CATEGORY_CANCEL_NOT_ELIGIBLE,
            ];
        }

        return $this->cancelBookingForBooking($booking);
    }

    /**
     * @param  array<string, mixed>  $normalizedOffer
     */
    public function validateNormalizedSabreOffer(array $normalizedOffer): SabreBookingOperationResult
    {
        $provider = strtolower(trim((string) ($normalizedOffer['supplier_provider'] ?? '')));
        if ($provider !== SupplierProvider::Sabre->value) {
            return new SabreBookingOperationResult(
                success: false,
                status: 'validation_failed',
                message: 'Offer is not a Sabre fare.',
                safe_context: ['reason' => 'wrong_provider'],
            );
        }

        $supplierOfferId = trim((string) ($normalizedOffer['supplier_offer_id'] ?? $normalizedOffer['offer_id'] ?? $normalizedOffer['id'] ?? ''));
        if ($supplierOfferId === '') {
            return new SabreBookingOperationResult(
                success: false,
                status: 'validation_failed',
                message: 'Sabre offer is missing supplier_offer_id.',
                safe_context: ['reason' => 'missing_supplier_offer_id'],
            );
        }

        $segments = $normalizedOffer['segments'] ?? [];
        if (! is_array($segments) || $segments === []) {
            return new SabreBookingOperationResult(
                success: false,
                status: 'validation_failed',
                message: 'Sabre offer is missing itinerary segments.',
                safe_context: ['reason' => 'missing_segments'],
            );
        }

        $fare = is_array($normalizedOffer['fare_breakdown'] ?? null) ? $normalizedOffer['fare_breakdown'] : [];
        $amount = (float) ($fare['supplier_total'] ?? $normalizedOffer['final_customer_price'] ?? 0);
        $currency = trim((string) ($fare['currency'] ?? $normalizedOffer['pricing_currency'] ?? ''));

        if ($amount <= 0.0) {
            return new SabreBookingOperationResult(
                success: false,
                status: 'validation_failed',
                message: 'Sabre offer is missing a priced total.',
                safe_context: ['reason' => 'missing_fare_amount'],
            );
        }

        if ($currency === '') {
            return new SabreBookingOperationResult(
                success: false,
                status: 'validation_failed',
                message: 'Sabre offer is missing currency.',
                safe_context: ['reason' => 'missing_currency'],
            );
        }

        $counts = is_array($fare['passenger_counts'] ?? null) ? $fare['passenger_counts'] : [];
        $adults = (int) ($counts['adults'] ?? $normalizedOffer['adults'] ?? 0);
        if ($adults < 1) {
            return new SabreBookingOperationResult(
                success: false,
                status: 'validation_failed',
                message: 'Sabre offer is missing passenger counts.',
                safe_context: ['reason' => 'missing_passenger_counts'],
            );
        }

        $timing = SabreItineraryTimingValidator::analyzeSegmentArrays(array_values($segments));
        if (! $timing['ok']) {
            Log::warning('sabre.booking.itinerary_timing_invalid', [
                'provider' => SupplierProvider::Sabre->value,
                'segment_count' => count($segments),
                'first_segment_origin' => $timing['first_segment_origin'],
                'last_segment_destination' => $timing['last_segment_destination'],
                'failed_time_link_count' => $timing['failed_time_link_count'],
                'invalid_segment_duration_count' => $timing['invalid_segment_duration_count'],
            ]);

            return new SabreBookingOperationResult(
                success: false,
                status: 'failed',
                message: 'Selected itinerary timing is invalid. Please choose another fare.',
                safe_context: array_merge(
                    [
                        'error_code' => 'sabre_invalid_itinerary_timing',
                        'reason' => 'sabre_invalid_itinerary_timing',
                    ],
                    $timing
                ),
            );
        }

        return new SabreBookingOperationResult(
            success: true,
            status: 'pending_revalidation',
            message: 'Offer passed structural checks for Sabre PNR booking.',
            safe_context: [
                'supplier_offer_id' => $supplierOfferId,
                'currency' => $currency,
                'passenger_counts_present' => true,
            ],
        );
    }

    /**
     * Dry-run: would call Sabre revalidation / price check (not wired).
     *
     * @param  array<string, mixed>  $normalizedOffer
     */
    public function revalidateOffer(array $normalizedOffer): SabreBookingOperationResult
    {
        $gate = $this->validateNormalizedSabreOffer($normalizedOffer);
        if (! $gate->success) {
            return $gate;
        }

        if (! $this->isBookingEnabled()) {
            return new SabreBookingOperationResult(
                success: false,
                status: 'disabled',
                message: (string) __('Sabre booking is not enabled yet.'),
                safe_context: ['step' => 'revalidate', 'live_call' => false],
            );
        }

        if (! $this->isBookingLiveCallEnabled()) {
            return new SabreBookingOperationResult(
                success: false,
                status: 'dry_run',
                message: 'Sabre live revalidation calls are disabled (SABRE_BOOKING_LIVE_CALL_ENABLED).',
                safe_context: ['step' => 'revalidate', 'live_call' => false],
            );
        }

        return new SabreBookingOperationResult(
            success: false,
            status: 'pending_implementation',
            message: 'Sabre fare revalidation is not implemented yet (dry-run only).',
            safe_context: ['step' => 'revalidate', 'live_call' => false],
        );
    }

    /**
     * Dry-run: legacy passenger-record path (superseded for live calls by Trip Orders when configured); requires live flags when implemented.
     *
     * @param  array<string, mixed>  $normalizedOffer
     * @param  list<array<string, mixed>>  $passengers
     */
    public function createPassengerRecord(array $normalizedOffer, array $passengers): SabreBookingOperationResult
    {
        $gate = $this->validateNormalizedSabreOffer($normalizedOffer);
        if (! $gate->success) {
            return $gate;
        }

        if (! $this->mayPerformLiveSabreBookingCall()) {
            return new SabreBookingOperationResult(
                success: false,
                status: 'dry_run',
                message: 'Sabre passenger record creation requires live calls to be enabled.',
                safe_context: ['step' => 'create_passenger', 'passenger_count' => count($passengers), 'live_call' => false],
            );
        }

        return new SabreBookingOperationResult(
            success: false,
            status: 'pending_implementation',
            message: 'Sabre passenger record creation is not implemented yet (dry-run only).',
            safe_context: ['step' => 'create_passenger', 'passenger_count' => count($passengers), 'live_call' => false],
        );
    }

    /**
     * Maps Sabre pipeline outcome to the shared supplier-booking result shape (admin/staff actions).
     */
    public function createSupplierBooking(
        Booking $booking,
        User $actor,
        bool $adminOverride = false,
        bool $allowControlledStaffPnr = false,
        bool $explicitRetry = false,
        string $attemptSource = 'system',
        ?array $controlledOperationContext = null,
    ): SupplierBookingResultData {
        if ($allowControlledStaffPnr && $attemptSource === 'system') {
            $attemptSource = $actor->isStaff() ? 'staff' : 'admin';
        }

        $controlledContext = is_array($controlledOperationContext) ? $controlledOperationContext : [];
        $deferOverrideUsed = $this->controlledPnrApprovalOverrideGate->allowsDeferOverride(
            $booking,
            $attemptSource,
            $allowControlledStaffPnr,
            $controlledContext,
        );

        if ($attemptSource === 'controlled_pnr_command' && $allowControlledStaffPnr) {
            $booking->loadMissing(['passengers', 'contact', 'fareBreakdown']);
            $digestInspect = $this->inspectControlledPnrPayloadDigestForBooking($booking);
            if (($digestInspect['digest_status'] ?? '') === 'ok') {
                $controlledContext['post_f9i_payload_digest_summary'] = app(SabrePassengerRecordsPayloadDigest::class)
                    ->commandSummaryFromDigest($digestInspect);
            }
        }

        $preflight = $this->preflightGuard->preflightAutomatedCreate(
            $booking,
            $actor,
            $attemptSource,
            $explicitRetry,
            $allowControlledStaffPnr,
            $controlledContext !== [] ? $controlledContext : null,
        );
        if ($preflight !== null) {
            return $preflight;
        }

        $retryAllowanceUsed = false;
        $retryAllowanceReason = null;
        $retryAfterAirpriceVcFixUsed = false;
        $retryAfterAirpriceVcFixReason = null;
        $schemaFixRecoveryUsed = false;
        $finalRetryAllowanceUsed = false;
        $postF9iPayloadDigestClean = ($controlledContext['post_f9i_payload_digest_summary'] ?? null) !== null
            && app(SabrePassengerRecordsPayloadDigest::class)->isPostF9iCleanForControlledRetry(
                is_array($controlledContext['post_f9i_payload_digest_summary'] ?? null)
                    ? $controlledContext['post_f9i_payload_digest_summary']
                    : [],
            );
        $previousNoFaresRbdCarrierErrorPresent = false;
        $booking->loadMissing('supplierBookingAttempts');
        $meaningfulCreateAttempt = SupplierBookingAttemptResolution::resolveLatestMeaningfulCreateAttempt(
            $booking->supplierBookingAttempts,
        );
        $schemaFixRecoveryEligible = $meaningfulCreateAttempt !== null
            && $attemptSource === 'controlled_pnr_command'
            && $this->controlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate->allows(
                $booking,
                $meaningfulCreateAttempt,
                $attemptSource,
                $allowControlledStaffPnr,
                $controlledContext,
            );
        if ($schemaFixRecoveryEligible) {
            $f9lAssess = $this->controlledPnrRetryAfterAirpriceVcSchemaFixAllowanceGate->assessSchemaRecoveryAvailability(
                $booking,
                is_array($controlledContext['post_f9i_payload_digest_summary'] ?? null)
                    ? $controlledContext['post_f9i_payload_digest_summary']
                    : null,
                $controlledContext,
                $meaningfulCreateAttempt,
                $attemptSource,
                $allowControlledStaffPnr,
                true,
            );
            $postF9iPayloadDigestClean = ($f9lAssess['post_f9i_payload_digest_clean'] ?? false) === true;
        }
        $retryAfterAirpriceVcFixEligible = ! $schemaFixRecoveryEligible
            && $meaningfulCreateAttempt !== null
            && $attemptSource === 'controlled_pnr_command'
            && $this->controlledPnrRetryAfterAirpriceVcFixAllowanceGate->allows(
                $booking,
                $meaningfulCreateAttempt,
                $attemptSource,
                $allowControlledStaffPnr,
                $controlledContext,
            );
        if ($retryAfterAirpriceVcFixEligible) {
            $f9jAssess = $this->controlledPnrRetryAfterAirpriceVcFixAllowanceGate->assessAvailability(
                $booking,
                is_array($controlledContext['post_f9i_payload_digest_summary'] ?? null)
                    ? $controlledContext['post_f9i_payload_digest_summary']
                    : null,
                $controlledContext,
                $meaningfulCreateAttempt,
                $attemptSource,
                $allowControlledStaffPnr,
            );
            $previousNoFaresRbdCarrierErrorPresent = ($f9jAssess['previous_no_fares_rbd_carrier_error_present'] ?? false) === true;
            $postF9iPayloadDigestClean = ($f9jAssess['post_f9i_payload_digest_clean'] ?? false) === true;
        }
        $retryAllowanceEligible = ! $retryAfterAirpriceVcFixEligible
            && ! $schemaFixRecoveryEligible
            && $meaningfulCreateAttempt !== null
            && $attemptSource === 'controlled_pnr_command'
            && $this->controlledPnrRetryAllowanceGate->allows(
                $booking,
                $meaningfulCreateAttempt,
                $attemptSource,
                $allowControlledStaffPnr,
                $controlledContext,
            );
        $finalRetryAllowanceEligible = ! $schemaFixRecoveryEligible
            && ! $retryAfterAirpriceVcFixEligible
            && ! $retryAllowanceEligible
            && $meaningfulCreateAttempt !== null
            && $attemptSource === 'controlled_pnr_command'
            && $this->controlledFinalPnrRetryAllowanceGate->allows(
                $booking,
                $meaningfulCreateAttempt,
                $attemptSource,
                $allowControlledStaffPnr,
                $controlledContext,
            );

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $p = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($p !== SupplierProvider::Sabre->value) {
            return new SupplierBookingResultData(
                success: false,
                status: 'failed',
                provider: $p !== '' ? $p : 'unknown',
                error_code: 'sabre_offer_validation_failed',
                error_message: 'Booking is not a Sabre itinerary.',
                safe_summary: ['source' => 'sabre_booking_service', 'reason' => 'wrong_provider'],
            );
        }

        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null)
            ? $meta['normalized_offer_snapshot']
            : (is_array($meta['flight_offer_snapshot'] ?? null) ? $meta['flight_offer_snapshot'] : []);

        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown']);

        if (ComplexItineraryPolicy::isComplex($booking)
            && ! (ComplexItineraryPolicy::complexItineraryPnrEnabled() && $adminOverride)) {
            $result = $this->complexItineraryPnrBlockedResult($booking);

            return $this->mapCreateBookingArrayToSupplierResult($booking, $actor, $result);
        }

        $snapshot = $this->mergePublicReviewSabreSnapshotFromBooking($booking, $snapshot);
        $passengerData = $this->passengerDataFromBooking($booking);

        if ($allowControlledStaffPnr) {
            $this->recordAdminStaffSupplierConfirmation($booking, $actor);
            $offerRefreshBlock = $this->controlledStaffOfferRefreshBeforePnr(
                $booking,
                $actor,
                $snapshot,
                $attemptSource,
            );
            if ($offerRefreshBlock !== null) {
                return $offerRefreshBlock;
            }
            $booking->refresh();
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null)
                ? $meta['normalized_offer_snapshot']
                : (is_array($meta['validated_offer_snapshot'] ?? null)
                    ? $meta['validated_offer_snapshot']
                    : (is_array($meta['flight_offer_snapshot'] ?? null) ? $meta['flight_offer_snapshot'] : $snapshot));
            $snapshot = $this->mergePublicReviewSabreSnapshotFromBooking($booking, $snapshot);
        }

        $controlledActionSource = $allowControlledStaffPnr
            ? ($attemptSource === 'staff' ? 'staff_supplier_action' : 'admin_supplier_action')
            : null;

        $this->strategyChangedRetryAuditSlice = $this->resolveStrategyChangedRetryAuditSlice(
            $booking,
            $allowControlledStaffPnr,
            $attemptSource,
        );

        $allowOperationalStaffPnr = $allowControlledStaffPnr
            || app(SabreOperationalPnrReadiness::class)->wouldAttemptPnr($booking);

        if ($retryAllowanceEligible && $meaningfulCreateAttempt !== null) {
            $this->controlledPnrRetryAllowanceGate->recordUsage($booking, $meaningfulCreateAttempt);
            $booking->refresh();
            $retryAllowanceUsed = true;
            $retryAllowanceReason = $this->controlledPnrRetryAllowanceGate->reasonCode();
        }

        $result = $this->createBooking($snapshot, $passengerData, $booking->id, array_filter([
            'admin_booking_route_override' => $adminOverride,
            'allow_controlled_staff_pnr' => $allowControlledStaffPnr,
            'allow_operational_staff_pnr' => $allowOperationalStaffPnr,
            'source' => $controlledActionSource,
            'controlled_manual_review_defer_override_used' => $deferOverrideUsed ? true : null,
            'controlled_supplier_retry_allowance_used' => $retryAllowanceUsed ? true : null,
            'controlled_supplier_retry_allowance_reason' => $retryAllowanceReason,
            'controlled_f9j_retry_eligible' => $retryAfterAirpriceVcFixEligible && $meaningfulCreateAttempt !== null ? true : null,
            'controlled_f9j_meaningful_attempt_id' => $meaningfulCreateAttempt?->id,
            'controlled_f9l_schema_recovery_eligible' => $schemaFixRecoveryEligible ? true : null,
            'controlled_f9q_final_retry_eligible' => $finalRetryAllowanceEligible ? true : null,
        ], static fn ($v) => $v !== null && $v !== false));

        if ($schemaFixRecoveryEligible) {
            $booking->refresh();
            $schemaFailed = ($result['cpnr_schema_validation_failed'] ?? false) === true
                || (($result['live_call_attempted'] ?? false) !== true
                    && app(SabreCpnrIatiWireSchemaValidator::class)->outcomeLooksLikeCpnrSchemaValidationFailure(
                        strtolower(trim((string) ($result['error_code'] ?? ''))),
                        trim((string) ($result['message'] ?? $result['error_message'] ?? '')),
                        ($result['application_error_digest_available'] ?? false) === true,
                    ));
            if (! $schemaFailed && ($result['controlled_f9l_schema_recovery_recorded'] ?? false) === true) {
                $schemaFixRecoveryUsed = true;
            }
            $booking->refresh();
        }

        if ($finalRetryAllowanceEligible) {
            $booking->refresh();
            if (($result['controlled_f9q_final_retry_recorded'] ?? false) === true) {
                $finalRetryAllowanceUsed = true;
                if (($result['success'] ?? false) !== true
                    && trim((string) ($result['pnr'] ?? '')) === ''
                    && trim((string) ($booking->pnr ?? '')) === '') {
                    $this->controlledFinalPnrRetryAllowanceGate->recordHostFailureOutcome(
                        $booking->fresh(),
                        is_array($result) ? $result : [],
                    );
                }
            }
            $booking->refresh();
        }

        if ($retryAfterAirpriceVcFixEligible && $meaningfulCreateAttempt !== null) {
            $booking->refresh();
            $schemaSummary = [
                'cpnr_schema_validation_pointer' => $result['cpnr_schema_validation_pointer'] ?? null,
                'cpnr_schema_validation_message_summary' => $result['cpnr_schema_validation_message_summary'] ?? null,
            ];
            $appDigestAvailable = ($result['application_error_digest_available'] ?? false) === true;
            $errorCode = strtolower(trim((string) ($result['error_code'] ?? '')));
            $errorMessage = trim((string) ($result['message'] ?? $result['error_message'] ?? ''));
            $schemaValidator = app(SabreCpnrIatiWireSchemaValidator::class);
            $schemaFailed = ($result['cpnr_schema_validation_failed'] ?? false) === true
                || (($result['live_call_attempted'] ?? false) !== true
                    && $schemaValidator->outcomeLooksLikeCpnrSchemaValidationFailure($errorCode, $errorMessage, $appDigestAvailable))
                || (($result['live_call_attempted'] ?? false) === true
                    && $schemaValidator->outcomeLooksLikeCpnrSchemaValidationFailure($errorCode, $errorMessage, $appDigestAvailable));

            if ($schemaFailed) {
                $this->controlledPnrRetryAfterAirpriceVcFixAllowanceGate->recordSchemaValidationOutcome(
                    $booking,
                    true,
                    is_array($result) ? $result : $schemaSummary,
                );
            } elseif (($result['controlled_f9j_retry_recorded'] ?? false) === true) {
                $retryAfterAirpriceVcFixUsed = true;
                $retryAfterAirpriceVcFixReason = $this->controlledPnrRetryAfterAirpriceVcFixAllowanceGate->reasonCode();
                if ($appDigestAvailable
                    || $errorCode === 'sabre_booking_application_error'
                    || ($result['success'] ?? false) === true) {
                    $this->controlledPnrRetryAfterAirpriceVcFixAllowanceGate->markHostApplicationResultsReceived($booking);
                }
            }
            $booking->refresh();
        }
        $result = $this->mergeControlledStaffPnrOptionsIntoBookingResult($result, [
            'allow_controlled_staff_pnr' => $allowControlledStaffPnr,
            'source' => $controlledActionSource,
        ]);

        if (($result['status'] ?? '') === 'pending_payment_or_ticketing' && ($result['success'] ?? false)) {
            $this->persistLiveSabrePnrOnBooking($booking->fresh(['passengers', 'contact']), $result, $actor);
        }

        if (($result['reason_code'] ?? '') === 'sabre_passenger_records_pnr_unconfirmed_segment_nn'
            && trim((string) ($result['pnr'] ?? '')) !== '') {
            $this->persistLiveSabrePnrManualReviewOnBooking($booking->fresh(['passengers', 'contact']), $result, $actor);
        }

        if ($allowOperationalStaffPnr) {
            $this->persistOperationalStaffAutoPnrMeta($booking, $result);
        }

        return $this->withControlledFinalRetryAllowanceSummary(
            $this->withControlledSchemaFixRecoverySummary(
                $this->withControlledRetryAfterAirpriceVcFixSummary(
                    $this->withControlledRetryAllowanceSummary(
                        $this->mapCreateBookingArrayToSupplierResult($booking, $actor, $result),
                        $retryAllowanceUsed,
                        $retryAllowanceReason,
                    ),
                    $retryAfterAirpriceVcFixUsed,
                    $retryAfterAirpriceVcFixReason,
                    $postF9iPayloadDigestClean,
                    $previousNoFaresRbdCarrierErrorPresent,
                ),
                $schemaFixRecoveryUsed,
                $postF9iPayloadDigestClean,
            ),
            $finalRetryAllowanceUsed,
        );
    }

    protected function withControlledFinalRetryAllowanceSummary(
        SupplierBookingResultData $result,
        bool $finalRetryAllowanceUsed,
    ): SupplierBookingResultData {
        if (! $finalRetryAllowanceUsed) {
            return $result;
        }

        $safeSummary = array_merge($result->safe_summary, [
            'controlled_final_pnr_retry_allowance_used' => true,
        ]);

        return new SupplierBookingResultData(
            success: $result->success,
            status: $result->status,
            provider: $result->provider,
            supplier_reference: $result->supplier_reference,
            pnr: $result->pnr,
            safe_summary: $safeSummary,
            request_payload: $result->request_payload,
            response_payload: $result->response_payload,
            error_code: $result->error_code,
            error_message: $result->error_message,
            warnings: $result->warnings,
        );
    }

    protected function withControlledSchemaFixRecoverySummary(
        SupplierBookingResultData $result,
        bool $schemaFixRecoveryUsed,
        bool $postF9iPayloadDigestClean,
    ): SupplierBookingResultData {
        if (! $schemaFixRecoveryUsed) {
            return $result;
        }

        $safeSummary = array_merge($result->safe_summary, array_filter([
            'controlled_supplier_retry_after_airprice_vc_schema_fix_used' => true,
            'post_f9i_payload_digest_clean' => $postF9iPayloadDigestClean,
        ], static fn ($v) => $v !== null && $v !== ''));

        return new SupplierBookingResultData(
            success: $result->success,
            status: $result->status,
            provider: $result->provider,
            supplier_reference: $result->supplier_reference,
            pnr: $result->pnr,
            safe_summary: $safeSummary,
            request_payload: $result->request_payload,
            response_payload: $result->response_payload,
            error_code: $result->error_code,
            error_message: $result->error_message,
            warnings: $result->warnings,
        );
    }

    protected function withControlledRetryAfterAirpriceVcFixSummary(
        SupplierBookingResultData $result,
        bool $retryAfterAirpriceVcFixUsed,
        ?string $retryAfterAirpriceVcFixReason,
        bool $postF9iPayloadDigestClean,
        bool $previousNoFaresRbdCarrierErrorPresent,
    ): SupplierBookingResultData {
        if (! $retryAfterAirpriceVcFixUsed) {
            return $result;
        }

        $safeSummary = array_merge($result->safe_summary, array_filter([
            'controlled_supplier_retry_after_airprice_vc_fix_used' => true,
            'controlled_supplier_retry_after_airprice_vc_fix_reason' => $retryAfterAirpriceVcFixReason,
            'post_f9i_payload_digest_clean' => $postF9iPayloadDigestClean,
            'previous_no_fares_rbd_carrier_error_present' => $previousNoFaresRbdCarrierErrorPresent,
        ], static fn ($v) => $v !== null && $v !== ''));

        return new SupplierBookingResultData(
            success: $result->success,
            status: $result->status,
            provider: $result->provider,
            supplier_reference: $result->supplier_reference,
            pnr: $result->pnr,
            safe_summary: $safeSummary,
            request_payload: $result->request_payload,
            response_payload: $result->response_payload,
            error_code: $result->error_code,
            error_message: $result->error_message,
            warnings: $result->warnings,
        );
    }

    protected function withControlledRetryAllowanceSummary(
        SupplierBookingResultData $result,
        bool $retryAllowanceUsed,
        ?string $retryAllowanceReason,
    ): SupplierBookingResultData {
        if (! $retryAllowanceUsed) {
            return $result;
        }

        $safeSummary = array_merge($result->safe_summary, array_filter([
            'controlled_supplier_retry_allowance_used' => true,
            'controlled_supplier_retry_allowance_reason' => $retryAllowanceReason,
        ], static fn ($v) => $v !== null && $v !== ''));

        return new SupplierBookingResultData(
            success: $result->success,
            status: $result->status,
            provider: $result->provider,
            supplier_reference: $result->supplier_reference,
            pnr: $result->pnr,
            safe_summary: $safeSummary,
            request_payload: $result->request_payload,
            response_payload: $result->response_payload,
            error_code: $result->error_code,
            error_message: $result->error_message,
            warnings: $result->warnings,
        );
    }

    /**
     * C5: Certification-only optional revalidation probe (does not mutate booking snapshot or public checkout).
     *
     * @return array<string, mixed>
     */
    public function runCertificationRevalidateFirst(Booking $booking): array
    {
        $connection = $booking->supplierConnection;
        if ($connection === null) {
            return ['attempted' => true, 'success' => false, 'revalidation_skipped' => 'no_connection'];
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null)
            ? $meta['normalized_offer_snapshot']
            : (is_array($meta['flight_offer_snapshot'] ?? null) ? $meta['flight_offer_snapshot'] : []);
        if ($snapshot === []) {
            return ['attempted' => true, 'success' => false, 'revalidation_skipped' => 'no_snapshot'];
        }

        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown']);
        $snapshot = $this->mergePublicReviewSabreSnapshotFromBooking($booking, $snapshot);
        $gate = $this->validateNormalizedSabreOffer($snapshot);
        if (! $gate->success) {
            return ['attempted' => true, 'success' => false, 'revalidation_skipped' => 'offer_gate_failed'];
        }

        $draft = $this->prepareBookingPayload($snapshot, $this->passengerDataFromBooking($booking));
        if (($draft['_valid'] ?? false) !== true) {
            return ['attempted' => true, 'success' => false, 'revalidation_skipped' => 'draft_invalid'];
        }
        unset($draft['_valid']);

        $outcome = $this->runRevalidationBeforeBooking($draft, $connection, null, null);
        $success = ($outcome['success'] ?? false) === true;
        $includes27131 = ($outcome['includes_sabre_error_27131'] ?? false) === true;
        $linkage = is_array($outcome['linkage'] ?? null) ? $outcome['linkage'] : [];

        return [
            'attempted' => true,
            'success' => $success,
            'includes_sabre_error_27131' => $includes27131,
            'revalidation_linkage_incomplete' => $includes27131 && ! $success,
            'http_status' => (int) ($outcome['http_status'] ?? 0),
            'linkage' => $linkage,
            'linkage_digest' => is_array($outcome['linkage_digest'] ?? null) ? $outcome['linkage_digest'] : [],
        ];
    }

    /**
     * P2c: Store compact revalidation linkage on booking meta for certification PNR only (never public checkout).
     *
     * @param  array<string, mixed>  $revalidateOutcome  Output of {@see runCertificationRevalidateFirst()}
     */
    public function persistCertificationRevalidateLinkageForBooking(Booking $booking, array $revalidateOutcome): void
    {
        if (($revalidateOutcome['success'] ?? false) !== true) {
            return;
        }
        $linkage = is_array($revalidateOutcome['linkage'] ?? null) ? $revalidateOutcome['linkage'] : [];
        if ($linkage === []) {
            return;
        }

        $safe = [];
        foreach ([
            'fare_basis_code', 'fare_reference', 'price_quote_reference', 'offer_reference', 'offer_id',
            'revalidation_reference', 'itinerary_reference', 'validating_carrier', 'currency',
            'revalidated_total', 'ticketing_time_limit',
        ] as $key) {
            if (! array_key_exists($key, $linkage)) {
                continue;
            }
            $v = $linkage[$key];
            if (is_string($v) && trim($v) !== '') {
                $safe[$key] = substr(trim($v), 0, 120);
            } elseif (is_int($v) || is_float($v)) {
                $safe[$key] = $v;
            }
        }

        if ($safe === []) {
            return;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta[SabrePnrCertificationSupport::META_CERTIFICATION_REVALIDATE_LINKAGE] = $safe;
        $meta[SabrePnrCertificationSupport::META_CERTIFICATION_REVALIDATE_AT] = now()->toIso8601String();
        $booking->meta = $meta;
        $booking->save();
    }

    /**
     * C1: Certification-only live PNR attempt — bypasses R5 complex-itinerary guard; never tickets.
     */
    public function createSupplierBookingForCertification(Booking $booking, ?User $actor = null): SupplierBookingResultData
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $p = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($p !== SupplierProvider::Sabre->value) {
            return new SupplierBookingResultData(
                success: false,
                status: 'failed',
                provider: $p !== '' ? $p : 'unknown',
                error_code: 'sabre_offer_validation_failed',
                error_message: 'Booking is not a Sabre itinerary.',
                safe_summary: ['source' => 'sabre_pnr_certification', 'reason' => 'wrong_provider'],
            );
        }

        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null)
            ? $meta['normalized_offer_snapshot']
            : (is_array($meta['flight_offer_snapshot'] ?? null) ? $meta['flight_offer_snapshot'] : []);

        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown']);
        $snapshot = $this->mergePublicReviewSabreSnapshotFromBooking($booking, $snapshot);
        $certLinkage = is_array($meta[SabrePnrCertificationSupport::META_CERTIFICATION_REVALIDATE_LINKAGE] ?? null)
            ? $meta[SabrePnrCertificationSupport::META_CERTIFICATION_REVALIDATE_LINKAGE]
            : [];
        $result = $this->createBooking(
            $snapshot,
            $this->passengerDataFromBooking($booking),
            $booking->id,
            array_filter([
                'certification_full_itinerary_fallback' => true,
                'certification_fare_linkage' => $certLinkage !== [] ? $certLinkage : null,
            ], static fn ($v) => $v !== null),
        );

        if (($result['status'] ?? '') === 'pending_payment_or_ticketing' && ($result['success'] ?? false)) {
            $this->persistLiveSabrePnrOnBooking($booking->fresh(['passengers', 'contact']), $result, $actor);
        }

        return $this->mapCreateBookingArrayToSupplierResult(
            $booking,
            $actor,
            $result,
            SabrePnrCertificationSupport::ACTION_CERTIFICATION,
        );
    }

    /**
     * @param  array<string, mixed>  $result  Output of {@see createBooking()}
     */
    protected function mapCreateBookingArrayToSupplierResult(
        Booking $booking,
        ?User $actor,
        array $result,
        string $attemptAction = 'create_pnr',
    ): SupplierBookingResultData {
        $status = (string) ($result['status'] ?? 'failed');
        $message = (string) ($result['message'] ?? 'Sabre booking failed.');

        if ($status === 'pending_payment_or_ticketing' && ($result['success'] ?? false)) {
            $pnr = isset($result['pnr']) && is_string($result['pnr']) ? trim($result['pnr']) : null;
            $apiBookingId = isset($result['provider_booking_id']) && is_string($result['provider_booking_id'])
                ? trim($result['provider_booking_id'])
                : '';
            $supplierRef = $apiBookingId !== '' ? $apiBookingId : (($pnr !== null && $pnr !== '') ? strtoupper(substr($pnr, 0, 32)) : '');
            $this->recordSucceededAttempt($booking, $actor, $result, $supplierRef, $attemptAction);

            return new SupplierBookingResultData(
                success: true,
                status: 'pending_ticketing',
                provider: SupplierProvider::Sabre->value,
                supplier_reference: $supplierRef !== '' ? $supplierRef : null,
                pnr: $pnr !== null && $pnr !== '' ? strtoupper(substr($pnr, 0, 32)) : null,
                safe_summary: array_merge(
                    ['source' => $attemptAction === SabrePnrCertificationSupport::ACTION_CERTIFICATION
                        ? 'sabre_pnr_certification' : 'sabre_booking_service', 'create_status' => $status],
                    array_intersect_key($result, array_flip([
                        'passenger_count', 'segment_count', 'live_call_attempted', 'live_call_allowed',
                        'http_status', 'provider_status', 'selected_offer_id', 'fare_amount', 'fare_currency',
                        'booking_schema', 'payload_schema',
                        'revalidation_skipped_by_config', 'revalidation_bypass_enabled', 'revalidation_before_booking_enabled',
                        'ticketing_enabled', 'previous_revalidation_reason_code',
                        'has_fare_basis', 'has_booking_class', 'has_validating_carrier',
                        'fresh_shop_guard_result',
                        'controlled_manual_review_defer_override_used',
                    ]))
                ),
                error_code: null,
                error_message: null,
            );
        }

        if (($result['error_code'] ?? '') === 'sabre_booking_application_error' && $status === 'needs_review') {
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $cid = $meta['supplier_connection_id'] ?? $result['supplier_connection_id'] ?? null;
            $cid = is_numeric($cid) ? (int) $cid : null;
            $attemptConnectionId = ($cid !== null && $cid > 0) ? $cid : null;
            $ep = $this->resolveEndpointSummaryPreferringBookingResult($result, (int) ($attemptConnectionId ?? 0));
            $safeKeys = is_array($result['response_safe_keys'] ?? null) ? array_slice($result['response_safe_keys'], 0, 48) : [];
            $digestKeys = [
                'response_error_count', 'response_error_codes', 'response_error_messages',
                'response_error_fields', 'response_error_paths', 'response_missing_fields',
                'request_id', 'request_correlation_id', 'trace_id',
                'timestamp', 'response_top_level_message', 'response_top_level_status',
            ];
            $digestSlice = array_intersect_key($result, array_flip($digestKeys));
            SupplierBookingAttempt::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $attemptConnectionId,
                'provider' => SupplierProvider::Sabre->value,
                'action' => $attemptAction,
                'status' => 'needs_review',
                'error_code' => 'sabre_booking_application_error',
                'error_message' => $message,
                'safe_summary' => $this->sabreBookingApplicationErrorAttemptSafeSummary(
                    $result,
                    $ep,
                    $digestSlice,
                    'sabre_booking_service',
                    $safeKeys,
                ),
                'attempted_by' => $actor?->id,
                'attempted_at' => now(),
                'completed_at' => now(),
            ]);
            $booking->forceFill(['supplier_booking_status' => 'manual_review'])->save();
            $this->persistPassengerRecordsApplicationFailureMeta($booking, $result);

            return new SupplierBookingResultData(
                success: false,
                status: 'manual_review',
                provider: SupplierProvider::Sabre->value,
                supplier_reference: null,
                pnr: null,
                safe_summary: array_merge(
                    ['source' => 'sabre_booking_service', 'create_status' => $status],
                    array_intersect_key($result, array_flip([
                        'passenger_count', 'segment_count', 'http_status', 'booking_schema', 'payload_schema',
                        'response_error_codes', 'response_error_messages', 'response_error_fields', 'response_error_paths', 'response_missing_fields',
                        'request_id', 'request_correlation_id', 'trace_id', 'timestamp',
                    ])),
                    self::passengerRecordsEndpointSliceFromResult($result),
                    self::createPayloadAndStructureSliceFromResult($result)
                ),
                error_code: 'sabre_booking_application_error',
                error_message: $message,
            );
        }

        $certifiedRouteError = (string) ($result['error_code'] ?? '');
        $staffConfirmationMessage = $this->customerStaffConfirmationBookingMessage();
        if ($status === 'needs_review'
            && ! ($result['success'] ?? false)
            && ($result['live_call_attempted'] ?? false) !== true
            && (
                ($result['message'] ?? '') === $staffConfirmationMessage
                || in_array((string) ($result['error_code'] ?? ''), [
                    'context_incomplete_manual_review',
                    'iati_cpnr_context_not_ready',
                    'refresh_required_but_missing',
                    'freshness_strategy_failed',
                    'fare_changed_review_required',
                    'sabre_booking_context_incomplete',
                    'iati_style_not_eligible',
                ], true)
            )) {
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $cid = $meta['supplier_connection_id'] ?? $result['supplier_connection_id'] ?? null;
            $cid = is_numeric($cid) ? (int) $cid : null;
            $attemptConnectionId = ($cid !== null && $cid > 0) ? $cid : null;
            $ep = $this->resolveEndpointSummaryPreferringBookingResult($result, (int) ($attemptConnectionId ?? 0));
            $errorCode = (string) ($result['error_code'] ?? $result['reason_code'] ?? 'staff_confirmation_required');
            $attemptSource = ($result['allow_controlled_staff_pnr'] ?? false) === true
                ? 'admin_supplier_action'
                : 'sabre_booking_service';
            $this->recordFailedAttempt(
                $booking,
                $actor,
                $errorCode,
                $message,
                array_merge([
                    'source' => $attemptSource,
                    'live_call_attempted' => false,
                    'booking_schema' => $result['booking_schema'] ?? null,
                    'payload_schema' => self::resolvePayloadSchemaForSummary($result),
                    'ticketing_disabled' => true,
                    'passenger_count' => (int) ($result['passenger_count'] ?? 0),
                    'segment_count' => (int) ($result['segment_count'] ?? 0),
                    'freshness_strategy_decision' => is_array($result['freshness_strategy_decision'] ?? null)
                        ? $result['freshness_strategy_decision']
                        : [],
                    'passenger_records_style_decision' => is_array($result['passenger_records_style_decision'] ?? null)
                        ? $result['passenger_records_style_decision']
                        : [],
                ], array_intersect_key($ep, array_flip(['endpoint_host', 'endpoint_path']))),
                $attemptAction,
            );
            if (($result['allow_controlled_staff_pnr'] ?? false) === true) {
                $booking->forceFill(['supplier_booking_status' => 'manual_review'])->save();
            }

            return new SupplierBookingResultData(
                success: false,
                status: 'manual_review',
                provider: SupplierProvider::Sabre->value,
                supplier_reference: null,
                pnr: null,
                safe_summary: array_merge(
                    ['source' => $attemptSource, 'create_status' => $status],
                    array_intersect_key($result, array_flip([
                        'passenger_count', 'segment_count', 'live_call_attempted', 'booking_schema', 'payload_schema', 'error_code', 'reason_code',
                        'freshness_strategy_decision', 'passenger_records_style_decision',
                    ]))
                ),
                error_code: $errorCode,
                error_message: $message,
            );
        }

        if ($status === 'needs_review'
            && ! ($result['success'] ?? false)
            && in_array($certifiedRouteError, [
                SabreCertifiedRouteSelector::ERROR_CODE_PENDING,
                SabreCertifiedRouteSelector::ERROR_CODE_NOT_CERTIFIED,
            ], true)
            && ($result['live_call_attempted'] ?? false) !== true) {
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $cid = $meta['supplier_connection_id'] ?? $result['supplier_connection_id'] ?? null;
            $cid = is_numeric($cid) ? (int) $cid : null;
            $attemptConnectionId = ($cid !== null && $cid > 0) ? $cid : null;
            $ep = $this->resolveEndpointSummaryPreferringBookingResult($result, (int) ($attemptConnectionId ?? 0));
            $attemptSource = ($result['allow_controlled_staff_pnr'] ?? false) === true
                ? 'admin_supplier_action'
                : 'sabre_booking_service';
            $this->recordFailedAttempt(
                $booking,
                $actor,
                $certifiedRouteError,
                $message,
                array_merge([
                    'source' => $attemptSource,
                    'live_call_attempted' => false,
                    'booking_schema' => $result['booking_schema'] ?? null,
                    'payload_schema' => self::resolvePayloadSchemaForSummary($result),
                    'ticketing_disabled' => true,
                    'passenger_count' => (int) ($result['passenger_count'] ?? 0),
                    'segment_count' => (int) ($result['segment_count'] ?? 0),
                    'supplier_pnr_deferred_reason' => SabreCertifiedRouteSelector::DEFER_REASON,
                    'certified_route_selection' => is_array($result['certified_route_selection'] ?? null)
                        ? $result['certified_route_selection']
                        : [],
                ], array_intersect_key($ep, array_flip(['endpoint_host', 'endpoint_path']))),
                $attemptAction,
            );
            if (($result['allow_controlled_staff_pnr'] ?? false) === true) {
                $meta['supplier_pnr_deferred_reason'] = SabreCertifiedRouteSelector::DEFER_REASON;
                $booking->forceFill(['supplier_booking_status' => 'manual_review', 'meta' => $meta])->save();
            }

            return new SupplierBookingResultData(
                success: false,
                status: 'manual_review',
                provider: SupplierProvider::Sabre->value,
                supplier_reference: null,
                pnr: null,
                safe_summary: array_merge(
                    ['source' => $attemptSource, 'create_status' => $status],
                    array_intersect_key($result, array_flip([
                        'passenger_count', 'segment_count', 'live_call_attempted', 'booking_schema', 'payload_schema', 'error_code',
                        'certified_route_selection', 'supplier_pnr_deferred_reason',
                    ]))
                ),
                error_code: $certifiedRouteError,
                error_message: $message,
            );
        }

        if (($result['error_code'] ?? '') === ComplexItineraryPolicy::ERROR_CODE && $status === 'needs_review') {
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $cid = $meta['supplier_connection_id'] ?? $result['supplier_connection_id'] ?? null;
            $cid = is_numeric($cid) ? (int) $cid : null;
            $attemptConnectionId = ($cid !== null && $cid > 0) ? $cid : null;
            $ep = $this->resolveEndpointSummaryPreferringBookingResult($result, (int) ($attemptConnectionId ?? 0));
            SupplierBookingAttempt::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $attemptConnectionId,
                'provider' => SupplierProvider::Sabre->value,
                'action' => $attemptAction,
                'status' => 'needs_review',
                'error_code' => ComplexItineraryPolicy::ERROR_CODE,
                'error_message' => $message,
                'safe_summary' => SensitiveDataRedactor::redact(array_merge([
                    'source' => 'sabre_booking_service',
                    'live_call_attempted' => false,
                    'booking_schema' => $result['booking_schema'] ?? null,
                    'payload_schema' => self::resolvePayloadSchemaForSummary($result),
                    'ticketing_disabled' => true,
                    'ticketing_pending' => true,
                    'passenger_count' => (int) ($result['passenger_count'] ?? 0),
                    'segment_count' => (int) ($result['segment_count'] ?? 0),
                    'supplier_pnr_deferred_reason' => ComplexItineraryPolicy::DEFER_REASON,
                ], array_intersect_key($ep, array_flip(['endpoint_host', 'endpoint_path'])))),
                'attempted_by' => $actor?->id,
                'attempted_at' => now(),
                'completed_at' => now(),
            ]);
            $meta['supplier_pnr_deferred_reason'] = ComplexItineraryPolicy::DEFER_REASON;
            $meta['defer_supplier_booking_to_manual_review'] = true;
            $booking->forceFill([
                'supplier_booking_status' => 'manual_review',
                'meta' => $meta,
            ])->save();

            return new SupplierBookingResultData(
                success: false,
                status: 'manual_review',
                provider: SupplierProvider::Sabre->value,
                supplier_reference: null,
                pnr: null,
                safe_summary: array_merge(
                    ['source' => 'sabre_booking_service', 'create_status' => $status],
                    array_intersect_key($result, array_flip([
                        'passenger_count', 'segment_count', 'live_call_attempted', 'booking_schema', 'payload_schema', 'error_code',
                    ]))
                ),
                error_code: ComplexItineraryPolicy::ERROR_CODE,
                error_message: $message,
            );
        }

        if (($result['error_code'] ?? '') === 'sabre_passenger_records_itinerary_guard' && $status === 'needs_review') {
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $cid = $meta['supplier_connection_id'] ?? $result['supplier_connection_id'] ?? null;
            $cid = is_numeric($cid) ? (int) $cid : null;
            $attemptConnectionId = ($cid !== null && $cid > 0) ? $cid : null;
            $ep = $this->resolveEndpointSummaryPreferringBookingResult($result, (int) ($attemptConnectionId ?? 0));
            SupplierBookingAttempt::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $attemptConnectionId,
                'provider' => SupplierProvider::Sabre->value,
                'action' => $attemptAction,
                'status' => 'needs_review',
                'error_code' => 'sabre_passenger_records_itinerary_guard',
                'error_message' => $message,
                'safe_summary' => SensitiveDataRedactor::redact(array_merge([
                    'source' => 'sabre_booking_service',
                    'live_call_attempted' => false,
                    'booking_schema' => $result['booking_schema'] ?? null,
                    'payload_schema' => self::resolvePayloadSchemaForSummary($result),
                    'ticketing_disabled' => true,
                    'ticketing_pending' => true,
                    'passenger_count' => (int) ($result['passenger_count'] ?? 0),
                    'segment_count' => (int) ($result['segment_count'] ?? 0),
                    'guard_trigger' => (string) ($result['guard_trigger'] ?? ''),
                    'segment_order_corrected' => (bool) ($result['segment_order_corrected'] ?? false),
                    'pnr_attempted' => false,
                    'public_auto_pnr_attempted' => false,
                ], app(SabrePassengerRecordsItineraryGuardPolicy::class)->safeSummarySlice($result), array_intersect_key($ep, array_flip(['endpoint_host', 'endpoint_path'])))),
                'attempted_by' => $actor?->id,
                'attempted_at' => now(),
                'completed_at' => now(),
            ]);
            $booking->forceFill(['supplier_booking_status' => 'manual_review'])->save();

            return new SupplierBookingResultData(
                success: false,
                status: 'manual_review',
                provider: SupplierProvider::Sabre->value,
                supplier_reference: null,
                pnr: null,
                safe_summary: array_merge(
                    ['source' => 'sabre_booking_service', 'create_status' => $status],
                    array_intersect_key($result, array_flip([
                        'passenger_count', 'segment_count', 'live_call_attempted', 'guard_trigger',
                        'segment_order_corrected', 'booking_schema', 'payload_schema', 'error_code', 'reason_code',
                    ]))
                ),
                error_code: 'sabre_passenger_records_itinerary_guard',
                error_message: $message,
            );
        }

        if (($result['error_code'] ?? '') === 'sabre_passenger_records_stale_shop_segment' && $status === 'needs_review') {
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $cid = $meta['supplier_connection_id'] ?? $result['supplier_connection_id'] ?? null;
            $cid = is_numeric($cid) ? (int) $cid : null;
            $attemptConnectionId = ($cid !== null && $cid > 0) ? $cid : null;
            $ep = $this->resolveEndpointSummaryPreferringBookingResult($result, (int) ($attemptConnectionId ?? 0));
            SupplierBookingAttempt::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $attemptConnectionId,
                'provider' => SupplierProvider::Sabre->value,
                'action' => $attemptAction,
                'status' => 'needs_review',
                'error_code' => 'sabre_passenger_records_stale_shop_segment',
                'error_message' => $message,
                'safe_summary' => SensitiveDataRedactor::redact(array_merge([
                    'source' => 'sabre_booking_service',
                    'live_call_attempted' => false,
                    'booking_schema' => $result['booking_schema'] ?? null,
                    'payload_schema' => self::resolvePayloadSchemaForSummary($result),
                    'ticketing_disabled' => true,
                    'ticketing_pending' => true,
                    'passenger_count' => (int) ($result['passenger_count'] ?? 0),
                    'segment_count' => (int) ($result['segment_count'] ?? 0),
                    'stale_segment_index' => isset($result['stale_segment_index']) ? (int) $result['stale_segment_index'] : null,
                    'stale_segment_route' => isset($result['stale_segment_route']) ? (string) $result['stale_segment_route'] : '',
                    'stale_segment_flight' => isset($result['stale_segment_flight']) ? (string) $result['stale_segment_flight'] : '',
                    'probable_issue' => isset($result['probable_issue']) ? (string) $result['probable_issue'] : '',
                    'fresh_shop_guard_result' => is_array($result['fresh_shop_guard_result'] ?? null)
                        ? $result['fresh_shop_guard_result']
                        : null,
                ], array_intersect_key($ep, array_flip(['endpoint_host', 'endpoint_path'])))),
                'attempted_by' => $actor?->id,
                'attempted_at' => now(),
                'completed_at' => now(),
            ]);
            $booking->forceFill(['supplier_booking_status' => 'manual_review'])->save();

            return new SupplierBookingResultData(
                success: false,
                status: 'manual_review',
                provider: SupplierProvider::Sabre->value,
                supplier_reference: null,
                pnr: null,
                safe_summary: array_merge(
                    ['source' => 'sabre_booking_service', 'create_status' => $status],
                    array_intersect_key($result, array_flip([
                        'passenger_count', 'segment_count', 'live_call_attempted', 'booking_schema', 'payload_schema', 'error_code', 'reason_code',
                        'stale_segment_index', 'stale_segment_route', 'stale_segment_flight', 'probable_issue',
                        'fresh_shop_guard_result',
                    ]))
                ),
                error_code: 'sabre_passenger_records_stale_shop_segment',
                error_message: $message,
            );
        }

        if ($status === 'needs_review' && ($result['success'] ?? false)) {
            $bid = trim((string) ($result['provider_booking_id'] ?? ''));
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $cid = $meta['supplier_connection_id'] ?? $result['supplier_connection_id'] ?? null;
            $cid = is_numeric($cid) ? (int) $cid : null;
            $attemptConnectionId = ($cid !== null && $cid > 0) ? $cid : null;
            $ep = $this->resolveEndpointSummaryPreferringBookingResult($result, (int) ($attemptConnectionId ?? 0));
            $safeKeys = is_array($result['response_safe_keys'] ?? null) ? array_slice($result['response_safe_keys'], 0, 48) : [];
            $digestKeys = [
                'response_error_count', 'response_error_codes', 'response_error_messages',
                'response_error_fields', 'response_error_paths', 'response_missing_fields',
                'request_id', 'request_correlation_id', 'trace_id',
                'timestamp', 'response_top_level_message', 'response_top_level_status',
            ];
            $digestSlice = array_intersect_key($result, array_flip($digestKeys));
            SupplierBookingAttempt::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $attemptConnectionId,
                'provider' => SupplierProvider::Sabre->value,
                'action' => $attemptAction,
                'status' => 'needs_review',
                'supplier_reference' => $bid !== '' ? substr($bid, 0, 191) : null,
                'safe_summary' => SensitiveDataRedactor::redact(array_merge([
                    'source' => 'sabre_booking_service',
                    'http_status' => $result['http_status'] ?? null,
                    'payload_schema' => self::resolvePayloadSchemaForSummary($result),
                    'booking_schema' => $result['booking_schema'] ?? null,
                    'ticketing_disabled' => true,
                    'ticketing_pending' => true,
                    'response_safe_keys' => $safeKeys,
                ], $digestSlice, array_intersect_key($ep, array_flip(['endpoint_host', 'endpoint_path'])))),
                'attempted_by' => $actor?->id,
                'attempted_at' => now(),
                'completed_at' => now(),
            ]);
            $patch = ['supplier_booking_status' => 'manual_review'];
            if ($bid !== '') {
                $patch['supplier_api_booking_id'] = substr($bid, 0, 191);
                $patch['supplier_reference'] = substr($bid, 0, 191);
            }
            $booking->forceFill($patch)->save();

            return new SupplierBookingResultData(
                success: false,
                status: 'manual_review',
                provider: SupplierProvider::Sabre->value,
                supplier_reference: $bid !== '' ? $bid : null,
                pnr: null,
                safe_summary: array_merge(
                    ['source' => 'sabre_booking_service', 'create_status' => $status],
                    array_intersect_key($result, array_flip([
                        'passenger_count', 'segment_count', 'http_status', 'provider_booking_id',
                        'payload_schema', 'booking_schema', 'selected_offer_id', 'response_safe_keys',
                    ]))
                ),
                error_code: 'sabre_booking_success_missing_locator',
                error_message: $message,
            );
        }

        if (($result['error_code'] ?? '') === 'sabre_invalid_itinerary_timing') {
            Log::notice('sabre_booking_itinerary_timing_blocked', [
                'booking_id' => $booking->id,
                'segment_count' => $result['segment_count'] ?? null,
                'failed_time_link_count' => $result['failed_time_link_count'] ?? null,
                'invalid_segment_duration_count' => $result['invalid_segment_duration_count'] ?? null,
            ]);
            $timingSummary = array_intersect_key($result, array_flip([
                'endpoint_host', 'endpoint_path', 'timeout_seconds', 'connect_timeout_seconds',
                'segment_count', 'passenger_count', 'failed_time_link_count', 'invalid_segment_duration_count',
                'live_call_attempted',
            ]));
            $this->recordFailedAttempt($booking, $actor, 'sabre_invalid_itinerary_timing', $message, $timingSummary, $attemptAction);

            return new SupplierBookingResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::Sabre->value,
                safe_summary: array_merge(
                    ['source' => 'sabre_booking_service', 'create_status' => $status],
                    $timingSummary
                ),
                error_code: 'sabre_invalid_itinerary_timing',
                error_message: $message,
            );
        }

        if ($status === 'disabled') {
            Log::notice('provider_routing_blocked', [
                'reason' => 'sabre_booking_disabled',
                'provider' => 'sabre',
                'booking_id' => $booking->id,
                'action' => 'create_supplier_booking',
            ]);
            $this->recordFailedAttempt($booking, $actor, 'sabre_booking_disabled', $message, [], $attemptAction);
        } elseif ($status === 'dry_run') {
            Log::notice('sabre_booking_skeleton', [
                'status' => $status,
                'booking_id' => $booking->id,
                'action' => 'create_supplier_booking',
            ]);
            $this->recordFailedAttempt($booking, $actor, 'sabre_booking_live_calls_disabled', $message, [], $attemptAction);
        } elseif ($status === 'failed' && ($result['live_call_attempted'] ?? false)) {
            $failCode = (string) ($result['error_code'] ?? 'sabre_booking_http_failed');
            $httpDiag = array_intersect_key($result, array_flip([
                'endpoint_host', 'endpoint_path', 'duration_ms', 'exception_class',
            ]));
            Log::notice('sabre_booking_http_failed', array_merge([
                'booking_id' => $booking->id,
                'http_status' => $result['http_status'] ?? null,
                'error_code' => $failCode,
            ], $httpDiag));
            $this->recordFailedAttempt($booking, $actor, $failCode, $message, array_intersect_key($result, array_flip([
                'endpoint_host', 'endpoint_path', 'duration_ms', 'exception_class', 'exception_safe_message', 'http_status',
                'timeout_seconds', 'connect_timeout_seconds', 'live_call_attempted',
                'safe_validation_excerpts', 'safe_validation_excerpts_structured', 'error_code', 'reason_code',
                'payload_schema', 'booking_schema',
                'cpnr_schema_validation_pointer', 'cpnr_schema_validation_message_summary',
                'v25_airprice_pricing_qualifiers_digest', 'v25_brand_qualifier_host_reason',
                'safe_reason_code', 'customer_safe_message', 'pnr_attempted', 'selected_payload_style',
            ])), $attemptAction);
        } elseif (in_array($status, ['pending_implementation', 'validation_failed'], true)) {
            Log::notice('sabre_booking_skeleton', [
                'status' => $status,
                'booking_id' => $booking->id,
                'action' => 'create_supplier_booking',
            ]);
            $code = $status === 'validation_failed' ? 'sabre_offer_validation_failed' : 'sabre_booking_pending_implementation';
            $this->recordFailedAttempt($booking, $actor, $code, $message, [], $attemptAction);
        } elseif ($status === 'payload_validation_failed') {
            Log::notice('sabre_booking_payload_validation_blocked', [
                'booking_id' => $booking->id,
                'action' => 'create_supplier_booking',
                'error_code' => 'sabre_booking_payload_validation_failed',
            ]);
            $this->recordFailedAttempt($booking, $actor, 'sabre_booking_payload_validation_failed', $message, array_merge(
                self::tripOrdersTravelerPayloadAuditSlice($result),
                array_intersect_key($result, array_flip([
                    'booking_schema', 'payload_schema', 'passenger_count', 'segment_count', 'live_call_attempted',
                    'error_code', 'reason_code',
                    'cpnr_schema_validation_pointer', 'cpnr_schema_validation_message_summary',
                    'cpnr_schema_validation_status', 'cpnr_schema_validation_failed',
                    'v25_airprice_pricing_qualifiers_digest',
                    'v25_brand_qualifier_host_reason',
                    'safe_reason_code', 'customer_safe_message', 'pnr_attempted', 'selected_payload_style',
                ]))
            ), $attemptAction);
        } elseif ($status === 'failed') {
            $this->recordFailedAttempt($booking, $actor, 'sabre_booking_failed', $message, [], $attemptAction);
        }

        $errorCode = match ($status) {
            'disabled' => 'sabre_booking_disabled',
            'dry_run' => 'sabre_booking_live_calls_disabled',
            'validation_failed' => 'sabre_offer_validation_failed',
            'payload_validation_failed' => 'sabre_booking_payload_validation_failed',
            'failed' => ($result['live_call_attempted'] ?? false)
                ? (string) ($result['error_code'] ?? 'sabre_booking_http_failed')
                : 'sabre_booking_failed',
            default => 'sabre_booking_pending_implementation',
        };

        $resolvedStatus = match ($status) {
            'disabled' => 'not_supported',
            'dry_run' => 'dry_run',
            'pending_implementation' => 'pending_implementation',
            'payload_validation_failed' => 'failed',
            default => 'failed',
        };

        $supplierBookingSuccess = false;

        return new SupplierBookingResultData(
            success: $supplierBookingSuccess,
            status: $resolvedStatus,
            provider: SupplierProvider::Sabre->value,
            safe_summary: array_merge(
                ['source' => 'sabre_booking_service', 'create_status' => $status],
                array_intersect_key($result, array_flip([
                    'passenger_count', 'segment_count', 'live_call_attempted', 'live_call_allowed', 'http_status',
                    'endpoint_host', 'endpoint_path', 'duration_ms', 'exception_class',
                    'wire_traveler_field_style', 'wire_has_givenName', 'wire_has_given_name',
                    'wire_has_passengerCode', 'wire_has_passengerTypeCode',
                    'wire_has_contact', 'wire_has_contactInfo', 'wire_contact_field_style', 'wire_has_contact_email', 'wire_has_contact_phone',
                    'wire_traveler_required_fields_valid', 'wire_invalid_traveler_field_keys',
                ]))
            ),
            error_code: $errorCode,
            error_message: $message,
        );
    }

    protected function recordSucceededAttempt(
        Booking $booking,
        ?User $actor,
        array $result,
        string $supplierReference,
        string $attemptAction = 'create_pnr',
    ): void {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $cid = $meta['supplier_connection_id'] ?? $result['supplier_connection_id'] ?? null;
        $cid = is_numeric($cid) ? (int) $cid : null;
        $attemptConnectionId = ($cid !== null && $cid > 0) ? $cid : null;

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $attemptConnectionId,
            'provider' => SupplierProvider::Sabre->value,
            'action' => $attemptAction,
            'status' => 'success',
            'supplier_reference' => $supplierReference !== '' ? $supplierReference : null,
            'safe_summary' => SensitiveDataRedactor::redact(array_merge([
                'source' => 'sabre_booking_service',
                'pnr' => $result['pnr'] ?? null,
                'http_status' => $result['http_status'] ?? null,
                'provider_status' => $result['provider_status'] ?? null,
                'selected_offer_id' => $result['selected_offer_id'] ?? null,
                'passenger_count' => $result['passenger_count'] ?? null,
                'fare_amount' => $result['fare_amount'] ?? null,
                'fare_currency' => $result['fare_currency'] ?? null,
                'payload_schema' => self::resolvePayloadSchemaForSummary($result),
                'booking_schema' => $result['booking_schema'] ?? null,
            ], self::bookingRevalidationAuditForMeta($result), self::linkageFlagSliceFromResult($result), self::createPayloadAndStructureSliceFromResult($result))),
            'attempted_by' => $actor?->id,
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);
    }

    protected function recordFailedAttempt(
        Booking $booking,
        ?User $actor,
        string $errorCode,
        string $errorMessage,
        array $safeSummaryExtras = [],
        string $attemptAction = 'create_pnr',
    ): void {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $cid = $meta['supplier_connection_id'] ?? null;
        $cid = is_numeric($cid) ? (int) $cid : null;
        $attemptConnectionId = ($cid !== null && $cid > 0) ? $cid : null;

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $attemptConnectionId,
            'provider' => SupplierProvider::Sabre->value,
            'action' => $attemptAction,
            'status' => 'failed',
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'attempted_by' => $actor?->id,
            'attempted_at' => now(),
            'completed_at' => now(),
            'safe_summary' => SensitiveDataRedactor::redact(array_merge([
                'source' => 'sabre_booking_service',
                'error_code' => $errorCode,
            ], $safeSummaryExtras)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function passengerDataFromBooking(Booking $booking): array
    {
        $contact = $booking->contact;
        $rows = [];
        foreach ($booking->passengers as $passenger) {
            $row = [
                'passenger_type' => (string) $passenger->passenger_type,
                'first_name' => (string) $passenger->first_name,
                'last_name' => (string) $passenger->last_name,
            ];
            $g = trim((string) ($passenger->gender ?? ''));
            if ($g !== '') {
                $row['gender'] = $g;
            }
            if ($passenger->date_of_birth !== null) {
                try {
                    $row['date_of_birth'] = Carbon::parse($passenger->date_of_birth)->format('Y-m-d');
                } catch (Throwable) {
                    $row['date_of_birth'] = (string) $passenger->date_of_birth;
                }
            }
            foreach ([
                'passport_number', 'passport_issuing_country', 'nationality', 'document_type', 'national_id_number',
            ] as $k) {
                $v = $passenger->{$k} ?? null;
                if (is_string($v) && trim($v) !== '') {
                    $row[$k] = trim($v);
                }
            }
            if ($passenger->passport_expiry_date !== null) {
                try {
                    $row['passport_expiry_date'] = Carbon::parse($passenger->passport_expiry_date)->format('Y-m-d');
                } catch (Throwable) {
                    $row['passport_expiry_date'] = (string) $passenger->passport_expiry_date;
                }
            }
            $rows[] = $row;
        }

        return [
            'contact' => [
                'email' => $contact !== null ? (string) $contact->email : '',
                'phone' => $contact !== null ? (string) $contact->phone : '',
            ],
            'passengers' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveBookingEndpointSummary(int $connectionId): array
    {
        $pathCfg = (string) config('suppliers.sabre.booking_path', '/v1/trip/orders/createBooking');
        if ($this->effectiveSabreBookingSchema() === 'create_passenger_name_record') {
            $pathCfg = $this->resolvePassengerRecordsEndpointPathForAttempt();
        }
        $timeouts = $this->sabreClient->httpTimeoutSettings();
        if ($connectionId > 0) {
            $c = SupplierConnection::query()->find($connectionId);
            if ($c !== null && $c->provider === SupplierProvider::Sabre) {
                $parts = $this->sabreClient->resolveEndpointParts($c, $pathCfg);

                return [
                    'endpoint_host' => $parts['endpoint_host'],
                    'endpoint_path' => $parts['endpoint_path'],
                    'timeout_seconds' => $timeouts['timeout_seconds'],
                    'connect_timeout_seconds' => $timeouts['connect_timeout_seconds'],
                ];
            }
        }
        $base = rtrim((string) config('suppliers.sabre.default_base_url'), '/');
        $host = parse_url(str_contains($base, '://') ? $base : 'https://'.$base, PHP_URL_HOST);
        $pathNorm = $pathCfg !== '' && $pathCfg[0] === '/' ? $pathCfg : '/'.$pathCfg;

        return [
            'endpoint_host' => is_string($host) && $host !== '' ? $host : 'unknown',
            'endpoint_path' => $pathNorm,
            'timeout_seconds' => $timeouts['timeout_seconds'],
            'connect_timeout_seconds' => $timeouts['connect_timeout_seconds'],
        ];
    }

    /**
     * Resolves payload schema for attempt/meta summaries (live results set top-level {@code payload_schema}).
     *
     * @param  array<string, mixed>  $result
     */
    protected static function resolvePayloadSchemaForSummary(array $result): ?string
    {
        $top = $result['payload_schema'] ?? null;
        if (is_string($top) && trim($top) !== '') {
            return trim($top);
        }
        if (is_array($result['payload_safe_summary'] ?? null)) {
            $ps = $result['payload_safe_summary']['payload_schema'] ?? null;
            if (is_string($ps) && trim($ps) !== '') {
                return trim($ps);
            }
        }

        return null;
    }

    /**
     * Trip Orders traveler field diagnostics for safe persistence (booleans + invalid key tokens only).
     *
     * @param  array<string, mixed>  $diag
     * @return array<string, mixed>
     */
    protected static function tripOrdersTravelerPayloadAuditSlice(array $diag): array
    {
        $out = [];
        $auditKeys = [
            'wire_traveler_field_style',
            'wire_has_givenName',
            'wire_has_given_name',
            'wire_has_passengerCode',
            'wire_has_passengerTypeCode',
            'wire_has_contact',
            'wire_has_contactInfo',
            'wire_contact_field_style',
            'wire_has_contact_email',
            'wire_has_contact_phone',
            'wire_has_customer_contact_phone',
            'wire_has_agency_phone',
            'wire_agency_phone_field_style',
            'wire_agency_phone_paths',
            'wire_agency_phone_redacted',
            'wire_agency_phone_ok',
            'wire_has_POS', 'wire_has_pos', 'wire_has_agency_block', 'wire_has_travelAgency', 'wire_has_customerInfo',
            'wire_pcc_present', 'wire_agency_config_phone_present', 'wire_agency_country_config_present',
            'wire_phone_use_type_values_sanitized', 'wire_phone_location_values_sanitized',
            'wire_traveler_required_fields_valid',
            'wire_invalid_traveler_field_keys',
            'wire_segment_field_style',
            'wire_segment_required_fields_valid',
            'wire_invalid_segment_field_keys',
            'wire_payload_null_free',
            'wire_contract_valid',
            'wire_invalid_contract_keys',
            'wire_null_path_count',
            'wire_null_paths',
            'wire_required_null_paths',
            'wire_has_any_nulls',
            'wire_nulls_safe_to_omit',
        ];
        foreach ($diag as $k => $v) {
            if (! is_string($k)) {
                continue;
            }
            if (in_array($k, $auditKeys, true)) {
                $out[$k] = $v;
            } elseif (str_starts_with($k, 'traveler_')) {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected function passengerRecordsMultiSegmentOutcomeSlice(array $result): array
    {
        return array_filter(array_intersect_key($result, array_flip([
            'passenger_records_multi_segment_enabled',
            'passenger_records_multi_segment_eligible',
            'passenger_records_multi_segment_validation_failed_reasons',
            'passenger_records_multi_segment_requires_revalidation',
            'passenger_records_multi_segment_revalidation_required',
            'passenger_records_multi_segment_revalidation_ok',
            'passenger_records_multi_segment_revalidation_applied',
            'passenger_records_multi_segment_revalidation_failed_reason',
            'route_continuity_valid',
            'chronology_valid',
            'all_segments_have_booking_class',
            'all_segments_have_flight_number',
            'all_segments_have_marketing_airline',
            'segment_order_repaired_for_sell',
            'date_repair_applied',
        ])), static fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    protected function sabreCheckoutOutcomeDigestSlice(array $result): array
    {
        $err = (string) ($result['error_code'] ?? '');

        if (($result['passenger_records_itinerary_advisory'] ?? false) === true) {
            $advisory = array_filter(array_merge(
                array_intersect_key($result, array_flip([
                    'passenger_records_itinerary_advisory',
                    'passenger_records_guard_waived_reason',
                    'guard_trigger',
                    'segment_order_corrected',
                ])),
                $this->passengerRecordsMultiSegmentOutcomeSlice($result)
            ), static fn ($v) => $v !== null && $v !== '' && $v !== []);

            if ($err === 'sabre_booking_application_error') {
                return array_filter(array_merge(
                    $advisory,
                    $this->passengerRecordsHostOutcomeSlice($result)
                ), static fn ($v) => $v !== null && $v !== '' && $v !== []);
            }

            return $advisory;
        }
        if ($err === 'sabre_booking_payload_validation_failed') {
            $slice = self::tripOrdersTravelerPayloadAuditSlice($result);

            return array_filter($slice, static fn ($v) => $v !== null);
        }
        if ($err === 'sabre_passenger_records_itinerary_guard') {
            return array_filter(array_merge(
                array_intersect_key($result, array_flip([
                    'guard_trigger', 'segment_order_corrected', 'segment_count', 'live_call_attempted',
                    'reason_code', 'booking_schema', 'payload_schema',
                ])),
                $this->passengerRecordsMultiSegmentOutcomeSlice($result)
            ), static fn ($v) => $v !== null && $v !== '' && $v !== []);
        }
        if ($err === 'sabre_passenger_records_stale_shop_segment') {
            return array_filter(array_intersect_key($result, array_flip([
                'stale_segment_index', 'stale_segment_route', 'stale_segment_flight', 'probable_issue',
                'segment_count', 'live_call_attempted', 'reason_code', 'booking_schema', 'payload_schema',
            ])), static fn ($v) => $v !== null && $v !== '' && $v !== []);
        }
        if ($err !== 'sabre_booking_application_error') {
            return [];
        }

        return $this->passengerRecordsHostOutcomeSlice($result);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected function passengerRecordsHostOutcomeSlice(array $result): array
    {
        $passengerDiagKeys = [
            'application_results_status',
            'application_results_incomplete',
            'host_warning_modules',
            'host_warning_sabre_codes',
            'host_warning_messages_truncated',
            'passenger_records_error_digest_present',
            'pnr_present_in_response_body',
            'airline_segment_status',
            'affected_flight_numbers',
            'halt_on_status_received',
            'probable_issue',
            'retry_blocker_reasons',
        ];
        $keys = [
            'response_error_count', 'response_error_codes', 'response_error_messages',
            'response_error_fields', 'response_error_paths', 'response_missing_fields',
            'request_id', 'request_correlation_id', 'trace_id', 'timestamp',
        ];
        $slice = array_merge(
            array_intersect_key($result, array_flip($passengerDiagKeys)),
            array_intersect_key($result, array_flip($keys))
        );
        $codes = is_array($slice['response_error_codes'] ?? null) ? $slice['response_error_codes'] : [];
        $slice['mandatory_data_missing'] = in_array('MANDATORY_DATA_MISSING', array_map('strval', $codes), true);
        if ($slice['mandatory_data_missing']) {
            $slice = array_merge($slice, self::linkageFlagSliceFromResult($result));
            if (is_array($result['response_error_messages'] ?? null)) {
                $slice['response_error_messages'] = $result['response_error_messages'];
            }
        }

        return array_filter($slice, static function ($v, $k): bool {
            if ($k === 'mandatory_data_missing') {
                return true;
            }
            if (is_string($k) && str_starts_with($k, 'wire_')) {
                return true;
            }

            return $v !== null && $v !== [] && $v !== '';
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * F9G: Pass through safe ApplicationResults digest from live booking client result.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected static function passengerRecordsApplicationDigestSliceFromResult(array $result): array
    {
        $digest = $result['passenger_records_application_digest'] ?? null;

        return is_array($digest) && $digest !== []
            ? ['passenger_records_application_digest' => $digest]
            : [];
    }

    /**
    /**
     * F9G: Persist safe Passenger Records ApplicationResults digest on application-error failures (no PNR).
     *
     * @param  array<string, mixed>  $result
     */
    protected function persistPassengerRecordsApplicationFailureMeta(Booking $booking, array $result): void
    {
        if (($result['error_code'] ?? '') !== 'sabre_booking_application_error') {
            return;
        }
        if ((string) ($result['status'] ?? '') !== 'needs_review') {
            return;
        }
        if (($result['live_call_attempted'] ?? false) !== true) {
            return;
        }
        if (trim((string) ($result['pnr'] ?? '')) !== '') {
            return;
        }

        $digest = is_array($result['passenger_records_application_digest'] ?? null)
            ? $result['passenger_records_application_digest']
            : [];
        if ($digest === []) {
            return;
        }

        $digestService = app(SabrePassengerRecordsApplicationResultDigest::class);
        if (! $digestService->shouldPersistForApplicationFailure($digest)) {
            return;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta[SabrePassengerRecordsApplicationResultDigest::META_DIGEST_KEY] = $digest;
        $meta = array_merge($meta, $digestService->convenienceMetaFromDigest($digest));
        $booking->forceFill(['meta' => $meta])->save();
    }

    protected static function flattenBookingDiagnostics(array $diag): array
    {
        $keys = [
            'endpoint_host', 'endpoint_path', 'timeout_seconds', 'connect_timeout_seconds',
            'duration_ms', 'exception_class', 'exception_safe_message', 'http_status',
            'live_call_attempted', 'supplier_connection_id', 'booking_id',
            'passenger_count', 'segment_count', 'has_contact_email', 'has_contact_phone',
            'has_booking_class', 'has_fare_basis', 'has_end_transaction',
            'has_commit_or_end_transaction', 'has_trip_orders_schema', 'has_payment_or_hold_mode',
            'has_fare_reference', 'has_price_quote_reference', 'has_offer_reference',
            'has_revalidation_reference', 'has_itinerary_reference', 'has_validating_carrier',
            'has_revalidated_fare', 'has_revalidated_currency',
            'reason_code', 'error_code',
            'safe_validation_excerpts',
            'safe_validation_excerpts_structured',
            'v25_airprice_pricing_qualifiers_digest',
            'v25_brand_qualifier_host_reason',
            'safe_reason_code',
            'customer_safe_message',
            'pnr_attempted',
            'selected_payload_style',
            'cpnr_schema_validation_pointer',
            'cpnr_schema_validation_message_summary',
            'payload_schema', 'booking_schema', 'booking_transport', 'response_safe_keys',
            'response_error_count', 'response_error_codes', 'response_error_messages',
            'response_error_fields', 'response_error_paths', 'response_missing_fields',
            'request_id', 'request_correlation_id', 'trace_id', 'timestamp', 'response_top_level_message', 'response_top_level_status',
            'response_top_level_keys', 'response_top_level_error_code', 'response_top_level_type', 'response_additional_messages',
            'payload_style', 'has_flight_offer', 'has_flight_details', 'has_required_booking_product_object',
            'has_segments_inside_flight_offer', 'has_segments_inside_flight_details',
            'wire_root_keys', 'wire_has_flight_offer_at_root', 'wire_has_flight_details_at_root',
            'wire_has_hotel_at_root', 'wire_has_car_at_root', 'wire_has_required_product_at_root',
            'wire_has_required_booking_product_nested', 'wire_flight_offer_path', 'wire_flight_details_path',
            'wire_segment_count', 'wire_has_passengers', 'wire_has_contact', 'wire_has_contactInfo', 'wire_contact_field_style', 'wire_has_contact_email', 'wire_has_contact_phone',
            'wire_has_customer_contact_phone', 'wire_has_agency_phone', 'wire_agency_phone_field_style', 'wire_agency_phone_paths', 'wire_agency_phone_redacted', 'wire_agency_phone_ok', 'wire_has_POS', 'wire_has_pos', 'wire_has_agency_block', 'wire_has_travelAgency', 'wire_has_customerInfo', 'wire_pcc_present', 'wire_agency_config_phone_present', 'wire_agency_country_config_present', 'wire_phone_use_type_values_sanitized', 'wire_phone_location_values_sanitized',
            'wire_has_payment_or_hold_mode',
            'wire_ticketing_enabled',
            'wire_flight_offer_segment_count', 'wire_flight_details_segment_count', 'wire_traveler_count',
            'wire_fare_basis_count', 'wire_booking_class_count', 'wire_has_validating_carrier',
            'wire_has_amount', 'wire_has_currency',
            'wire_gender_values_sanitized', 'wire_gender_enum_valid',
            'wire_has_remarks', 'wire_remarks_count',
            'wire_traveler_field_style', 'wire_has_givenName', 'wire_has_given_name',
            'wire_has_passengerCode', 'wire_has_passengerTypeCode',
            'wire_traveler_required_fields_valid', 'wire_invalid_traveler_field_keys',
            'wire_null_path_count', 'wire_null_paths', 'wire_required_null_paths',
            'wire_has_any_nulls', 'wire_nulls_safe_to_omit', 'wire_payload_null_free',
            'wire_contract_valid', 'wire_invalid_contract_keys',
            'wire_segment_field_style', 'wire_segment_required_fields_valid', 'wire_invalid_segment_field_keys',
            'application_results_status', 'application_results_incomplete',
            'host_warning_modules', 'host_warning_sabre_codes', 'host_warning_messages_truncated',
            'passenger_records_error_digest_present', 'pnr_present_in_response_body',
            'airline_segment_status', 'affected_flight_numbers', 'halt_on_status_received',
            'probable_issue', 'retry_blocker_reasons',
        ];
        $out = [];
        foreach ($keys as $k) {
            if (! array_key_exists($k, $diag)) {
                continue;
            }
            $v = $diag[$k];
            if (is_scalar($v) || is_bool($v) || $v === null) {
                $out[$k] = $v;
            } elseif (is_array($v) && in_array($k, [
                'safe_validation_excerpts', 'safe_validation_excerpts_structured', 'v25_airprice_pricing_qualifiers_digest',
                'response_safe_keys', 'response_error_codes', 'response_error_messages',
                'response_error_fields', 'response_error_paths', 'response_missing_fields', 'response_top_level_keys', 'response_additional_messages',
                'wire_root_keys', 'wire_gender_values_sanitized', 'wire_invalid_traveler_field_keys',
                'wire_null_paths', 'wire_required_null_paths', 'wire_invalid_contract_keys',
                'wire_invalid_segment_field_keys', 'wire_agency_phone_paths', 'wire_phone_use_type_values_sanitized', 'wire_phone_location_values_sanitized',
                'host_warning_modules', 'host_warning_sabre_codes', 'host_warning_messages_truncated',
                'affected_flight_numbers', 'retry_blocker_reasons',
            ], true)) {
                $out[$k] = $v;
            }
        }
        foreach ($diag as $k => $v) {
            if (! is_string($k) || ! str_starts_with($k, 'traveler_')) {
                continue;
            }
            if (is_bool($v) || is_scalar($v) || $v === null) {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    /**
     * Phase B3 alias: create PNR hold / passenger record (maps to {@see createBooking()}).
     *
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $passengerData
     * @return array<string, mixed>
     */
    public function createPnrHold(array $offer, array $passengerData, ?int $bookingIdForDiagnostics = null): array
    {
        return $this->createBooking($offer, $passengerData, $bookingIdForDiagnostics);
    }

    /**
     * @param  list<string>  $wireRootKeys
     * @param  array<string, mixed>  $payloadSummary
     * @param  array<string, mixed>  $linkage
     * @param  array<string, mixed>  $linkageDigest
     * @param  array<string, mixed>  $errorDigest
     * @param  array{top_level_keys: string, key_paths: string, empty_body: string, json_valid: string, candidate_fields: string, candidate_count: string}  $responseStructure
     * @return array<string, mixed>
     */
    protected function revalidationFailureOutcome(
        ?int $http,
        int $durationMs,
        string $reasonCode,
        string $message,
        array $payloadSummary,
        array $linkage,
        array $linkageDigest,
        array $errorDigest,
        string $styleLabel,
        string $endpointPath,
        array $wireRootKeys,
        array $responseStructure,
        string $failureClass,
        string $freezeFingerprint,
        array $payloadCoverageSummary = [],
    ): array {
        return [
            'success' => false,
            'http_status' => $http,
            'duration_ms' => $durationMs,
            'reason_code' => $reasonCode,
            'message' => $message,
            'payload_safe_summary' => $payloadSummary,
            'payload_coverage_summary' => $payloadCoverageSummary,
            'linkage' => $linkage,
            'linkage_digest' => $linkageDigest,
            'error_digest' => $errorDigest,
            'payload_style' => $styleLabel,
            'endpoint_path' => $endpointPath,
            'wire_root_keys' => $wireRootKeys,
            'includes_sabre_error_27131' => false,
            'changed_from_typical_27131_failure' => true,
            'response_structure' => $responseStructure,
            'revalidation_failure_class' => $failureClass,
            'revalidation_freeze_fingerprint' => $freezeFingerprint,
        ];
    }

    /**
     * @param  array<string, mixed>  $apiDraft
     */
    protected function draftSegmentsMissingBookingClass(array $apiDraft): bool
    {
        foreach (is_array($apiDraft['segments'] ?? null) ? $apiDraft['segments'] : [] as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            if (trim((string) ($seg['booking_class'] ?? $seg['class_of_service'] ?? '')) === '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $revalidationOutcome
     */
    /**
     * @param  array<string, mixed>  $apiDraft
     * @return array<string, mixed>|null
     */
    protected function revalidationFrozenRetryBlockedOutcome(
        ?int $bookingId,
        array $apiDraft,
        int $paxCount,
        int $segCount,
        int $connId,
        string $selectedOffer,
        float $fareAmt,
        string $fareCur,
    ): ?array {
        if ($bookingId === null || $bookingId < 1) {
            return null;
        }
        $booking = Booking::query()->find($bookingId);
        if ($booking === null) {
            return null;
        }
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $frozenAt = trim((string) ($meta['revalidation_frozen_at'] ?? ''));
        $storedFingerprint = trim((string) ($meta['revalidation_freeze_fingerprint'] ?? ''));
        if ($frozenAt === '' || $storedFingerprint === '') {
            return null;
        }

        $payload = $this->revalidationBuilder->buildPayload($apiDraft);
        $currentFingerprint = $this->revalidationBuilder->revalidationPayloadFreezeFingerprint($payload, $apiDraft);
        if (! hash_equals($storedFingerprint, $currentFingerprint)) {
            return null;
        }

        return [
            'success' => false,
            'status' => 'needs_review',
            'message' => (string) __('Sabre revalidation previously failed for this itinerary; refresh the offer or re-shop before retrying.'),
            'live_call_attempted' => false,
            'live_call_allowed' => true,
            'passenger_count' => $paxCount,
            'segment_count' => $segCount,
            'supplier_connection_id' => $connId,
            'selected_offer_id' => $selectedOffer,
            'fare_amount' => $fareAmt,
            'fare_currency' => $fareCur,
            'error_code' => 'sabre_revalidation_frozen_retry_blocked',
            'reason_code' => 'revalidation_frozen_snapshot_unchanged',
            'revalidation_attempted' => false,
            'revalidation_outcome' => 'frozen',
            'revalidation_failure_class' => (string) ($meta['revalidation_failure_class'] ?? 'revalidation_frozen'),
            'booking_schema' => $this->effectiveSabreBookingSchema(),
        ];
    }

    protected function persistRevalidationFreezeOnBooking(?int $bookingId, array $revalidationOutcome): void
    {
        if ($bookingId === null || $bookingId < 1) {
            return;
        }
        $booking = Booking::query()->find($bookingId);
        if ($booking === null) {
            return;
        }
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $fingerprint = trim((string) ($revalidationOutcome['revalidation_freeze_fingerprint'] ?? ''));
        if ($fingerprint === '') {
            return;
        }
        $meta['revalidation_frozen_at'] = now()->toIso8601String();
        $meta['revalidation_freeze_fingerprint'] = $fingerprint;
        $meta['revalidation_failure_class'] = (string) ($revalidationOutcome['revalidation_failure_class']
            ?? $revalidationOutcome['reason_code']
            ?? 'sabre_revalidation_failed');
        $booking->meta = $meta;
        $booking->save();
    }

    /**
     * Run a sanitized Sabre revalidation HTTP call before live Trip Orders {@code createBooking}. Returns success +
     * extracted fare linkage when the response is HTTP 2xx with a usable structure, or {@code success=false} otherwise
     * so the caller can short-circuit live booking with {@code sabre_revalidation_failed} (public {@code error_code})
     * and a specific {@code reason_code}: {@code sabre_revalidation_application_warning_or_error},
     * {@code sabre_revalidation_empty_or_unusable_response}, or {@code sabre_revalidation_failed} (HTTP/transport).
     *
     * @param  array<string, mixed>  $apiDraft  Internal booking draft (with \_sabre_shop_identifiers)
     * @param  ?string  $payloadStyle  Optional override for {@code SABRE_REVALIDATE_PAYLOAD_STYLE} (local/testing)
     * @param  ?string  $pathOverride  Optional POST path override (local/testing inspect only; production uses config)
     * @return array<string, mixed>
     */
    public function runRevalidationBeforeBooking(
        array $apiDraft,
        SupplierConnection $connection,
        ?string $payloadStyle = null,
        ?string $pathOverride = null,
        ?int $bookingIdForDiagnostics = null,
        ?string $bookingReference = null,
    ): array {
        $effectiveStyle = $payloadStyle !== null && trim($payloadStyle) !== ''
            ? trim($payloadStyle)
            : null;
        $payload = $this->revalidationBuilder->buildPayload($apiDraft, $effectiveStyle);
        $payloadSummary = $this->revalidationBuilder->safePayloadSummary($payload);
        $payloadCoverageSummary = $this->revalidationBuilder->normalizedPayloadCoverageSummary($payload);
        $styleLabel = (string) ($payload['_ota_revalidate_payload_style'] ?? 'bfm_revalidate_v1');
        $freezeFingerprint = $this->revalidationBuilder->revalidationPayloadFreezeFingerprint($payload, $apiDraft);
        $wire = $this->revalidationBuilder->wireableRequestPayload($payload);
        $wireRootKeys = array_keys($wire);
        $endpointPath = $this->effectiveRevalidatePathSuffix($pathOverride);
        $expectedSegmentCount = count(is_array($apiDraft['segments'] ?? null) ? $apiDraft['segments'] : []);
        $startedAt = microtime(true);

        try {
            $this->revalidationBuilder->assertGatekeeperOrThrow($payload, $apiDraft);
        } catch (SabreRevalidateGatekeeperException $e) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            return $this->revalidationFailureOutcome(
                http: null,
                durationMs: $durationMs,
                reasonCode: 'sabre_revalidation_gatekeeper_failed',
                message: $this->contextualRevalidationFailureMessage(
                    'Sabre revalidation blocked by pre-flight gatekeeper; Trip Orders booking was not attempted.',
                ),
                payloadSummary: $payloadSummary,
                linkage: [],
                linkageDigest: [],
                errorDigest: [
                    'response_error_codes' => ['gatekeeper_failed'],
                    'response_error_messages' => array_slice($e->violations, 0, 12),
                ],
                styleLabel: $styleLabel,
                endpointPath: $endpointPath,
                wireRootKeys: $wireRootKeys,
                responseStructure: [
                    'top_level_keys' => '',
                    'key_paths' => '',
                    'empty_body' => 'true',
                    'json_valid' => 'false',
                    'candidate_fields' => '',
                    'candidate_count' => '0',
                ],
                failureClass: 'gatekeeper_failed',
                freezeFingerprint: $freezeFingerprint,
                payloadCoverageSummary: $payloadCoverageSummary,
            );
        }

        try {
            $response = $this->sabreClient->postRevalidatePayload($connection, $payload, $pathOverride);
        } catch (Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            Log::notice('sabre.revalidate.exception', [
                'provider' => SupplierProvider::Sabre->value,
                'connection_id' => $connection->id,
                'duration_ms' => $durationMs,
                'exception_class' => $e::class,
            ]);

            return [
                'success' => false,
                'http_status' => null,
                'duration_ms' => $durationMs,
                'reason_code' => 'sabre_revalidation_failed',
                'message' => 'Sabre revalidation request could not be completed.',
                'payload_safe_summary' => $payloadSummary,
                'payload_coverage_summary' => $payloadCoverageSummary,
                'linkage' => [],
                'payload_style' => $styleLabel,
                'endpoint_path' => $endpointPath,
                'wire_root_keys' => $wireRootKeys,
                'includes_sabre_error_27131' => false,
                'changed_from_typical_27131_failure' => true,
                'error_digest' => [],
                'response_structure' => [
                    'top_level_keys' => '',
                    'key_paths' => '',
                    'empty_body' => 'true',
                    'json_valid' => 'false',
                    'candidate_fields' => '',
                    'candidate_count' => '0',
                ],
            ];
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $http = $response->status();
        $json = $response->json();
        $arr = is_array($json) ? $json : [];
        $rawBody = $response->body();
        $responseStructure = $this->normalizeRevalidateResponseStructureForOutcome(
            $this->revalidationBuilder->digestRevalidateResponseStructure($rawBody, is_array($json) ? $json : null)
        );
        $linkage = $this->revalidationBuilder->extractFareLinkage($arr);
        $linkageDigest = $this->revalidationBuilder->linkageDigest($linkage);
        $errorDigest = $response->successful()
            ? []
            : $this->revalidationBuilder->extractSafeErrorDigest($arr);
        $includes27131 = $this->revalidationErrorDigestIncludes27131($errorDigest);
        $changed27131 = $this->revalidationResponseDeviatesFrom27131Pattern($http, $errorDigest);

        if (! $response->successful()) {
            Log::notice('sabre.revalidate.http_failed', [
                'provider' => SupplierProvider::Sabre->value,
                'connection_id' => $connection->id,
                'http_status' => $http,
                'duration_ms' => $durationMs,
            ]);

            return [
                'success' => false,
                'http_status' => $http,
                'duration_ms' => $durationMs,
                'reason_code' => 'sabre_revalidation_failed',
                'message' => $this->contextualRevalidationFailureMessage(
                    'Sabre revalidation returned HTTP '.$http.'; Trip Orders booking was not attempted.',
                    $http,
                ),
                'payload_safe_summary' => $payloadSummary,
                'payload_coverage_summary' => $payloadCoverageSummary,
                'linkage' => [],
                'linkage_digest' => $linkageDigest,
                'error_digest' => $errorDigest,
                'payload_style' => $styleLabel,
                'endpoint_path' => $endpointPath,
                'wire_root_keys' => $wireRootKeys,
                'includes_sabre_error_27131' => $includes27131,
                'changed_from_typical_27131_failure' => $changed27131,
                'response_structure' => $responseStructure,
            ];
        }

        $girFailure = $this->revalidationBuilder->evaluateGroupedItineraryMessages($arr);
        if ($girFailure !== null) {
            Log::notice('sabre.revalidate.gir_messages_failed', [
                'provider' => SupplierProvider::Sabre->value,
                'connection_id' => $connection->id,
                'http_status' => $http,
                'duration_ms' => $durationMs,
                'failure_class' => $girFailure['failure_class'] ?? null,
            ]);

            return $this->revalidationFailureOutcome(
                http: $http,
                durationMs: $durationMs,
                reasonCode: 'sabre_revalidation_application_warning_or_error',
                message: $this->contextualRevalidationFailureMessage(
                    'Sabre revalidation returned HTTP 200 with groupedItineraryResponse errors; Trip Orders booking was not attempted.',
                ),
                payloadSummary: $payloadSummary,
                linkage: $linkage,
                linkageDigest: $linkageDigest,
                errorDigest: [
                    'response_error_codes' => $girFailure['codes'] ?? [],
                    'response_error_messages' => $girFailure['messages'] ?? [],
                    'revalidation_failure_class' => $girFailure['failure_class'] ?? 'gir_message',
                ],
                styleLabel: $styleLabel,
                endpointPath: $endpointPath,
                wireRootKeys: $wireRootKeys,
                responseStructure: $responseStructure,
                failureClass: (string) ($girFailure['failure_class'] ?? 'gir_message'),
                freezeFingerprint: $freezeFingerprint,
                payloadCoverageSummary: $payloadCoverageSummary,
            );
        }

        $warnDigest = $this->revalidationBuilder->extractHttp200ApplicationWarningDigest($arr);
        if ($this->revalidationBuilder->http200ApplicationWarningDigestNonEmpty($warnDigest)) {
            Log::notice('sabre.revalidate.application_warnings', [
                'provider' => SupplierProvider::Sabre->value,
                'connection_id' => $connection->id,
                'http_status' => $http,
                'duration_ms' => $durationMs,
            ]);

            return $this->revalidationFailureOutcome(
                http: $http,
                durationMs: $durationMs,
                reasonCode: 'sabre_revalidation_application_warning_or_error',
                message: $this->contextualRevalidationFailureMessage(
                    'Sabre revalidation returned HTTP 200 with application warnings or errors; Trip Orders booking was not attempted.',
                ),
                payloadSummary: $payloadSummary,
                linkage: $linkage,
                linkageDigest: $linkageDigest,
                errorDigest: $warnDigest,
                styleLabel: $styleLabel,
                endpointPath: $endpointPath,
                wireRootKeys: $wireRootKeys,
                responseStructure: $responseStructure,
                failureClass: 'application_warning',
                freezeFingerprint: $freezeFingerprint,
                payloadCoverageSummary: $payloadCoverageSummary,
            );
        }

        $fareBasisGap = $this->revalidationBuilder->assertPerSegmentFareBasisComplete($arr, $expectedSegmentCount);
        if ($fareBasisGap !== null) {
            return $this->revalidationFailureOutcome(
                http: $http,
                durationMs: $durationMs,
                reasonCode: 'sabre_revalidation_empty_or_unusable_response',
                message: $this->contextualRevalidationFailureMessage(
                    'Sabre revalidation returned HTTP 200 without per-segment fare basis; Trip Orders booking was not attempted.',
                ),
                payloadSummary: $payloadSummary,
                linkage: $linkage,
                linkageDigest: $linkageDigest,
                errorDigest: [
                    'response_error_codes' => ['fare_basis_incomplete'],
                    'response_error_messages' => [
                        'expected='.$fareBasisGap['expected'].' actual='.$fareBasisGap['actual'],
                    ],
                    'revalidation_failure_class' => $fareBasisGap['failure_class'] ?? 'fare_basis_incomplete',
                ],
                styleLabel: $styleLabel,
                endpointPath: $endpointPath,
                wireRootKeys: $wireRootKeys,
                responseStructure: $responseStructure,
                failureClass: (string) ($fareBasisGap['failure_class'] ?? 'fare_basis_incomplete'),
                freezeFingerprint: $freezeFingerprint,
                payloadCoverageSummary: $payloadCoverageSummary,
            );
        }

        $pricingTripwire = $this->revalidationBuilder->evaluateRevalidationPricingTripwire($linkage, $apiDraft);
        if ($pricingTripwire !== null) {
            return $this->revalidationFailureOutcome(
                http: $http,
                durationMs: $durationMs,
                reasonCode: 'sabre_revalidation_empty_or_unusable_response',
                message: (string) ($pricingTripwire['message'] ?? 'Revalidation pricing tripwire failed.'),
                payloadSummary: $payloadSummary,
                linkage: $linkage,
                linkageDigest: $linkageDigest,
                errorDigest: [
                    'response_error_codes' => [(string) ($pricingTripwire['failure_class'] ?? 'pricing_tripwire')],
                    'revalidation_failure_class' => $pricingTripwire['failure_class'] ?? 'pricing_tripwire',
                ],
                styleLabel: $styleLabel,
                endpointPath: $endpointPath,
                wireRootKeys: $wireRootKeys,
                responseStructure: $responseStructure,
                failureClass: (string) ($pricingTripwire['failure_class'] ?? 'pricing_tripwire'),
                freezeFingerprint: $freezeFingerprint,
                payloadCoverageSummary: $payloadCoverageSummary,
            );
        }

        $linkage['expected_segment_count'] = $expectedSegmentCount;
        $linkageDigest = $this->revalidationBuilder->linkageDigest($linkage);

        $usableLinkage = $linkage !== []
            && ($linkageDigest['per_segment_fare_basis_complete'] ?? false) === true
            && ($linkageDigest['has_revalidated_fare'] ?? false)
            && ($linkageDigest['has_revalidated_currency'] ?? false);

        if (! $usableLinkage) {
            Log::notice('sabre.revalidate.no_linkage', [
                'provider' => SupplierProvider::Sabre->value,
                'connection_id' => $connection->id,
                'http_status' => $http,
                'duration_ms' => $durationMs,
            ]);

            return $this->revalidationFailureOutcome(
                http: $http,
                durationMs: $durationMs,
                reasonCode: 'sabre_revalidation_empty_or_unusable_response',
                message: $this->contextualRevalidationFailureMessage(
                    'Sabre revalidation returned HTTP 200 without usable fare/offer linkage; Trip Orders booking was not attempted.',
                ),
                payloadSummary: $payloadSummary,
                linkage: $linkage,
                linkageDigest: $linkageDigest,
                errorDigest: $errorDigest,
                styleLabel: $styleLabel,
                endpointPath: $endpointPath,
                wireRootKeys: $wireRootKeys,
                responseStructure: $responseStructure,
                failureClass: 'unusable_linkage',
                freezeFingerprint: $freezeFingerprint,
                payloadCoverageSummary: $payloadCoverageSummary,
            );
        }

        return [
            'success' => true,
            'http_status' => $http,
            'duration_ms' => $durationMs,
            'reason_code' => 'sabre_revalidation_ok',
            'message' => 'Sabre revalidation returned usable fare linkage.',
            'payload_safe_summary' => $payloadSummary,
            'payload_coverage_summary' => $payloadCoverageSummary,
            'linkage' => $linkage,
            'linkage_digest' => $linkageDigest,
            'payload_style' => $styleLabel,
            'endpoint_path' => $endpointPath,
            'wire_root_keys' => $wireRootKeys,
            'includes_sabre_error_27131' => false,
            'changed_from_typical_27131_failure' => true,
            'error_digest' => [],
            'response_structure' => $responseStructure,
        ];
    }

    /**
     * Flatten B20 digest keys for Artisan / safe_summary consumers (no raw body).
     *
     * @param  array<string, mixed>  $digest  Output of {@see SabreRevalidationPayloadBuilder::digestRevalidateResponseStructure()}
     * @return array{top_level_keys: string, key_paths: string, empty_body: string, json_valid: string, candidate_fields: string, candidate_count: string}
     */
    protected function normalizeRevalidateResponseStructureForOutcome(array $digest): array
    {
        $keys = isset($digest['response_top_level_keys']) && is_array($digest['response_top_level_keys'])
            ? implode(',', $digest['response_top_level_keys']) : '';
        $paths = isset($digest['response_nested_key_paths']) && is_array($digest['response_nested_key_paths'])
            ? implode(' | ', array_slice($digest['response_nested_key_paths'], 0, 48)) : '';
        $cands = isset($digest['response_scalar_candidates']) && is_array($digest['response_scalar_candidates'])
            ? implode(' | ', array_slice($digest['response_scalar_candidates'], 0, 24)) : '';
        $cnt = isset($digest['candidate_count']) ? (string) (int) $digest['candidate_count'] : '0';

        return [
            'top_level_keys' => substr($keys, 0, 400),
            'key_paths' => substr($paths, 0, 1200),
            'empty_body' => ! empty($digest['response_body_empty']) ? 'true' : 'false',
            'json_valid' => ! empty($digest['response_json_valid']) ? 'true' : 'false',
            'candidate_fields' => substr($cands, 0, 1200),
            'candidate_count' => $cnt,
        ];
    }

    /**
     * Normalized revalidate POST path (config or inspect override).
     */
    protected function effectiveRevalidatePathSuffix(?string $pathOverride): string
    {
        $configured = (string) config('suppliers.sabre.revalidate_path', '/v4/shop/flights/revalidate');
        $configured = $configured !== '' && $configured[0] === '/' ? $configured : '/'.$configured;
        $path = $pathOverride !== null && trim($pathOverride) !== ''
            ? trim($pathOverride)
            : $configured;

        return $path !== '' && $path[0] === '/' ? $path : '/'.$path;
    }

    /**
     * @param  array<string, mixed>  $digest
     */
    protected function revalidationErrorDigestIncludes27131(array $digest): bool
    {
        foreach ($digest['response_error_codes'] ?? [] as $c) {
            if (trim((string) $c) === '27131') {
                return true;
            }
        }
        foreach ($digest['response_error_messages'] ?? [] as $m) {
            if (str_contains((string) $m, '27131')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Heuristic: Sabre 27131 on HTTP 400/422 is the observed “default” revalidate failure; other statuses or codes imply a different contract/path outcome.
     *
     * @param  array<string, mixed>  $digest
     */
    protected function revalidationResponseDeviatesFrom27131Pattern(int $http, array $digest): bool
    {
        if (in_array($http, [403, 404, 405], true)) {
            return true;
        }
        if ($http >= 200 && $http < 300) {
            return true;
        }
        if (in_array($http, [400, 422], true)) {
            return ! $this->revalidationErrorDigestIncludes27131($digest);
        }

        return false;
    }

    /**
     * Placeholder / manual-gated ticketing. Does not perform live ticketing HTTP unless product wiring is completed.
     *
     * @return array<string, mixed>
     */
    public function issueTicket(Booking $booking, User $actor): array
    {
        if (! $this->isTicketingEnabled()) {
            return [
                'success' => false,
                'status' => 'disabled',
                'message' => (string) __('Sabre automated ticketing is not enabled.'),
                'live_call_attempted' => false,
            ];
        }

        if (! $this->mayPerformLiveSabreBookingCall()) {
            return [
                'success' => false,
                'status' => 'dry_run',
                'message' => (string) __('Sabre ticketing requires live booking calls to be enabled and certified.'),
                'live_call_attempted' => false,
            ];
        }

        return [
            'success' => false,
            'status' => 'pending_implementation',
            'message' => (string) __('Sabre issue ticket API is not implemented; use manual ticketing.'),
            'live_call_attempted' => false,
        ];
    }

    /**
     * B63: Pre-live Passenger Records guard for multi-segment or search-corrected-order itineraries.
     *
     * @param  array<string, mixed>  $offer
     * @return array{guard_trigger: string, segment_order_corrected: bool}|null
     */
    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $apiDraft
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $apiDraft
     * @param  array<string, mixed>  $b67RevalidationSlice
     * @return array<string, mixed>
     */
    /**
     * @param  list<array<string, mixed>>  $structuralSegments  Shop/pre-merge rows for B65 structural checks (B67 wire merge may differ).
     */
    protected function passengerRecordsMultiSegmentEligibilitySlice(
        array $offer,
        array $apiDraft,
        array $b67RevalidationSlice = [],
        array $structuralSegments = [],
    ): array {
        $prep = is_array($apiDraft['_b65_multi_segment_prep'] ?? null) ? $apiDraft['_b65_multi_segment_prep'] : [];
        $segs = $structuralSegments !== []
            ? $structuralSegments
            : (is_array($apiDraft['segments'] ?? null) ? array_values($apiDraft['segments']) : []);

        $out = SabrePassengerRecordsMultiSegmentSellVerifier::evaluate(
            $offer,
            $segs,
            (bool) ($prep['segment_order_repaired_for_sell'] ?? false),
            (bool) ($prep['date_repair_applied'] ?? false),
        );
        $out = array_merge($out, $b67RevalidationSlice);

        $requiresRev = ($out['passenger_records_multi_segment_requires_revalidation'] ?? false) === true;
        $revOk = ($out['passenger_records_multi_segment_revalidation_ok'] ?? false) === true;
        $structuralEligible = ($out['passenger_records_multi_segment_eligible'] ?? false) === true;
        $out['passenger_records_multi_segment_structural_eligible'] = $structuralEligible;
        $out['passenger_records_multi_segment_eligible'] = $structuralEligible && (! $requiresRev || $revOk);

        if ($requiresRev && ! $revOk) {
            $reasons = is_array($out['passenger_records_multi_segment_validation_failed_reasons'] ?? null)
                ? $out['passenger_records_multi_segment_validation_failed_reasons']
                : [];
            $revReason = trim((string) ($out['passenger_records_multi_segment_revalidation_failed_reason'] ?? ''));
            if ($revReason !== '') {
                $reasons[] = $revReason;
            }
            $out['passenger_records_multi_segment_validation_failed_reasons'] = array_values(array_unique(array_slice($reasons, 0, 24)));
        }

        return $out;
    }

    /**
     * B67: Revalidation + per-segment class linkage required before verified multi-segment Passenger Records live CPNR.
     *
     * @param  array<string, mixed>|null  $revalidationOutcome
     * @param  array<string, mixed>  $apiDraft
     * @return array<string, mixed>
     */
    protected function passengerRecordsVerifiedMultiSegmentRevalidationSlice(
        int $segCount,
        bool $revEnabled,
        ?array $revalidationOutcome,
        bool $revalidationSkippedByConfig,
        bool $allowBypass,
        array $apiDraft,
    ): array {
        $fresh = $this->attemptFreshnessStrategyDecision;
        if (is_array($fresh) && ($fresh['iati_like_selected'] ?? false) === true) {
            return [
                'passenger_records_multi_segment_requires_revalidation' => false,
                'passenger_records_multi_segment_revalidation_required' => false,
                'passenger_records_multi_segment_revalidation_ok' => true,
                'passenger_records_multi_segment_revalidation_applied' => false,
                'passenger_records_multi_segment_revalidation_failed_reason' => null,
            ];
        }

        $requires = $this->effectiveSabreBookingSchema() === 'create_passenger_name_record'
            && $segCount >= 2
            && $this->isPassengerRecordsAllowVerifiedMultiSegmentEnabled();

        if (! $requires) {
            return [
                'passenger_records_multi_segment_requires_revalidation' => false,
                'passenger_records_multi_segment_revalidation_required' => false,
                'passenger_records_multi_segment_revalidation_ok' => true,
                'passenger_records_multi_segment_revalidation_applied' => false,
                'passenger_records_multi_segment_revalidation_failed_reason' => null,
            ];
        }

        $failedReason = null;
        $ok = false;
        $applied = false;

        if (! $revEnabled) {
            $failedReason = 'revalidation_disabled_by_config';
        } elseif ($revalidationSkippedByConfig && $allowBypass) {
            $failedReason = 'revalidation_bypassed_after_failure';
        } elseif ($revalidationSkippedByConfig) {
            $failedReason = 'revalidation_skipped';
        } elseif ($revalidationOutcome === null || ($revalidationOutcome['success'] ?? false) !== true) {
            $failedReason = 'revalidation_failed';
        } else {
            $linkage = is_array($apiDraft['_fare_linkage'] ?? null) ? $apiDraft['_fare_linkage'] : [];
            $segs = is_array($apiDraft['segments'] ?? null) ? array_values($apiDraft['segments']) : [];
            if (! $this->bookingPayloadBuilder->linkageCoversSegmentsWithClassOfService($segs, $linkage)) {
                $failedReason = 'revalidation_linkage_missing_per_segment_class';
            } else {
                $ok = true;
                $applied = true;
            }
        }

        return [
            'passenger_records_multi_segment_requires_revalidation' => true,
            'passenger_records_multi_segment_revalidation_required' => true,
            'passenger_records_multi_segment_revalidation_ok' => $ok,
            'passenger_records_multi_segment_revalidation_applied' => $applied,
            'passenger_records_multi_segment_revalidation_failed_reason' => $failedReason,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>|null
     */
    protected function resolvePublicCertifiedRouteForAttempt(?int $bookingIdForDiagnostics, array $options): ?array
    {
        if ($this->isScenarioRunnerPnrCreateActive($options)) {
            return null;
        }
        if (! config('suppliers.sabre.certified_route_selector_public_checkout_enabled', true)) {
            return null;
        }
        if (($options['certification_full_itinerary_fallback'] ?? false) === true) {
            return null;
        }
        if (($options['admin_booking_route_override'] ?? false) === true) {
            return null;
        }
        if ($bookingIdForDiagnostics === null || $bookingIdForDiagnostics < 1) {
            return null;
        }
        $booking = Booking::query()->find($bookingIdForDiagnostics);
        if ($booking === null) {
            return null;
        }

        return $this->certifiedRouteSelector->selectForBooking($booking);
    }

    /**
     * Sprint 11B: Resolve route for IATI-like style gating on admin/cert attempts (no public-checkout block).
     *
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>|null  $publicRouteSelection
     * @return array<string, mixed>|null
     */
    protected function resolveRouteSelectionForStyleDecision(
        ?int $bookingIdForDiagnostics,
        array $options,
        ?array $publicRouteSelection,
    ): ?array {
        if ($publicRouteSelection !== null) {
            return $publicRouteSelection;
        }
        if ($bookingIdForDiagnostics === null || $bookingIdForDiagnostics < 1) {
            return null;
        }
        if ($this->isScenarioRunnerPnrCreateActive($options)) {
            $booking = Booking::query()->find($bookingIdForDiagnostics);

            return $booking !== null ? $this->certifiedRouteSelector->selectForBooking($booking) : null;
        }
        if (($options['admin_booking_route_override'] ?? false) !== true
            && ($options['certification_full_itinerary_fallback'] ?? false) !== true) {
            return null;
        }
        $booking = Booking::query()->find($bookingIdForDiagnostics);
        if ($booking === null) {
            return null;
        }

        return $this->certifiedRouteSelector->selectForBooking($booking);
    }

    /**
     * @param  array<string, mixed>  $routeSelection
     */
    protected function routeSelectionAllowsIatiLikeCpnrConsideration(array $routeSelection): bool
    {
        $status = (string) ($routeSelection['route_status'] ?? '');
        if (! in_array($status, [
            SabreCertifiedRouteSelector::STATUS_CERTIFIED,
            SabreCertifiedRouteSelector::STATUS_CONTROLLED_CERTIFIED,
        ], true)) {
            return false;
        }
        $category = (string) ($routeSelection['category'] ?? '');
        if ($category === SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_DIRECT_SAME_CARRIER) {
            return true;
        }
        if ($category === SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_CONNECTING_SAME_CARRIER_GDS) {
            return SabreCertifiedRouteSelector::isConnectingSameCarrierGdsEnabled();
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $routeSelection
     * @param  array<string, mixed>  $draft
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>
     */
    /**
     * Sprint 11H: Admin/staff controlled same-carrier 2-segment may bypass public-checkout live_booking gate when readiness passes.
     *
     * @param  array<string, mixed>  $routeSelection
     * @param  array<string, mixed>  $options
     */
    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function mergeControlledStaffPnrOptionsIntoBookingResult(array $result, array $options): array
    {
        if (($options['allow_controlled_staff_pnr'] ?? false) !== true) {
            return $result;
        }

        $result['allow_controlled_staff_pnr'] = true;
        $source = trim((string) ($options['source'] ?? ''));
        if ($source !== '') {
            $result['source'] = $source;
        }

        return $result;
    }

    /**
     * Sprint 11I: Admin/staff controlled PNR action records staff supplier confirmation (no PII).
     */
    protected function recordAdminStaffSupplierConfirmation(Booking $booking, User $actor): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        if (trim((string) ($meta['staff_supplier_confirmation_confirmed_at'] ?? '')) !== '') {
            return;
        }

        $meta['staff_supplier_confirmation_confirmed_at'] = now()->toIso8601String();
        $meta['staff_supplier_confirmation_confirmed_by'] = $actor->id;
        $booking->forceFill(['meta' => $meta])->save();

        Log::info('sabre.admin_staff_supplier_confirmation', [
            'booking_id' => $booking->id,
            'actor_id' => $actor->id,
            'source' => 'admin_supplier_action',
        ]);
    }

    protected function publicCheckoutGateBypassActive(
        ?int $bookingIdForDiagnostics,
        array $routeSelection,
        array $options,
    ): bool {
        if ($this->isOperatorApprovedPnrBypassActive($options)) {
            return true;
        }

        return $this->controlledStaffPnrBypassesPublicCheckoutGate($bookingIdForDiagnostics, $routeSelection, $options)
            || $this->operationalPublicAutoPnrBypassesPublicCheckoutGate($bookingIdForDiagnostics, $routeSelection, $options)
            || $this->verifiedPublicAutoPnrBypassesPublicCheckoutGate($bookingIdForDiagnostics, $routeSelection, $options);
    }

    protected function operationalPublicAutoPnrBypassesPublicCheckoutGate(
        ?int $bookingIdForDiagnostics,
        array $routeSelection,
        array $options,
    ): bool {
        if (($options['allow_operational_public_auto_pnr'] ?? false) !== true
            && ($options['allow_operational_staff_pnr'] ?? false) !== true) {
            return false;
        }
        if ($bookingIdForDiagnostics === null || $bookingIdForDiagnostics < 1) {
            return false;
        }
        if ((string) ($routeSelection['category'] ?? '') !== SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_CONNECTING_SAME_CARRIER_GDS) {
            return false;
        }
        if ($this->isTicketingEnabled()) {
            return false;
        }

        $booking = Booking::query()->find($bookingIdForDiagnostics);
        if ($booking === null) {
            return false;
        }

        return app(SabreOperationalPnrReadiness::class)->wouldAttemptPnr($booking);
    }

    protected function verifiedPublicAutoPnrBypassesPublicCheckoutGate(
        ?int $bookingIdForDiagnostics,
        array $routeSelection,
        array $options,
    ): bool {
        if (($options['allow_verified_public_auto_pnr'] ?? false) !== true) {
            return false;
        }
        if ($bookingIdForDiagnostics === null || $bookingIdForDiagnostics < 1) {
            return false;
        }
        if ((string) ($routeSelection['category'] ?? '') !== SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_CONNECTING_SAME_CARRIER_GDS) {
            return false;
        }
        if ($this->isTicketingEnabled()) {
            return false;
        }

        $booking = Booking::query()->find($bookingIdForDiagnostics);
        if ($booking === null) {
            return false;
        }

        return app(SabreVerifiedAutoPnrReadiness::class)->canAttemptLivePublicAutoPnr($booking);
    }

    protected function controlledStaffPnrBypassesPublicCheckoutGate(
        ?int $bookingIdForDiagnostics,
        array $routeSelection,
        array $options,
    ): bool {
        if (($options['allow_controlled_staff_pnr'] ?? false) !== true) {
            return false;
        }
        if ($bookingIdForDiagnostics === null || $bookingIdForDiagnostics < 1) {
            return false;
        }
        if ((string) ($routeSelection['category'] ?? '') !== SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_CONNECTING_SAME_CARRIER_GDS) {
            return false;
        }
        if (! SabreCertifiedRouteSelector::isConnectingSameCarrierGdsEnabled()) {
            return false;
        }
        if ($this->isTicketingEnabled()) {
            return false;
        }

        $booking = Booking::query()->find($bookingIdForDiagnostics);
        if ($booking === null) {
            return false;
        }

        $diag = app(SabrePnrCertificationSupport::class)->buildMultiSegmentPnrReadinessDiagnostics($booking);

        return ($diag['admin_pnr_live_action_allowed'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $routeSelection
     * @param  array<string, mixed>  $draft
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function certifiedRouteBlockedResultFromDraft(
        ?int $bookingIdForDiagnostics,
        array $routeSelection,
        array $draft,
        array $offer,
        array $options = [],
    ): array {
        $booking = $bookingIdForDiagnostics !== null && $bookingIdForDiagnostics > 0
            ? Booking::query()->find($bookingIdForDiagnostics)
            : null;
        if ($booking !== null) {
            return $this->certifiedRouteBlockedResult($booking, $routeSelection, $options);
        }

        $pax = count(is_array($draft['passengers'] ?? null) ? $draft['passengers'] : []);
        $seg = count(is_array($draft['segments'] ?? null) ? $draft['segments'] : []);
        $connId = (int) ($draft['supplier_connection_id'] ?? 0);

        return array_merge(
            $this->certifiedRouteBlockedResultShape($routeSelection, $pax, $seg, $connId, $offer, $options),
            $this->resolveBookingEndpointSummary($connId),
        );
    }

    /**
     * @param  array<string, mixed>  $routeSelection
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function certifiedRouteBlockedResult(Booking $booking, array $routeSelection, array $options = []): array
    {
        $booking->loadMissing(['passengers']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null)
            ? $meta['normalized_offer_snapshot']
            : (is_array($meta['validated_offer_snapshot'] ?? null)
                ? $meta['validated_offer_snapshot']
                : (is_array($meta['flight_offer_snapshot'] ?? null) ? $meta['flight_offer_snapshot'] : []));
        $segments = is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : [];
        $connId = (int) ($meta['supplier_connection_id'] ?? 0);

        return $this->certifiedRouteBlockedResultShape(
            $routeSelection,
            $booking->passengers->count(),
            count($segments),
            $connId,
            $snapshot,
            $options,
            $booking,
        );
    }

    /**
     * @param  array<string, mixed>  $routeSelection
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function certifiedRouteBlockedResultShape(
        array $routeSelection,
        int $passengerCount,
        int $segmentCount,
        int $connectionId,
        array $offer,
        array $options = [],
        ?Booking $booking = null,
    ): array {
        $errorCode = (string) ($routeSelection['error_code'] ?? SabreCertifiedRouteSelector::ERROR_CODE_PENDING);
        $blockers = [];
        if (($options['allow_controlled_staff_pnr'] ?? false) === true && $booking !== null) {
            $diag = app(SabrePnrCertificationSupport::class)->buildMultiSegmentPnrReadinessDiagnostics($booking);
            $blockers = is_array($diag['blocker_reasons'] ?? null) ? $diag['blocker_reasons'] : [];
        }
        $message = ($options['allow_controlled_staff_pnr'] ?? false) === true
            ? $this->certifiedRouteSelector->adminStaffBlockedNoticeForSelection($routeSelection, $blockers)
            : $this->certifiedRouteSelector->publicCheckoutNoticeForSelection($routeSelection);

        return [
            'success' => false,
            'status' => 'needs_review',
            'message' => $message,
            'error_code' => $errorCode,
            'live_call_attempted' => false,
            'live_call_allowed' => true,
            'passenger_count' => $passengerCount,
            'segment_count' => $segmentCount,
            'supplier_connection_id' => $connectionId,
            'selected_offer_id' => (string) ($offer['id'] ?? $offer['offer_id'] ?? ''),
            'fare_amount' => (float) ($offer['total'] ?? 0),
            'fare_currency' => (string) ($offer['currency'] ?? 'PKR'),
            'pnr' => null,
            'provider_booking_id' => null,
            'provider_status' => null,
            'http_status' => null,
            'booking_schema' => 'create_passenger_name_record',
            'payload_schema' => SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1,
            'certified_route_selection' => [
                'category' => $routeSelection['category'] ?? null,
                'route_status' => $routeSelection['route_status'] ?? null,
                'endpoint_path' => $routeSelection['endpoint_path'] ?? null,
                'payload_style' => $routeSelection['payload_style'] ?? null,
                'recommended_path_label' => $routeSelection['recommended_path_label'] ?? null,
            ],
            'supplier_pnr_deferred_reason' => SabreCertifiedRouteSelector::DEFER_REASON,
            'allow_controlled_staff_pnr' => ($options['allow_controlled_staff_pnr'] ?? false) === true,
            'source' => (string) ($options['source'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $routeSelection
     */
    protected function persistCertifiedRouteDeferMeta(Booking $booking, array $routeSelection): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['supplier_pnr_deferred_reason'] = SabreCertifiedRouteSelector::DEFER_REASON;
        $meta['defer_supplier_booking_to_manual_review'] = true;
        $meta['certified_route_selection'] = [
            'category' => $routeSelection['category'] ?? null,
            'route_status' => $routeSelection['route_status'] ?? null,
            'endpoint_path' => $routeSelection['endpoint_path'] ?? null,
            'payload_style' => $routeSelection['payload_style'] ?? null,
        ];
        $booking->forceFill(['meta' => $meta])->save();
    }

    protected function complexItineraryPnrBlockedResult(Booking $booking): array
    {
        $booking->loadMissing(['passengers']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null)
            ? $meta['normalized_offer_snapshot']
            : (is_array($meta['validated_offer_snapshot'] ?? null)
                ? $meta['validated_offer_snapshot']
                : (is_array($meta['flight_offer_snapshot'] ?? null) ? $meta['flight_offer_snapshot'] : []));
        $segments = is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : [];
        $connId = (int) ($meta['supplier_connection_id'] ?? 0);

        return [
            'success' => false,
            'status' => 'needs_review',
            'message' => ComplexItineraryPolicy::publicCheckoutNotice(),
            'error_code' => ComplexItineraryPolicy::ERROR_CODE,
            'live_call_attempted' => false,
            'live_call_allowed' => true,
            'passenger_count' => $booking->passengers->count(),
            'segment_count' => count($segments),
            'supplier_connection_id' => $connId,
            'selected_offer_id' => (string) ($snapshot['id'] ?? $snapshot['offer_id'] ?? ''),
            'fare_amount' => (float) ($snapshot['total'] ?? 0),
            'fare_currency' => (string) ($snapshot['currency'] ?? 'PKR'),
            'pnr' => null,
            'provider_booking_id' => null,
            'provider_status' => null,
            'http_status' => null,
            'booking_schema' => $this->effectiveSabreBookingSchema(),
            'payload_schema' => $this->expectedSabrePayloadSchemaHintForFailures(),
            'supplier_pnr_deferred_reason' => ComplexItineraryPolicy::DEFER_REASON,
        ];
    }

    protected function persistComplexItineraryDeferMeta(Booking $booking): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['supplier_pnr_deferred_reason'] = ComplexItineraryPolicy::DEFER_REASON;
        $meta['defer_supplier_booking_to_manual_review'] = true;
        $booking->forceFill(['meta' => $meta])->save();
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $b65Eligibility
     * @return array{guard_trigger: string, segment_order_corrected: bool}|null
     */
    protected function passengerRecordsRiskyItineraryGuardSlice(array $offer, int $segCount, array $b65Eligibility = []): ?array
    {
        $orderCorrected = self::offerSegmentOrderCorrectedFlag($offer);
        $risky = $segCount >= 2 || $orderCorrected;
        if (! $risky) {
            return null;
        }

        if (($b65Eligibility['passenger_records_multi_segment_eligible'] ?? false) === true) {
            return null;
        }

        if ($segCount >= 2 && ($b65Eligibility['passenger_records_multi_segment_enabled'] ?? false) === true) {
            if (($b65Eligibility['passenger_records_multi_segment_structural_eligible'] ?? false) !== true) {
                return [
                    'guard_trigger' => 'multi_segment_validation_failed',
                    'segment_order_corrected' => $orderCorrected,
                ];
            }

            if (($b65Eligibility['passenger_records_multi_segment_revalidation_required'] ?? false) === true
                && ($b65Eligibility['passenger_records_multi_segment_revalidation_ok'] ?? false) !== true) {
                return [
                    'guard_trigger' => 'multi_segment_revalidation_required',
                    'segment_order_corrected' => $orderCorrected,
                ];
            }

            return [
                'guard_trigger' => 'multi_segment_validation_failed',
                'segment_order_corrected' => $orderCorrected,
            ];
        }

        if ($segCount >= 2) {
            return [
                'guard_trigger' => 'multi_segment',
                'segment_order_corrected' => $orderCorrected,
            ];
        }

        return [
            'guard_trigger' => 'segment_order_corrected',
            'segment_order_corrected' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    protected static function offerSegmentOrderCorrectedFlag(array $offer): bool
    {
        return data_get($offer, 'raw_payload.sabre_segment_order.segment_order_corrected') === true;
    }

    /**
     * E1C: Controlled admin/staff same-carrier connecting PNR — refresh stale offer context before live create.
     *
     * @param  array<string, mixed>  $snapshot
     */
    protected function controlledStaffOfferRefreshBeforePnr(
        Booking $booking,
        ?User $actor,
        array $snapshot,
        string $attemptSource,
    ): ?SupplierBookingResultData {
        $source = in_array($attemptSource, ['admin', 'staff'], true) ? $attemptSource : 'admin';
        if (! in_array($source, ['admin', 'staff'], true)) {
            return null;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($provider !== SupplierProvider::Sabre->value) {
            return null;
        }

        if ((bool) config('suppliers.sabre.ticketing_enabled', false)) {
            return null;
        }

        $freshness = app(SabreOfferFreshness::class);

        if (SabreOfferRefreshAcceptance::requiresAcceptance($booking)) {
            return $this->controlledStaffOfferValidationBlockedResult(
                $booking,
                $actor,
                SabreOfferRefreshAcceptance::ADMIN_MESSAGE,
                'offer_validation_required',
                SabreOfferRefreshAcceptance::ERROR_CODE_REQUIRES_ACCEPTANCE,
                $attemptSource,
            );
        }

        $certBypass = app(SabrePnrCertificationSupport::class)
            ->allowsControlledStaffPnrBypassDeferManualReview($booking, $source, true);

        if (! $this->controlledStaffOfferContextNeedsRefresh($booking, $snapshot, $certBypass)) {
            return null;
        }

        if (! (bool) config('suppliers.sabre.refresh_offer_before_public_pnr', true)) {
            $block = $this->resolveControlledStaffOfferFreshnessBlock($booking, $snapshot);
            $code = (string) ($block['code'] ?? 'offer_stale_before_checkout');
            $message = (string) ($block['message'] ?? $freshness->customerSafeMessage($code));

            return $this->controlledStaffOfferValidationBlockedResult(
                $booking,
                $actor,
                $message,
                'offer_validation_required',
                $code,
                $attemptSource,
            );
        }

        try {
            $refresh = $this->offerRefresh->refresh($booking, true);
        } catch (Throwable $e) {
            Log::warning('sabre.controlled_staff_offer_refresh_failed', [
                'booking_id' => $booking->id,
                'message' => SensitiveDataRedactor::sanitizeErrorMessage($e->getMessage()),
                'exception_class' => $e::class,
            ]);

            $refreshDiagnostics = $this->controlledStaffOfferRefreshSafeSummary(
                $booking,
                'offer_refresh_failed',
                'offer_refresh_failed',
                true,
                null,
                $e,
            );

            return $this->controlledStaffOfferValidationBlockedResult(
                $booking,
                $actor,
                (string) ($refreshDiagnostics['refresh_message'] ?? $freshness->customerSafeMessage('selected_offer_revalidation_failed')),
                'offer_refresh_failed',
                'offer_refresh_failed',
                $attemptSource,
                $refreshDiagnostics,
            );
        }

        $booking->refresh();
        $refreshError = trim((string) ($refresh['error'] ?? ''));
        if ($refreshError !== '') {
            $skipRefreshErrors = [
                'missing_stored_segments',
                'missing_search_criteria',
                'missing_offer_snapshot',
                'not_sabre_booking',
            ];
            if (! in_array($refreshError, $skipRefreshErrors, true)) {
                $refreshDiagnostics = $this->controlledStaffOfferRefreshSafeSummary(
                    $booking,
                    'offer_refresh_failed',
                    $refreshError,
                    true,
                    $refresh,
                );

                return $this->controlledStaffOfferValidationBlockedResult(
                    $booking,
                    $actor,
                    (string) ($refreshDiagnostics['refresh_message'] ?? $freshness->customerSafeMessage('selected_offer_revalidation_failed')),
                    'offer_refresh_failed',
                    $refreshError,
                    $attemptSource,
                    $refreshDiagnostics,
                );
            }

            $meta = is_array($booking->meta) ? $booking->meta : [];
            $snapshotAfterSkip = is_array($meta['normalized_offer_snapshot'] ?? null)
                ? $meta['normalized_offer_snapshot']
                : (is_array($meta['validated_offer_snapshot'] ?? null)
                    ? $meta['validated_offer_snapshot']
                    : (is_array($meta['flight_offer_snapshot'] ?? null) ? $meta['flight_offer_snapshot'] : $snapshot));
            $block = $this->resolveControlledStaffOfferFreshnessBlock($booking, $snapshotAfterSkip);
            $code = (string) ($block['code'] ?? 'offer_refresh_unavailable');
            $message = (string) ($block['message'] ?? $freshness->customerSafeMessage($code));

            return $this->controlledStaffOfferValidationBlockedResult(
                $booking,
                $actor,
                $message,
                'offer_validation_required',
                $code,
                $attemptSource,
                $this->controlledStaffOfferRefreshSafeSummary($booking, 'offer_validation_required', $code, true, $refresh),
            );
        }

        if (($refresh['match_found'] ?? false) !== true) {
            $refreshDiagnostics = $this->controlledStaffOfferRefreshSafeSummary(
                $booking,
                'offer_validation_required',
                'offer_refresh_unavailable',
                true,
                $refresh,
            );

            return $this->controlledStaffOfferValidationBlockedResult(
                $booking,
                $actor,
                (string) ($refreshDiagnostics['refresh_message'] ?? $freshness->customerSafeMessage('selected_offer_revalidation_failed')),
                'offer_validation_required',
                'offer_refresh_unavailable',
                $attemptSource,
                $refreshDiagnostics,
            );
        }

        if (($refresh['price_changed'] ?? false) === true || SabreOfferRefreshAcceptance::requiresAcceptance($booking->fresh())) {
            return $this->controlledStaffOfferValidationBlockedResult(
                $booking,
                $actor,
                SabreOfferRefreshAcceptance::ADMIN_MESSAGE,
                'offer_validation_required',
                SabreOfferRefreshAcceptance::ERROR_CODE_REQUIRES_ACCEPTANCE,
                $attemptSource,
                $this->controlledStaffOfferRefreshSafeSummary(
                    $booking,
                    'offer_validation_required',
                    SabreOfferRefreshAcceptance::ERROR_CODE_REQUIRES_ACCEPTANCE,
                    true,
                    $refresh,
                ),
            );
        }

        $this->markControlledStaffOfferValidationFresh($booking);
        $booking->refresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null)
            ? $meta['normalized_offer_snapshot']
            : (is_array($meta['validated_offer_snapshot'] ?? null)
                ? $meta['validated_offer_snapshot']
                : (is_array($meta['flight_offer_snapshot'] ?? null) ? $meta['flight_offer_snapshot'] : $snapshot));
        $block = $this->resolveControlledStaffOfferFreshnessBlock($booking, $snapshot);
        if ($block !== null) {
            $code = (string) ($block['code'] ?? 'offer_stale_before_checkout');
            $message = (string) ($block['message'] ?? $freshness->customerSafeMessage($code));

            return $this->controlledStaffOfferValidationBlockedResult(
                $booking,
                $actor,
                $message,
                'offer_validation_required',
                $code,
                $attemptSource,
                $this->controlledStaffOfferRefreshSafeSummary($booking, 'offer_validation_required', $code, true, $refresh),
            );
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $refreshResult
     * @return array<string, mixed>
     */
    protected function controlledStaffOfferRefreshSafeSummary(
        Booking $booking,
        string $reason,
        string $reasonCode,
        bool $refreshAttempted = false,
        ?array $refreshResult = null,
        ?Throwable $exception = null,
    ): array {
        return app(ControlledStaffOfferRefreshDiagnostics::class)->buildAttemptSafeSummary(
            $booking,
            $reason,
            $reasonCode,
            $refreshAttempted,
            $refreshResult,
            $exception,
        );
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    protected function controlledStaffOfferContextNeedsRefresh(
        Booking $booking,
        array $snapshot,
        bool $certBypass = false,
    ): bool {
        if ($this->resolveControlledStaffOfferFreshnessBlock($booking, $snapshot) !== null) {
            return true;
        }

        if (! $certBypass) {
            return false;
        }

        if (! (bool) config('suppliers.sabre.refresh_offer_before_public_pnr', true)) {
            return false;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];

        return trim((string) ($meta['offer_refresh_status'] ?? '')) !== 'refreshed';
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{code: string, message: string, diagnostic: string}|null
     */
    protected function resolveControlledStaffOfferFreshnessBlock(Booking $booking, array $snapshot): ?array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $searchPayload = null;
        $searchId = trim((string) ($meta['checkout_search_id'] ?? ''));
        if ($searchId !== '') {
            $searchPayload = app(FlightSearchResultStore::class)->get($searchId);
        }
        if ($searchPayload === null) {
            $safeContext = app(SabreSafeRefreshContext::class)->fromMeta($meta);
            if ($safeContext !== null) {
                $anchor = trim((string) ($safeContext['refreshed_at'] ?? $safeContext['offer_validated_at'] ?? $safeContext['created_at'] ?? ''));
                if ($anchor !== '') {
                    $searchPayload = [
                        'search_created_at' => $anchor,
                        'created_at' => $anchor,
                    ];
                }
            }
        }

        return app(SabreOfferFreshness::class)->blocksBookingSubmit($snapshot, $meta, $searchPayload);
    }

    protected function markControlledStaffOfferValidationFresh(Booking $booking): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta = app(SabreOfferFreshness::class)->stampBookingMetaAfterSuccessfulOfferRefresh($meta);
        $booking->forceFill(['meta' => $meta])->save();
    }

    protected function controlledStaffOfferValidationBlockedResult(
        Booking $booking,
        ?User $actor,
        string $message,
        string $reason,
        string $reasonCode,
        string $attemptSource,
        array $extraSafeSummary = [],
    ): SupplierBookingResultData {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $cid = $meta['supplier_connection_id'] ?? null;
        $cid = is_numeric($cid) ? (int) $cid : null;
        $attemptConnectionId = ($cid !== null && $cid > 0) ? $cid : null;

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $attemptConnectionId,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'needs_review',
            'error_code' => $reasonCode,
            'error_message' => $message,
            'safe_summary' => SensitiveDataRedactor::redact(array_merge([
                'source' => 'sabre_booking_service',
                'reason' => $reason,
                'reason_code' => $reasonCode,
                'attempt_source' => $attemptSource,
                'live_call_attempted' => false,
                'ticketing_disabled' => true,
            ], $extraSafeSummary)),
            'attempted_by' => $actor?->id,
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);
        $booking->forceFill(['supplier_booking_status' => 'manual_review'])->save();

        Log::notice('sabre.controlled_staff_offer_validation_blocked', [
            'booking_id' => $booking->id,
            'reason' => $reason,
            'reason_code' => $reasonCode,
            'attempt_source' => $attemptSource,
        ]);

        return new SupplierBookingResultData(
            success: false,
            status: 'manual_review',
            provider: SupplierProvider::Sabre->value,
            supplier_reference: null,
            pnr: null,
            safe_summary: array_merge([
                'source' => 'sabre_booking_service',
                'reason' => $reason,
                'reason_code' => $reasonCode,
                'live_call_attempted' => false,
            ], $extraSafeSummary),
            error_code: $reasonCode,
            error_message: $message,
        );
    }

    /**
     * Sprint 11K-F: Block PNR/createBooking when stored offer freshness or revalidation is insufficient.
     *
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>|null
     */
    protected function offerFreshnessBlockBeforePnr(array $offer, ?int $bookingIdForDiagnostics): ?array
    {
        if (strcasecmp((string) ($offer['supplier_provider'] ?? ''), SupplierProvider::Sabre->value) !== 0) {
            return null;
        }

        $bookingMeta = null;
        $searchPayload = null;
        if ($bookingIdForDiagnostics !== null && $bookingIdForDiagnostics > 0) {
            $booking = Booking::query()->find($bookingIdForDiagnostics);
            if ($booking !== null) {
                $bookingMeta = is_array($booking->meta) ? $booking->meta : [];
                $searchId = trim((string) ($bookingMeta['checkout_search_id'] ?? ''));
                if ($searchId !== '') {
                    $searchPayload = app(FlightSearchResultStore::class)->get($searchId);
                }
            }
        }

        $freshness = app(SabreOfferFreshness::class);
        $block = $freshness->blocksBookingSubmit($offer, $bookingMeta, $searchPayload);
        if ($block === null) {
            return null;
        }

        $paxCount = 0;
        $counts = is_array($offer['fare_breakdown']['passenger_counts'] ?? null) ? $offer['fare_breakdown']['passenger_counts'] : [];
        $paxCount = (int) ($counts['adults'] ?? 0) + (int) ($counts['children'] ?? 0) + (int) ($counts['infants'] ?? 0);
        $segCount = count(is_array($offer['segments'] ?? null) ? $offer['segments'] : []);

        return [
            'success' => false,
            'status' => 'validation_failed',
            'message' => (string) ($block['message'] ?? $freshness->customerSafeMessage('offer_stale_before_checkout')),
            'live_call_attempted' => false,
            'live_call_allowed' => false,
            'error_code' => 'sabre_offer_freshness_blocked',
            'reason_code' => (string) ($block['code'] ?? 'offer_stale_before_checkout'),
            'freshness_diagnostic' => (string) ($block['diagnostic'] ?? ''),
            'passenger_count' => $paxCount,
            'segment_count' => $segCount,
            'supplier_connection_id' => (int) ($offer['supplier_connection_id'] ?? 0),
            'selected_offer_id' => (string) ($offer['offer_id'] ?? $offer['id'] ?? ''),
        ];
    }
}
