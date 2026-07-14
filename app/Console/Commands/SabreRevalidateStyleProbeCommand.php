<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Diagnostics\SabreRevalidationPayloadStyleComparator;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use App\Services\Suppliers\Sabre\SabreRevalidationPayloadBuilder;
use App\Support\Bookings\SabrePnrCertificationSupport;
use Illuminate\Console\Command;
use Throwable;

/**
 * Sprint 11K-L: CERT/local-safe revalidation style probe — {@code bfm_revalidate_v1} vs
 * {@code bfm_revalidate_with_pricing_context} only. Scalar output; no raw payload JSON; no PNR/ticketing/cancel/NDC.
 */
class SabreRevalidateStyleProbeCommand extends Command
{
    public const PRODUCTION_CONFIRM_PHRASE = 'CERT-REVALIDATION-STYLE-PROBE';

    protected $signature = 'sabre:revalidate-style-probe
                            {--booking= : Booking ID with Sabre offer snapshot}
                            {--fixture : Built-in fixture draft when --booking omitted}
                            {--confirm= : Production: must be CERT-REVALIDATION-STYLE-PROBE}
                            {--send : CERT revalidation HTTP only (never production live host)}
                            {--connection= : Sabre supplier connection ID for --send}';

    protected $description = 'Sprint 11K-L: compare bfm_revalidate_v1 vs bfm_revalidate_with_pricing_context (fixture/booking; optional CERT --send)';

    public function handle(
        SabreBookingService $sabreBooking,
        SabreRevalidationPayloadStyleComparator $comparator,
        SabreRevalidationPayloadBuilder $builder,
        SabrePnrCertificationSupport $certificationSupport,
    ): int {
        $gate = $this->resolveGate();
        if ($gate === null) {
            return self::FAILURE;
        }

        $draft = $this->resolveDraft($sabreBooking);
        if ($draft === null) {
            return self::FAILURE;
        }

        $report = $comparator->compareLaunchStylesForDraft($draft);
        $connection = $this->resolveConnectionForSend($draft);

        if ($this->option('send')) {
            if ($connection === null) {
                $this->components->error('Pass --connection={id} or use --booking with a Sabre connection for --send.');

                return self::FAILURE;
            }

            $sendGate = $this->resolveSendGate($connection, $gate['production_confirmed']);
            if ($sendGate === null) {
                return self::FAILURE;
            }

            $path = (string) config('suppliers.sabre.revalidate_path', '/v4/shop/flights/revalidate');
            $path = $path !== '' && $path[0] === '/' ? $path : '/'.$path;
            $styles = is_array($report['styles'] ?? null) ? $report['styles'] : [];

            foreach (SabreRevalidationPayloadStyleComparator::LAUNCH_PROBE_STYLES as $style) {
                $outcome = $sabreBooking->runRevalidationBeforeBooking($draft, $connection, $style, $path);
                $payload = $builder->buildPayload($draft, $style);
                $styles[$style] = $builder->launchStyleProbeSummary($payload, $outcome);
            }

            $report['styles'] = $styles;
            $report['cert_http_probe'] = true;
            $report['revalidate_path'] = $path;
            $report['connection_id'] = $connection->id;
        } else {
            $report['cert_http_probe'] = false;
        }

        try {
            $certificationSupport->assertOutputSafe($report);
        } catch (Throwable) {
            $this->components->error('Probe report failed safety check (details omitted).');

            return self::FAILURE;
        }

        $this->printReport($report, $gate['production_confirmed'], (bool) $this->option('send'));

        return self::SUCCESS;
    }

    /**
     * @return array{production_confirmed: bool}|null
     */
    protected function resolveGate(): ?array
    {
        if (SabreInspectGate::allowed()) {
            return ['production_confirmed' => false];
        }

        $env = (string) config('app.env', 'production');
        if ($env !== 'production') {
            $this->components->error('This command only runs when APP_ENV is local, testing, or production with --confirm.');

            return null;
        }

        $confirm = trim((string) $this->option('confirm'));
        if ($confirm === self::PRODUCTION_CONFIRM_PHRASE) {
            return ['production_confirmed' => true];
        }

        if ($confirm === '') {
            $this->components->error(
                'Production requires --confirm='.self::PRODUCTION_CONFIRM_PHRASE.'.'
            );
        } else {
            $this->components->error('Invalid --confirm phrase for production revalidation style probe.');
        }

        return null;
    }

