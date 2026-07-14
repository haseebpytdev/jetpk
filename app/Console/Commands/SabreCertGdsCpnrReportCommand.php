<?php

namespace App\Console\Commands;

use App\Data\FlightSearchRequestData;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Core\SabreBookingClient;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Services\Suppliers\Sabre\Diagnostics\SabreCertEntitlementMatrix;
use App\Services\Suppliers\Sabre\Diagnostics\SabreInspectSanitizer;
use App\Services\Suppliers\Sabre\SabreBookingPayloadBuilder;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Services\Suppliers\Sabre\SabreFlightSearchNormalizer;
use App\Services\Suppliers\Sabre\SabreFlightSearchRequestBuilder;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use App\Services\Suppliers\Sabre\SabreStoredPricingContextDigest;
use App\Support\Bookings\SabrePnrCertificationClassifier;
use App\Support\Bookings\SabrePnrCertificationSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * CERT-only GDS shop → CPNR wire preview ({@code /v4/offers/shop} then Passenger Records payload build).
 * Preview by default — optional gated {@code --send} POST to Passenger Records (no ticketing, no cancel, no Booking row).
 * Send scenarios: ow_direct single-segment; PK same-carrier 2-segment; QR same-carrier 2-segment (IATI v2.4 only).
 * Reuses {@see SabreInspectGate::certEntitlementMatrixSendAllowed()}.
 */
class SabreCertGdsCpnrReportCommand extends Command
{
    public const REPORT_VERSION = 'cert_gds_cpnr_v1';

    public const REPORT_VERSION_SEND = 'cert_gds_cpnr_send_v1';

    /** @var array<string, string> Phase 3C: only these style→endpoint pairs may use --send. */
    public const CERTIFIED_SEND_COMBINATIONS = [
        SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1 => '/v2.5.0/passenger/records?mode=create',
        SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS => SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH,
    ];

    public const EXPECTED_FIRST_SEND_ENDPOINT = '/v2.5.0/passenger/records?mode=create';

    public const SEND_SCENARIO_TYPE_OW_DIRECT_SINGLE = 'ow_direct_single_segment';

    public const SEND_SCENARIO_TYPE_PK_TWO_SEGMENT = 'pk_same_carrier_two_segment';

    public const SEND_SCENARIO_TYPE_QR_TWO_SEGMENT = 'qr_same_carrier_two_segment';

    public const PK_CONNECTING_CERT_CARRIER = 'PK';

    public const QR_CONNECTING_CERT_CARRIER = 'QR';

    protected $signature = 'sabre:cert-gds-cpnr-report
                            {--connection= : Sabre supplier connection ID}
                            {--from=LHE : Origin IATA}
                            {--to=DXB : Destination IATA}
                            {--date=2026-07-15 : Departure date YYYY-MM-DD}
                            {--return-date= : Return date YYYY-MM-DD (required for --scenario=return)}
                            {--scenario=ow_direct : Filter: ow_direct, ow_connecting, or return}
                            {--carrier=EK : Optional marketing/validating carrier filter}
                            {--offer-index=0 : Zero-based index among eligible offers after filtering}
                            {--style= : CPNR payload style override (default traditional_pnr_create_passenger_name_record_v1)}
                            {--endpoint= : Override Passenger Records POST path (leading /); does not change config}
                            {--send : POST Passenger Records create after preview gates pass (CERT only; requires --confirm-cert-pnr-send=YES)}
                            {--confirm-cert-pnr-send= : Required with --send; must be exactly YES}
                            {--allow-nn-cert-diagnostic= : CERT PK or QR 2-segment only; must be exactly YES to omit NN/WN from HaltOnStatus}
                            {--json : Emit cert_gds_cpnr_report_json=... only}
                            {--output= : Optional path to write sanitized JSON}
                            {--log : Log summary counts only (no raw payload)}';

    protected $description = 'CERT GDS CPNR wire preview (default) or gated Passenger Records send (--send + --confirm-cert-pnr-send=YES)';

