<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use App\Services\Suppliers\Sabre\SabreRevalidationPayloadBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * [local/testing only] Build a sanitized Sabre revalidation summary for an existing booking and, with --send, perform
 * the live revalidation HTTP call to extract fare/offer linkage (fareBasisCode, fareReference, priceQuoteReference,
 * offerReference, validatingCarrier, revalidated total/currency). Prints only safe scalar flags; never prints raw
 * payload / Authorization / PCC / passenger names / passport / DOB / contact values / raw provider JSON.
 *
 * B17: {@code --preview-json} + {@code --write-preview=} export a redacted wireable request body; {@code --style=client_gds_revalidate_v1}
 * uses a {@code RevalidateItineraryRQ} envelope. On HTTP 2xx from {@code --send}, persists a compact {@code meta.sabre_revalidate_inspect} linkage digest.
 *
 * B18: {@code --path=} overrides the revalidate POST URL for {@code --send} / preview metadata only (does not mutate config).
 *
 * B20: On `--send`, when HTTP 2xx and `revalidation_success=false`, prints `response_structure.top_level_keys`, `key_paths`,
 * `empty_body`, `json_valid`, `candidate_count`, `candidate_fields` (capped; no raw body).
 */
class SabreInspectBookingRevalidateCommand extends Command
{
    protected $signature = 'sabre:inspect-booking-revalidate {--booking= : Booking ID} {--send : Perform the live revalidation HTTP call (local/testing only)} {--style= : Override SABRE_REVALIDATE_PAYLOAD_STYLE (bfm_revalidate_v1, bfm_revalidate_minimal_segments, bfm_revalidate_with_pricing_context, bfm_revalidate_original_like, client_gds_revalidate_v1)} {--path= : Override revalidate POST path (leading /); local/testing only — does not change config} {--preview-json : Print structural summary + redacted wireable JSON preview} {--write-preview= : Write sanitized preview JSON document to this path (relative to project base unless absolute)}';

    protected $description = '[local/testing only] Sanitized Sabre revalidation summary for a booking (optional --send for live HTTP; --preview-json / --write-preview for safe export)';

    public function handle(SabreBookingService $sabreBooking, SabreRevalidationPayloadBuilder $builder): int
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

        $styleOpt = $this->option('style');
        $styleOverride = is_string($styleOpt) && trim($styleOpt) !== '' ? trim($styleOpt) : null;

        $pathOverride = $this->normalizeRevalidatePathOption($this->option('path'));
        $pathConfig = (string) config('suppliers.sabre.revalidate_path', '/v4/shop/flights/revalidate');
        $pathConfig = $pathConfig !== '' && $pathConfig[0] === '/' ? $pathConfig : '/'.$pathConfig;
        $endpointPathEffective = $pathOverride ?? $pathConfig;

        $payload = $builder->buildPayload($apiDraft, $styleOverride);
        $payloadSummary = $builder->safePayloadSummary($payload);
        $coverageSummary = $builder->normalizedPayloadCoverageSummary($payload);
        $diagnostics = $builder->structuralPayloadDiagnostics($payload);
        $this->printSummarySection('payload_summary', $payloadSummary);
        $this->printSummarySection('payload_coverage', $coverageSummary);
        $this->printSummarySection('payload_diagnostics', $diagnostics);
        $this->line('config_revalidate_payload_style='.(string) config('suppliers.sabre.revalidate_payload_style', 'bfm_revalidate_v1'));
        $this->line('revalidate_before_booking_enabled='.($sabreBooking->isRevalidationBeforeBookingEnabled() ? 'true' : 'false'));
        $this->line('revalidate_path='.$pathConfig);
        $this->line('revalidate_path_config='.$pathConfig);
        $this->line('revalidate_path_effective='.$endpointPathEffective);
        if ($pathOverride !== null) {
            $this->line('revalidate_path_override_active=true');
        }

