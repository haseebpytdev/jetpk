<?php

namespace App\Console\Commands;

use App\Data\FlightSearchRequestData;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Services\Suppliers\Sabre\Diagnostics\SabreCertEntitlementMatrix;
use App\Services\Suppliers\Sabre\Diagnostics\SabreInspectSanitizer;
use App\Services\Suppliers\Sabre\SabreFlightSearchNormalizer;
use App\Services\Suppliers\Sabre\SabreFlightSearchRequestBuilder;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use App\Services\Suppliers\Sabre\SabreStoredPricingContextDigest;
use App\Support\Bookings\SabrePnrCertificationSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * CERT-only GDS shop linkage-readiness report ({@code /v4/offers/shop}).
 * No PNR create, no ticketing, no cancel. Reuses {@see SabreInspectGate::certEntitlementMatrixSendAllowed()}.
 */
class SabreCertGdsLinkageReportCommand extends Command
{
    public const REPORT_VERSION = 'cert_gds_linkage_v1';

    protected $signature = 'sabre:cert-gds-linkage-report
                            {--connection= : Sabre supplier connection ID}
                            {--from=LHE : Origin IATA}
                            {--to=DXB : Destination IATA}
                            {--date=2026-07-15 : Departure date YYYY-MM-DD}
                            {--return-date= : Return date YYYY-MM-DD (required for --scenario=return)}
                            {--scenario= : Filter: ow_direct, ow_connecting, or return}
                            {--limit=20 : Max offers in report}
                            {--json : Emit cert_gds_linkage_report_json=... only}
                            {--output= : Optional path to write sanitized JSON}
                            {--log : Log summary counts only (no raw payload)}';

    protected $description = 'CERT GDS linkage-readiness report from live /v4/offers/shop (no PNR/ticketing/cancel)';

