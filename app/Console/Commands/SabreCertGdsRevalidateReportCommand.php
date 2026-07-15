<?php

namespace App\Console\Commands;

use App\Data\FlightSearchRequestData;
use App\Enums\SupplierProvider;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Services\Suppliers\Sabre\Diagnostics\SabreCertEntitlementMatrix;
use App\Services\Suppliers\Sabre\Diagnostics\SabreInspectSanitizer;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Services\Suppliers\Sabre\SabreFlightSearchNormalizer;
use App\Services\Suppliers\Sabre\SabreFlightSearchRequestBuilder;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use App\Services\Suppliers\Sabre\SabreRevalidationPayloadBuilder;
use App\Services\Suppliers\Sabre\SabreStoredPricingContextDigest;
use App\Support\Bookings\SabrePnrCertificationSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * CERT-only GDS shop → revalidate readiness report ({@code /v4/offers/shop} then
 * {@code /v4/shop/flights/revalidate}). No PNR create, no ticketing, no cancel.
 * Reuses {@see SabreInspectGate::certEntitlementMatrixSendAllowed()}.
 */
class SabreCertGdsRevalidateReportCommand extends Command
{
    public const REPORT_VERSION = 'cert_gds_revalidate_v1';

    protected $signature = 'sabre:cert-gds-revalidate-report
                            {--connection= : Sabre supplier connection ID}
                            {--from=LHE : Origin IATA}
                            {--to=DXB : Destination IATA}
                            {--date=2026-07-15 : Departure date YYYY-MM-DD}
                            {--return-date= : Return date YYYY-MM-DD (required for --scenario=return)}
                            {--scenario= : Filter: ow_direct, ow_connecting, or return}
                            {--carrier= : Optional marketing/validating carrier filter (e.g. EK, GF, PK)}
                            {--offer-index=0 : Zero-based index among eligible offers after filtering}
                            {--style= : Override SABRE_REVALIDATE_PAYLOAD_STYLE for this probe only}
                            {--path= : Override revalidate POST path (leading /); does not change config}
                            {--json : Emit cert_gds_revalidate_report_json=... only}
                            {--output= : Optional path to write sanitized JSON}
                            {--log : Log summary counts only (no raw payload)}
                            {--show-response-digest : Include safe response_structure digest (also on failure)}';

    protected $description = 'CERT GDS revalidate readiness: live shop + /v4/shop/flights/revalidate (no PNR/ticketing/cancel)';

    public function handle(
        SabreFlightSearchRequestBuilder $builder,
        SabreClient $client,
        SabreFlightSearchNormalizer $normalizer,
        SabreStoredPricingContextDigest $digestor,
        SabreBookingService $sabreBooking,
        SabreRevalidationPayloadBuilder $revalidationBuilder,
        SabrePnrCertificationSupport $certificationSupport,
    ): int {
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
        $this->line('CERT GDS revalidate report: live /v4/offers/shop + revalidate only. No PNR, no ticketing, no cancel.');
        $this->newLine();

        if (! SabreInspectGate::certEntitlementMatrixSendAllowed($connection)) {
            $reason = SabreInspectGate::certEntitlementMatrixSendBlockReason($connection) ?? 'blocked';
            $this->components->error('Sabre CERT GDS revalidate report is not allowed ('.$reason.').');

            return self::FAILURE;
        }

        $resolvedBase = SabreInspectGate::resolveSabreBaseUrlForGate($connection);
        if ($resolvedBase === '' || SabreInspectGate::isProductionLiveSabreHost($resolvedBase)) {
            $this->components->error('Sabre CERT GDS revalidate report blocks api.platform.sabre.com; use a CERT host (e.g. api.cert.platform.sabre.com).');

            return self::FAILURE;
        }

        if (! SabreInspectGate::isCertSabreHost($resolvedBase)) {
            $this->components->error('Sabre CERT GDS revalidate report requires a CERT Sabre host (e.g. api.cert.platform.sabre.com).');

            return self::FAILURE;
        }

        $scenario = strtolower(trim((string) ($this->option('scenario') ?? '')));
        if ($scenario !== '' && ! in_array($scenario, ['ow_direct', 'ow_connecting', 'return'], true)) {
            $this->components->error('Invalid --scenario; use ow_direct, ow_connecting, or return.');

            return self::FAILURE;
        }

        $returnDate = trim((string) ($this->option('return-date') ?? ''));
        if ($scenario === 'return' && $returnDate === '') {
            $this->components->error('Pass --return-date=YYYY-MM-DD when --scenario=return.');

            return self::FAILURE;
        }

        $origin = strtoupper(trim((string) $this->option('from')));
        $destination = strtoupper(trim((string) $this->option('to')));
        $departDate = trim((string) $this->option('date'));
        $tripType = $scenario === 'return' ? 'round_trip' : 'one_way';
        $carrierFilter = strtoupper(trim((string) ($this->option('carrier') ?? '')));
        $offerIndex = max(0, (int) $this->option('offer-index'));

        $styleOpt = $this->option('style');
        $styleOverride = is_string($styleOpt) && trim($styleOpt) !== '' ? trim($styleOpt) : null;
        $pathOverride = $this->normalizeRevalidatePathOption($this->option('path'));
        $pathConfig = (string) config('suppliers.sabre.revalidate_path', '/v4/shop/flights/revalidate');
        $pathConfig = $pathConfig !== '' && $pathConfig[0] === '/' ? $pathConfig : '/'.$pathConfig;
        $effectivePath = $pathOverride ?? $pathConfig;
        $styleConfig = (string) config('suppliers.sabre.revalidate_payload_style', 'bfm_revalidate_v1');

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
                $effectivePath,
                $styleConfig,
                $styleOverride,
                $pathOverride,
            );
            $report['selection_error'] = 'no_eligible_gds_offer';
            $report['response'] = [
                'http_status' => null,
                'sabre_error_code' => null,
                'safe_message' => 'No GDS offer matched cpnr_eligible filters for this scenario/carrier.',
                'revalidation_success' => false,
                'revalidated_total' => null,
                'revalidated_currency' => null,
                'class_of_service_returned' => false,
                'fare_basis_returned' => false,
                'validating_carrier_returned' => false,
                'cpnr_context_ready_after_revalidate' => false,
                'failure_classification' => 'validation_error',
                'recommended_next_action' => 're_shop_with_different_date_carrier_or_scenario',
            ];

