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
 * CERT-only GDS shop → revalidation path/style matrix ({@code /v4/offers/shop} then many revalidate probes).
 * No PNR create, no ticketing, no cancel. Reuses {@see SabreInspectGate::certEntitlementMatrixSendAllowed()}.
 */
class SabreCertGdsRevalidateMatrixCommand extends Command
{
    public const REPORT_VERSION = 'cert_gds_revalidate_matrix_v1';

    /** @var list<string> */
    public const DEFAULT_PATHS = [
        '/v4/shop/flights/revalidate',
        '/v5/shop/flights/revalidate',
        '/v4/offers/shop/revalidate',
        '/v5/offers/shop/revalidate',
        '/v4/offers/revalidate',
        '/v5/offers/revalidate',
        '/v4/shop/revalidate',
        '/v5/shop/revalidate',
        '/v1/shop/flights/revalidate',
    ];

    /** @var list<string> */
    public const DEFAULT_STYLES = [
        'bfm_revalidate_v1',
        'bfm_revalidate_with_pricing_context',
        'bfm_revalidate_minimal_segments',
        'bfm_revalidate_original_like',
        'client_gds_revalidate_v1',
        'client_gds_revalidate_without_pos',
        'client_gds_revalidate_without_travel_preferences',
        'client_gds_revalidate_segments_only',
        'shop_replay_selected_itinerary_v1',
        'iati_like_bfm_revalidate_v1',
        'manager_like_bfm_revalidate_v1',
        'manager_like_bfm_revalidate_enriched_v1',
    ];

    protected $signature = 'sabre:cert-gds-revalidate-matrix
                            {--connection= : Sabre supplier connection ID}
                            {--from=LHE : Origin IATA}
                            {--to=DXB : Destination IATA}
                            {--date=2026-07-15 : Departure date YYYY-MM-DD}
                            {--return-date= : Return date YYYY-MM-DD (required for --scenario=return)}
                            {--scenario=ow_direct : Filter: ow_direct, ow_connecting, or return}
                            {--carrier=EK : Optional marketing/validating carrier filter}
                            {--offer-index=0 : Zero-based index among eligible offers after filtering}
                            {--paths= : Comma-separated revalidate POST paths (leading /)}
                            {--styles= : Comma-separated revalidate payload styles}
                            {--max-attempts=30 : Cap total path×style probes (prevents excessive Sabre calls)}
                            {--stop-on-success : Stop matrix after first true revalidation_success with usable linkage}
                            {--json : Emit cert_gds_revalidate_matrix_json=... only}
                            {--output= : Optional path to write sanitized JSON}
                            {--log : Log summary counts only (no raw payload)}';

    protected $description = 'CERT GDS revalidation matrix: live shop + path/style grid (no PNR/ticketing/cancel)';

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
        if (! (bool) $this->option('json')) {
            $this->printBaseUrlResolution($baseUrlContext);
            $this->line('connection_id='.$connection->id);
            $this->line('CERT GDS revalidate matrix: live shop + path/style grid. No PNR, no ticketing, no cancel.');
            $this->newLine();
        }

        if (! SabreInspectGate::certEntitlementMatrixSendAllowed($connection)) {
            $reason = SabreInspectGate::certEntitlementMatrixSendBlockReason($connection) ?? 'blocked';
            $this->components->error('Sabre CERT GDS revalidate matrix is not allowed ('.$reason.').');

            return self::FAILURE;
        }

        $resolvedBase = SabreInspectGate::resolveSabreBaseUrlForGate($connection);
        if ($resolvedBase === '' || SabreInspectGate::isProductionLiveSabreHost($resolvedBase)) {
            $this->components->error('Sabre CERT GDS revalidate matrix blocks api.platform.sabre.com; use a CERT host (e.g. api.cert.platform.sabre.com).');

            return self::FAILURE;
        }

        if (! SabreInspectGate::isCertSabreHost($resolvedBase)) {
            $this->components->error('Sabre CERT GDS revalidate matrix requires a CERT Sabre host (e.g. api.cert.platform.sabre.com).');

            return self::FAILURE;
        }

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

        $paths = $this->resolvePathsOption();
        $styles = $this->resolveStylesOption();
        if ($paths === [] || $styles === []) {
            $this->components->error('No revalidate paths or styles to probe.');

            return self::FAILURE;
        }