    public function handle(
        SabreFlightSearchRequestBuilder $builder,
        SabreClient $client,
        SabreFlightSearchNormalizer $normalizer,
        SabreStoredPricingContextDigest $digestor,
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
        $this->line('CERT GDS linkage report: live /v4/offers/shop only. No PNR, no ticketing, no cancel.');
        $this->newLine();

        if (! SabreInspectGate::certEntitlementMatrixSendAllowed($connection)) {
            $reason = SabreInspectGate::certEntitlementMatrixSendBlockReason($connection) ?? 'blocked';
            $this->components->error('Sabre CERT GDS linkage report is not allowed ('.$reason.').');

            return self::FAILURE;
        }

        $resolvedBase = SabreInspectGate::resolveSabreBaseUrlForGate($connection);
        if ($resolvedBase === '' || SabreInspectGate::isProductionLiveSabreHost($resolvedBase)) {
            $this->components->error('Sabre CERT GDS linkage report blocks api.platform.sabre.com; use a CERT host (e.g. api.cert.platform.sabre.com).');

            return self::FAILURE;
        }

        if (! SabreInspectGate::isCertSabreHost($resolvedBase)) {
            $this->components->error('Sabre CERT GDS linkage report requires a CERT Sabre host (e.g. api.cert.platform.sabre.com).');

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
        $payload = $builder->build($request, $connection);

        try {
            $response = $client->postShopPayload($connection, $payload);
        } catch (Throwable) {
            $this->components->error('Shop request failed (details omitted).');

            return self::FAILURE;
        }

        $httpStatus = $response->status();
        $json = $response->json();

        if (! $response->successful() || ! is_array($json)) {
            $safe = SabreInspectSanitizer::sanitizeErrorBody(is_array($json) ? $json : null);
            $this->line('http_status='.$httpStatus);
            $this->line('shop_error_summary='.json_encode($safe, JSON_UNESCAPED_SLASHES));

            return self::FAILURE;
        }

        $normalized = $normalizer->normalize($json, $connection, $request);
        $offerRows = [];
        foreach ($normalized as $offer) {
            $snap = $normalizer->mergeSabrePricingLinkageHandoff(
                $normalizer->ensureSabreBookingContextOnCachedOffer($offer->toArray())
            );
            $offerRows[] = $this->buildOfferRow($snap, $digestor, $scenario);
        }

        $offerRows = $this->filterOffersForScenario($offerRows, $scenario, $origin);
        $limit = max(1, min(100, (int) $this->option('limit')));
        $offerRows = array_slice($offerRows, 0, $limit);

        $readyCount = count(array_filter($offerRows, static fn (array $r): bool => ($r['auto_pnr_pricing_context_ready'] ?? false) === true));
        $ndcCount = count(array_filter($offerRows, static fn (array $r): bool => ($r['distribution_channel'] ?? '') === 'ndc'));

        $report = [
            'report_version' => self::REPORT_VERSION,
            'connection_id' => $connection->id,
            'base_url_resolution' => $baseUrlContext,
            'resolved_base_host' => $baseUrlContext['resolved_base_host'] ?? 'unknown',
            'shop_endpoint_path' => $shopPath,
            'http_status' => $httpStatus,
            'scenario' => $scenario !== '' ? $scenario : null,
            'search' => [
                'origin' => $origin,
                'destination' => $destination,
                'depart_date' => $departDate,
                'return_date' => $returnDate !== '' ? $returnDate : null,
                'trip_type' => $tripType,
            ],
            'normalized_offer_count' => count($normalized),
            'reported_offer_count' => count($offerRows),
            'auto_pnr_ready_count' => $readyCount,
            'ndc_offer_count' => $ndcCount,
            'offers' => $offerRows,
        ];

        try {
            $certificationSupport->assertOutputSafe($report);
        } catch (Throwable $e) {
            $this->components->error('Report failed safety check (details omitted).');

            return self::FAILURE;
        }

        if ((bool) $this->option('json')) {
            $this->line('cert_gds_linkage_report_json='.json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->printHumanSummary($report);
        }

        if ((bool) $this->option('log')) {
            Log::info('sabre.cert_gds_linkage_report', [
                'connection_id' => $connection->id,
                'resolved_base_host' => $report['resolved_base_host'] ?? null,
                'scenario' => $report['scenario'] ?? null,
                'http_status' => $httpStatus,
                'normalized_offer_count' => $report['normalized_offer_count'] ?? 0,
                'reported_offer_count' => $report['reported_offer_count'] ?? 0,
                'auto_pnr_ready_count' => $readyCount,
                'ndc_offer_count' => $ndcCount,
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

        return self::SUCCESS;
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
        $operating = is_array($snap['operating_carrier_chain'] ?? null) ? $snap['operating_carrier_chain'] : [];
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

        $legRefs = is_array($ctx['leg_refs'] ?? null) ? $ctx['leg_refs'] : [];
        $scheduleRefs = is_array($ctx['schedule_refs'] ?? null) ? $ctx['schedule_refs'] : [];
        $bookingBySeg = is_array($handoff['booking_classes_by_segment'] ?? null)
            ? $handoff['booking_classes_by_segment']
            : (is_array($ctx['booking_class'] ?? null) ? $ctx['booking_class'] : []);
        $fareBasisBySeg = is_array($handoff['fare_basis_codes_by_segment'] ?? null)
            ? $handoff['fare_basis_codes_by_segment']
            : [];

        $fare = is_array($snap['fare_breakdown'] ?? null) ? $snap['fare_breakdown'] : [];
        $baggage = is_array($snap['baggage'] ?? null) ? $snap['baggage'] : [];
        $baggageSummary = trim((string) ($baggage['summary'] ?? ''));
        if ($baggageSummary === '') {
            $baggageSummary = trim((string) (($baggage['checked'] ?? '').' '.($baggage['cabin'] ?? '')));
        }

        $brandId = trim((string) ($ctx['brand_code'] ?? $handoff['brand_code'] ?? $snap['brand_code'] ?? ''));
        $brandName = trim((string) ($snap['fare_family'] ?? ''));
        $fareFamily = $brandName;
        $cabin = trim((string) ($snap['cabin'] ?? ''));

        $distributionChannel = $this->resolveDistributionChannel($snap, $ctx, $handoff);
        $autoReady = ($readiness['auto_pnr_pricing_context_ready'] ?? false) === true;
        $cpnrEligible = $distributionChannel !== 'ndc' && $autoReady;
        $cpnrIneligibleReason = $this->resolveCpnrIneligibleReason($distributionChannel, $autoReady, $readiness);

        $itineraryRefPresent = ($readiness['has_itinerary_reference'] ?? false) === true
            || trim((string) ($digest['itinerary_ref'] ?? '')) !== '';
        $piIndex = $readiness['bfm_pricing_information_index'] ?? ($digest['pricing_information_index'] ?? null);
        $piIndexPresent = ($readiness['bfm_pricing_information_index_present'] ?? false) === true;

        $missing = is_array($readiness['missing_pricing_context_fields'] ?? null)
            ? array_values($readiness['missing_pricing_context_fields'])
            : [];
        $policy = (string) ($readiness['pricing_context_policy'] ?? '');

        $row = [
            'offer_id' => substr(hash('sha256', (string) ($snap['offer_id'] ?? '')), 0, 16),
            'route' => $route,
            'origin' => strtoupper(trim((string) ($snap['origin'] ?? ''))),
            'destination' => strtoupper(trim((string) ($snap['destination'] ?? ''))),
            'carrier_chain' => $carrierChain,
            'marketing_carriers' => array_values(array_map(static fn ($c): string => strtoupper(trim((string) $c)), $marketing)),
            'operating_carriers' => array_values(array_map(static fn ($c): string => strtoupper(trim((string) $c)), $operating)),
            'validating_carrier' => strtoupper(trim((string) ($snap['validating_carrier'] ?? $digest['validating_carrier'] ?? ''))),
            'segment_count' => $segmentCount,
            'distribution_channel' => $distributionChannel,
            'cpnr_eligible' => $cpnrEligible,
            'cpnr_ineligible_reason' => $cpnrIneligibleReason,
            'itinerary_ref_present' => $itineraryRefPresent,
            'pricing_information_index_present' => $piIndexPresent,
            'pricing_information_index' => is_numeric($piIndex) ? (int) $piIndex : null,
            'leg_refs_count' => count($legRefs),
            'schedule_refs_count' => count($scheduleRefs),
            'booking_classes_by_segment_present' => $this->perSegmentListComplete($bookingBySeg, $segmentCount),
            'booking_classes_by_segment' => $this->capStringList($bookingBySeg),
            'fare_basis_codes_by_segment_present' => $this->perSegmentListComplete($fareBasisBySeg, $segmentCount)
                || ($digest['fare_basis_codes'] ?? []) !== [],
            'fare_basis_codes_by_segment' => $this->capStringList($fareBasisBySeg !== []
                ? $fareBasisBySeg
                : (is_array($digest['fare_basis_codes'] ?? null) ? $digest['fare_basis_codes'] : [])),
            'cabin_present' => $cabin !== '',
            'cabin' => $cabin !== '' ? $cabin : null,
            'brand_id_present' => $brandId !== '',
            'brand_id' => $brandId !== '' ? substr($brandId, 0, 32) : null,
            'brand_name_present' => $brandName !== '',
            'brand_name' => $brandName !== '' ? substr($brandName, 0, 64) : null,
            'fare_family_present' => $fareFamily !== '',
            'fare_family' => $fareFamily !== '' ? substr($fareFamily, 0, 64) : null,
            'baggage_present' => $baggageSummary !== '',
            'total_fare' => isset($fare['supplier_total']) ? round((float) $fare['supplier_total'], 2) : null,
            'base_fare' => isset($fare['base_fare']) ? round((float) $fare['base_fare'], 2) : null,
            'taxes' => isset($fare['taxes']) ? round((float) $fare['taxes'], 2) : null,
            'currency' => isset($fare['currency']) ? strtoupper(substr(trim((string) $fare['currency']), 0, 6)) : null,
            'auto_pnr_pricing_context_ready' => $autoReady,
            'pricing_context_policy' => $policy,
            'missing_pricing_context_fields' => $missing,
            'not_ready_reason' => $this->buildNotReadyReason($cpnrEligible, $distributionChannel, $missing, $policy),
            'recommended_next_action' => $this->buildRecommendedNextAction($cpnrEligible, $distributionChannel, $missing, $autoReady),
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

        if ($scenario === 'ow_connecting') {
            usort($filtered, static function (array $a, array $b): int {
                $aSame = ($a['connecting_carrier_profile'] ?? '') === 'same_carrier' ? 0 : 1;
                $bSame = ($b['connecting_carrier_profile'] ?? '') === 'same_carrier' ? 0 : 1;
                if ($aSame !== $bSame) {
                    return $aSame <=> $bSame;
                }

                return ((int) ($b['auto_pnr_pricing_context_ready'] ?? 0)) <=> ((int) ($a['auto_pnr_pricing_context_ready'] ?? 0));
            });
        }

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
     * @param  array<string, mixed>  $readiness
     */
    protected function resolveCpnrIneligibleReason(string $distributionChannel, bool $autoReady, array $readiness): ?string
    {
        if ($distributionChannel === 'ndc') {
            return 'unsupported_distribution_channel';
        }

        if ($autoReady) {
            return null;
        }

        $missing = is_array($readiness['missing_pricing_context_fields'] ?? null)
            ? $readiness['missing_pricing_context_fields']
            : [];
        if ($missing !== []) {
            return 'missing_pricing_context:'.implode(',', array_slice($missing, 0, 8));
        }

        return 'pricing_context_not_ready';
    }

    /**
     * @param  list<string>  $missing
     */
    protected function buildNotReadyReason(bool $cpnrEligible, string $distributionChannel, array $missing, string $policy): ?string
    {
        if ($cpnrEligible) {
            return null;
        }

        if ($distributionChannel === 'ndc') {
            return 'NDC offer; CPNR path not used (NDC order lifecycle not implemented).';
        }

        if ($missing !== []) {
            return 'GDS linkage incomplete ('.implode(', ', $missing).').';
        }

        if ($policy !== '') {
            return 'GDS linkage policy '.$policy.' not satisfied.';
        }

        return 'GDS pricing linkage not ready for auto-PNR.';
    }

    /**
     * @param  list<string>  $missing
     */
    protected function buildRecommendedNextAction(bool $cpnrEligible, string $distributionChannel, array $missing, bool $autoReady): string
    {
        if ($cpnrEligible) {
            return 'proceed_to_cert_revalidate_then_cpnr_when_approved';
        }

        if ($distributionChannel === 'ndc') {
            return 'exclude_from_cpnr_use_gds_fare_or_implement_ndc_order_path_later';
        }

        if (in_array('leg_refs_schedule_refs', $missing, true) || in_array('itinerary_reference', $missing, true)) {
            return 're_shop_or_refresh_offer_snapshot_before_cpnr';
        }

        if (! $autoReady) {
            return 'manual_sabre_pricing_or_select_different_gds_fare';
        }

        return 'review_missing_linkage_fields_before_cpnr';
    }

    /**
     * @param  list<mixed>  $list
     */
    protected function perSegmentListComplete(array $list, int $segmentCount): bool
    {
        if ($segmentCount <= 0) {
            return false;
        }

        if (count($list) < $segmentCount) {
            return false;
        }

        for ($i = 0; $i < $segmentCount; $i++) {
            if (! isset($list[$i]) || trim((string) $list[$i]) === '') {
                return false;
            }
        }

        return true;
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
        $this->line('http_status='.($report['http_status'] ?? 0));
        $this->line('scenario='.(string) ($report['scenario'] ?? 'all'));
        $this->line('normalized_offer_count='.($report['normalized_offer_count'] ?? 0));
        $this->line('reported_offer_count='.($report['reported_offer_count'] ?? 0));
        $this->line('auto_pnr_ready_count='.($report['auto_pnr_ready_count'] ?? 0));
        $this->line('ndc_offer_count='.($report['ndc_offer_count'] ?? 0));
        $this->newLine();

        $tableRows = [];
        foreach ((array) ($report['offers'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $tableRows[] = [
                (string) ($row['offer_id'] ?? ''),
                (string) ($row['route'] ?? ''),
                (string) ($row['carrier_chain'] ?? ''),
                (string) ($row['segment_count'] ?? ''),
                (string) ($row['distribution_channel'] ?? ''),
                (($row['cpnr_eligible'] ?? false) ? 'yes' : 'no'),
                (($row['auto_pnr_pricing_context_ready'] ?? false) ? 'yes' : 'no'),
                (string) ($row['pricing_context_policy'] ?? ''),
            ];
        }

        $this->table(
            ['offer_id', 'route', 'carriers', 'segs', 'channel', 'cpnr_ok', 'linkage_ready', 'policy'],
            $tableRows,
        );
    }

    protected function resolveOutputPath(string $p): string
    {
        $p = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($p));
        if ($p === '') {
            return storage_path('app/sabre-cert-gds-linkage-report.json');
        }
        if (preg_match('#^[A-Za-z]:\\\\#', $p) || str_starts_with($p, DIRECTORY_SEPARATOR)) {
            return $p;
        }

        return base_path($p);
    }
}
