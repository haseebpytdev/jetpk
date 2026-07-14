<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * [local/testing only] Full matrix: plausible Sabre revalidate-related POST paths × payload styles. Safe scalar output
 * only (no raw request/response bodies, no Authorization, PCC, passenger/contact). Does not call Trip Orders
 * {@code createBooking} or ticketing.
 *
 * B22: Ranks rows by heuristic score; distinguishes HTTP 2xx from {@code revalidation_success}; optional JSON report
 * (metadata + digests only); {@code --max-calls} caps revalidate POST attempts. {@code /v1/trip/orders/createBooking}
 * is documented as excluded from the matrix.
 */
class SabreCompareRevalidateEndpointsCommand extends Command
{
    /** @var list<string> */
    public const ALL_STYLES = [
        'bfm_revalidate_v1',
        'bfm_revalidate_with_pricing_context',
        'bfm_revalidate_minimal_segments',
        'bfm_revalidate_original_like',
        'client_gds_revalidate_v1',
        'client_gds_revalidate_without_pos',
        'client_gds_revalidate_without_travel_preferences',
        'client_gds_revalidate_segments_only',
        'shop_replay_selected_itinerary_v1',
    ];

    /** @var list<string> */
    public const DEFAULT_PATH_CANDIDATES = [
        '/v4/shop/flights/revalidate',
        '/v4/offers/shop/revalidate',
        '/v5/offers/shop/revalidate',
        '/v4/offers/shop',
        '/v5/offers/shop',
        '/v4/offers/revalidate',
        '/v5/offers/revalidate',
        '/v4/shop/revalidate',
        '/v5/shop/revalidate',
        '/v1/shop/flights/revalidate',
        '/v1/shop/flights/fares',
    ];

    protected $signature = 'sabre:compare-revalidate-endpoints
                            {--booking= : Booking ID}
                            {--connection= : Force Sabre supplier_connection id}
                            {--paths= : Comma-separated POST path overrides (must start with /)}
                            {--styles= : Comma-separated payload styles (subset of ALL_STYLES)}
                            {--max-calls=120 : Maximum revalidate POST attempts (matrix iteration stops when reached)}
                            {--show-response-digest : Append safe response digest columns}
                            {--write-report= : Write JSON report to path (relative to project root if not absolute)}';

    protected $description = '[local/testing only] Matrix Sabre revalidate paths × styles with scoring (no createBooking)';

    public function handle(SabreBookingService $sabreBooking): int
    {
        if (! SabreInspectGate::allowed()) {
            $this->components->error('This command only runs when APP_ENV is local or testing.');

            return self::FAILURE;
        }

        $raw = $this->option('booking');
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            $this->components->error('Pass --booking={id} with a numeric booking id.');

            return self::FAILURE;
        }

        $booking = Booking::query()->find((int) $raw);
        if ($booking === null) {
            $this->components->error('Booking not found.');

            return self::FAILURE;
        }

        $apiDraft = $this->resolveInternalDraftForBooking($booking, $sabreBooking);
        if ($apiDraft === null) {
            $this->line('booking_id='.$booking->id);
            $this->line('error=booking_not_sabre_or_invalid_offer');

            return self::SUCCESS;
        }

        $connOpt = $this->option('connection');
        $forcedCid = ($connOpt !== null && $connOpt !== '' && is_numeric($connOpt)) ? (int) $connOpt : null;
        $connection = $this->resolveConnection($booking, $forcedCid);
        if ($connection === null) {
            return self::FAILURE;
        }

        $paths = $this->resolvePathsOption();
        $styles = $this->resolveStylesOption();
        $maxCalls = max(1, (int) $this->option('max-calls'));
        $showDigest = (bool) $this->option('show-response-digest');

        $this->line('Local matrix only: live Sabre revalidate HTTP; Trip Orders createBooking is intentionally not probed here; no ticketing.');
        $this->line('booking_id='.$booking->id);
        $this->line('connection_id='.$connection->id);
        $this->line('paths='.implode(',', $paths));
        $this->line('styles='.implode(',', $styles));
        $this->line('max_calls='.$maxCalls);
        $this->newLine();