    public function handle(
        SabreFlightSearchRequestBuilder $builder,
        SabreClient $client,
        SabreFlightSearchNormalizer $normalizer,
        SabreStoredPricingContextDigest $digestor,
        SabreBookingService $sabreBooking,
        SabreBookingPayloadBuilder $payloadBuilder,
        SabreBookingClient $bookingClient,
        SabrePnrCertificationSupport $certificationSupport,
    ): int {
        $wantsSend = (bool) $this->option('send');
        $confirmSend = trim((string) ($this->option('confirm-cert-pnr-send') ?? ''));
        $connectionId = $this->option('connection');
        $hasConnection = $connectionId !== null && $connectionId !== '' && is_numeric($connectionId);
        $connection = SabreCertEntitlementMatrix::resolveConnection(
            $hasConnection ? (int) $connectionId : null,
        );

        if ($connection === null) {
            $this->components->error('No Sabre supplier connection found. Pass --connection={id} or configure one in API settings.');

            return self::FAILURE;
        }

        $baseUrlContext = SabreInspectGate::resolveSabreBaseUrlContext($connection);
        $this->printBaseUrlResolution($baseUrlContext);
        $this->line('connection_id='.$connection->id);
        if ($wantsSend) {
            $this->line('CERT GDS CPNR send: live /v4/offers/shop + gated Passenger Records POST. No ticketing, no cancel, no Booking row.');
        } else {
            $this->line('CERT GDS CPNR report: live /v4/offers/shop + wire preview only. No Passenger Records POST, no PNR, no ticketing, no cancel.');
        }
        $this->newLine();

        if ($wantsSend) {
            if ($confirmSend !== 'YES') {
                $this->components->error('Pass --confirm-cert-pnr-send=YES with --send to approve CERT Passenger Records create.');

                return self::FAILURE;
            }
            if ($sabreBooking->isTicketingEnabled()) {
                $this->components->error('Sabre CERT GDS CPNR send blocks when suppliers.sabre.ticketing_enabled is true.');

                return self::FAILURE;
            }
        }

        if (! SabreInspectGate::certEntitlementMatrixSendAllowed($connection)) {
            $reason = SabreInspectGate::certEntitlementMatrixSendBlockReason($connection) ?? 'blocked';
            $this->components->error('Sabre CERT GDS CPNR report is not allowed ('.$reason.').');

            return self::FAILURE;
        }

        $resolvedBase = SabreInspectGate::resolveSabreBaseUrlForGate($connection);
        if ($resolvedBase === '' || SabreInspectGate::isProductionLiveSabreHost($resolvedBase)) {
            $this->components->error('Sabre CERT GDS CPNR report blocks api.platform.sabre.com; use a CERT host (e.g. api.cert.platform.sabre.com).');

            return self::FAILURE;
        }

        if (! SabreInspectGate::isCertSabreHost($resolvedBase)) {
            $this->components->error('Sabre CERT GDS CPNR report requires a CERT Sabre host (e.g. api.cert.platform.sabre.com).');

            return self::FAILURE;
        }

        $allowNnOpt = trim((string) ($this->option('allow-nn-cert-diagnostic') ?? ''));
        if ($allowNnOpt !== '' && $allowNnOpt !== 'YES') {
            $this->components->error('--allow-nn-cert-diagnostic must be exactly YES when set.');

            return self::FAILURE;
        }
        $wantsAllowNnDiagnostic = $allowNnOpt === 'YES';

        $scenario = strtolower(trim((string) ($this->option('scenario') ?? 'ow_direct')));
        if ($scenario !== '' && ! in_array($scenario, ['ow_direct', 'ow_connecting', 'return'], true)) {
            $this->components->error('Invalid --scenario; use ow_direct, ow_connecting, or return.');

            return self::FAILURE;
        }

        $returnDate = trim((string) ($this->option('return-date') ?? ''));
        if ($scenario === 'return' && $returnDate === '') {
            $this->components->error('Pass --return-date=YYYY-MM-DD when --scenario=return.');

            return self::FAILURE;
        }

        $styleOpt = $this->option('style');
        $styleOverride = is_string($styleOpt) && trim($styleOpt) !== '' ? trim($styleOpt) : null;
        $styleConfig = SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1;
        $effectiveStyle = $styleOverride ?? $styleConfig;

        if (! in_array($effectiveStyle, SabreBookingPayloadBuilder::BOOKING_ENDPOINT_COMPARE_PASSENGER_RECORDS_P4_STYLES, true)) {
            $this->components->error('Invalid --style; use a supported Passenger Records CPNR style (e.g. traditional_pnr_create_passenger_name_record_v1 or iati_like_cpnr_v2_4_gds).');

            return self::FAILURE;
        }

        $endpointOverride = $this->normalizeEndpointOption($this->option('endpoint'));
        $endpointConfig = $payloadBuilder->resolvePassengerRecordsCreateEndpointPath($effectiveStyle);
        $effectiveEndpoint = $endpointOverride ?? $endpointConfig;

        if ($wantsAllowNnDiagnostic) {
            $staticGateError = $this->resolveAllowNnCertDiagnosticStaticBlockReason(
                $sabreBooking,
                $effectiveStyle,
                $effectiveEndpoint,
            );
            if ($staticGateError !== null) {
                $this->components->error('Sabre CERT allow-NN diagnostic blocked ('.$staticGateError.').');

                return self::FAILURE;
            }
        }

        $origin = strtoupper(trim((string) $this->option('from')));
        $destination = strtoupper(trim((string) $this->option('to')));
        $departDate = trim((string) $this->option('date'));
        $tripType = $scenario === 'return' ? 'round_trip' : 'one_way';
        $carrierFilter = strtoupper(trim((string) ($this->option('carrier') ?? '')));
        $offerIndex = max(0, (int) $this->option('offer-index'));

        $request = FlightSearchRequestData::fromArray([
            'origin' => $origin,
            'destination' => $destination,
            'depart_date' => $departDate,
            'return_date' => $returnDate !== '' ? $returnDate : null,
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'cabin' => 'economy',
            'trip_type' => $tripType,
            'currency' => 'PKR',
        ]);

        $shopPath = (string) config('suppliers.sabre.shop_path', '/v4/offers/shop');
        $shopPayload = $builder->build($request, $connection);

        try {
            $response = $client->postShopPayload($connection, $shopPayload);
        } catch (Throwable) {
            $this->components->error('Shop request failed (details omitted).');

            return self::FAILURE;
        }

        $shopHttpStatus = $response->status();
        $json = $response->json();

        if (! $response->successful() || ! is_array($json)) {
            $safe = SabreInspectSanitizer::sanitizeErrorBody(is_array($json) ? $json : null);
            $this->line('shop_http_status='.$shopHttpStatus);
            $this->line('shop_error_summary='.json_encode($safe, JSON_UNESCAPED_SLASHES));

            return self::FAILURE;
        }

        $normalized = $normalizer->normalize($json, $connection, $request);
        $candidates = [];
        foreach ($normalized as $offer) {
            $snap = $normalizer->mergeSabrePricingLinkageHandoff(
                $normalizer->ensureSabreBookingContextOnCachedOffer($offer->toArray())
            );
            $row = $this->buildOfferRow($snap, $digestor, $scenario);
            $candidates[] = ['row' => $row, 'snap' => $snap];
        }

        $eligible = $this->filterEligibleCandidates($candidates, $scenario, $origin, $carrierFilter);
        $normalizedOfferCount = count($normalized);
        $eligibleCount = count($eligible);

        $revalidationPolicy = $sabreBooking->isPnrOnlyPreBookingRevalidationWaived()
            ? 'waived_for_pnr_only'
            : false;

        if ($eligibleCount === 0) {
            $report = $this->buildBaseReport(
                $baseUrlContext,
                $connection->id,
                $shopPath,
                $shopHttpStatus,
                $scenario,
                $origin,
                $destination,
                $departDate,
                $returnDate,
                $tripType,
                $normalizedOfferCount,
                $eligibleCount,
                $effectiveEndpoint,
                $effectiveStyle,
                $styleOverride,
                $endpointOverride,
                $revalidationPolicy,
            );
            $report['selection_error'] = 'no_eligible_gds_offer';
            $report['readiness'] = $this->buildReadiness([], false, false, ['no_eligible_gds_offer']);

            return $this->emitReport($report, $certificationSupport, $connection->id, $scenario, $shopHttpStatus, false);
        }

        if ($offerIndex >= $eligibleCount) {
            $this->components->error('No eligible offer at --offer-index='.$offerIndex.' (eligible_count='.$eligibleCount.').');

            return self::FAILURE;
        }

        $selected = $eligible[$offerIndex];
        $selectedRow = is_array($selected['row'] ?? null) ? $selected['row'] : [];
        $selectedSnap = is_array($selected['snap'] ?? null) ? $selected['snap'] : [];
        $selectedSnap['supplier_provider'] = SupplierProvider::Sabre->value;
        $selectedSnap['supplier_connection_id'] = $connection->id;

        $gate = $sabreBooking->validateNormalizedSabreOffer($selectedSnap);
        if (! $gate->success) {
            $report = $this->buildBaseReport(
                $baseUrlContext,
                $connection->id,
                $shopPath,
                $shopHttpStatus,
                $scenario,
                $origin,
                $destination,
                $departDate,
                $returnDate,
                $tripType,
                $normalizedOfferCount,
                $eligibleCount,
                $effectiveEndpoint,
                $effectiveStyle,
                $styleOverride,
                $endpointOverride,
                $revalidationPolicy,
            );
            $report['offer_index_selected'] = $offerIndex;
            $report['selected_offer'] = $this->selectedOfferSummary($selectedRow);
            $report['selection_error'] = 'offer_gate_failed';
            $report['readiness'] = $this->buildReadiness([], false, false, ['offer_validation_gate_failed']);

            return $this->emitReport($report, $certificationSupport, $connection->id, $scenario, $shopHttpStatus, false);
        }

        $draft = $sabreBooking->prepareBookingPayload($selectedSnap, $this->minimalCertPassengerData());
        if (($draft['_valid'] ?? false) !== true) {
            $report = $this->buildBaseReport(
                $baseUrlContext,
                $connection->id,
                $shopPath,
                $shopHttpStatus,
                $scenario,
                $origin,
                $destination,
                $departDate,
                $returnDate,
                $tripType,
                $normalizedOfferCount,
                $eligibleCount,
                $effectiveEndpoint,
                $effectiveStyle,
                $styleOverride,
                $endpointOverride,
                $revalidationPolicy,
            );
            $report['offer_index_selected'] = $offerIndex;
            $report['selected_offer'] = $this->selectedOfferSummary($selectedRow);
            $report['selection_error'] = 'draft_invalid';
            $report['readiness'] = $this->buildReadiness([], false, false, ['internal_draft_invalid']);

            return $this->emitReport($report, $certificationSupport, $connection->id, $scenario, $shopHttpStatus, false);
        }
        unset($draft['_valid']);

        if ($wantsAllowNnDiagnostic) {
            $scenarioGateError = $this->resolveAllowNnCertDiagnosticScenarioBlockReason($selectedRow, $scenario);
            if ($scenarioGateError !== null) {
                $this->components->error('Sabre CERT allow-NN diagnostic blocked ('.$scenarioGateError.').');

                return self::FAILURE;
            }
            $draft['_ota_cert_allow_nn_diagnostic'] = true;
        }

        $rawWire = $payloadBuilder->buildPassengerRecordsCpnrWireForStyle($draft, [], $effectiveStyle);
        $strippedWire = $payloadBuilder->stripOtaInternalKeysFromBookingWire($rawWire);
        $wireDiag = $payloadBuilder->summarizeTraditionalPnrWirePostBody($strippedWire, null, $effectiveStyle);
        $safePreview = $payloadBuilder->previewRedactedTraditionalPnrCreatePassengerNameRecordV1Wire($rawWire, $effectiveStyle);

        $wireContractValid = ($wireDiag['wire_traditional_pnr_contract_valid'] ?? false) === true;
        $segmentFlags = $this->scanCpnrSegmentFlags($strippedWire);
        $payloadSummary = $this->buildCpnrPayloadSummary($wireDiag, $strippedWire, $segmentFlags);
        $pricingDiagnostics = $this->buildCpnrPricingDiagnostics(
            $wireDiag,
            $strippedWire,
            $segmentFlags,
            $effectiveStyle,
            $effectiveEndpoint,
        );
        $styleComparison = $this->buildCertifiedStyleComparison($payloadBuilder, $draft);
        $readiness = $this->buildReadiness(
            $wireDiag,
            $wireContractValid,
            ($selectedRow['auto_pnr_pricing_context_ready'] ?? false) === true,
            [],
        );

        $report = $this->buildBaseReport(
            $baseUrlContext,
            $connection->id,
            $shopPath,
            $shopHttpStatus,
            $scenario,
            $origin,
            $destination,
            $departDate,
            $returnDate,
            $tripType,
            $normalizedOfferCount,
            $eligibleCount,
            $effectiveEndpoint,
            $effectiveStyle,
            $styleOverride,
            $endpointOverride,
            $revalidationPolicy,
        );
        $report['offer_index_selected'] = $offerIndex;
        $report['selected_offer'] = $this->selectedOfferSummary($selectedRow);
        $report['payload_summary'] = $payloadSummary;
        $report['pricing_diagnostics'] = $pricingDiagnostics;
        $report['style_comparison'] = $styleComparison;
        $report['safe_wire_preview'] = $safePreview;
        $report['readiness'] = $readiness;
        $report['reason_code'] = $this->resolveReasonCode($wireContractValid, $readiness);
        $report['wire_payload_schema'] = (string) ($rawWire['_ota_payload_schema'] ?? $effectiveStyle);
        $report['allow_nn_cert_diagnostic'] = ($draft['_ota_cert_allow_nn_diagnostic'] ?? false) === true;
        $report['wire_halt_on_status_codes_sanitized'] = array_values(
            (array) ($wireDiag['wire_halt_on_status_codes_sanitized'] ?? [])
        );
        $report['wire_halt_on_status_nn_omitted'] = ($wireDiag['wire_halt_on_status_nn_omitted'] ?? false) === true;

        $finalWireFingerprint = $payloadBuilder->fingerprintPassengerRecordsFinalPostBody($rawWire);
        $report['final_wire_fingerprint'] = $finalWireFingerprint;
        $report['preview_final_wire_fingerprint_match'] = $this->finalWireFingerprintMatchesPreviewWireDiag(
            $finalWireFingerprint,
            $wireDiag,
        );

        $sendGateEvaluation = $this->evaluateSendGates(
            $selectedRow,
            $scenario,
            $wireContractValid,
            $effectiveStyle,
            $effectiveEndpoint,
        );
        $report['send_gate_summary'] = $sendGateEvaluation['send_gate_summary'];

        $previewReady = ($readiness['cpnr_preview_ready'] ?? false) === true;

        if ($wantsSend) {
            $report['report_version'] = self::REPORT_VERSION_SEND;
            $report['cpnr_config']['cancel_enabled'] = false;

            if (! $previewReady) {
                $report['send_result'] = $this->buildBlockedSendResult(
                    $effectiveEndpoint,
                    'cpnr_preview_not_ready',
                );
                $report['reason_code'] = 'cert_cpnr_send_preview_not_ready';
                $report['readiness']['recommended_next_action'] = 'fix_preview_blockers_before_cert_cpnr_send';

                return $this->emitReport(
                    $report,
                    $certificationSupport,
                    $connection->id,
                    $scenario,
                    $shopHttpStatus,
                    false,
                    true,
                );
            }

            $sendBlockReason = $sendGateEvaluation['block_reason'];
            if ($sendBlockReason !== null) {
                $report['send_result'] = $this->buildBlockedSendResult($effectiveEndpoint, $sendBlockReason);
                $report['reason_code'] = 'cert_cpnr_send_gate_blocked';
                $report['readiness']['recommended_next_action'] = 'resolve_send_gate_blocker_'.$sendBlockReason;

                return $this->emitReport(
                    $report,
                    $certificationSupport,
                    $connection->id,
                    $scenario,
                    $shopHttpStatus,
                    false,
                    true,
                );
            }

            $report['cpnr_config']['send_enabled'] = true;
            $sendResult = $this->executeCertCpnrSend(
                $bookingClient,
                $connection,
                $rawWire,
                $effectiveEndpoint,
                $effectiveStyle,
                $wireDiag,
                $selectedRow,
            );
            $report['send_result'] = $sendResult;
            $report['reason_code'] = $this->resolveSendReasonCode($sendResult);
            $report['readiness']['recommended_next_action'] = $this->resolveSendRecommendedNextAction($sendResult);

            return $this->emitReport(
                $report,
                $certificationSupport,
                $connection->id,
                $scenario,
                $shopHttpStatus,
                ($sendResult['pnr_created'] ?? false) === true,
                true,
            );
        }

        return $this->emitReport(
            $report,
            $certificationSupport,
            $connection->id,
            $scenario,
            $shopHttpStatus,
            $previewReady,
            false,
        );
    }