        $maxAttempts = max(1, (int) $this->option('max-attempts'));
        $stopOnSuccess = (bool) $this->option('stop-on-success');
        $pairs = $this->buildMatrixPairs($paths, $styles, $maxAttempts);

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

        $report = [
            'report_version' => self::REPORT_VERSION,
            'connection_id' => $connection->id,
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
            'matrix_config' => [
                'paths_requested' => $paths,
                'styles_requested' => $styles,
                'pairs_planned' => count($paths) * count($styles),
                'max_attempts' => $maxAttempts,
                'pairs_executed_cap' => count($pairs),
                'stop_on_success' => $stopOnSuccess,
                'inter_call_delay_ms' => 400,
            ],
            'attempts' => [],
        ];

        if ($eligibleCount === 0) {
            $report['selection_error'] = 'no_eligible_gds_offer';
            $report['matrix_summary'] = $this->buildEmptyMatrixSummary();

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
            $report['offer_index_selected'] = $offerIndex;
            $report['selected_offer'] = $this->selectedOfferSummary($selectedRow);
            $report['selection_error'] = 'offer_gate_failed';
            $report['matrix_summary'] = $this->buildEmptyMatrixSummary();

            return $this->emitReport($report, $certificationSupport, $connection->id, $scenario, $shopHttpStatus);
        }

        $draft = $sabreBooking->prepareBookingPayload($selectedSnap, $this->minimalCertPassengerData());
        if (($draft['_valid'] ?? false) !== true) {
            $report['offer_index_selected'] = $offerIndex;
            $report['selected_offer'] = $this->selectedOfferSummary($selectedRow);
            $report['selection_error'] = 'draft_invalid';
            $report['matrix_summary'] = $this->buildEmptyMatrixSummary();

            return $this->emitReport($report, $certificationSupport, $connection->id, $scenario, $shopHttpStatus);
        }
        unset($draft['_valid']);

        $report['offer_index_selected'] = $offerIndex;
        $report['selected_offer'] = $this->selectedOfferSummary($selectedRow);

        $attempts = [];
        $foundSuccess = false;
        foreach ($pairs as $idx => $pair) {
            if ($idx > 0) {
                usleep(400000);
            }

            $path = $pair['path'];
            $style = $pair['style'];
            $outcome = $sabreBooking->runRevalidationBeforeBooking($draft, $connection, $style, $path);
            $attempt = $this->buildMatrixAttemptRow($outcome, $path, $style, $revalidationBuilder);
            $attempts[] = $attempt;

            if ($stopOnSuccess && ($attempt['revalidation_success'] ?? false) === true) {
                $foundSuccess = true;
                break;
            }
        }

        $report['attempts'] = $attempts;
        $report['matrix_summary'] = $this->buildMatrixSummary($attempts, $pairs, $foundSuccess && $stopOnSuccess);