        $writeOpt = $this->option('write-preview');
        $writePath = is_string($writeOpt) && trim($writeOpt) !== '' ? trim($writeOpt) : null;
        if ($writePath !== null) {
            $this->writePreviewFile($builder, $payload, $endpointPathEffective, $diagnostics, $writePath);
        }

        if ($this->option('preview-json')) {
            $this->printPreviewJsonBlock($builder, $payload, $endpointPathEffective, $diagnostics);
        }

        if (! $this->option('send')) {
            $this->line('send=false');
            $this->line('hint=Re-run with --send to attempt a live revalidation HTTP call.');

            return self::SUCCESS;
        }

        $connection = $this->resolveConnection($booking);
        if ($connection === null) {
            $this->components->error('No Sabre supplier connection resolvable for this booking.');

            return self::FAILURE;
        }

        $outcome = $sabreBooking->runRevalidationBeforeBooking($apiDraft, $connection, $styleOverride, $pathOverride);
        $this->line('send=true');
        $this->printSendDiagnosticsBlock($outcome);
        $httpSend = (int) ($outcome['http_status'] ?? 0);
        if ($httpSend >= 200 && $httpSend < 300 && ! ($outcome['success'] ?? false)) {
            $rs = is_array($outcome['response_structure'] ?? null) ? $outcome['response_structure'] : [];
            $this->line('response_structure.top_level_keys='.($rs['top_level_keys'] ?? '—'));
            $this->line('response_structure.key_paths='.(($rs['key_paths'] ?? '') !== '' ? $rs['key_paths'] : '—'));
            $this->line('response_structure.empty_body='.($rs['empty_body'] ?? '—'));
            $this->line('response_structure.json_valid='.($rs['json_valid'] ?? '—'));
            $this->line('response_structure.candidate_count='.($rs['candidate_count'] ?? '—'));
            $this->line('response_structure.candidate_fields='.(($rs['candidate_fields'] ?? '') !== '' ? $rs['candidate_fields'] : '—'));
        }
        $errorDigest = is_array($outcome['error_digest'] ?? null) ? $outcome['error_digest'] : [];
        $this->line('response_error_messages='.implode(' | ', array_slice((array) ($errorDigest['response_error_messages'] ?? []), 0, 12)));
        $this->line('response_error_hints='.implode(' | ', array_slice((array) ($errorDigest['response_error_hints'] ?? []), 0, 6)));
        $this->printSummarySection('revalidation_error_digest', $errorDigest);
        $digest = is_array($outcome['linkage_digest'] ?? null) ? $outcome['linkage_digest'] : [];
        $this->printSummarySection('linkage_digest', $digest);

        $this->maybePrintShopContractHint($endpointPathEffective, (string) ($outcome['payload_style'] ?? ''), (int) ($outcome['http_status'] ?? 0));