    /**
     * @param  array<string, mixed>  $selectedRow
     * @return array{block_reason: ?string, send_gate_summary: array<string, mixed>}
     */
    protected function evaluateSendGates(
        array $selectedRow,
        string $scenario,
        bool $wireContractValid,
        string $effectiveStyle,
        string $effectiveEndpoint,
    ): array {
        $segmentCount = (int) ($selectedRow['segment_count'] ?? 0);
        $mixedCarrier = $this->detectMixedCarrier($selectedRow);
        $pkSameCarrier = $this->isPkSameCarrierOffer($selectedRow);
        $qrSameCarrier = $this->isQrSameCarrierOffer($selectedRow);
        $certifiedPair = $this->isCertifiedSendStyleEndpointPair($effectiveStyle, $effectiveEndpoint);
        $scenarioType = $this->resolveSendScenarioType(
            $scenario,
            $segmentCount,
            $selectedRow,
            $effectiveStyle,
            $effectiveEndpoint,
        );

        $twoSegmentSameCarrierAllowed = $scenario === 'ow_connecting'
            && $segmentCount === 2
            && ! $mixedCarrier
            && ($pkSameCarrier || $qrSameCarrier);

        $segmentCountAllowed = ($scenario === 'ow_direct' && $segmentCount === 1)
            || $twoSegmentSameCarrierAllowed;

        $carrierChainValid = match ($scenarioType) {
            self::SEND_SCENARIO_TYPE_PK_TWO_SEGMENT => $pkSameCarrier && ! $mixedCarrier,
            self::SEND_SCENARIO_TYPE_QR_TWO_SEGMENT => $qrSameCarrier && ! $mixedCarrier,
            self::SEND_SCENARIO_TYPE_OW_DIRECT_SINGLE => true,
            default => $scenario === 'ow_connecting' && $segmentCount === 2
                ? (($pkSameCarrier || $qrSameCarrier) && ! $mixedCarrier)
                : ($scenario === 'ow_direct' && $segmentCount === 1),
        };

        $blockReason = $this->resolveSendBlockReason(
            $selectedRow,
            $scenario,
            $segmentCount,
            $wireContractValid,
            $effectiveStyle,
            $effectiveEndpoint,
            $scenarioType,
            $mixedCarrier,
            $pkSameCarrier,
            $certifiedPair,
        );

        $summaryScenarioType = $scenarioType !== 'none'
            ? $scenarioType
            : $this->inferSendScenarioTypeForSummary($scenario, $segmentCount, $pkSameCarrier, $qrSameCarrier, $mixedCarrier);

        return [
            'block_reason' => $blockReason,
            'send_gate_summary' => [
                'send_scenario_allowed' => $blockReason === null,
                'send_scenario_type' => $summaryScenarioType,
                'carrier_chain_valid' => $carrierChainValid,
                'segment_count_allowed' => $segmentCountAllowed,
                'mixed_carrier_detected' => $mixedCarrier,
                'certified_style_endpoint_pair' => $certifiedPair,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $selectedRow
     */
    protected function resolveSendBlockReason(
        array $selectedRow,
        string $scenario,
        int $segmentCount,
        bool $wireContractValid,
        string $effectiveStyle,
        string $effectiveEndpoint,
        string $scenarioType,
        bool $mixedCarrier,
        bool $pkSameCarrier,
        bool $certifiedPair,
    ): ?string {
        if (($selectedRow['distribution_channel'] ?? '') !== 'gds') {
            return 'send_requires_gds_distribution_channel';
        }
        if (($selectedRow['cpnr_eligible'] ?? false) !== true) {
            return 'send_requires_cpnr_eligible_offer';
        }
        if (($selectedRow['auto_pnr_pricing_context_ready'] ?? false) !== true) {
            return 'send_requires_auto_pnr_pricing_context_ready';
        }
        if ($segmentCount > 2) {
            return 'send_blocks_segment_count_above_two';
        }
        if (! $wireContractValid) {
            return 'send_requires_wire_contract_valid';
        }

        if ($scenarioType === self::SEND_SCENARIO_TYPE_OW_DIRECT_SINGLE) {
            if ($scenario !== 'ow_direct' || $segmentCount !== 1) {
                return 'send_requires_ow_direct_single_segment';
            }
            if (! $certifiedPair) {
                return 'send_requires_certified_style_endpoint_pair';
            }

            return null;
        }

        if ($scenarioType === self::SEND_SCENARIO_TYPE_PK_TWO_SEGMENT) {
            if ($scenario !== 'ow_connecting' || $segmentCount !== 2) {
                return 'send_requires_pk_two_segment_ow_connecting';
            }
            if ($mixedCarrier) {
                return 'send_blocks_mixed_carrier_connecting';
            }
            if (! $pkSameCarrier) {
                return 'send_blocks_non_pk_same_carrier_connecting';
            }
            if ($effectiveStyle !== SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS
                || $effectiveEndpoint !== SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH) {
                return 'send_pk_two_segment_requires_iati_v24_only';
            }

            return null;
        }

        if ($scenarioType === self::SEND_SCENARIO_TYPE_QR_TWO_SEGMENT) {
            if ($scenario !== 'ow_connecting' || $segmentCount !== 2) {
                return 'send_requires_qr_two_segment_ow_connecting';
            }
            if ($mixedCarrier) {
                return 'send_blocks_mixed_carrier_connecting';
            }
            if (! $this->isQrSameCarrierOffer($selectedRow)) {
                return 'send_blocks_non_qr_same_carrier_connecting';
            }
            if ($effectiveStyle !== SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS
                || $effectiveEndpoint !== SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH) {
                return 'send_qr_two_segment_requires_iati_v24_only';
            }

            return null;
        }

        if ($scenario === 'ow_connecting' && $segmentCount === 2) {
            if ($mixedCarrier) {
                return 'send_blocks_mixed_carrier_connecting';
            }
            if ($pkSameCarrier) {
                if ($effectiveStyle !== SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS
                    || $effectiveEndpoint !== SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH) {
                    return 'send_pk_two_segment_requires_iati_v24_only';
                }
            } elseif ($this->isQrSameCarrierOffer($selectedRow)) {
                if ($effectiveStyle !== SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS
                    || $effectiveEndpoint !== SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH) {
                    return 'send_qr_two_segment_requires_iati_v24_only';
                }
            } else {
                return 'send_blocks_non_pk_same_carrier_connecting';
            }
        }

        if ($scenario !== 'ow_direct') {
            return 'send_scenario_not_allowed';
        }
        if ($segmentCount !== 1) {
            return 'send_requires_ow_direct_single_segment';
        }
        if (! $certifiedPair) {
            return 'send_requires_certified_style_endpoint_pair';
        }

        return 'send_scenario_not_allowed';
    }

    protected function resolveSendScenarioType(
        string $scenario,
        int $segmentCount,
        array $selectedRow,
        string $effectiveStyle,
        string $effectiveEndpoint,
    ): string {
        if ($scenario === 'ow_direct' && $segmentCount === 1) {
            return self::SEND_SCENARIO_TYPE_OW_DIRECT_SINGLE;
        }

        if ($scenario === 'ow_connecting'
            && $segmentCount === 2
            && $this->isPkSameCarrierOffer($selectedRow)
            && ! $this->detectMixedCarrier($selectedRow)
            && $effectiveStyle === SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS
            && $effectiveEndpoint === SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH) {
            return self::SEND_SCENARIO_TYPE_PK_TWO_SEGMENT;
        }

        if ($scenario === 'ow_connecting'
            && $segmentCount === 2
            && $this->isQrSameCarrierOffer($selectedRow)
            && ! $this->detectMixedCarrier($selectedRow)
            && $effectiveStyle === SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS
            && $effectiveEndpoint === SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH) {
            return self::SEND_SCENARIO_TYPE_QR_TWO_SEGMENT;
        }

        return 'none';
    }

    protected function inferSendScenarioTypeForSummary(
        string $scenario,
        int $segmentCount,
        bool $pkSameCarrier,
        bool $qrSameCarrier,
        bool $mixedCarrier,
    ): string {
        if ($scenario === 'ow_direct' && $segmentCount === 1) {
            return self::SEND_SCENARIO_TYPE_OW_DIRECT_SINGLE;
        }
        if ($scenario === 'ow_connecting' && $segmentCount === 2 && $pkSameCarrier && ! $mixedCarrier) {
            return self::SEND_SCENARIO_TYPE_PK_TWO_SEGMENT;
        }
        if ($scenario === 'ow_connecting' && $segmentCount === 2 && $qrSameCarrier && ! $mixedCarrier) {
            return self::SEND_SCENARIO_TYPE_QR_TWO_SEGMENT;
        }

        return 'none';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function detectMixedCarrier(array $row): bool
    {
        if (($row['connecting_carrier_profile'] ?? '') === 'mixed_carrier') {
            return true;
        }

        $marketing = [];
        foreach ((array) ($row['marketing_carriers'] ?? []) as $carrier) {
            $code = strtoupper(trim((string) $carrier));
            if ($code !== '') {
                $marketing[] = $code;
            }
        }
        $unique = array_values(array_unique($marketing));
        if (count($unique) > 1) {
            return true;
        }

        $chain = strtoupper(trim((string) ($row['carrier_chain'] ?? '')));
        if ($chain !== '' && str_contains($chain, '+')) {
            $parts = array_values(array_filter(explode('+', $chain), static fn (string $p): bool => trim($p) !== ''));
            $uniqueChain = array_values(array_unique($parts));
            if (count($uniqueChain) > 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function isPkSameCarrierOffer(array $row): bool
    {
        return $this->isSameCarrierOffer($row, self::PK_CONNECTING_CERT_CARRIER);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function isQrSameCarrierOffer(array $row): bool
    {
        return $this->isSameCarrierOffer($row, self::QR_CONNECTING_CERT_CARRIER);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function isSameCarrierOffer(array $row, string $carrier): bool
    {
        if ($this->detectMixedCarrier($row)) {
            return false;
        }

        $carrier = strtoupper(trim($carrier));

        if (strtoupper(trim((string) ($row['validating_carrier'] ?? ''))) !== $carrier) {
            return false;
        }

        foreach ((array) ($row['marketing_carriers'] ?? []) as $marketingCarrier) {
            if (strtoupper(trim((string) $marketingCarrier)) !== $carrier) {
                return false;
            }
        }

        $chain = strtoupper(trim((string) ($row['carrier_chain'] ?? '')));
        if ($chain !== '' && $chain !== $carrier) {
            return false;
        }

        return true;
    }

    protected function isCertifiedSendStyleEndpointPair(string $style, string $endpoint): bool
    {
        $expected = self::CERTIFIED_SEND_COMBINATIONS[$style] ?? null;

        return is_string($expected) && $expected === $endpoint;
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    protected function buildCertifiedStyleComparison(SabreBookingPayloadBuilder $payloadBuilder, array $draft): array
    {
        $entries = [];
        $scores = [];

        foreach (self::CERTIFIED_SEND_COMBINATIONS as $style => $endpoint) {
            $rawWire = $payloadBuilder->buildPassengerRecordsCpnrWireForStyle($draft, [], $style);
            $strippedWire = $payloadBuilder->stripOtaInternalKeysFromBookingWire($rawWire);
            $wireDiag = $payloadBuilder->summarizeTraditionalPnrWirePostBody($strippedWire, null, $style);
            $segmentFlags = $this->scanCpnrSegmentFlags($strippedWire);
            $diag = $this->buildCpnrPricingDiagnostics($wireDiag, $strippedWire, $segmentFlags, $style, $endpoint);
            $diag['wire_contract_valid'] = ($wireDiag['wire_traditional_pnr_contract_valid'] ?? false) === true;
            $diag['send_allowed'] = $diag['wire_contract_valid'];
            $diag['pricing_block_strength_score'] = $this->scorePricingBlockStrength($diag, $wireDiag);
            $entries[$style] = $diag;
            $scores[$style] = (int) $diag['pricing_block_strength_score'];
        }

        $stronger = $this->resolveStrongerPricingBlockStyle($scores);

        return [
            'certified_send_combinations' => self::CERTIFIED_SEND_COMBINATIONS,
            'entries' => $entries,
            'stronger_pricing_block' => $stronger,
            'recommended_next_action' => $stronger === SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS
                ? 'preview_iati_v24_pricing_block_then_send_with_style_and_matching_endpoint'
                : 'compare_style_comparison_entries_before_choosing_send_style',
        ];
    }

    /**
     * @param  array<string, mixed>  $diag
     * @param  array<string, mixed>  $wireDiag
     */
    protected function scorePricingBlockStrength(array $diag, array $wireDiag): int
    {
        $score = 0;
        if (($diag['has_air_price'] ?? false) === true) {
            $score += 2;
        }
        $score += min(3, (int) ($diag['air_price_node_count'] ?? 0));
        if (($diag['has_price_request_information'] ?? false) === true) {
            $score += 2;
        }
        if (($diag['has_price_comparison'] ?? false) === true) {
            $score += 3;
        }
        if (($diag['has_fare_basis_in_air_price'] ?? false) === true) {
            $score += 3;
        }
        if (($wireDiag['wire_air_price_has_pricing_qualifiers'] ?? false) === true) {
            $score += 2;
        }
        if (($wireDiag['wire_airprice_has_validating_carrier'] ?? false) === true) {
            $score += 2;
        }
        if (($wireDiag['wire_air_price_passenger_type_count'] ?? 0) > 0) {
            $score += 2;
        }

        return $score;
    }

    /**
     * @param  array<string, int>  $scores
     */
    protected function resolveStrongerPricingBlockStyle(array $scores): string
    {
        if ($scores === []) {
            return 'tie';
        }
        arsort($scores);
        $styles = array_keys($scores);
        $top = $styles[0];
        $second = $styles[1] ?? null;
        if ($second !== null && $scores[$top] === $scores[$second]) {
            return 'tie';
        }

        return $top;
    }

    /**
     * @param  array<string, mixed>  $wireDiag
     * @param  array<string, mixed>  $strippedWire
     * @param  array<string, mixed>  $segmentFlags
     * @return array<string, mixed>
     */
    protected function buildCpnrPricingDiagnostics(
        array $wireDiag,
        array $strippedWire,
        array $segmentFlags,
        string $payloadStyle,
        string $endpoint,
    ): array {
        $cpnr = is_array($strippedWire['CreatePassengerNameRecordRQ'] ?? null)
            ? $strippedWire['CreatePassengerNameRecordRQ']
            : [];
        $airPriceList = $this->extractRootAirPriceRows($cpnr);
        $hasPriceRequestInformation = false;
        $hasPriceComparison = false;
        $hasFareBasisInAirPrice = false;

        foreach ($airPriceList as $row) {
            if (! is_array($row)) {
                continue;
            }
            $pri = is_array($row['PriceRequestInformation'] ?? null) ? $row['PriceRequestInformation'] : [];
            if ($pri !== []) {
                $hasPriceRequestInformation = true;
            }
            if (isset($pri['PriceComparison']) && is_array($pri['PriceComparison']) && $pri['PriceComparison'] !== []) {
                $hasPriceComparison = true;
            }
            if ($this->airPriceRowContainsFareBasis($row)) {
                $hasFareBasisInAirPrice = true;
            }
        }

        $hasAirPrice = ($wireDiag['wire_has_root_air_price'] ?? false) === true
            || ($wireDiag['wire_has_air_price'] ?? false) === true
            || $airPriceList !== [];
        $airPriceNodeCount = max(
            (int) ($wireDiag['wire_root_air_price_count'] ?? 0),
            count($airPriceList),
        );
        $wireAirpricePassengerTypeCount = (int) ($wireDiag['wire_air_price_passenger_type_count'] ?? 0);
        $wireAirpricePassengerTypeCodes = array_values(
            (array) ($wireDiag['wire_air_price_passenger_type_codes_sanitized'] ?? [])
        );
        $wireAirpriceValidatingCarriers = array_values(
            (array) ($wireDiag['wire_airprice_validating_carriers_sanitized'] ?? [])
        );
        $hasTicketing = isset($cpnr['Ticketing']) && is_array($cpnr['Ticketing']) && $cpnr['Ticketing'] !== [];

        return [
            'payload_style' => $payloadStyle,
            'endpoint' => $endpoint,
            'has_air_price' => $hasAirPrice,
            'air_price_node_count' => $airPriceNodeCount,
            'wire_airprice_node_count' => $airPriceNodeCount,
            'wire_airprice_has_validating_carrier' => ($wireDiag['wire_airprice_has_validating_carrier'] ?? false) === true,
            'wire_airprice_validating_carriers_sanitized' => $wireAirpriceValidatingCarriers,
            'wire_airprice_has_passenger_type' => $wireAirpricePassengerTypeCount > 0,
            'wire_airprice_passenger_type_count' => $wireAirpricePassengerTypeCount,
            'wire_airprice_passenger_type_codes_sanitized' => $wireAirpricePassengerTypeCodes,
            'wire_root_air_price_retain_present' => ($wireDiag['wire_root_air_price_retain_present'] ?? false) === true,
            'has_price_comparison' => $hasPriceComparison,
            'has_price_request_information' => $hasPriceRequestInformation,
            'has_fare_basis_in_air_price' => $hasFareBasisInAirPrice,
            'has_booking_code_in_flight_segment' => ($wireDiag['wire_flight_segment_has_res_book_desig_code'] ?? false) === true
                || ($segmentFlags['has_booking_classes'] ?? false) === true,
            'has_travel_itinerary_add_info' => ($wireDiag['wire_has_travel_itinerary_add_info'] ?? false) === true,
            'has_air_book' => ($wireDiag['wire_has_air_book'] ?? false) === true,
            'has_post_processing_end_transaction' => ($wireDiag['wire_post_processing_has_end_transaction'] ?? false) === true,
            'has_ticketing' => $hasTicketing,
        ];
    }

    /**
     * @param  array<string, mixed>  $cpnr
     * @return list<array<string, mixed>>
     */
    protected function extractRootAirPriceRows(array $cpnr): array
    {
        $raw = $cpnr['AirPrice'] ?? null;
        if (! is_array($raw)) {
            return [];
        }

        return array_is_list($raw) ? array_values(array_filter($raw, 'is_array')) : [$raw];
    }

    /**
     * @param  array<string, mixed>  $airPriceRow
     */
    protected function airPriceRowContainsFareBasis(array $airPriceRow): bool
    {
        $pri = is_array($airPriceRow['PriceRequestInformation'] ?? null) ? $airPriceRow['PriceRequestInformation'] : [];
        $oq = is_array($pri['OptionalQualifiers'] ?? null) ? $pri['OptionalQualifiers'] : [];
        $pq = is_array($oq['PricingQualifiers'] ?? null) ? $oq['PricingQualifiers'] : [];
        $cmd = is_array($pq['CommandPricing'] ?? null) ? $pq['CommandPricing'] : [];
        foreach (['FareBasis', 'fareBasis'] as $key) {
            if (trim((string) ($cmd[$key] ?? '')) !== '') {
                return true;
            }
        }
        $segments = $cmd['SegmentSelect'] ?? $cmd['segmentSelect'] ?? null;
        if (is_array($segments)) {
            $list = array_is_list($segments) ? $segments : [$segments];
            foreach ($list as $seg) {
                if (! is_array($seg)) {
                    continue;
                }
                foreach (['FareBasis', 'fareBasis'] as $key) {
                    if (trim((string) ($seg[$key] ?? '')) !== '') {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $rawWire
     * @param  array<string, mixed>  $wireDiag
     * @param  array<string, mixed>  $selectedRow
     * @return array<string, mixed>
     */
    protected function executeCertCpnrSend(
        SabreBookingClient $bookingClient,
        SupplierConnection $connection,
        array $rawWire,
        string $effectiveEndpoint,
        string $effectiveStyle,
        array $wireDiag,
        array $selectedRow,
    ): array {
        $segmentCount = (int) ($selectedRow['segment_count'] ?? 1);
        $diagnosticsContext = [
            'supplier_connection_id' => $connection->id,
            'passenger_count' => 1,
            'segment_count' => $segmentCount,
            'has_booking_class' => ($wireDiag['wire_flight_segment_has_res_book_desig_code'] ?? false) === true,
            'has_fare_basis' => ($wireDiag['wire_has_fare_basis'] ?? false) === true,
            'has_end_transaction' => ($wireDiag['wire_post_processing_has_end_transaction'] ?? false) === true,
            'payload_style' => $effectiveStyle,
        ];

        try {
            $bookingResult = $bookingClient->createPassengerRecordBooking(
                $connection,
                $rawWire,
                $diagnosticsContext,
                $effectiveEndpoint,
            );
        } catch (Throwable) {
            $failed = $this->buildSendResultFromBookingOutcome(
                $effectiveEndpoint,
                null,
                false,
                false,
                null,
                null,
                [],
                ['Sabre Passenger Records request failed (details omitted).'],
                'unknown',
            );
            $failed['attempted'] = true;

            return $failed;
        }

        $httpStatus = isset($bookingResult['http_status']) && is_numeric($bookingResult['http_status'])
            ? (int) $bookingResult['http_status']
            : null;
        $success = ($bookingResult['success'] ?? false) === true;
        $pnr = trim((string) ($bookingResult['pnr'] ?? $bookingResult['record_locator'] ?? ''));
        $pnrCreated = $pnr !== '';
        $supplierRef = trim((string) ($bookingResult['provider_booking_id'] ?? ''));
        $diag = is_array($bookingResult['booking_diagnostics'] ?? null)
            ? $bookingResult['booking_diagnostics']
            : [];
        $errorDigest = SabrePnrCertificationClassifier::sanitizedErrorDigest($diag);
        $safeMessages = array_values(array_unique(array_merge(
            $errorDigest['response_error_messages'],
            $pnrCreated ? [] : [trim((string) ($bookingResult['safe_message'] ?? ''))],
        )));
        $safeMessages = array_values(array_filter($safeMessages, static fn (string $m): bool => $m !== ''));
        $segmentStatuses = SabrePnrCertificationClassifier::sanitizedHostStatuses($diag);
        $hostClassification = $this->classifyHostStatusForSendResult(
            $pnrCreated,
            $success,
            $httpStatus ?? 0,
            array_merge($diag, [
                'error_code' => $bookingResult['error_code'] ?? $bookingResult['reason_code'] ?? null,
            ]),
        );

        return $this->buildSendResultFromBookingOutcome(
            $effectiveEndpoint,
            $httpStatus,
            $success,
            $pnrCreated,
            $pnrCreated ? strtoupper(substr($pnr, 0, 8)) : null,
            $supplierRef !== '' ? substr($supplierRef, 0, 32) : null,
            $segmentStatuses,
            array_slice($safeMessages, 0, 8),
            $hostClassification,
            (int) ($diag['host_warning_count'] ?? count((array) ($diag['host_warning_messages_truncated'] ?? []))),
            (int) ($diag['response_error_count'] ?? count($errorDigest['response_error_codes'])),
        );
    }

    /**
     * @param  list<string>  $segmentStatuses
     * @param  list<string>  $safeMessages
     * @return array<string, mixed>
     */
    protected function buildSendResultFromBookingOutcome(
        string $endpoint,
        ?int $httpStatus,
        bool $success,
        bool $pnrCreated,
        ?string $pnr,
        ?string $supplierReference,
        array $segmentStatuses,
        array $safeMessages,
        string $hostClassification,
        int $warningCount = 0,
        int $errorCount = 0,
        ?string $blockReason = null,
    ): array {
        $result = [
            'attempted' => $blockReason === null,
            'endpoint' => $endpoint,
            'http_status' => $httpStatus,
            'success' => $success,
            'pnr_created' => $pnrCreated,
            'pnr' => $pnr,
            'supplier_reference' => $supplierReference,
            'segment_statuses' => $segmentStatuses,
            'warning_count' => $warningCount,
            'error_count' => $errorCount,
            'safe_messages' => $safeMessages,
            'host_status_classification' => $hostClassification,
            'ticketing_attempted' => false,
            'cancel_attempted' => false,
        ];
        if ($blockReason !== null) {
            $result['block_reason'] = $blockReason;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildBlockedSendResult(string $endpoint, string $blockReason): array
    {
        return $this->buildSendResultFromBookingOutcome(
            $endpoint,
            null,
            false,
            false,
            null,
            null,
            [],
            [],
            'unknown',
            blockReason: $blockReason,
        );
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     */
    protected function classifyHostStatusForSendResult(
        bool $pnrCreated,
        bool $success,
        int $httpStatus,
        array $safeSummary,
    ): string {
        if ($pnrCreated) {
            $statuses = SabrePnrCertificationClassifier::sanitizedHostStatuses($safeSummary);
            if ($statuses === [] || in_array('HK', $statuses, true) || in_array('KK', $statuses, true)) {
                return 'success_hk';
            }
            if (in_array('NN', $statuses, true) || in_array('HL', $statuses, true)) {
                return 'success_nn_pending';
            }

            return 'success_hk';
        }

        $classification = SabrePnrCertificationClassifier::mapFailureClassification(
            isset($safeSummary['error_code']) ? (string) $safeSummary['error_code'] : null,
            $safeSummary,
        );

        return match ($classification) {
            SabrePnrCertificationClassifier::HOST_SELL_REJECTED_UC => 'host_sell_rejected_uc',
            SabrePnrCertificationClassifier::HOST_SELL_PENDING_NN => 'host_sell_pending_nn',
            SabrePnrCertificationClassifier::NO_FARES_RBD_CARRIER => 'no_fares_rbd_carrier',
            SabrePnrCertificationClassifier::SCHEMA_OR_PAYLOAD_VALIDATION_ERROR => 'validation_error',
            default => $this->classifyHttpOrAuthFailure($httpStatus, $safeSummary),
        };
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     */
    protected function classifyHttpOrAuthFailure(int $httpStatus, array $safeSummary): string
    {
        if (in_array($httpStatus, [401, 403], true)) {
            return 'not_authorized';
        }

        $messages = strtoupper(implode(' ', array_map(
            'strval',
            (array) ($safeSummary['response_error_messages'] ?? []),
        )));
        $codes = array_map('strtoupper', array_map('strval', (array) ($safeSummary['response_error_codes'] ?? [])));

        foreach ($codes as $code) {
            if (str_contains($code, 'NOT_AUTHORIZED') || str_contains($code, 'NOT AUTHORIZED')) {
                return 'not_authorized';
            }
        }
        if (str_contains($messages, 'NOT AUTHORIZED') || str_contains($messages, 'NOT_AUTHORIZED')) {
            return 'not_authorized';
        }
        if (in_array($httpStatus, [400, 422], true)) {
            return 'validation_error';
        }
        if (str_contains($messages, 'NO FARES') || (str_contains($messages, 'RBD') && str_contains($messages, 'CARRIER'))) {
            return 'no_fares_rbd_carrier';
        }

        return 'unknown';
    }

    /**
     * @param  array<string, mixed>  $sendResult
     */
    protected function resolveSendReasonCode(array $sendResult): string
    {
        if (($sendResult['pnr_created'] ?? false) === true) {
            return 'cert_cpnr_send_pnr_created';
        }

        return match ($sendResult['host_status_classification'] ?? 'unknown') {
            'host_sell_rejected_uc' => 'cert_cpnr_send_host_sell_rejected_uc',
            'host_sell_pending_nn' => 'cert_cpnr_send_host_pending_nn',
            'no_fares_rbd_carrier' => 'cert_cpnr_send_no_fares_rbd_carrier',
            'validation_error' => 'cert_cpnr_send_validation_error',
            'not_authorized' => 'cert_cpnr_send_not_authorized',
            'success_nn_pending' => 'cert_cpnr_send_nn_pending_no_locator',
            default => ($sendResult['attempted'] ?? false) === true
                ? 'cert_cpnr_send_completed_no_pnr'
                : 'cert_cpnr_send_not_attempted',
        };
    }

    /**
     * @param  array<string, mixed>  $sendResult
     */
    protected function resolveSendRecommendedNextAction(array $sendResult): string
    {
        if (($sendResult['pnr_created'] ?? false) === true) {
            if (($sendResult['host_status_classification'] ?? '') === 'success_nn_pending') {
                return 'manual_cert_review_and_cleanup_no_ticketing';
            }

            return 'record_cert_pnr_for_cleanup_plan_no_ticketing';
        }

        return match ($sendResult['host_status_classification'] ?? 'unknown') {
            'host_sell_rejected_uc' => 're_shop_and_retry_different_flight_or_rbd',
            'host_sell_pending_nn' => 'retry_with_allow_nn_cert_diagnostic_or_re_shop',
            'no_fares_rbd_carrier' => 'compare_iati_v24_style_send_if_stronger_pricing_block_else_re_shop',
            'validation_error' => 'review_payload_summary_and_wire_contract',
            'not_authorized' => 'review_cert_credentials_and_entitlements',
            default => 'review_send_result_safe_messages_and_re_shop',
        };
    }

    protected function resolveAllowNnCertDiagnosticStaticBlockReason(
        SabreBookingService $sabreBooking,
        string $effectiveStyle,
        string $effectiveEndpoint,
    ): ?string {
        if ($sabreBooking->isTicketingEnabled()) {
            return 'allow_nn_cert_diagnostic_blocks_ticketing_enabled';
        }
        if ($effectiveStyle !== SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS) {
            return 'allow_nn_cert_diagnostic_requires_iati_v24_style';
        }
        if ($effectiveEndpoint !== SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH) {
            return 'allow_nn_cert_diagnostic_requires_v24_create_endpoint';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $selectedRow
     */
    protected function resolveAllowNnCertDiagnosticScenarioBlockReason(array $selectedRow, string $scenario): ?string
    {
        if ($scenario !== 'ow_connecting') {
            return 'allow_nn_cert_diagnostic_requires_pk_two_segment_ow_connecting';
        }
        if ((int) ($selectedRow['segment_count'] ?? 0) !== 2) {
            return 'allow_nn_cert_diagnostic_requires_two_segments';
        }
        if ($this->detectMixedCarrier($selectedRow)) {
            return 'allow_nn_cert_diagnostic_blocks_mixed_carrier';
        }
        if ($this->isPkSameCarrierOffer($selectedRow) || $this->isQrSameCarrierOffer($selectedRow)) {
            return null;
        }

        return 'allow_nn_cert_diagnostic_requires_pk_or_qr_same_carrier';
    }

    /**
     * @param  array<string, mixed>  $wireDiag
     * @param  list<string>  $extraBlockers
     * @return array<string, mixed>
     */
    protected function buildReadiness(
        array $wireDiag,
        bool $wireContractValid,
        bool $pricingContextReady,
        array $extraBlockers,
    ): array {
        $blockers = array_values($extraBlockers);
        $warnings = [];

        if (! $pricingContextReady) {
            $blockers[] = 'auto_pnr_pricing_context_not_ready';
        }

        if (! $wireContractValid && $wireDiag !== []) {
            $blockers[] = 'wire_contract_invalid';
            foreach (array_slice((array) ($wireDiag['wire_invalid_traditional_pnr_contract_keys'] ?? []), 0, 12) as $key) {
                if (is_string($key) && $key !== '') {
                    $blockers[] = $key;
                }
            }
        }

        $blockers = array_values(array_unique($blockers));
        $cpnrPreviewReady = $wireContractValid && $pricingContextReady && $extraBlockers === [];

        $recommended = match (true) {
            $cpnrPreviewReady => 'review_style_comparison_and_pricing_diagnostics_then_approve_certified_send',
            in_array('no_eligible_gds_offer', $blockers, true) => 're_shop_with_different_date_carrier_or_scenario',
            in_array('wire_contract_invalid', $blockers, true) => 'fix_wire_contract_blockers_before_cpnr_send',
            in_array('auto_pnr_pricing_context_not_ready', $blockers, true) => 'select_offer_with_complete_shop_pricing_context',
            default => 'review_readiness_blockers_and_payload_summary',
        };

        return [
            'wire_contract_valid' => $wireContractValid,
            'cpnr_preview_ready' => $cpnrPreviewReady,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'recommended_next_action' => $recommended,
        ];
    }

    /**
     * @param  array<string, mixed>  $fingerprint
     * @param  array<string, mixed>  $wireDiag
     */
    protected function finalWireFingerprintMatchesPreviewWireDiag(array $fingerprint, array $wireDiag): bool
    {
        $previewCodes = array_values((array) ($wireDiag['wire_halt_on_status_codes_sanitized'] ?? []));
        $finalCodes = array_values((array) ($fingerprint['final_wire_halt_on_status_codes'] ?? []));
        sort($previewCodes);
        sort($finalCodes);
        if ($previewCodes !== $finalCodes) {
            return false;
        }

        return ($wireDiag['wire_airbook_has_retry_rebook'] ?? false) === ($fingerprint['final_wire_retry_rebook_present'] ?? false)
            && ($wireDiag['wire_airbook_has_redisplay_reservation'] ?? false) === ($fingerprint['final_wire_airbook_redisplay_present'] ?? false)
            && ($wireDiag['wire_post_processing_has_redisplay_reservation'] ?? false) === ($fingerprint['final_wire_post_processing_redisplay_present'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $wireDiag
     * @param  array<string, mixed>  $strippedWire
     * @param  array<string, mixed>  $segmentFlags
     * @return array<string, mixed>
     */
    protected function buildCpnrPayloadSummary(array $wireDiag, array $strippedWire, array $segmentFlags): array
    {
        $cpnr = is_array($strippedWire['CreatePassengerNameRecordRQ'] ?? null)
            ? $strippedWire['CreatePassengerNameRecordRQ']
            : [];
        $hasTicketing = isset($cpnr['Ticketing']) && is_array($cpnr['Ticketing']) && $cpnr['Ticketing'] !== [];

        return [
            'has_create_passenger_name_record_rq' => ($wireDiag['wire_has_create_passenger_name_record_rq'] ?? false) === true,
            'has_travel_itinerary_add_info' => ($wireDiag['wire_has_travel_itinerary_add_info'] ?? false) === true,
            'has_customer_info' => ($wireDiag['wire_has_customer_info'] ?? false) === true,
            'has_air_book' => ($wireDiag['wire_has_air_book'] ?? false) === true,
            'flight_segment_count' => (int) ($wireDiag['wire_segment_count'] ?? 0),
            'has_post_processing_end_transaction' => ($wireDiag['wire_post_processing_has_end_transaction'] ?? false) === true,
            'has_received_from' => ($wireDiag['wire_has_received_from'] ?? false) === true,
            'has_ticketing' => $hasTicketing,
            'has_phone' => ($wireDiag['wire_customer_info_has_contact_numbers'] ?? false) === true
                || ($wireDiag['wire_has_contact_numbers'] ?? false) === true,
            'has_passenger_name' => ($wireDiag['wire_has_person_name'] ?? false) === true,
            'has_booking_classes' => ($wireDiag['wire_flight_segment_has_res_book_desig_code'] ?? false) === true
                || ($segmentFlags['has_booking_classes'] ?? false) === true,
            'has_flight_numbers' => ($segmentFlags['has_flight_numbers'] ?? false) === true,
            'has_origin_destination' => ($segmentFlags['has_origin_destination'] ?? false) === true,
            'has_marketing_airline' => ($segmentFlags['has_marketing_airline'] ?? false) === true,
        ];
    }

    /**
     * @param  array<string, mixed>  $strippedWire
     * @return array{has_flight_numbers: bool, has_origin_destination: bool, has_marketing_airline: bool, has_booking_classes: bool}
     */
    protected function scanCpnrSegmentFlags(array $strippedWire): array
    {
        $segments = $this->extractCpnrFlightSegments($strippedWire);
        $hasFlightNumbers = false;
        $hasMarketing = false;
        $hasBookingClasses = false;

        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $fn = trim((string) ($seg['FlightNumber'] ?? $seg['flightNumber'] ?? ''));
            if ($fn !== '') {
                $hasFlightNumbers = true;
            }
            $rbd = trim((string) ($seg['ResBookDesigCode'] ?? $seg['resBookDesigCode'] ?? ''));
            if ($rbd !== '') {
                $hasBookingClasses = true;
            }
            $mkt = trim((string) data_get($seg, 'MarketingAirline.Code', data_get($seg, 'Airline.Marketing.Code', '')));
            if ($mkt !== '') {
                $hasMarketing = true;
            }
        }

        $cpnr = is_array($strippedWire['CreatePassengerNameRecordRQ'] ?? null)
            ? $strippedWire['CreatePassengerNameRecordRQ']
            : [];
        $air = is_array($cpnr['AirBook'] ?? null) ? $cpnr['AirBook'] : [];
        $odi = is_array($air['OriginDestinationInformation'] ?? null) ? $air['OriginDestinationInformation'] : [];
        $hasOdi = $odi !== [] && (
            isset($odi['OriginLocation']) || isset($odi['DestinationLocation'])
            || isset($odi['FlightSegment'])
        );

        return [
            'has_flight_numbers' => $hasFlightNumbers,
            'has_origin_destination' => $hasOdi,
            'has_marketing_airline' => $hasMarketing,
            'has_booking_classes' => $hasBookingClasses,
        ];
    }

    /**
     * @param  array<string, mixed>  $strippedWire
     * @return list<array<string, mixed>>
     */
    protected function extractCpnrFlightSegments(array $strippedWire): array
    {
        $cpnr = is_array($strippedWire['CreatePassengerNameRecordRQ'] ?? null)
            ? $strippedWire['CreatePassengerNameRecordRQ']
            : [];
        $air = is_array($cpnr['AirBook'] ?? null) ? $cpnr['AirBook'] : [];
        $odi = is_array($air['OriginDestinationInformation'] ?? null) ? $air['OriginDestinationInformation'] : [];
        $fs = $odi['FlightSegment'] ?? null;
        if (! is_array($fs)) {
            return [];
        }

        return array_is_list($fs) ? $fs : [$fs];
    }

    /**
     * @param  array<string, mixed>  $readiness
     */
    protected function resolveReasonCode(bool $wireContractValid, array $readiness): string
    {
        if (($readiness['cpnr_preview_ready'] ?? false) === true) {
            return 'cpnr_wire_preview_ready';
        }

        return $wireContractValid ? 'cpnr_wire_preview_incomplete_readiness' : 'cpnr_wire_contract_invalid';
    }

    /**
     * @return array<string, mixed>
     */
    protected function minimalCertPassengerData(): array
    {
        return [
            'passengers' => [
                [
                    'type' => 'ADT',
                    'first_name' => 'Cert',
                    'last_name' => 'Probe',
                    'date_of_birth' => '1990-01-01',
                    'gender' => 'M',
                ],
            ],
            'contact' => [
                'email' => 'cert-probe@example.invalid',
                'phone' => '+920000000000',
            ],
        ];
    }

    /**
     * @param  array{
     *     connection_base_url: string|null,
     *     config_base_url: string,
     *     resolved_base_url: string,
     *     resolved_base_host: string,
     *     resolved_source: string,
     * }  $baseUrlContext
     * @return array<string, mixed>
     */
    protected function buildBaseReport(
        array $baseUrlContext,
        int $connectionId,
        string $shopPath,
        int $shopHttpStatus,
        string $scenario,
        string $origin,
        string $destination,
        string $departDate,
        string $returnDate,
        string $tripType,
        int $normalizedOfferCount,
        int $eligibleCount,
        string $effectiveEndpoint,
        string $effectiveStyle,
        ?string $styleOverride,
        ?string $endpointOverride,
        bool|string $revalidationPolicy,
    ): array {
        return [
            'report_version' => self::REPORT_VERSION,
            'connection_id' => $connectionId,
            'base_url_resolution' => $baseUrlContext,
            'resolved_base_host' => $baseUrlContext['resolved_base_host'] ?? 'unknown',
            'shop_endpoint_path' => $shopPath,
            'shop_http_status' => $shopHttpStatus,
            'scenario' => $scenario !== '' ? $scenario : null,
            'search' => [
                'origin' => $origin,
                'destination' => $destination,
                'depart_date' => $departDate,
                'return_date' => $returnDate !== '' ? $returnDate : null,
                'trip_type' => $tripType,
            ],
            'normalized_offer_count' => $normalizedOfferCount,
            'eligible_offer_count' => $eligibleCount,
            'cpnr_config' => [
                'endpoint' => $effectiveEndpoint,
                'payload_style' => $effectiveStyle,
                'style_source' => $styleOverride !== null ? 'override' : 'default',
                'endpoint_source' => $endpointOverride !== null ? 'override' : 'default',
                'send_enabled' => false,
                'ticketing_enabled' => false,
                'cancel_enabled' => false,
                'revalidation_required' => $revalidationPolicy,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function selectedOfferSummary(array $row): array
    {
        return [
            'route' => $row['route'] ?? null,
            'carrier_chain' => $row['carrier_chain'] ?? null,
            'validating_carrier' => $row['validating_carrier'] ?? null,
            'segment_count' => $row['segment_count'] ?? null,
            'booking_classes_by_segment' => $row['booking_classes_by_segment'] ?? [],
            'fare_basis_codes_by_segment' => $row['fare_basis_codes_by_segment'] ?? [],
            'total_fare' => $row['total_fare'] ?? null,
            'currency' => $row['currency'] ?? null,
            'pricing_context_policy' => $row['pricing_context_policy'] ?? null,
            'auto_pnr_pricing_context_ready' => ($row['auto_pnr_pricing_context_ready'] ?? false) === true,
        ];
    }

    /**
     * @param  list<array{row: array<string, mixed>, snap: array<string, mixed>}>  $candidates
     * @return list<array{row: array<string, mixed>, snap: array<string, mixed>}>
     */
    protected function filterEligibleCandidates(
        array $candidates,
        string $scenario,
        string $origin,
        string $carrierFilter,
    ): array {
        $rows = array_map(static fn (array $c): array => $c['row'], $candidates);
        $filteredRows = $this->filterOffersForScenario($rows, $scenario, $origin);
        $filteredIds = [];
        foreach ($filteredRows as $filteredRow) {
            $oid = (string) ($filteredRow['offer_id'] ?? '');
            if ($oid !== '') {
                $filteredIds[$oid] = true;
            }
        }

        $eligible = [];
        foreach ($candidates as $candidate) {
            $row = $candidate['row'];
            $oid = (string) ($row['offer_id'] ?? '');
            if ($oid === '' || ! isset($filteredIds[$oid])) {
                continue;
            }
            if (($row['cpnr_eligible'] ?? false) !== true) {
                continue;
            }
            if (($row['auto_pnr_pricing_context_ready'] ?? false) !== true) {
                continue;
            }
            if (($row['distribution_channel'] ?? '') !== 'gds') {
                continue;
            }
            if ($scenario === 'ow_connecting' && ($row['connecting_carrier_profile'] ?? '') === 'mixed_carrier') {
                continue;
            }
            if ($carrierFilter !== '' && ! $this->offerMatchesCarrier($row, $carrierFilter)) {
                continue;
            }
            $eligible[] = $candidate;
        }

        if ($scenario === 'ow_connecting') {
            usort($eligible, static function (array $a, array $b): int {
                $aSame = ($a['row']['connecting_carrier_profile'] ?? '') === 'same_carrier' ? 0 : 1;
                $bSame = ($b['row']['connecting_carrier_profile'] ?? '') === 'same_carrier' ? 0 : 1;
                if ($aSame !== $bSame) {
                    return $aSame <=> $bSame;
                }

                return ((int) ($b['row']['auto_pnr_pricing_context_ready'] ?? 0))
                    <=> ((int) ($a['row']['auto_pnr_pricing_context_ready'] ?? 0));
            });
        }

        return $eligible;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function offerMatchesCarrier(array $row, string $carrier): bool
    {
        if ($carrier === '') {
            return true;
        }
        if (strtoupper(trim((string) ($row['validating_carrier'] ?? ''))) === $carrier) {
            return true;
        }
        $chain = strtoupper(trim((string) ($row['carrier_chain'] ?? '')));
        if ($chain !== '' && str_contains($chain, $carrier)) {
            return true;
        }
        foreach ((array) ($row['marketing_carriers'] ?? []) as $mkt) {
            if (strtoupper(trim((string) $mkt)) === $carrier) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function filterOffersForScenario(array $rows, string $scenario, string $origin): array
    {
        if ($scenario === '') {
            return $rows;
        }

        $filtered = array_values(array_filter($rows, function (array $row) use ($scenario, $origin): bool {
            $segmentCount = (int) ($row['segment_count'] ?? 0);

            return match ($scenario) {
                'ow_direct' => $segmentCount === 1,
                'ow_connecting' => $segmentCount === 2,
                'return' => $this->offerMatchesReturnScenario($row, $origin),
                default => true,
            };
        }));

        if ($scenario === 'ow_direct') {
            usort($filtered, static fn (array $a, array $b): int => ((int) ($b['auto_pnr_pricing_context_ready'] ?? 0))
                <=> ((int) ($a['auto_pnr_pricing_context_ready'] ?? 0)));
        }

        return $filtered;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function offerMatchesReturnScenario(array $row, string $origin): bool
    {
        $route = strtoupper(trim((string) ($row['route'] ?? '')));
        $origin = strtoupper(trim($origin));
        if ($route === '' || $origin === '') {
            return false;
        }

        return str_ends_with($route, '-'.$origin) && (int) ($row['segment_count'] ?? 0) >= 2;
    }

    /**
     * @param  array<string, mixed>  $snap
     * @return array<string, mixed>
     */
    protected function buildOfferRow(array $snap, SabreStoredPricingContextDigest $digestor, string $scenario): array
    {
        $readiness = $digestor->assessReadiness($snap);
        $digest = $digestor->digest($snap);

        $raw = is_array($snap['raw_payload'] ?? null) ? $snap['raw_payload'] : [];
        $ctx = is_array($raw['sabre_shop_context'] ?? null) ? $raw['sabre_shop_context'] : [];
        $handoff = is_array($snap['sabre_booking_context'] ?? null)
            ? $snap['sabre_booking_context']
            : (is_array($raw['sabre_booking_context'] ?? null) ? $raw['sabre_booking_context'] : []);

        $segments = is_array($snap['segments'] ?? null) ? array_values($snap['segments']) : [];
        $segmentCount = count($segments);
        $marketing = is_array($snap['marketing_carrier_chain'] ?? null) ? $snap['marketing_carrier_chain'] : [];
        $carrierChain = implode('+', array_map(static fn ($c): string => strtoupper(trim((string) $c)), $marketing));

        $routeParts = [];
        if ($segments !== []) {
            $routeParts[] = strtoupper(trim((string) ($segments[0]['origin'] ?? $snap['origin'] ?? '')));
            foreach ($segments as $seg) {
                if (! is_array($seg)) {
                    continue;
                }
                $routeParts[] = strtoupper(trim((string) ($seg['destination'] ?? '')));
            }
        }
        $route = implode('-', array_values(array_filter($routeParts, static fn (string $p): bool => $p !== '')));

        $bookingBySeg = is_array($handoff['booking_classes_by_segment'] ?? null)
            ? $handoff['booking_classes_by_segment']
            : (is_array($ctx['booking_class'] ?? null) ? $ctx['booking_class'] : []);
        $fareBasisBySeg = is_array($handoff['fare_basis_codes_by_segment'] ?? null)
            ? $handoff['fare_basis_codes_by_segment']
            : [];

        $fare = is_array($snap['fare_breakdown'] ?? null) ? $snap['fare_breakdown'] : [];
        $distributionChannel = $this->resolveDistributionChannel($snap, $ctx, $handoff);
        $autoReady = ($readiness['auto_pnr_pricing_context_ready'] ?? false) === true;
        $cpnrEligible = $distributionChannel !== 'ndc' && $autoReady;

        $row = [
            'offer_id' => substr(hash('sha256', (string) ($snap['offer_id'] ?? '')), 0, 16),
            'route' => $route,
            'carrier_chain' => $carrierChain,
            'marketing_carriers' => array_values(array_map(static fn ($c): string => strtoupper(trim((string) $c)), $marketing)),
            'validating_carrier' => strtoupper(trim((string) ($snap['validating_carrier'] ?? $digest['validating_carrier'] ?? ''))),
            'segment_count' => $segmentCount,
            'distribution_channel' => $distributionChannel,
            'cpnr_eligible' => $cpnrEligible,
            'booking_classes_by_segment' => $this->capStringList($bookingBySeg),
            'fare_basis_codes_by_segment' => $this->capStringList($fareBasisBySeg !== []
                ? $fareBasisBySeg
                : (is_array($digest['fare_basis_codes'] ?? null) ? $digest['fare_basis_codes'] : [])),
            'total_fare' => isset($fare['supplier_total']) ? round((float) $fare['supplier_total'], 2) : null,
            'currency' => isset($fare['currency']) ? strtoupper(substr(trim((string) $fare['currency']), 0, 6)) : null,
            'auto_pnr_pricing_context_ready' => $autoReady,
            'pricing_context_policy' => (string) ($readiness['pricing_context_policy'] ?? ''),
        ];

        if ($scenario === 'ow_connecting' && $segmentCount === 2) {
            $uniqueMarketing = array_values(array_unique(array_filter(array_map(
                static fn ($c): string => strtoupper(trim((string) $c)),
                $marketing
            ), static fn (string $c): bool => $c !== '')));
            $row['connecting_carrier_profile'] = count($uniqueMarketing) <= 1 ? 'same_carrier' : 'mixed_carrier';
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $snap
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $handoff
     */
    protected function resolveDistributionChannel(array $snap, array $ctx, array $handoff): string
    {
        foreach ([$snap, $handoff, $ctx] as $map) {
            $channel = strtolower(trim((string) ($map['distribution_channel'] ?? '')));
            if ($channel === 'ndc') {
                return 'ndc';
            }
            if ($channel === 'gds') {
                return 'gds';
            }
        }

        foreach (['pricing_subsource', 'fare_source', 'itinerary_source'] as $key) {
            $v = strtolower(trim((string) ($ctx[$key] ?? '')));
            if ($v !== '' && str_contains($v, 'ndc')) {
                return 'ndc';
            }
        }

        return 'gds';
    }

    /**
     * @param  list<mixed>  $list
     * @return list<string>
     */
    protected function capStringList(array $list): array
    {
        $out = [];
        foreach (array_slice($list, 0, 12) as $item) {
            if (! is_scalar($item)) {
                continue;
            }
            $s = trim((string) $item);
            if ($s !== '') {
                $out[] = substr($s, 0, 16);
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    protected function emitReport(
        array $report,
        SabrePnrCertificationSupport $certificationSupport,
        int $connectionId,
        string $scenario,
        int $shopHttpStatus,
        bool $successOutcome,
        bool $sendMode = false,
    ): int {
        try {
            $certificationSupport->assertOutputSafe($report);
        } catch (Throwable) {
            $this->components->error('Report failed safety check (details omitted).');

            return self::FAILURE;
        }

        if ((bool) $this->option('json')) {
            $this->line('cert_gds_cpnr_report_json='.json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->printHumanSummary($report);
        }

        if ((bool) $this->option('log')) {
            Log::info('sabre.cert_gds_cpnr_report', [
                'connection_id' => $connectionId,
                'resolved_base_host' => $report['resolved_base_host'] ?? null,
                'scenario' => $report['scenario'] ?? null,
                'shop_http_status' => $shopHttpStatus,
                'normalized_offer_count' => $report['normalized_offer_count'] ?? 0,
                'eligible_offer_count' => $report['eligible_offer_count'] ?? 0,
                'cpnr_preview_ready' => data_get($report, 'readiness.cpnr_preview_ready'),
                'send_mode' => $sendMode,
                'send_attempted' => data_get($report, 'send_result.attempted'),
                'pnr_created' => data_get($report, 'send_result.pnr_created'),
                'reason_code' => $report['reason_code'] ?? null,
                'wire_contract_valid' => data_get($report, 'readiness.wire_contract_valid'),
            ]);
        }

        $outputOpt = $this->option('output');
        $outputStr = is_string($outputOpt) ? trim($outputOpt) : '';
        if ($outputStr !== '') {
            $path = $this->resolveOutputPath($outputStr);
            $dir = dirname($path);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->line('wrote_output='.$path);
        }

        $failed = ! $successOutcome || (($report['selection_error'] ?? null) !== null);

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array{
     *     connection_base_url: string|null,
     *     config_base_url: string,
     *     resolved_base_url: string,
     *     resolved_base_host: string,
     *     resolved_source: string,
     * }  $context
     */
    protected function printBaseUrlResolution(array $context): void
    {
        $this->line('resolved_source='.$context['resolved_source']);
        $this->line('connection_base_url='.($context['connection_base_url'] ?? 'null'));
        $this->line('config_base_url='.$context['config_base_url']);
        $this->line('resolved_base_url='.$context['resolved_base_url']);
        $this->line('resolved_base_host='.$context['resolved_base_host']);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    protected function printHumanSummary(array $report): void
    {
        $this->line('shop_http_status='.($report['shop_http_status'] ?? 0));
        $this->line('scenario='.(string) ($report['scenario'] ?? 'all'));
        $this->line('normalized_offer_count='.($report['normalized_offer_count'] ?? 0));
        $this->line('eligible_offer_count='.($report['eligible_offer_count'] ?? 0));
        $selected = is_array($report['selected_offer'] ?? null) ? $report['selected_offer'] : [];
        if ($selected !== []) {
            $this->line('selected_offer.route='.($selected['route'] ?? '—'));
            $this->line('selected_offer.carrier_chain='.($selected['carrier_chain'] ?? '—'));
            $this->line('selected_offer.validating_carrier='.($selected['validating_carrier'] ?? '—'));
        }
        $cpnr = is_array($report['cpnr_config'] ?? null) ? $report['cpnr_config'] : [];
        $this->line('cpnr_config.endpoint='.(string) ($cpnr['endpoint'] ?? '—'));
        $this->line('cpnr_config.payload_style='.(string) ($cpnr['payload_style'] ?? '—'));
        $this->line('cpnr_config.send_enabled='.(($cpnr['send_enabled'] ?? false) ? 'true' : 'false'));
        $this->line('cpnr_config.ticketing_enabled=false');
        $this->line('cpnr_config.cancel_enabled=false');
        $this->line('allow_nn_cert_diagnostic='.(($report['allow_nn_cert_diagnostic'] ?? false) ? 'true' : 'false'));
        $haltCodes = (array) ($report['wire_halt_on_status_codes_sanitized'] ?? []);
        if ($haltCodes !== []) {
            $this->line('wire_halt_on_status_codes_sanitized='.implode(',', array_map('strval', $haltCodes)));
        }
        $this->line('wire_halt_on_status_nn_omitted='.(($report['wire_halt_on_status_nn_omitted'] ?? false) ? 'true' : 'false'));
        $fingerprint = is_array($report['final_wire_fingerprint'] ?? null) ? $report['final_wire_fingerprint'] : [];
        if ($fingerprint !== []) {
            $finalHaltCodes = (array) ($fingerprint['final_wire_halt_on_status_codes'] ?? []);
            if ($finalHaltCodes !== []) {
                $this->line('final_wire_fingerprint.final_wire_halt_on_status_codes='.implode(',', array_map('strval', $finalHaltCodes)));
            }
            $this->line('final_wire_fingerprint.final_wire_contains_nn_halt='.(($fingerprint['final_wire_contains_nn_halt'] ?? false) ? 'true' : 'false'));
            $this->line('final_wire_fingerprint.final_wire_contains_wn_halt='.(($fingerprint['final_wire_contains_wn_halt'] ?? false) ? 'true' : 'false'));
            $segStatuses = (array) ($fingerprint['final_wire_flight_segment_statuses'] ?? []);
            if ($segStatuses !== []) {
                $this->line('final_wire_fingerprint.final_wire_flight_segment_statuses='.implode(',', array_map('strval', $segStatuses)));
            }
            $this->line('final_wire_fingerprint.final_wire_retry_rebook_present='.(($fingerprint['final_wire_retry_rebook_present'] ?? false) ? 'true' : 'false'));
            $this->line('final_wire_fingerprint.final_wire_airbook_redisplay_present='.(($fingerprint['final_wire_airbook_redisplay_present'] ?? false) ? 'true' : 'false'));
            $this->line('final_wire_fingerprint.final_wire_post_processing_redisplay_present='.(($fingerprint['final_wire_post_processing_redisplay_present'] ?? false) ? 'true' : 'false'));
        }
        if (array_key_exists('preview_final_wire_fingerprint_match', $report)) {
            $this->line('preview_final_wire_fingerprint_match='.(($report['preview_final_wire_fingerprint_match'] ?? false) ? 'true' : 'false'));
        }
        $sendResult = is_array($report['send_result'] ?? null) ? $report['send_result'] : [];
        if ($sendResult !== []) {
            $this->line('send_result.attempted='.(($sendResult['attempted'] ?? false) ? 'true' : 'false'));
            $this->line('send_result.http_status='.(string) ($sendResult['http_status'] ?? '—'));
            $this->line('send_result.pnr_created='.(($sendResult['pnr_created'] ?? false) ? 'true' : 'false'));
            if (($sendResult['pnr'] ?? null) !== null) {
                $this->line('send_result.pnr='.(string) $sendResult['pnr']);
            }
            $this->line('send_result.host_status_classification='.(string) ($sendResult['host_status_classification'] ?? '—'));
        }
        $pricing = is_array($report['pricing_diagnostics'] ?? null) ? $report['pricing_diagnostics'] : [];
        if ($pricing !== []) {
            $this->line('pricing_diagnostics.payload_style='.(string) ($pricing['payload_style'] ?? '—'));
            $this->line('pricing_diagnostics.endpoint='.(string) ($pricing['endpoint'] ?? '—'));
            $this->line('pricing_diagnostics.has_air_price='.(($pricing['has_air_price'] ?? false) ? 'true' : 'false'));
            $this->line('pricing_diagnostics.air_price_node_count='.(string) ($pricing['air_price_node_count'] ?? 0));
            $this->line('pricing_diagnostics.airprice_nodes='.(string) ($pricing['wire_airprice_node_count'] ?? 0));
            $vcCarriers = array_values((array) ($pricing['wire_airprice_validating_carriers_sanitized'] ?? []));
            $this->line('pricing_diagnostics.airprice_validating_carrier='.(($pricing['wire_airprice_has_validating_carrier'] ?? false) ? 'true' : 'false')
                .($vcCarriers !== [] ? ':'.implode(',', array_map('strval', $vcCarriers)) : ''));
            $this->line('pricing_diagnostics.airprice_passenger_type='.(($pricing['wire_airprice_has_passenger_type'] ?? false) ? 'true' : 'false')
                .' count='.(string) ($pricing['wire_airprice_passenger_type_count'] ?? 0));
            $this->line('pricing_diagnostics.has_price_request_information='.(($pricing['has_price_request_information'] ?? false) ? 'true' : 'false'));
            $this->line('pricing_diagnostics.has_ticketing='.(($pricing['has_ticketing'] ?? false) ? 'true' : 'false'));
        }
        $comparison = is_array($report['style_comparison'] ?? null) ? $report['style_comparison'] : [];
        if (($comparison['stronger_pricing_block'] ?? '') !== '') {
            $this->line('style_comparison.stronger_pricing_block='.(string) $comparison['stronger_pricing_block']);
        }
        $sendGate = is_array($report['send_gate_summary'] ?? null) ? $report['send_gate_summary'] : [];
        if ($sendGate !== []) {
            $this->line('send_gate_summary.send_scenario_allowed='.(($sendGate['send_scenario_allowed'] ?? false) ? 'true' : 'false'));
            $this->line('send_gate_summary.send_scenario_type='.(string) ($sendGate['send_scenario_type'] ?? '—'));
            $this->line('send_gate_summary.carrier_chain_valid='.(($sendGate['carrier_chain_valid'] ?? false) ? 'true' : 'false'));
            $this->line('send_gate_summary.segment_count_allowed='.(($sendGate['segment_count_allowed'] ?? false) ? 'true' : 'false'));
            $this->line('send_gate_summary.mixed_carrier_detected='.(($sendGate['mixed_carrier_detected'] ?? false) ? 'true' : 'false'));
            $this->line('send_gate_summary.certified_style_endpoint_pair='.(($sendGate['certified_style_endpoint_pair'] ?? false) ? 'true' : 'false'));
        }
        $readiness = is_array($report['readiness'] ?? null) ? $report['readiness'] : [];
        $this->line('readiness.cpnr_preview_ready='.(($readiness['cpnr_preview_ready'] ?? false) ? 'true' : 'false'));
        $this->line('readiness.wire_contract_valid='.(($readiness['wire_contract_valid'] ?? false) ? 'true' : 'false'));
        $this->line('reason_code='.(string) ($report['reason_code'] ?? '—'));
        $this->line('readiness.recommended_next_action='.(string) ($readiness['recommended_next_action'] ?? '—'));
    }

    protected function resolveOutputPath(string $p): string
    {
        $p = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($p));
        if ($p === '') {
            return storage_path('app/sabre-cert-gds-cpnr-report.json');
        }
        if (preg_match('#^[A-Za-z]:\\\\#', $p) || str_starts_with($p, DIRECTORY_SEPARATOR)) {
            return $p;
        }

        return base_path($p);
    }

    protected function normalizeEndpointOption(mixed $raw): ?string
    {
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }
        $p = trim($raw);

        return ($p !== '' && $p[0] === '/') ? $p : '/'.$p;
    }
}