        return $this->emitReport($report, $certificationSupport, $connection->id, $scenario, $shopHttpStatus);
    }

    /**
     * @param  list<string>  $paths
     * @param  list<string>  $styles
     * @return list<array{path: string, style: string}>
     */
    protected function buildMatrixPairs(array $paths, array $styles, int $maxAttempts): array
    {
        $pairs = [];
        foreach ($paths as $path) {
            foreach ($styles as $style) {
                $pairs[] = ['path' => $path, 'style' => $style];
                if (count($pairs) >= $maxAttempts) {
                    return $pairs;
                }
            }
        }

        return $pairs;
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @return array<string, mixed>
     */
    protected function buildMatrixAttemptRow(
        array $outcome,
        string $path,
        string $style,
        SabreRevalidationPayloadBuilder $revalidationBuilder,
    ): array {
        $linkage = is_array($outcome['linkage'] ?? null) ? $outcome['linkage'] : [];
        $linkageDigest = is_array($outcome['linkage_digest'] ?? null)
            ? $outcome['linkage_digest']
            : $revalidationBuilder->linkageDigest($linkage);
        $errorDigest = is_array($outcome['error_digest'] ?? null) ? $outcome['error_digest'] : [];
        $httpStatus = isset($outcome['http_status']) ? (int) $outcome['http_status'] : null;
        $revalidationSuccess = ($outcome['success'] ?? false) === true;

        $responseStructure = $this->buildCertResponseStructure($outcome);
        $failureClassification = $this->classifyFailure($outcome, $httpStatus, $errorDigest);
        $cpnrReadyAfter = $this->assessCpnrContextReadyAfterRevalidate($revalidationSuccess, $linkage, $linkageDigest);

        $has27131 = $this->responseSignalsSabre27131($outcome, $errorDigest);
        $usableGir = ($responseStructure['grouped_itinerary_usable_hint'] ?? false) === true
            || $this->linkageDigestHintsUsableGroupedItinerary($linkageDigest);
        if (! $revalidationSuccess && $has27131 && $usableGir) {
            $failureClassification = 'warning_27131_recoverable_gir';
        }

        $reasonCode = $this->resolveReasonCode($outcome, $revalidationSuccess, $httpStatus, $errorDigest);
        if (! $revalidationSuccess && $has27131 && $usableGir) {
            $reasonCode = 'sabre_27131_with_usable_grouped_itinerary_response';
        }

        return [
            'path' => $path,
            'style' => $style,
            'http_status' => $httpStatus,
            'sabre_error_code' => $this->firstErrorCode($errorDigest),
            'safe_message' => $this->firstSafeMessage($errorDigest, $outcome),
            'revalidation_success' => $revalidationSuccess,
            'failure_classification' => $failureClassification,
            'reason_code' => $reasonCode,
            'contains_grouped_itinerary_response' => ($responseStructure['contains_grouped_itinerary_response'] ?? false) === true,
            'grouped_itinerary_usable_hint' => $usableGir,
            'contains_total_fare' => ($responseStructure['contains_total_fare'] ?? false) === true,
            'contains_booking_code' => ($responseStructure['contains_booking_code'] ?? false) === true,
            'contains_fare_basis_code' => ($responseStructure['contains_fare_basis_code'] ?? false) === true,
            'contains_validating_carrier' => ($responseStructure['contains_validating_carrier'] ?? false) === true,
            'revalidated_total_present' => isset($linkage['revalidated_total']) && is_numeric($linkage['revalidated_total']),
            'revalidated_currency_present' => isset($linkage['revalidated_currency'])
                && is_string($linkage['revalidated_currency'])
                && trim($linkage['revalidated_currency']) !== '',
            'candidate_count' => is_numeric($responseStructure['candidate_count'] ?? null)
                ? (int) $responseStructure['candidate_count']
                : 0,
            'class_of_service_returned' => $this->linkageHasClassOfService($linkage, $linkageDigest),
            'fare_basis_returned' => ($linkageDigest['has_fare_basis'] ?? false) === true,
            'validating_carrier_returned' => ($linkageDigest['has_validating_carrier'] ?? false) === true,
            'cpnr_context_ready_after_revalidate' => $cpnrReadyAfter,
            'recommended_next_action' => $this->recommendedNextAction($failureClassification, $cpnrReadyAfter, $revalidationSuccess),
            'duration_ms' => isset($outcome['duration_ms']) ? (int) $outcome['duration_ms'] : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $attempts
     * @param  list<array{path: string, style: string}>  $pairs
     * @return array<string, mixed>
     */
    protected function buildMatrixSummary(array $attempts, array $pairs, bool $stoppedEarly): array
    {
        $successCount = 0;
        $usableGirCount = 0;
        $http200 = 0;
        $http400 = 0;
        $notAuth = 0;
        $schemaError = 0;
        $noFaresRbd = 0;
        $bestCandidate = null;
        $bestScore = -1;

        foreach ($attempts as $attempt) {
            if (($attempt['revalidation_success'] ?? false) === true) {
                $successCount++;
            }
            if (($attempt['grouped_itinerary_usable_hint'] ?? false) === true) {
                $usableGirCount++;
            }
            $http = (int) ($attempt['http_status'] ?? 0);
            if ($http === 200) {
                $http200++;
            }
            if ($http === 400) {
                $http400++;
            }
            if (($attempt['failure_classification'] ?? '') === 'not_authorized') {
                $notAuth++;
            }
            if (($attempt['failure_classification'] ?? '') === 'schema_error') {
                $schemaError++;
            }
            if (($attempt['failure_classification'] ?? '') === 'no_fares_rbd_carrier') {
                $noFaresRbd++;
            }

            $score = $this->scoreMatrixAttempt($attempt);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestCandidate = [
                    'path' => $attempt['path'] ?? null,
                    'style' => $attempt['style'] ?? null,
                    'score' => $score,
                    'revalidation_success' => ($attempt['revalidation_success'] ?? false) === true,
                    'cpnr_context_ready_after_revalidate' => ($attempt['cpnr_context_ready_after_revalidate'] ?? false) === true,
                    'failure_classification' => $attempt['failure_classification'] ?? null,
                    'reason_code' => $attempt['reason_code'] ?? null,
                ];
            }
        }

        return [
            'total_attempts' => count($attempts),
            'pairs_planned_total' => count($pairs),
            'stopped_early_on_success' => $stoppedEarly,
            'success_count' => $successCount,
            'usable_gir_count' => $usableGirCount,
            'http_200_count' => $http200,
            'http_400_count' => $http400,
            'not_authorized_count' => $notAuth,
            'schema_error_count' => $schemaError,
            'no_fares_rbd_carrier_count' => $noFaresRbd,
            'best_candidate' => $bestCandidate,
            'final_recommendation' => $this->finalMatrixRecommendation($successCount, $usableGirCount, $bestCandidate),
        ];
    }

    /**
     * @param  array<string, mixed>  $attempt
     */
    protected function scoreMatrixAttempt(array $attempt): int
    {
        $score = 0;
        if (($attempt['revalidation_success'] ?? false) === true) {
            $score += 1000;
        }
        if (($attempt['cpnr_context_ready_after_revalidate'] ?? false) === true) {
            $score += 500;
        }
        if (($attempt['grouped_itinerary_usable_hint'] ?? false) === true) {
            $score += 200;
        }
        if (($attempt['revalidated_total_present'] ?? false) === true) {
            $score += 50;
        }
        if (($attempt['fare_basis_returned'] ?? false) === true) {
            $score += 30;
        }
        if (($attempt['validating_carrier_returned'] ?? false) === true) {
            $score += 20;
        }
        if ((int) ($attempt['http_status'] ?? 0) === 200) {
            $score += 10;
        }

        return $score;
    }

    /**
     * @param  array<string, mixed>|null  $bestCandidate
     */
    protected function finalMatrixRecommendation(int $successCount, int $usableGirCount, ?array $bestCandidate): string
    {
        if ($successCount > 0 && ($bestCandidate['cpnr_context_ready_after_revalidate'] ?? false) === true) {
            return 'use_best_candidate_path_style_for_optional_revalidation_before_ticketing';
        }
        if ($successCount > 0) {
            return 'revalidation_partial_success_review_linkage_before_enabling_mandatory_revalidate';
        }
        if ($usableGirCount > 0) {
            return 'extend_linkage_parser_for_usable_gir_attempts_or_proceed_pnr_only_without_mandatory_revalidate';
        }

        return 'no_cert_revalidate_combo_working_proceed_phase_3b_cpnr_send_with_pnr_only_revalidation_waived';
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildEmptyMatrixSummary(): array
    {
        return [
            'total_attempts' => 0,
            'pairs_planned_total' => 0,
            'stopped_early_on_success' => false,
            'success_count' => 0,
            'usable_gir_count' => 0,
            'http_200_count' => 0,
            'http_400_count' => 0,
            'not_authorized_count' => 0,
            'schema_error_count' => 0,
            'no_fares_rbd_carrier_count' => 0,
            'best_candidate' => null,
            'final_recommendation' => 'fix_offer_selection_before_matrix',
        ];
    }

    /**
     * @return list<string>
     */
    protected function resolvePathsOption(): array
    {
        return $this->parseCsvPaths($this->option('paths'), self::DEFAULT_PATHS);
    }

    /**
     * @return list<string>
     */
    protected function resolveStylesOption(): array
    {
        $allowed = self::DEFAULT_STYLES;
        $parsed = $this->parseCsvStrings($this->option('styles'), self::DEFAULT_STYLES);

        return array_values(array_filter($parsed, static fn (string $s): bool => in_array($s, $allowed, true)));
    }

    /**
     * @param  list<string>  $defaults
     * @return list<string>
     */
    protected function parseCsvPaths(mixed $raw, array $defaults): array
    {
        $items = $this->parseCsvStrings($raw, $defaults);
        $out = [];
        foreach ($items as $item) {
            $p = trim($item);
            if ($p === '') {
                continue;
            }
            $out[] = ($p[0] === '/') ? $p : '/'.$p;
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  list<string>  $defaults
     * @return list<string>
     */
    protected function parseCsvStrings(mixed $raw, array $defaults): array
    {
        if (! is_string($raw) || trim($raw) === '') {
            return $defaults;
        }

        $parts = array_map('trim', explode(',', $raw));

        return array_values(array_filter($parts, static fn (string $s): bool => $s !== ''));
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
     * @param  array<string, mixed>  $report
     */
    protected function emitReport(
        array $report,
        SabrePnrCertificationSupport $certificationSupport,
        int $connectionId,
        string $scenario,
        int $shopHttpStatus,
    ): int {
        try {
            $certificationSupport->assertOutputSafe($report);
        } catch (Throwable) {
            $this->components->error('Report failed safety check (details omitted).');

            return self::FAILURE;
        }

        if ((bool) $this->option('json')) {
            $this->line('cert_gds_revalidate_matrix_json='.json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->printHumanSummary($report);
        }

        if ((bool) $this->option('log')) {
            $summary = is_array($report['matrix_summary'] ?? null) ? $report['matrix_summary'] : [];
            Log::info('sabre.cert_gds_revalidate_matrix', [
                'connection_id' => $connectionId,
                'resolved_base_host' => $report['resolved_base_host'] ?? null,
                'scenario' => $report['scenario'] ?? null,
                'shop_http_status' => $shopHttpStatus,
                'normalized_offer_count' => $report['normalized_offer_count'] ?? 0,
                'eligible_offer_count' => $report['eligible_offer_count'] ?? 0,
                'total_attempts' => $summary['total_attempts'] ?? 0,
                'success_count' => $summary['success_count'] ?? 0,
                'usable_gir_count' => $summary['usable_gir_count'] ?? 0,
                'final_recommendation' => $summary['final_recommendation'] ?? null,
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

        return (($report['selection_error'] ?? null) !== null) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    protected function printHumanSummary(array $report): void
    {
        $this->line('shop_http_status='.($report['shop_http_status'] ?? 0));
        $this->line('eligible_offer_count='.($report['eligible_offer_count'] ?? 0));
        $summary = is_array($report['matrix_summary'] ?? null) ? $report['matrix_summary'] : [];
        $this->line('matrix_summary.total_attempts='.($summary['total_attempts'] ?? 0));
        $this->line('matrix_summary.success_count='.($summary['success_count'] ?? 0));
        $this->line('matrix_summary.usable_gir_count='.($summary['usable_gir_count'] ?? 0));
        $this->line('matrix_summary.final_recommendation='.(string) ($summary['final_recommendation'] ?? '—'));
        $attempts = is_array($report['attempts'] ?? null) ? $report['attempts'] : [];
        foreach (array_slice($attempts, 0, 12) as $attempt) {
            if (! is_array($attempt)) {
                continue;
            }
            $this->line(
                'attempt '.($attempt['path'] ?? '?').' + '.($attempt['style'] ?? '?')
                .' http='.(string) ($attempt['http_status'] ?? '—')
                .' success='.(($attempt['revalidation_success'] ?? false) ? 'true' : 'false')
                .' class='.(string) ($attempt['failure_classification'] ?? '—')
            );
        }
        if (count($attempts) > 12) {
            $this->line('... '.(count($attempts) - 12).' more attempts (use --json or --output)');
        }
    }

    protected function resolveOutputPath(string $p): string
    {
        $p = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($p));
        if ($p === '') {
            return storage_path('app/sabre-cert-gds-revalidate-matrix.json');
        }
        if (preg_match('#^[A-Za-z]:\\\\#', $p) || str_starts_with($p, DIRECTORY_SEPARATOR)) {
            return $p;
        }

        return base_path($p);
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
     * @param  array<string, mixed>  $linkageDigest
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
            'no_fares_rbd_carrier' => 'try_alternate_path_or_enriched_style_in_matrix',
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
            'contains_grouped_itinerary_response' => $containsGir,
            'contains_total_fare' => $containsTotalFare,
            'contains_booking_code' => $containsBookingCode,
            'contains_fare_basis_code' => $containsFareBasis,
            'contains_validating_carrier' => $containsValidatingCarrier,
            'grouped_itinerary_usable_hint' => $usableHint,
            'candidate_count' => is_numeric($rs['candidate_count'] ?? null) ? (int) $rs['candidate_count'] : 0,
        ];
    }
}