        /** @var list<array<string, mixed>> $detailRows */
        $detailRows = [];
        $calls = 0;

        foreach ($paths as $path) {
            foreach ($styles as $style) {
                if ($calls >= $maxCalls) {
                    break 2;
                }
                $outcome = $sabreBooking->runRevalidationBeforeBooking($apiDraft, $connection, $style, $path);
                $calls++;
                $detailRows[] = $this->analyzeMatrixCell($path, $style, $outcome);
            }
        }

        if ($calls >= $maxCalls && count($paths) * count($styles) > $calls) {
            $this->line('stopped_early_due_to_max_calls=true');
            $this->newLine();
        }

        $headers = [
            'path',
            'style',
            'http_status',
            'payload_result',
            'score',
            'revalidation_success',
            'fare',
            'ref',
            'currency',
            'candidate_count',
            'safe_error_messages',
        ];
        if ($showDigest) {
            $headers[] = 'endpoint_result';
            $headers[] = 'response_json_valid';
            $headers[] = 'response_body_empty';
            $headers[] = 'response_top_level_keys';
        }

        $tableRows = [];
        foreach ($detailRows as $dr) {
            $row = [
                'path' => (string) $dr['path'],
                'style' => (string) $dr['style'],
                'http_status' => (string) ($dr['http_status'] ?? '—'),
                'payload_result' => (string) $dr['payload_result'],
                'score' => (string) $dr['score'],
                'revalidation_success' => (($dr['revalidation_success'] ?? false) ? 'true' : 'false'),
                'fare' => (($dr['has_revalidated_fare'] ?? false) ? 'true' : 'false'),
                'ref' => (($dr['has_any_reference'] ?? false) ? 'true' : 'false'),
                'currency' => (($dr['has_revalidated_currency'] ?? false) ? 'true' : 'false'),
                'candidate_count' => (string) ($dr['candidate_count'] ?? '0'),
                'safe_error_messages' => (string) ($dr['safe_error_messages'] ?? '—'),
            ];
            if ($showDigest) {
                $row['endpoint_result'] = (string) ($dr['endpoint_result'] ?? '—');
                $row['response_json_valid'] = (($dr['response_json_valid'] ?? false) ? 'true' : 'false');
                $row['response_body_empty'] = (($dr['response_body_empty'] ?? false) ? 'true' : 'false');
                $row['response_top_level_keys'] = substr((string) ($dr['response_top_level_keys'] ?? ''), 0, 200);
                if ($row['response_top_level_keys'] === '') {
                    $row['response_top_level_keys'] = '—';
                }
            }
            $tableRows[] = $row;
        }

        $this->table($headers, $tableRows);

        $best = $this->pickRecommendation($detailRows);
        $this->newLine();
        if ($best !== null) {
            $this->line('recommended_revalidate_path='.($best['path'] ?? ''));
            $this->line('recommended_revalidate_style='.($best['style'] ?? ''));
            $this->line('recommendation_reason='.($best['recommendation_reason'] ?? ''));
        } else {
            $this->line('recommended_revalidate_path=(none — no usable revalidation response)');
            $this->line('recommended_revalidate_style=(none — no usable revalidation response)');
            $this->line('recommendation_reason=no row with score>0 and (revalidation_success or fare/currency/reference signals)');
        }