        $http = (int) ($outcome['http_status'] ?? 0);
        if ($http >= 200 && $http < 300) {
            $this->persistInspectLinkageMeta($booking, $outcome);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $diagnostics
     */
    protected function printPreviewJsonBlock(
        SabreRevalidationPayloadBuilder $builder,
        array $payload,
        string $endpointPath,
        array $diagnostics,
    ): void {
        $wire = $builder->wireableRequestPayload($payload);
        $clean = $builder->sanitizeRevalidatePreviewTree($wire);
        $style = (string) ($payload['_ota_revalidate_payload_style'] ?? '');
        $schema = (string) ($payload['_ota_payload_schema'] ?? '');
        $this->line('preview.payload_style='.$style);
        $this->line('preview.payload_schema='.$schema);
        $this->line('preview.endpoint_path='.$endpointPath);
        $this->printSummarySection('preview.structural_summary', $diagnostics);
        $json = json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $json = is_string($json) ? $json : '';
        $max = 14000;
        if (strlen($json) > $max) {
            $json = substr($json, 0, $max)."\n…(truncated)";
        }
        $this->line('preview.redacted_json_begin');
        $this->line($json);
        $this->line('preview.redacted_json_end');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $diagnostics
     */
    protected function writePreviewFile(
        SabreRevalidationPayloadBuilder $builder,
        array $payload,
        string $endpointPath,
        array $diagnostics,
        string $relativeOrAbsolute,
    ): void {
        $full = $this->resolveWritePreviewPath($relativeOrAbsolute);
        $wire = $builder->wireableRequestPayload($payload);
        $clean = $builder->sanitizeRevalidatePreviewTree($wire);
        $doc = [
            'meta' => [
                'payload_style' => (string) ($payload['_ota_revalidate_payload_style'] ?? ''),
                'payload_schema' => (string) ($payload['_ota_payload_schema'] ?? ''),
                'endpoint_path' => $endpointPath,
                'structural_summary' => $diagnostics,
            ],
            'request_body' => $clean,
        ];
        $json = json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (! is_string($json)) {
            $this->components->error('Could not encode preview JSON.');

            return;
        }
        $dir = dirname($full);
        if (! is_dir($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
        File::put($full, $json);
        $this->line('preview_written_path='.$full);
    }

    protected function resolveWritePreviewPath(string $path): string
    {
        $path = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        if ($path === '') {
            return storage_path('app/sabre-revalidate-preview.json');
        }
        if (strlen($path) > 2 && ctype_alpha($path[0]) && $path[1] === ':') {
            return $path;
        }
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return base_path($path);
    }

    /**
     * @param  array<string, mixed>  $outcome  Output of {@see SabreBookingService::runRevalidationBeforeBooking()}
     */
    protected function persistInspectLinkageMeta(Booking $booking, array $outcome): void
    {
        $digest = is_array($outcome['linkage_digest'] ?? null) ? $outcome['linkage_digest'] : [];
        $linkage = is_array($outcome['linkage'] ?? null) ? $outcome['linkage'] : [];
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['sabre_revalidate_inspect'] = array_filter([
            'captured_at' => now()->toAtomString(),
            'payload_style' => $outcome['payload_style'] ?? null,
            'http_status' => $outcome['http_status'] ?? null,
            'revalidation_success' => (bool) ($outcome['success'] ?? false),
            'has_revalidated_fare' => (bool) ($digest['has_revalidated_fare'] ?? false),
            'has_revalidated_currency' => (bool) ($digest['has_revalidated_currency'] ?? false),
            'has_revalidation_reference' => (bool) ($digest['has_revalidation_reference'] ?? false),
            'has_ticketing_time_limit' => (bool) ($digest['has_ticketing_time_limit'] ?? false),
            'has_fare_basis' => (bool) ($digest['has_fare_basis'] ?? false),
            'has_offer_reference' => (bool) ($digest['has_offer_reference'] ?? false),
            'revalidated_total' => isset($linkage['revalidated_total']) && is_numeric($linkage['revalidated_total'])
                ? round((float) $linkage['revalidated_total'], 2) : null,
            'revalidated_currency' => isset($linkage['revalidated_currency']) && is_string($linkage['revalidated_currency'])
                ? strtoupper(substr(trim($linkage['revalidated_currency']), 0, 6)) : null,
            'revalidation_reference' => $this->capScalar($linkage['revalidation_reference'] ?? null, 96),
            'ticketing_time_limit' => $this->capScalar($linkage['ticketing_time_limit'] ?? null, 64),
        ], static fn ($v): bool => $v !== null && $v !== '');
        $booking->meta = $meta;
        $booking->save();
        $this->line('booking_meta.sabre_revalidate_inspect=updated');
    }

    protected function capScalar(mixed $v, int $max): ?string
    {
        if (! is_string($v) && ! is_numeric($v)) {
            return null;
        }
        $s = substr(trim((string) $v), 0, max(1, $max));

        return $s !== '' ? $s : null;
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

    protected function resolveConnection(Booking $booking): ?SupplierConnection
    {
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

    /**
     * Print scalar/list values of a section in `key=value` form for skimmable inspect output (no raw JSON).
     *
     * @param  array<string, mixed>  $payload
     */
    protected function printSummarySection(string $prefix, array $payload): void
    {
        foreach ($payload as $k => $v) {
            $key = $prefix !== '' ? $prefix.'.'.$k : (string) $k;
            if (is_bool($v)) {
                $this->line($key.'='.($v ? 'true' : 'false'));
            } elseif (is_scalar($v) || $v === null) {
                $this->line($key.'='.(string) ($v ?? ''));
            } elseif (is_array($v)) {
                if ($this->isAssocArray($v)) {
                    $this->printSummarySection($key, $v);
                } else {
                    $items = array_slice($v, 0, 12);
                    $rendered = array_map(static function ($item): string {
                        if (is_scalar($item)) {
                            return (string) $item;
                        }
                        if (is_array($item)) {
                            return json_encode($item, JSON_UNESCAPED_SLASHES) ?: '';
                        }

                        return '';
                    }, $items);
                    $this->line($key.'='.implode(', ', array_filter($rendered, static fn ($s): bool => $s !== '')));
                }
            }
        }
    }

    /**
     * @param  array<mixed>  $arr
     */
    protected function isAssocArray(array $arr): bool
    {
        return $arr !== [] && array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * @param  array<string, mixed>  $outcome
     */
    protected function printSendDiagnosticsBlock(array $outcome): void
    {
        $keys = $outcome['wire_root_keys'] ?? [];
        $rootKeys = is_array($keys) ? implode(',', $keys) : '';
        $this->line('payload_style='.(string) ($outcome['payload_style'] ?? '—'));
        $this->line('diag.endpoint_path='.(string) ($outcome['endpoint_path'] ?? '—'));
        $this->line('diag.payload_style='.(string) ($outcome['payload_style'] ?? '—'));
        $this->line('diag.root_keys='.$rootKeys);
        $this->line('diag.http_status='.(string) ($outcome['http_status'] ?? '—'));
        $this->line('duration_ms='.(string) ($outcome['duration_ms'] ?? '—'));
        $this->line('reason_code='.(string) ($outcome['reason_code'] ?? '—'));
        $this->line('revalidation_success='.(($outcome['success'] ?? false) ? 'true' : 'false'));
        $errorDigest = is_array($outcome['error_digest'] ?? null) ? $outcome['error_digest'] : [];
        $codes = array_slice((array) ($errorDigest['response_error_codes'] ?? []), 0, 8);
        $msgs = array_slice((array) ($errorDigest['response_error_messages'] ?? []), 0, 4);
        $this->line('diag.safe_error_codes='.implode(',', array_map(static fn ($c): string => substr((string) $c, 0, 32), $codes)));
        $this->line('diag.safe_error_messages='.implode(' | ', array_map(static fn ($m): string => substr((string) $m, 0, 160), $msgs)));
        $this->line('diag.includes_sabre_error_27131='.((($outcome['includes_sabre_error_27131'] ?? false) ? 'true' : 'false')));
        $this->line('diag.changed_from_typical_27131_failure='.((($outcome['changed_from_typical_27131_failure'] ?? false) ? 'true' : 'false')));
        $hints = array_slice((array) ($errorDigest['response_error_hints'] ?? []), 0, 6);
        $this->line('diag.response_error_hints='.implode(' | ', $hints));
    }

    protected function maybePrintShopContractHint(string $endpointPathEffective, string $payloadStyle, int $http): void
    {
        if (! in_array($http, [400, 422], true)) {
            return;
        }
        if ($payloadStyle !== 'client_gds_revalidate_v1') {
            return;
        }
        $p = rtrim($endpointPathEffective, '/');

        if ($p !== '/v4/offers/shop' && $p !== '/v5/offers/shop') {
            return;
        }

        $this->line('contract_hint=Revalidation contract may require OTA_AirLowFareSearchRQ-style body or shop replay, not RevalidateItineraryRQ.');
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