            return $this->emitReport($report, $certificationSupport, $connection->id, $scenario, $shopHttpStatus);
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
                $effectivePath,
                $styleConfig,
                $styleOverride,
                $pathOverride,
            );
            $report['selected_offer'] = $this->selectedOfferSummary($selectedRow);
            $report['selection_error'] = 'offer_gate_failed';
            $report['response'] = [
                'http_status' => null,
                'sabre_error_code' => null,
                'safe_message' => 'Selected offer failed internal Sabre validation gate.',
                'revalidation_success' => false,
                'revalidated_total' => null,
                'revalidated_currency' => null,
                'class_of_service_returned' => false,
                'fare_basis_returned' => false,
                'validating_carrier_returned' => false,
                'cpnr_context_ready_after_revalidate' => false,
                'failure_classification' => 'validation_error',
                'recommended_next_action' => 'select_different_offer_or_re_shop',
            ];

            return $this->emitReport($report, $certificationSupport, $connection->id, $scenario, $shopHttpStatus);
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
                $effectivePath,
                $styleConfig,
                $styleOverride,
                $pathOverride,
            );
            $report['selected_offer'] = $this->selectedOfferSummary($selectedRow);
            $report['selection_error'] = 'draft_invalid';
            $report['response'] = [
                'http_status' => null,
                'sabre_error_code' => null,
                'safe_message' => 'Could not build internal revalidation draft from selected offer.',
                'revalidation_success' => false,
                'revalidated_total' => null,
                'revalidated_currency' => null,
                'class_of_service_returned' => false,
                'fare_basis_returned' => false,
                'validating_carrier_returned' => false,
                'cpnr_context_ready_after_revalidate' => false,
                'failure_classification' => 'validation_error',
                'recommended_next_action' => 'select_different_offer_or_re_shop',
            ];

            return $this->emitReport($report, $certificationSupport, $connection->id, $scenario, $shopHttpStatus);
        }
        unset($draft['_valid']);

        $payload = $revalidationBuilder->buildPayload($draft, $styleOverride);
        $payloadSummary = $revalidationBuilder->safePayloadSummary($payload);

        $outcome = $sabreBooking->runRevalidationBeforeBooking($draft, $connection, $styleOverride, $pathOverride);
        $linkage = is_array($outcome['linkage'] ?? null) ? $outcome['linkage'] : [];
        $linkageDigest = is_array($outcome['linkage_digest'] ?? null)
            ? $outcome['linkage_digest']
            : $revalidationBuilder->linkageDigest($linkage);
        $errorDigest = is_array($outcome['error_digest'] ?? null) ? $outcome['error_digest'] : [];
        $revalidationSuccess = ($outcome['success'] ?? false) === true;
        $httpStatus = isset($outcome['http_status']) ? (int) $outcome['http_status'] : null;

        $failureClassification = $this->classifyFailure($outcome, $httpStatus, $errorDigest);
        $cpnrReadyAfter = $this->assessCpnrContextReadyAfterRevalidate($revalidationSuccess, $linkage, $linkageDigest);

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
            $effectivePath,
            $styleConfig,
            $styleOverride,
            $pathOverride,
        );
        $report['offer_index_selected'] = $offerIndex;
        $report['selected_offer'] = $this->selectedOfferSummary($selectedRow);
        $report['revalidation_config'] = [
            'revalidate_path' => $effectivePath,
            'revalidate_payload_style' => (string) ($outcome['payload_style'] ?? $styleOverride ?? $styleConfig),
            'style_source' => $styleOverride !== null ? 'override' : 'config',
            'path_source' => $pathOverride !== null ? 'override' : 'config',
        ];
        $report['payload_summary'] = $this->buildPayloadInclusionSummary($payloadSummary, $draft);
        $report['response'] = [
            'http_status' => $httpStatus,
            'sabre_error_code' => $this->firstErrorCode($errorDigest),
            'safe_message' => $this->firstSafeMessage($errorDigest, $outcome),
            'revalidation_success' => $revalidationSuccess,
            'revalidated_total' => isset($linkage['revalidated_total']) && is_numeric($linkage['revalidated_total'])
                ? round((float) $linkage['revalidated_total'], 2) : null,
            'revalidated_currency' => isset($linkage['revalidated_currency']) && is_string($linkage['revalidated_currency'])
                ? strtoupper(substr(trim($linkage['revalidated_currency']), 0, 6)) : null,
            'class_of_service_returned' => $this->linkageHasClassOfService($linkage, $linkageDigest),
            'fare_basis_returned' => ($linkageDigest['has_fare_basis'] ?? false) === true,
            'validating_carrier_returned' => ($linkageDigest['has_validating_carrier'] ?? false) === true,
            'cpnr_context_ready_after_revalidate' => $cpnrReadyAfter,
            'failure_classification' => $failureClassification,
            'recommended_next_action' => $this->recommendedNextAction($failureClassification, $cpnrReadyAfter, $revalidationSuccess),
        ];
        $report = $this->appendRevalidationDiagnostics(
            $report,
            $payload,
            $outcome,
            $linkage,
            $linkageDigest,
            $revalidationBuilder,
            $revalidationSuccess,
            $httpStatus,
            $errorDigest,
        );
        $report = $this->refineReportForGroupedItineraryHints(
            $report,
            $outcome,
            $errorDigest,
            $revalidationSuccess,
            $linkageDigest,
        );

        return $this->emitReport($report, $certificationSupport, $connection->id, $scenario, $shopHttpStatus, $revalidationSuccess);
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $outcome
     * @param  array<string, mixed>  $linkage
     * @param  array<string, mixed>  $linkageDigest
     * @return array<string, mixed>
     */
    protected function appendRevalidationDiagnostics(
        array $report,
        array $payload,
        array $outcome,
        array $linkage,
        array $linkageDigest,
        SabreRevalidationPayloadBuilder $revalidationBuilder,
        bool $revalidationSuccess,
        ?int $httpStatus,
        array $errorDigest,
    ): array {
        $wire = $revalidationBuilder->wireableRequestPayload($payload);
        $wireKeys = array_values(array_filter(array_keys($wire), static fn ($k): bool => is_string($k)));
        $schema = trim((string) ($payload['_ota_payload_schema'] ?? ''));

        $report['wire_root_keys'] = array_slice($wireKeys, 0, 24);
        $report['wire_payload_schema'] = $schema !== '' ? $schema : null;
        $report['payload_diagnostics'] = $this->buildCertPayloadDiagnostics($wire);
        $report['linkage_digest'] = $this->buildCertLinkageDigest($linkageDigest, $linkage);
        $report['reason_code'] = $this->resolveReasonCode($outcome, $revalidationSuccess, $httpStatus, $errorDigest);

        $showDigest = (bool) $this->option('show-response-digest');
        if (! $revalidationSuccess || $showDigest) {
            $report['response_structure'] = $this->buildCertResponseStructure($outcome);
        }

        return $report;
    }

    /**
     * @param  array<string, mixed>  $wire
     * @return array<string, mixed>
     */
    protected function buildCertPayloadDiagnostics(array $wire): array
    {
        $ota = is_array($wire['OTA_AirLowFareSearchRQ'] ?? null) ? $wire['OTA_AirLowFareSearchRQ'] : [];
        $odis = is_array($ota['OriginDestinationInformation'] ?? null) ? $ota['OriginDestinationInformation'] : [];
        $firstSeg = $this->firstWireFlightSegment($wire, $odis);

        $has = static function (array $seg, array $paths): bool {
            foreach ($paths as $p) {
                $v = data_get($seg, $p);
                if (is_string($v) && trim($v) !== '') {
                    return true;
                }
                if (is_numeric($v) && (string) $v !== '') {
                    return true;
                }
                if (is_array($v) && $v !== []) {
                    return true;
                }
            }

            return false;
        };

        $seg = is_array($firstSeg) ? $firstSeg : [];
        $tpaRoot = data_get($ota, 'TPA_Extensions');
        $tpaOdi = is_array($odis[0] ?? null) ? data_get($odis[0], 'TPA_Extensions') : null;
        $intelliRoot = trim((string) data_get($ota, 'TPA_Extensions.IntelliSellTransaction.RequestType.Name', ''));
        $intelliTraveler = trim((string) data_get($ota, 'TravelerInfoSummary.TPA_Extensions.IntelliSellTransaction.RequestType.Name', ''));

        return [
            'has_ota_air_low_fare_search_rq' => $ota !== [],
            'has_pos' => is_array($ota['POS'] ?? null) && $ota['POS'] !== [],
            'has_pseudo_city_code' => trim((string) data_get($ota, 'POS.Source.0.PseudoCityCode', '')) !== '',
            'has_origin_destination_information' => $odis !== [],
            'origin_destination_information_count' => count($odis),
            'has_travel_preferences' => is_array($ota['TravelPreferences'] ?? null) && $ota['TravelPreferences'] !== [],
            'has_verification_itin_call_logic' => trim((string) data_get(
                $ota,
                'TravelPreferences.TPA_Extensions.VerificationItinCallLogic.Value',
                ''
            )) !== '' || $this->wireContainsKeyFragment($wire, 'verificationitincalllogic'),
            'has_traveler_info_summary' => is_array($ota['TravelerInfoSummary'] ?? null) && $ota['TravelerInfoSummary'] !== [],
            'has_tpa_extensions' => (is_array($tpaRoot) && $tpaRoot !== []) || (is_array($tpaOdi) && $tpaOdi !== []),
            'has_intelli_sell_transaction' => $intelliRoot !== '' || $intelliTraveler !== '',
            'has_air_itinerary_pricing_info' => $this->wireContainsKeyFragment($wire, 'airitinerarypricinginfo'),
            'has_pricing_information_root' => array_key_exists('pricingInformation', $wire)
                && is_array($wire['pricingInformation'])
                && $wire['pricingInformation'] !== [],
            'has_shop_context_root' => array_key_exists('shop_context', $wire)
                && is_array($wire['shop_context'])
                && $wire['shop_context'] !== [],
            'has_fare_context_root' => array_key_exists('fare_context', $wire)
                && is_array($wire['fare_context'])
                && $wire['fare_context'] !== [],
            'has_itinerary_root' => array_key_exists('itinerary', $wire)
                && is_array($wire['itinerary'])
                && $wire['itinerary'] !== [],
            'has_passenger_counts_root' => array_key_exists('passenger_counts', $wire)
                && is_array($wire['passenger_counts'])
                && $wire['passenger_counts'] !== [],
            'segment_count' => $this->countWireSegments($wire, $odis),
            'has_marketing_airline' => $has($seg, ['MarketingAirline', 'Airline.Marketing', 'marketingAirline']),
            'has_operating_airline' => $has($seg, ['OperatingAirline', 'Airline.Operating', 'operatingAirline']),
            'has_res_book_desig_code' => $has($seg, ['ResBookDesigCode', 'resBookDesigCode']),
            'has_class_of_service' => $has($seg, ['ClassOfService', 'classOfService', 'ResBookDesigCode']),
            'has_fare_basis_code' => $has($seg, ['FareBasisCode', 'fareBasisCode']),
            'flight_nodes_with_operating_airline' => $this->countWireIatiFlightNodePresence($wire, 'operating_airline'),
            'flight_nodes_with_res_book_desig_code' => $this->countWireIatiFlightNodePresence($wire, 'res_book_desig_code'),
            'flight_nodes_with_fare_basis_code' => $this->countWireIatiFlightNodePresence($wire, 'fare_basis_code'),
        ];
    }

    /**
     * @param  array<string, mixed>  $wire
     */
    protected function countWireIatiFlightNodePresence(array $wire, string $field): int
    {
        $odis = data_get($wire, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation');
        if (! is_array($odis) || $odis === []) {
            return 0;
        }

        $count = 0;
        foreach ($odis as $odi) {
            if (! is_array($odi)) {
                continue;
            }
            $flights = data_get($odi, 'TPA_Extensions.Flight');
            if (! is_array($flights) || $flights === []) {
                continue;
            }
            foreach ($flights as $flight) {
                if (! is_array($flight)) {
                    continue;
                }
                $present = match ($field) {
                    'operating_airline' => is_array($flight['Airline']['Operating'] ?? null)
                        && trim((string) data_get($flight, 'Airline.Operating.Code', '')) !== '',
                    'res_book_desig_code' => trim((string) ($flight['ResBookDesigCode'] ?? '')) !== '',
                    'fare_basis_code' => trim((string) ($flight['FareBasisCode'] ?? '')) !== '',
                    default => false,
                };
                if ($present) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $wire
     * @param  list<mixed>  $odis
     * @return array<string, mixed>|null
     */
    protected function firstWireFlightSegment(array $wire, array $odis): ?array
    {
        if (isset($odis[0]) && is_array($odis[0])) {
            $iati = data_get($odis[0], 'TPA_Extensions.Flight.0');
            if (is_array($iati)) {
                return $iati;
            }
            $fs = $odis[0]['FlightSegment'] ?? null;
            if (is_array($fs)) {
                return $fs;
            }
        }

        $rq = is_array($wire['RevalidateItineraryRQ'] ?? null) ? $wire['RevalidateItineraryRQ'] : null;
        if ($rq !== null) {
            $rows = is_array($rq['FlightSegments'] ?? null) ? $rq['FlightSegments'] : [];
            if (isset($rows[0]) && is_array($rows[0])) {
                return $rows[0];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $wire
     * @param  list<mixed>  $odis
     */
    protected function countWireSegments(array $wire, array $odis): int
    {
        $count = 0;
        foreach ($odis as $odi) {
            if (! is_array($odi)) {
                continue;
            }
            $iati = data_get($odi, 'TPA_Extensions.Flight');
            if (is_array($iati)) {
                $count += count($iati);

                continue;
            }
            if (isset($odi['FlightSegment']) && is_array($odi['FlightSegment'])) {
                $count++;
            }
        }
        if ($count > 0) {
            return $count;
        }

        $rq = is_array($wire['RevalidateItineraryRQ'] ?? null) ? $wire['RevalidateItineraryRQ'] : null;
        if ($rq !== null) {
            $rows = is_array($rq['FlightSegments'] ?? null) ? $rq['FlightSegments'] : [];

            return count($rows);
        }

        $itinSegs = is_array(data_get($wire, 'itinerary.segments')) ? data_get($wire, 'itinerary.segments') : [];

        return is_array($itinSegs) ? count($itinSegs) : 0;
    }

    /**
     * @param  array<string, mixed>  $wire
     */
    protected function wireContainsKeyFragment(array $wire, string $fragment): bool
    {
        $fragment = strtolower($fragment);
        $json = json_encode($wire, JSON_UNESCAPED_SLASHES);

        return is_string($json) && str_contains(strtolower($json), $fragment);
    }

    /**
     * @param  array<string, mixed>  $linkageDigest
     * @param  array<string, mixed>  $linkage
     * @return array<string, mixed>
     */
    protected function buildCertLinkageDigest(array $linkageDigest, array $linkage): array
    {
        return [
            'has_revalidated_fare' => ($linkageDigest['has_revalidated_fare'] ?? false) === true,
            'has_fare_basis' => ($linkageDigest['has_fare_basis'] ?? false) === true,
            'has_validating_carrier' => ($linkageDigest['has_validating_carrier'] ?? false) === true,
            'has_offer_reference' => ($linkageDigest['has_offer_reference'] ?? false) === true,
            'has_revalidation_reference' => ($linkageDigest['has_revalidation_reference'] ?? false) === true,
            'has_price_quote_reference' => ($linkageDigest['has_price_quote_reference'] ?? false) === true,
            'has_order_reference' => ($linkageDigest['has_order_reference'] ?? false) === true,
            'revalidated_total_present' => isset($linkage['revalidated_total']) && is_numeric($linkage['revalidated_total']),
            'revalidated_currency_present' => isset($linkage['revalidated_currency'])
                && is_string($linkage['revalidated_currency'])
                && trim($linkage['revalidated_currency']) !== '',
        ];
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @param  array<string, mixed>  $errorDigest
     */
    protected function resolveReasonCode(
        array $outcome,
        bool $revalidationSuccess,
        ?int $httpStatus,
        array $errorDigest,
    ): string {
        if ($revalidationSuccess) {
            return 'sabre_revalidation_success';
        }

        $outcomeReason = strtolower(trim((string) ($outcome['reason_code'] ?? '')));
        if ($outcomeReason === 'sabre_revalidation_empty_or_unusable_response') {
            return 'sabre_revalidation_empty_or_unusable_response';
        }

        if (($outcome['includes_sabre_error_27131'] ?? false) === true) {
            return 'sabre_27131_revalidate_contract_or_pricing_context_rejected';
        }

        $codes = array_map(static fn ($c): string => strtolower(trim((string) $c)), (array) ($errorDigest['response_error_codes'] ?? []));
        $messages = array_map(static fn ($m): string => strtolower(trim((string) $m)), (array) ($errorDigest['response_error_messages'] ?? []));
        $blob = implode(' ', array_merge($codes, $messages));
        if (in_array('27131', $codes, true) || str_contains($blob, '27131')) {
            return 'sabre_27131_revalidate_contract_or_pricing_context_rejected';
        }

        $http = $httpStatus ?? 0;
        if ($http === 0) {
            return 'sabre_revalidation_unknown';
        }

        if ($http >= 200 && $http < 300) {
            if ($outcomeReason === 'sabre_revalidation_application_warning_or_error') {
                return 'sabre_revalidation_empty_or_unusable_response';
            }

            return 'sabre_revalidation_empty_or_unusable_response';
        }

        return 'sabre_revalidation_http_error';
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @return array<string, mixed>
     */
    protected function buildCertResponseStructure(array $outcome): array
    {
        $rs = is_array($outcome['response_structure'] ?? null) ? $outcome['response_structure'] : [];
        $topKeysStr = (string) ($rs['top_level_keys'] ?? '');
        $pathsStr = (string) ($rs['key_paths'] ?? '');
        $topKeys = array_values(array_filter(array_map('trim', explode(',', $topKeysStr)), static fn (string $s): bool => $s !== ''));
        $pathParts = array_values(array_filter(array_map('trim', preg_split('/\s*\|\s*/', $pathsStr) ?: []), static fn (string $s): bool => $s !== ''));
        $pathParts = array_slice($pathParts, 0, 80);
        $haystack = strtolower($topKeysStr.' '.$pathsStr);

        $containsGir = str_contains($haystack, 'groupeditineraryresponse');
        $containsItinGroup = str_contains($haystack, 'itinerarygroup');
        $containsPricing = str_contains($haystack, 'pricinginformation');
        $containsTotalFare = str_contains($haystack, 'totalfare');
        $containsFareComponent = str_contains($haystack, 'farecomponent');
        $containsPassengerInfo = str_contains($haystack, 'passengerinfo');
        $containsBookingCode = str_contains($haystack, 'bookingcode');
        $containsFareBasis = str_contains($haystack, 'farebasis');
        $containsValidatingCarrier = str_contains($haystack, 'validatingcarrier');

        $usableHint = $containsGir && $containsItinGroup && (
            $containsPricing
            || $containsTotalFare
            || $containsFareComponent
            || $containsPassengerInfo
            || ($containsBookingCode && $containsFareBasis)
        );

        return [
            'json_valid' => (($rs['json_valid'] ?? 'false') === 'true'),
            'empty_body' => (($rs['empty_body'] ?? 'false') === 'true'),
            'top_level_keys' => array_slice($topKeys, 0, 40),
            'nested_key_paths' => $pathParts,
            'contains_grouped_itinerary_response' => $containsGir,
            'contains_priced_itinerary' => str_contains($haystack, 'priceditinerar'),
            'contains_itinerary_group' => $containsItinGroup,
            'contains_schedule_desc' => str_contains($haystack, 'scheduledesc'),
            'contains_fare_component' => $containsFareComponent,
            'contains_passenger_info' => $containsPassengerInfo,
            'contains_total_fare' => $containsTotalFare,
            'contains_booking_code' => $containsBookingCode,
            'contains_fare_basis_code' => $containsFareBasis,
            'contains_validating_carrier' => $containsValidatingCarrier,
            'contains_error' => str_contains($haystack, 'error'),
            'grouped_itinerary_usable_hint' => $usableHint,
            'candidate_count' => is_numeric($rs['candidate_count'] ?? null) ? (int) $rs['candidate_count'] : 0,
        ];
    }

    /**
     * CERT-only: when 27131 appears but response structure hints at usable groupedItineraryResponse fare data.
     *
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $outcome
     * @param  array<string, mixed>  $errorDigest
     * @return array<string, mixed>
     */
    protected function refineReportForGroupedItineraryHints(
        array $report,
        array $outcome,
        array $errorDigest,
        bool $revalidationSuccess,
        array $linkageDigest = [],
    ): array {
        if ($revalidationSuccess) {
            return $report;
        }

        $rs = is_array($report['response_structure'] ?? null) ? $report['response_structure'] : [];
        $usableHint = ($rs['grouped_itinerary_usable_hint'] ?? false) === true
            || $this->linkageDigestHintsUsableGroupedItinerary($linkageDigest);
        $has27131 = $this->responseSignalsSabre27131($outcome, $errorDigest);

        $response = is_array($report['response'] ?? null) ? $report['response'] : [];
        $response['grouped_itinerary_usable_hint'] = $usableHint;
        $response['warning_27131_with_usable_gir_data'] = $has27131 && $usableHint;

        if ($has27131 && $usableHint) {
            $response['failure_classification'] = 'warning_27131_recoverable_gir';
            $response['recommended_next_action'] = 'extend_parser_to_extract_grouped_itinerary_linkage_parser_not_fatal_yet';
            $report['reason_code'] = 'sabre_27131_with_usable_grouped_itinerary_response';
        } elseif ($usableHint && ! $revalidationSuccess) {
            $response['recommended_next_action'] = 'inspect_grouped_itinerary_response_paths_for_parser_extension';
        }

        $report['response'] = $response;

        if ($usableHint && is_array($report['response_structure'] ?? null)) {
            $report['response_structure']['grouped_itinerary_usable_hint'] = true;
        }

        return $report;
    }

    /**
     * @param  array<string, mixed>  $linkageDigest  Outcome {@see SabreRevalidationPayloadBuilder::linkageDigest()} (may exist on HTTP 4xx).
     */
    protected function linkageDigestHintsUsableGroupedItinerary(array $linkageDigest): bool
    {
        if (($linkageDigest['has_fare_basis'] ?? false) === true) {
            return true;
        }

        if (($linkageDigest['has_revalidated_fare'] ?? false) === true) {
            return true;
        }

        return ($linkageDigest['has_validating_carrier'] ?? false) === true
            && ($linkageDigest['has_revalidated_currency'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @param  array<string, mixed>  $errorDigest
     */
    protected function responseSignalsSabre27131(array $outcome, array $errorDigest): bool
    {
        if (($outcome['includes_sabre_error_27131'] ?? false) === true) {
            return true;
        }

        $codes = array_map(static fn ($c): string => strtolower(trim((string) $c)), (array) ($errorDigest['response_error_codes'] ?? []));
        $messages = array_map(static fn ($m): string => strtolower(trim((string) $m)), (array) ($errorDigest['response_error_messages'] ?? []));
        $blob = implode(' ', array_merge($codes, $messages));

        return in_array('27131', $codes, true) || str_contains($blob, '27131');
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
        string $effectivePath,
        string $styleConfig,
        ?string $styleOverride,
        ?string $pathOverride,
    ): array {
        return [
            'report_version' => self::REPORT_VERSION,
            'connection_id' => $connectionId,
            'base_url_resolution' => $baseUrlContext,
            'resolved_base_host' => $baseUrlContext['resolved_base_host'] ?? 'unknown',
            'shop_endpoint_path' => $shopPath,
            'shop_http_status' => $shopHttpStatus,
            'revalidate_endpoint_path' => $effectivePath,
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
            'revalidation_config' => [
                'revalidate_path' => $effectivePath,
                'revalidate_payload_style' => $styleOverride ?? $styleConfig,
                'style_source' => $styleOverride !== null ? 'override' : 'config',
                'path_source' => $pathOverride !== null ? 'override' : 'config',
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
            'offer_id' => $row['offer_id'] ?? null,
            'route' => $row['route'] ?? null,
            'carrier_chain' => $row['carrier_chain'] ?? null,
            'validating_carrier' => $row['validating_carrier'] ?? null,
            'segment_count' => $row['segment_count'] ?? null,
            'connecting_carrier_profile' => $row['connecting_carrier_profile'] ?? null,
            'booking_classes_by_segment' => $row['booking_classes_by_segment'] ?? [],
            'fare_basis_codes_by_segment' => $row['fare_basis_codes_by_segment'] ?? [],
            'total_fare' => $row['total_fare'] ?? null,
            'currency' => $row['currency'] ?? null,
            'pricing_context_policy' => $row['pricing_context_policy'] ?? null,
            'auto_pnr_pricing_context_ready' => ($row['auto_pnr_pricing_context_ready'] ?? false) === true,
        ];
    }

    /**
     * @param  array<string, mixed>  $payloadSummary
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    protected function buildPayloadInclusionSummary(array $payloadSummary, array $draft): array
    {
        $shopCtx = is_array($draft['_sabre_shop_context'] ?? null) ? $draft['_sabre_shop_context'] : [];
        $piIndexPresent = isset($shopCtx['pricing_information_index']) && is_numeric($shopCtx['pricing_information_index']);

        return [
            'itinerary_ref_included' => ($payloadSummary['has_itinerary_reference'] ?? false) === true,
            'pricing_information_index_included' => $piIndexPresent
                || ($payloadSummary['has_reconstructed_pricing_context'] ?? false) === true
                || ($payloadSummary['has_pricing_information_ref'] ?? false) === true,
            'leg_refs_included' => ($payloadSummary['has_leg_refs'] ?? false) === true,
            'schedule_refs_included' => ($payloadSummary['has_schedule_refs'] ?? false) === true,
            'booking_classes_included' => ($payloadSummary['has_booking_class'] ?? false) === true,
            'fare_basis_included' => ($payloadSummary['has_fare_basis'] ?? false) === true,
            'validating_carrier_included' => ($payloadSummary['has_validating_carrier'] ?? false) === true,
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
     * @param  array<string, mixed>  $linkage
     * @param  array<string, mixed>  $linkageDigest
     */
    protected function assessCpnrContextReadyAfterRevalidate(bool $success, array $linkage, array $linkageDigest): bool
    {
        if (! $success) {
            return false;
        }

        $hasFare = ($linkageDigest['has_revalidated_fare'] ?? false) === true
            && ($linkageDigest['has_revalidated_currency'] ?? false) === true;
        $hasBasis = ($linkageDigest['has_fare_basis'] ?? false) === true;
        $hasVc = ($linkageDigest['has_validating_carrier'] ?? false) === true;
        $hasRef = ($linkageDigest['has_revalidation_reference'] ?? false) === true
            || ($linkageDigest['has_fare_reference'] ?? false) === true
            || ($linkageDigest['has_offer_reference'] ?? false) === true;

        return $hasFare && $hasBasis && $hasVc && ($hasRef || $this->linkageHasClassOfService($linkage, $linkageDigest));
    }

    /**
     * @param  array<string, mixed>  $linkage
     * @param  array<string, mixed>  $linkageDigest
     */
    protected function linkageHasClassOfService(array $linkage, array $linkageDigest): bool
    {
        if (trim((string) ($linkage['class_of_service_first'] ?? '')) !== '') {
            return true;
        }
        if (trim((string) ($linkage['booking_code'] ?? '')) !== '') {
            return true;
        }
        $perSeg = is_array($linkage['per_segment'] ?? null) ? $linkage['per_segment'] : [];
        foreach ($perSeg as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (trim((string) ($row['class_of_service'] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @param  array<string, mixed>  $errorDigest
     */
    protected function classifyFailure(array $outcome, ?int $httpStatus, array $errorDigest): string
    {
        if (($outcome['success'] ?? false) === true) {
            return 'success';
        }

        $http = $httpStatus ?? 0;
        if ($http === 0) {
            return 'unknown_host_error';
        }

        if (in_array($http, [401, 403], true)) {
            return 'not_authorized';
        }

        $codes = array_map(static fn ($c): string => strtolower(trim((string) $c)), (array) ($errorDigest['response_error_codes'] ?? []));
        $messages = array_map(static fn ($m): string => strtolower(trim((string) $m)), (array) ($errorDigest['response_error_messages'] ?? []));
        $blob = implode(' ', array_merge($codes, $messages));
        $reason = strtolower(trim((string) ($outcome['reason_code'] ?? '')));

        if (str_contains($blob, 'expired') || str_contains($blob, 'no longer available') || str_contains($blob, 'offer_expired')) {
            return 'offer_expired';
        }

        if (str_contains($blob, ' uc') || str_contains($blob, 'halt_on_status') || str_contains($blob, 'unable to sell')) {
            return 'uc_status';
        }

        if (str_contains($blob, 'no fare for') || str_contains($blob, 'class not available') || str_contains($blob, 'booking class')) {
            return 'no_fare_for_class';
        }

        if (str_contains($blob, 'no fare') || str_contains($blob, 'no fares') || str_contains($blob, 'rbd')
            || in_array('27131', $codes, true) || str_contains($blob, '27131')) {
            return 'no_fares_rbd_carrier';
        }

        if ($http === 200 && in_array($reason, ['sabre_revalidation_empty_or_unusable_response', 'sabre_revalidation_application_warning_or_error'], true)) {
            return 'schema_error';
        }

        if (in_array($http, [400, 422], true)) {
            return 'validation_error';
        }

        if ($http >= 500) {
            return 'unknown_host_error';
        }

        return 'validation_error';
    }

    protected function recommendedNextAction(string $classification, bool $cpnrReady, bool $success): string
    {
        if ($success && $cpnrReady) {
            return 'proceed_to_cert_cpnr_when_approved';
        }

        if ($success && ! $cpnrReady) {
            return 'review_revalidation_linkage_before_cpnr';
        }

        return match ($classification) {
            'warning_27131_recoverable_gir' => 'extend_parser_to_extract_grouped_itinerary_linkage_parser_not_fatal_yet',
            'not_authorized' => 'request_sabre_cert_entitlement_for_revalidate_endpoint',
            'offer_expired' => 're_shop_immediately_then_revalidate_same_session',
            'no_fares_rbd_carrier' => 'try_manager_like_bfm_revalidate_enriched_v1_or_manager_like_bfm_revalidate_v1',
            'no_fare_for_class' => 're_shop_and_select_alternate_booking_class',
            'uc_status' => 'choose_alternate_itinerary_or_re_shop',
            'schema_error' => 'inspect_response_structure_and_payload_style',
            'unknown_host_error' => 'verify_cert_host_and_network_then_retry',
            default => 'review_payload_summary_and_sabre_error_code',
        };
    }

    /**
     * @param  array<string, mixed>  $errorDigest
     */
    protected function firstErrorCode(array $errorDigest): ?string
    {
        $codes = array_slice((array) ($errorDigest['response_error_codes'] ?? []), 0, 4);
        foreach ($codes as $code) {
            $s = trim((string) $code);
            if ($s !== '') {
                return substr($s, 0, 32);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $errorDigest
     * @param  array<string, mixed>  $outcome
     */
    protected function firstSafeMessage(array $errorDigest, array $outcome): ?string
    {
        $msgs = array_slice((array) ($errorDigest['response_error_messages'] ?? []), 0, 4);
        foreach ($msgs as $msg) {
            $s = trim((string) $msg);
            if ($s !== '') {
                return substr($s, 0, 180);
            }
        }
        $fallback = trim((string) ($outcome['message'] ?? ''));

        return $fallback !== '' ? substr($fallback, 0, 180) : null;
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
        ?bool $revalidationSuccess = null,
    ): int {
        try {
            $certificationSupport->assertOutputSafe($report);
        } catch (Throwable) {
            $this->components->error('Report failed safety check (details omitted).');

            return self::FAILURE;
        }

        if ((bool) $this->option('json')) {
            $this->line('cert_gds_revalidate_report_json='.json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->printHumanSummary($report);
        }

        if ((bool) $this->option('log')) {
            Log::info('sabre.cert_gds_revalidate_report', [
                'connection_id' => $connectionId,
                'resolved_base_host' => $report['resolved_base_host'] ?? null,
                'scenario' => $report['scenario'] ?? null,
                'shop_http_status' => $shopHttpStatus,
                'normalized_offer_count' => $report['normalized_offer_count'] ?? 0,
                'eligible_offer_count' => $report['eligible_offer_count'] ?? 0,
                'revalidation_success' => $revalidationSuccess,
                'reason_code' => $report['reason_code'] ?? null,
                'failure_classification' => data_get($report, 'response.failure_classification'),
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

        $failedRevalidate = $revalidationSuccess === false
            || (($report['selection_error'] ?? null) !== null);

        return $failedRevalidate ? self::FAILURE : self::SUCCESS;
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
        $wireKeys = is_array($report['wire_root_keys'] ?? null) ? $report['wire_root_keys'] : [];
        if ($wireKeys !== []) {
            $this->line('wire_root_keys='.implode(',', array_map(static fn ($k): string => (string) $k, $wireKeys)));
        }
        $this->line('reason_code='.(string) ($report['reason_code'] ?? '—'));
        $response = is_array($report['response'] ?? null) ? $report['response'] : [];
        $this->line('response.http_status='.(string) ($response['http_status'] ?? '—'));
        $this->line('response.revalidation_success='.(($response['revalidation_success'] ?? false) ? 'true' : 'false'));
        $this->line('response.failure_classification='.(string) ($response['failure_classification'] ?? '—'));
        $this->line('response.recommended_next_action='.(string) ($response['recommended_next_action'] ?? '—'));
        if (isset($report['response_structure']) && is_array($report['response_structure'])) {
            $rs = $report['response_structure'];
            $this->line('response_structure.top_level_keys='.implode(',', (array) ($rs['top_level_keys'] ?? [])));
            $this->line('response_structure.candidate_count='.(string) ($rs['candidate_count'] ?? '0'));
        }
    }

    protected function resolveOutputPath(string $p): string
    {
        $p = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($p));
        if ($p === '') {
            return storage_path('app/sabre-cert-gds-revalidate-report.json');
        }
        if (preg_match('#^[A-Za-z]:\\\\#', $p) || str_starts_with($p, DIRECTORY_SEPARATOR)) {
            return $p;
        }

        return base_path($p);
    }

    protected function normalizeRevalidatePathOption(mixed $raw): ?string
    {
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }
        $p = trim($raw);

        return ($p !== '' && $p[0] === '/') ? $p : '/'.$p;
    }
}