    protected function resolveSendGate(SupplierConnection $connection, bool $productionConfirmed): ?bool
    {
        if (! SabreInspectGate::certEntitlementMatrixSendAllowed($connection)) {
            $reason = SabreInspectGate::certEntitlementMatrixSendBlockReason($connection) ?? 'blocked';
            $this->components->error('CERT revalidation HTTP probe is not allowed ('.$reason.').');

            return null;
        }

        $resolvedBase = SabreInspectGate::resolveSabreBaseUrlForGate($connection);
        if ($resolvedBase === '' || SabreInspectGate::isProductionLiveSabreHost($resolvedBase)) {
            $this->components->error('CERT probe blocks api.platform.sabre.com; use a CERT host only.');

            return null;
        }

        if (! SabreInspectGate::isCertSabreHost($resolvedBase)) {
            $this->components->error('CERT probe requires a CERT Sabre host (e.g. api.cert.platform.sabre.com).');

            return null;
        }

        if ($productionConfirmed && ! SabreInspectGate::isCertEntitlementMatrixEnabled()) {
            $this->components->error('Production CERT --send requires SABRE_CERT_ENTITLEMENT_MATRIX_ENABLED=true.');

            return null;
        }

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolveDraft(SabreBookingService $sabreBooking): ?array
    {
        $raw = $this->option('booking');
        if (is_string($raw) && trim($raw) !== '' && is_numeric($raw)) {
            $booking = Booking::query()->find((int) $raw);
            if ($booking === null) {
                $this->components->error('Booking not found.');

                return null;
            }

            return $this->resolveInternalDraftForBooking($booking, $sabreBooking);
        }

        if ($this->option('fixture')) {
            return $this->fixtureDraft();
        }

        $this->components->error('Pass --fixture or --booking={id}.');

        return null;
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    protected function resolveConnectionForSend(array $draft): ?SupplierConnection
    {
        $connOpt = $this->option('connection');
        if (is_string($connOpt) && trim($connOpt) !== '' && is_numeric($connOpt)) {
            $c = SupplierConnection::query()->find((int) $connOpt);
            if ($c !== null && $c->provider === SupplierProvider::Sabre) {
                return $c;
            }
        }

        $cid = (int) ($draft['_supplier_connection_id'] ?? $draft['supplier_connection_id'] ?? 0);
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
     * @param  array<string, mixed>  $report
     */
    protected function printReport(array $report, bool $productionConfirmed, bool $send): void
    {
        $this->line('report_version='.($report['report_version'] ?? ''));
        $this->line('production_confirmed='.($productionConfirmed ? 'true' : 'false'));
        $this->line('live_sabre_http='.($send ? 'true' : 'false'));
        $this->line('cert_http_probe='.(($report['cert_http_probe'] ?? false) ? 'true' : 'false'));
        $this->line('pnr_create_attempted=false');
        $this->line('ticketing_attempted=false');
        $this->line('cancellation_attempted=false');
        $this->line('ndc_enabled=false');
        $this->line('active_config_style='.($report['active_config_style'] ?? ''));
        $this->line('production_default_unchanged='.(($report['production_default_unchanged'] ?? false) ? 'true' : 'false'));
        $this->line('launch_recommendation='.($report['launch_recommendation'] ?? ''));
        $adds = is_array($report['pricing_context_adds_vs_baseline'] ?? null)
            ? $report['pricing_context_adds_vs_baseline']
            : [];
        $this->line('pricing_context_adds_vs_baseline='.implode(',', $adds));
        $this->line('baseline_linkage_preserved_in_pricing_style='.(($report['baseline_linkage_preserved_in_pricing_style'] ?? false) ? 'true' : 'false'));
        $this->newLine();

        $styles = is_array($report['styles'] ?? null) ? $report['styles'] : [];
        $rows = [];
        foreach (SabreRevalidationPayloadStyleComparator::LAUNCH_PROBE_STYLES as $style) {
            $summary = is_array($styles[$style] ?? null) ? $styles[$style] : [];
            $rows[] = $this->formatProbeTableRow($summary);
        }

        $this->table($this->probeTableHeaders(), $rows);
    }

    /**
     * @return list<string>
     */
    protected function probeTableHeaders(): array
    {
        return [
            'style',
            'payload_coverage_summary',
            'segment_count',
            'passenger_count',
            'pricing_context',
            'itin_ref',
            'pi_index',
            'leg_refs',
            'sched_refs',
            'booking_classes',
            'fare_basis',
            'validating_vc',
            'currency',
            'total',
            'http_status',
            'reval_success',
            'safe_error_family',
            'safe_reason_code',
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return list<string>
     */
    protected function formatProbeTableRow(array $summary): array
    {
        return [
            (string) ($summary['style'] ?? '—'),
            (string) ($summary['payload_coverage_summary'] ?? '—'),
            (string) ($summary['segment_count'] ?? '0'),
            (string) ($summary['passenger_count'] ?? '0'),
            $this->boolCell($summary['pricing_context_present'] ?? false),
            $this->boolCell($summary['itinerary_ref_present'] ?? false),
            $this->boolCell($summary['pricing_information_index_present'] ?? false),
            $this->boolCell($summary['leg_refs_present'] ?? false),
            $this->boolCell($summary['schedule_refs_present'] ?? false),
            $this->boolCell($summary['booking_classes_present'] ?? false),
            $this->boolCell($summary['fare_basis_present'] ?? false),
            $this->boolCell($summary['validating_carrier_present'] ?? false),
            $this->boolCell($summary['currency_present'] ?? false),
            $this->boolCell($summary['total_present'] ?? false),
            $summary['revalidation_http_status'] === null ? '—' : (string) $summary['revalidation_http_status'],
            $summary['revalidation_success'] === null ? '—' : $this->boolCell($summary['revalidation_success']),
            (string) ($summary['safe_error_family'] ?? '—'),
            (string) ($summary['safe_reason_code'] ?? '—'),
        ];
    }

    protected function boolCell(mixed $value): string
    {
        return $value === true ? 'true' : 'false';
    }

    /**
     * @return array<string, mixed>
     */
    protected function fixtureDraft(): array
    {
        return [
            'provider' => SupplierProvider::Sabre->value,
            'selected_offer_id' => '11kl-fixture-offer',
            'supplier_offer_id' => '11kl-fixture-offer',
            'validating_carrier' => 'EK',
            'fare' => ['amount' => 450.0, 'currency' => 'USD'],
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'departure_at' => '2026-09-01T10:00:00',
                    'arrival_at' => '2026-09-01T14:00:00',
                    'carrier' => 'EK',
                    'operating_airline_code' => 'EK',
                    'flight_number' => '615',
                    'booking_class' => 'T',
                    'fare_basis_code' => 'TAAOPPK1',
                ],
            ],
            'passengers' => [['type' => 'ADT']],
            '_sabre_shop_context' => [
                'itinerary_group_index' => 1,
                'itinerary_ref' => '10',
                'pricing_information_index' => 2,
                'leg_refs' => [3],
                'schedule_refs' => [9],
                'fare_component_refs' => [7],
                'pricing_information_ref' => 'pi-2',
            ],
            '_sabre_shop_identifiers' => [
                'pseudo_city_code' => 'TEST',
            ],
        ];
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
            $this->components->error('Booking is not a Sabre booking.');

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
            $this->components->error('Could not build a valid Sabre revalidation draft from booking.');

            return null;
        }
        unset($draft['_valid']);

        return $draft;
    }
}