        $reportPath = $this->option('write-report');
        if (is_string($reportPath) && trim($reportPath) !== '') {
            $full = $this->normalizeFilesystemPath($reportPath);
            File::ensureDirectoryExists(dirname($full));
            $report = $this->buildJsonReport($booking->id, $connection->id, $maxCalls, $calls, $paths, $styles, $detailRows, $best);
            File::put($full, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->newLine();
            $this->line('wrote_report='.$full);
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    protected function defaultMatrixPaths(): array
    {
        $cfg = trim((string) config('suppliers.sabre.revalidate_path', '/v4/shop/flights/revalidate'));
        $cfg = $cfg !== '' && $cfg[0] === '/' ? $cfg : '/'.$cfg;
        $merged = array_merge([$cfg], self::DEFAULT_PATH_CANDIDATES);

        $out = [];
        $seen = [];
        foreach ($merged as $p) {
            $p = trim((string) $p);
            if ($p === '') {
                continue;
            }
            $p = $p[0] === '/' ? $p : '/'.$p;
            if (isset($seen[$p])) {
                continue;
            }
            $seen[$p] = true;
            $out[] = $p;
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    protected function resolvePathsOption(): array
    {
        $raw = $this->option('paths');
        if (! is_string($raw) || trim($raw) === '') {
            return $this->defaultMatrixPaths();
        }
        $parts = array_map('trim', explode(',', $raw));
        $out = [];
        foreach ($parts as $p) {
            if ($p === '') {
                continue;
            }
            $out[] = $p[0] === '/' ? $p : '/'.$p;
        }

        return $out !== [] ? array_values(array_unique($out)) : $this->defaultMatrixPaths();
    }

    /**
     * @return list<string>
     */
    protected function resolveStylesOption(): array
    {
        $raw = $this->option('styles');
        if (! is_string($raw) || trim($raw) === '') {
            return self::ALL_STYLES;
        }
        $parts = array_map('trim', explode(',', $raw));
        $allowed = array_flip(self::ALL_STYLES);
        $out = [];
        foreach ($parts as $p) {
            if ($p !== '' && isset($allowed[$p])) {
                $out[] = $p;
            }
        }

        return $out !== [] ? array_values(array_unique($out)) : self::ALL_STYLES;
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @return array<string, mixed>
     */
    protected function analyzeMatrixCell(string $path, string $style, array $outcome): array
    {
        $http = $outcome['http_status'] ?? null;
        $httpInt = is_int($http) || (is_numeric($http) && (string) (int) $http === (string) $http) ? (int) $http : null;

        $digest = is_array($outcome['linkage_digest'] ?? null) ? $outcome['linkage_digest'] : [];
        $err = is_array($outcome['error_digest'] ?? null) ? $outcome['error_digest'] : [];

        $includes27131 = ($outcome['includes_sabre_error_27131'] ?? false) === true
            || $this->digestMentions27131($err);

        $endpointResult = $this->classifyEndpointResult($httpInt);
        $payloadResult = $this->classifyPayloadResult($outcome, $httpInt, $includes27131);

        $hasFare = ($digest['has_revalidated_fare'] ?? false) === true;
        $hasCur = ($digest['has_revalidated_currency'] ?? false) === true;
        $hasRevRef = ($digest['has_revalidation_reference'] ?? false) === true;
        $hasOffer = ($digest['has_offer_reference'] ?? false) === true;
        $hasOrder = ($digest['has_order_reference'] ?? false) === true;
        $hasPq = ($digest['has_price_quote_reference'] ?? false) === true;
        $hasAnyRef = $hasRevRef || $hasOffer || $hasOrder || $hasPq;

        $success = ($outcome['success'] ?? false) === true;
        $score = $this->computeScore($success, $hasFare, $hasCur, $hasAnyRef, $httpInt, $includes27131, $payloadResult);

        $rs = is_array($outcome['response_structure'] ?? null) ? $outcome['response_structure'] : [];
        $jsonValid = ($rs['json_valid'] ?? '') === 'true';
        $bodyEmpty = ($rs['empty_body'] ?? '') === 'true';
        $cand = (string) ($rs['candidate_count'] ?? '0');

        $safeMsgs = $this->formatSafeMessages($err);

        return [
            'path' => $path,
            'style' => $style,
            'http_status' => $httpInt !== null ? (string) $httpInt : '—',
            'endpoint_result' => $endpointResult,
            'payload_result' => $payloadResult,
            'score' => $score,
            'revalidation_success' => $success,
            'has_revalidated_fare' => $hasFare,
            'has_revalidated_currency' => $hasCur,
            'has_revalidation_reference' => $hasRevRef,
            'has_offer_reference' => $hasOffer,
            'has_order_reference' => $hasOrder,
            'has_price_quote_reference' => $hasPq,
            'has_any_reference' => $hasAnyRef,
            'candidate_count' => $cand,
            'response_json_valid' => $jsonValid,
            'response_body_empty' => $bodyEmpty,
            'response_top_level_keys' => (string) ($rs['top_level_keys'] ?? ''),
            'safe_error_messages' => $safeMsgs,
            'includes_27131' => $includes27131,
            'reason_code' => (string) ($outcome['reason_code'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $digest
     */
    protected function digestMentions27131(array $digest): bool
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

    protected function classifyEndpointResult(?int $http): string
    {
        if ($http === null) {
            return 'unknown';
        }
        if ($http === 403) {
            return 'forbidden';
        }
        if ($http === 404) {
            return 'not_found';
        }
        if ($http === 405) {
            return 'method_not_allowed';
        }
        if ($http >= 400 && $http < 500) {
            return 'reachable_validation_error';
        }
        if ($http >= 200 && $http < 300) {
            return 'ready';
        }

        return 'unknown';
    }

    /**
     * @param  array<string, mixed>  $outcome
     */
    protected function classifyPayloadResult(array $outcome, ?int $http, bool $includes27131): string
    {
        if ($http === null) {
            return 'unusable';
        }
        if ($http >= 200 && $http < 300 && ($outcome['success'] ?? false) === true) {
            return 'accepted_http_2xx';
        }
        if ($includes27131) {
            return 'rejected_27131';
        }
        if ($http >= 400 && $http < 500) {
            return 'rejected_validation';
        }
        if ($http >= 500) {
            return 'unusable';
        }
        $reason = (string) ($outcome['reason_code'] ?? '');
        if ($http >= 200 && $http < 300) {
            if ($reason === 'sabre_revalidation_application_warning_or_error') {
                return 'application_warning';
            }
            if ($reason === 'sabre_revalidation_empty_or_unusable_response') {
                return 'empty_200';
            }

            return 'unusable';
        }

        return 'unusable';
    }

    protected function computeScore(
        bool $success,
        bool $hasFare,
        bool $hasCur,
        bool $hasAnyRef,
        ?int $http,
        bool $includes27131,
        string $payloadResult,
    ): int {
        $score = 0;
        if ($success) {
            $score += 50;
        }
        if ($hasFare && $hasCur) {
            $score += 20;
        }
        if ($hasAnyRef) {
            $score += 20;
        }
        if ($http !== null && $http >= 200 && $http < 300) {
            $score += 10;
        }
        if ($includes27131) {
            $score -= 20;
        }
        if (in_array($payloadResult, ['empty_200', 'unusable', 'application_warning'], true) && $http !== null && $http >= 200 && $http < 300 && ! $success) {
            $score -= 10;
        }
        if ($http !== null && in_array($http, [403, 404, 405], true)) {
            $score -= 30;
        }

        return $score;
    }

    /**
     * @param  array<string, mixed>  $err
     */
    protected function formatSafeMessages(array $err): string
    {
        $msgs = array_slice((array) ($err['response_error_messages'] ?? []), 0, 6);
        $safeMsgs = implode(' | ', array_map(static fn ($m): string => substr((string) $m, 0, 120), $msgs));
        if ($safeMsgs === '') {
            $codes = array_slice((array) ($err['response_error_codes'] ?? []), 0, 4);
            $safeMsgs = implode(' | ', array_map(static fn ($c): string => substr((string) $c, 0, 32), $codes));
        }
        if (strlen($safeMsgs) > 260) {
            $safeMsgs = substr($safeMsgs, 0, 260).'…';
        }

        return $safeMsgs !== '' ? $safeMsgs : '—';
    }

    /**
     * @param  list<array<string, mixed>>  $detailRows
     * @return array<string, mixed>|null
     */
    protected function pickRecommendation(array $detailRows): ?array
    {
        $candidates = [];
        foreach ($detailRows as $dr) {
            $sc = (int) ($dr['score'] ?? 0);
            if ($sc <= 0) {
                continue;
            }
            $usable = ($dr['revalidation_success'] ?? false) === true
                || ($dr['has_revalidated_fare'] ?? false) === true
                || ($dr['has_revalidated_currency'] ?? false) === true
                || ($dr['has_any_reference'] ?? false) === true;
            if (! $usable) {
                continue;
            }
            $candidates[] = $dr;
        }
        if ($candidates === []) {
            return null;
        }

        usort($candidates, static function (array $a, array $b): int {
            $sa = (int) ($a['score'] ?? 0);
            $sb = (int) ($b['score'] ?? 0);
            if ($sa !== $sb) {
                return $sb <=> $sa;
            }
            $as = ($a['revalidation_success'] ?? false) === true ? 1 : 0;
            $bs = ($b['revalidation_success'] ?? false) === true ? 1 : 0;
            if ($as !== $bs) {
                return $bs <=> $as;
            }
            $af = (($a['has_revalidated_fare'] ?? false) && ($a['has_revalidated_currency'] ?? false)) ? 1 : 0;
            $bf = (($b['has_revalidated_fare'] ?? false) && ($b['has_revalidated_currency'] ?? false)) ? 1 : 0;
            if ($af !== $bf) {
                return $bf <=> $af;
            }
            $ar = ($a['has_any_reference'] ?? false) === true ? 1 : 0;
            $br = ($b['has_any_reference'] ?? false) === true ? 1 : 0;

            return $br <=> $ar;
        });

        $top = $candidates[0];
        $why = [];
        if (($top['revalidation_success'] ?? false) === true) {
            $why[] = 'revalidation_success';
        }
        if (($top['has_revalidated_fare'] ?? false) === true && ($top['has_revalidated_currency'] ?? false) === true) {
            $why[] = 'revalidated_fare_and_currency';
        }
        if (($top['has_any_reference'] ?? false) === true) {
            $why[] = 'reference_fields';
        }
        $why[] = 'score='.$top['score'];
        $why[] = 'payload_result='.$top['payload_result'];

        return [
            'path' => $top['path'],
            'style' => $top['style'],
            'recommendation_reason' => implode('; ', $why),
        ];
    }

    /**
     * @param  list<string>  $paths
     * @param  list<string>  $styles
     * @param  list<array<string, mixed>>  $detailRows
     * @param  array<string, mixed>|null  $best
     * @return array<string, mixed>
     */
    protected function buildJsonReport(
        int $bookingId,
        int $connectionId,
        int $maxCalls,
        int $callsMade,
        array $paths,
        array $styles,
        array $detailRows,
        ?array $best,
    ): array {
        $rows = [];
        foreach ($detailRows as $dr) {
            $rows[] = [
                'path' => (string) $dr['path'],
                'style' => (string) $dr['style'],
                'http_status' => $dr['http_status'],
                'endpoint_result' => (string) $dr['endpoint_result'],
                'payload_result' => (string) $dr['payload_result'],
                'score' => (int) $dr['score'],
                'revalidation_success' => (bool) ($dr['revalidation_success'] ?? false),
                'has_revalidated_fare' => (bool) ($dr['has_revalidated_fare'] ?? false),
                'has_revalidated_currency' => (bool) ($dr['has_revalidated_currency'] ?? false),
                'has_revalidation_reference' => (bool) ($dr['has_revalidation_reference'] ?? false),
                'has_offer_reference' => (bool) ($dr['has_offer_reference'] ?? false),
                'has_order_reference' => (bool) ($dr['has_order_reference'] ?? false),
                'has_price_quote_reference' => (bool) ($dr['has_price_quote_reference'] ?? false),
                'candidate_count' => (string) ($dr['candidate_count'] ?? '0'),
                'response_json_valid' => (bool) ($dr['response_json_valid'] ?? false),
                'response_body_empty' => (bool) ($dr['response_body_empty'] ?? false),
                'response_top_level_keys' => substr((string) ($dr['response_top_level_keys'] ?? ''), 0, 400),
                'safe_error_messages' => substr((string) ($dr['safe_error_messages'] ?? ''), 0, 320),
                'reason_code' => (string) ($dr['reason_code'] ?? ''),
                'includes_27131' => (bool) ($dr['includes_27131'] ?? false),
            ];
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'booking_id' => $bookingId,
            'connection_id' => $connectionId,
            'max_calls' => $maxCalls,
            'calls_made' => $callsMade,
            'paths' => $paths,
            'styles' => $styles,
            'informational' => 'Trip Orders POST /v1/trip/orders/createBooking is excluded from this revalidation matrix (booking side-effect risk). This command never calls it.',
            'rows' => $rows,
            'recommended_revalidate_path' => $best['path'] ?? null,
            'recommended_revalidate_style' => $best['style'] ?? null,
            'recommendation_reason' => $best['recommendation_reason'] ?? null,
        ];
    }

    protected function normalizeFilesystemPath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return $path;
        }
        if (preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1 || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return $path;
        }

        return base_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path));
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolveInternalDraftForBooking(Booking $booking, SabreBookingService $sabreBooking): ?array
    {
        $booking->loadMissing(['passengers', 'contact', 'fareBreakdown']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $p = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($p !== SupplierProvider::Sabre->value) {
            return null;
        }

        $reflection = new \ReflectionClass($sabreBooking);
        $merge = $reflection->getMethod('mergePublicReviewSabreSnapshotFromBooking');
        $merge->setAccessible(true);
        $passengerData = $reflection->getMethod('passengerDataFromBooking');
        $passengerData->setAccessible(true);

        $snapshot = [];
        if (is_array($meta['normalized_offer_snapshot'] ?? null)) {
            $snapshot = $meta['normalized_offer_snapshot'];
        } elseif (is_array($meta['validated_offer_snapshot'] ?? null)) {
            $snapshot = $meta['validated_offer_snapshot'];
        } elseif (is_array($meta['flight_offer_snapshot'] ?? null)) {
            $snapshot = $meta['flight_offer_snapshot'];
        }
        $snapshot = $merge->invoke($sabreBooking, $booking, $snapshot);
        $draft = $sabreBooking->prepareBookingPayload($snapshot, $passengerData->invoke($sabreBooking, $booking));
        if (! is_array($draft) || ($draft['_valid'] ?? false) !== true) {
            return null;
        }
        unset($draft['_valid']);

        return $draft;
    }

    protected function resolveConnection(Booking $booking, ?int $forcedConnectionId): ?SupplierConnection
    {
        if ($forcedConnectionId !== null && $forcedConnectionId > 0) {
            $c = SupplierConnection::query()->find($forcedConnectionId);
            if ($c === null) {
                $this->components->error('Supplier connection not found for --connection='.$forcedConnectionId);

                return null;
            }
            if ($c->provider !== SupplierProvider::Sabre) {
                $this->components->error('Supplier connection is not Sabre.');

                return null;
            }
            if ((int) $c->agency_id !== (int) $booking->agency_id) {
                $this->components->error('Supplier connection agency_id does not match the booking agency.');

                return null;
            }

            return $c;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $cid = (int) ($meta['supplier_connection_id'] ?? 0);
        if ($cid > 0) {
            $c = SupplierConnection::query()->find($cid);
            if ($c !== null && $c->provider === SupplierProvider::Sabre) {
                return $c;
            }
        }

        return SupplierConnection::query()
            ->where('provider', SupplierProvider::Sabre)
            ->orderBy('id')
            ->first();
    }
}
