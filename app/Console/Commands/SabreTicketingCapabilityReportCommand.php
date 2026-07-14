<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\TicketingAttempt;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use App\Services\Suppliers\TicketingAdapters\SabreSupplierTicketingAdapter;
use App\Support\Bookings\TicketingReadinessPresenter;
use Illuminate\Console\Command;

class SabreTicketingCapabilityReportCommand extends Command
{
    /**
     * Static Sabre ticketing endpoint families (not probed; no HTTP).
     *
     * @var list<array{key: string, family: string, candidate_path_pattern: string, status: string, risk_note: string}>
     */
    public const ENDPOINT_CANDIDATES = [
        [
            'key' => 'trip_orders_fulfillment_candidate',
            'family' => 'trip_orders',
            'candidate_path_pattern' => '/v1/trip/orders/{orderId}/fulfillment',
            'status' => 'not_probed',
            'risk_note' => 'Candidate only; entitlement and payload unknown until discovery.',
        ],
        [
            'key' => 'air_ticket_candidate',
            'family' => 'rest_air_ticket',
            'candidate_path_pattern' => '/v1/air/ticket',
            'status' => 'not_probed',
            'risk_note' => 'Candidate only; may not match tenant REST catalog.',
        ],
        [
            'key' => 'passenger_records_update_candidate',
            'family' => 'passenger_records',
            'candidate_path_pattern' => '/v2.5.0/passenger/records?mode=update',
            'status' => 'not_probed',
            'risk_note' => 'Update-mode PR; ticketing linkage unverified.',
        ],
        [
            'key' => 'soap_air_ticket_candidate',
            'family' => 'soap',
            'candidate_path_pattern' => 'AirTicketRQ (SOAP)',
            'status' => 'not_probed',
            'risk_note' => 'Legacy SOAP path; separate session/printer workflow likely.',
        ],
        [
            'key' => 'designate_printer_candidate',
            'family' => 'soap_or_rest_aux',
            'candidate_path_pattern' => 'DesignatePrinter / queue setup (vendor-specific)',
            'status' => 'not_probed',
            'risk_note' => 'Often required before issue-ticket; not wired in OTA.',
        ],
    ];

    protected $signature = 'sabre:ticketing-capability-report
                            {--booking= : Booking ID}
                            {--json : Emit one JSON line (sabre_ticketing_capability_report_json=...)}';

    protected $description = '[local/testing only] T2: Inspect-only Sabre ticketing readiness report (no live HTTP, no ticketing, no DB writes)';

    public function handle(): int
    {
        if (! SabreInspectGate::allowed()) {
            $this->components->error('This command only runs when APP_ENV is local or testing.');

            return self::FAILURE;
        }

        $bookingId = $this->option('booking');
        if ($bookingId === null || $bookingId === '' || ! is_numeric($bookingId)) {
            $this->components->error('Pass --booking={id} with a numeric booking id.');

            return self::FAILURE;
        }

        $booking = Booking::query()
            ->with(['tickets', 'latestTicketingAttempt'])
            ->find((int) $bookingId);

        if ($booking === null) {
            $this->components->error('Booking not found.');

            return self::FAILURE;
        }

        $report = $this->isSabreBooking($booking)
            ? $this->buildSabreReport($booking)
            : $this->buildUnsupportedProviderReport($booking);

        if ($this->option('json')) {
            $this->line('sabre_ticketing_capability_report_json='.json_encode($report, JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->printReport($report);

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildSabreReport(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $pnr = trim((string) ($booking->pnr ?? ''));
        $supplierReference = trim((string) ($booking->supplier_reference ?? ''));
        $pnrPresent = $pnr !== '' || $supplierReference !== '';

        $readiness = TicketingReadinessPresenter::forBooking($booking);
        $readiness['items'] = array_map(
            static fn (array $item): array => [
                'key' => $item['key'],
                'status' => $item['status'],
                'message' => $item['message'],
            ],
            $readiness['items'],
        );

        $itinerary = $this->pnrItinerarySummary($meta);
        $config = $this->configSection();
        $latestAttempt = $booking->latestTicketingAttempt;

        return [
            'booking' => [
                'booking_id' => $booking->id,
                'booking_reference' => $this->safeBookingReference($booking),
                'provider' => SupplierProvider::Sabre->value,
                'pnr_present' => $pnrPresent,
                'supplier_reference_present' => $supplierReference !== '',
                'payment_status' => (string) ($booking->payment_status ?? ''),
                'ticketing_status' => (string) ($booking->ticketing_status ?? ''),
                'supplier_booking_status' => (string) ($booking->supplier_booking_status ?? ''),
            ],
            'config' => $config,
            'e10_readiness' => $readiness,
            'pnr_itinerary' => $itinerary,
            'ticketing_records' => [
                'booking_tickets_count' => $booking->tickets()->count(),
                'latest_ticketing_attempt' => $latestAttempt instanceof TicketingAttempt
                    ? [
                        'status' => (string) $latestAttempt->status,
                        'error_code' => $latestAttempt->error_code !== null
                            ? (string) $latestAttempt->error_code
                            : null,
                        'provider' => (string) ($latestAttempt->provider ?? ''),
                    ]
                    : null,
                'latest_ticketing_attempt_at' => $latestAttempt?->attempted_at?->toIso8601String(),
            ],
            'adapter' => [
                'supplier_ticketing_adapter' => SabreSupplierTicketingAdapter::class,
                'adapter_supported' => false,
                'reason' => 'not_supported',
                'detail' => 'pending_implementation',
            ],
            'endpoint_candidates' => self::ENDPOINT_CANDIDATES,
            'recommended_next_action' => $this->recommendedNextAction(
                $readiness['overall_status'],
                $itinerary,
                $pnrPresent,
                $config,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildUnsupportedProviderReport(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));

        return [
            'booking' => [
                'booking_id' => $booking->id,
                'booking_reference' => $this->safeBookingReference($booking),
                'provider' => $provider !== '' ? $provider : 'unknown',
                'pnr_present' => false,
                'supplier_reference_present' => false,
                'payment_status' => (string) ($booking->payment_status ?? ''),
                'ticketing_status' => (string) ($booking->ticketing_status ?? ''),
                'supplier_booking_status' => (string) ($booking->supplier_booking_status ?? ''),
            ],
            'supported' => false,
            'message' => 'Booking provider is not Sabre; Sabre ticketing capability report does not apply.',
            'recommended_next_action' => 'manual_ticketing_only',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function configSection(): array
    {
        $sabreConfig = config('suppliers.sabre', []);
        if (! is_array($sabreConfig)) {
            $sabreConfig = [];
        }

        $ticketingLiveConfigured = array_key_exists('ticketing_live_call_enabled', $sabreConfig);

        return [
            'sabre_ticketing_enabled' => (bool) ($sabreConfig['ticketing_enabled'] ?? false),
            'sabre_booking_live_call_enabled' => (bool) ($sabreConfig['booking_live_call_enabled'] ?? false),
            'sabre_ticketing_live_call_enabled' => $ticketingLiveConfigured
                ? (bool) $sabreConfig['ticketing_live_call_enabled']
                : false,
            'sabre_ticketing_live_call_source' => $ticketingLiveConfigured ? 'configured' : 'not_configured',
            'sabre_booking_mode' => (string) ($sabreConfig['booking_mode'] ?? ''),
            'sabre_booking_schema' => $this->nullableString($sabreConfig['booking_schema'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function pnrItinerarySummary(array $meta): array
    {
        $syncSidecar = is_array($meta['pnr_itinerary_sync'] ?? null) ? $meta['pnr_itinerary_sync'] : [];
        $snapshot = is_array($meta['pnr_itinerary_snapshot'] ?? null) ? $meta['pnr_itinerary_snapshot'] : [];
        $segments = is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : [];

        $statuses = [];
        $allHk = $segments !== [];
        foreach ($segments as $segment) {
            if (! is_array($segment)) {
                $statuses[] = 'unknown';
                $allHk = false;

                continue;
            }
            $status = strtoupper(trim((string) ($segment['segment_status'] ?? '')));
            $statuses[] = $status !== '' ? $status : 'empty';
            if ($status !== 'HK') {
                $allHk = false;
            }
        }

        return [
            'pnr_itinerary_sync_status' => $this->nullableString($syncSidecar['status'] ?? null),
            'pnr_itinerary_synced_at' => $this->nullableString($syncSidecar['synced_at'] ?? $syncSidecar['attempted_at'] ?? null),
            'pnr_itinerary_snapshot_segment_count' => count($segments),
            'segment_statuses_sanitized' => array_values($statuses),
            'all_segments_hk' => $segments !== [] && $allHk,
        ];
    }

    /**
     * @param  array<string, mixed>  $itinerary
     * @param  array<string, mixed>  $config
     */
    protected function recommendedNextAction(
        string $overallStatus,
        array $itinerary,
        bool $pnrPresent,
        array $config,
    ): string {
        return match ($overallStatus) {
            TicketingReadinessPresenter::OVERALL_BLOCKED_MISSING_PNR => $pnrPresent
                ? 'sync_pnr_itinerary'
                : 'sync_pnr_itinerary',
            TicketingReadinessPresenter::OVERALL_BLOCKED_ITINERARY_NOT_SYNCED => 'sync_pnr_itinerary',
            TicketingReadinessPresenter::OVERALL_BLOCKED_SEGMENT_STATUS => 'resolve_segment_status',
            TicketingReadinessPresenter::OVERALL_BLOCKED_PAYMENT => 'verify_payment',
            TicketingReadinessPresenter::OVERALL_BLOCKED_PASSENGER_DATA => 'manual_ticketing_only',
            TicketingReadinessPresenter::OVERALL_BLOCKED_MISSING_FARE => 'manual_ticketing_only',
            TicketingReadinessPresenter::OVERALL_BLOCKED_SUPPLIER_NOT_SUPPORTED => 'manual_ticketing_only',
            TicketingReadinessPresenter::OVERALL_READY_EXCEPT_TICKETING_DISABLED => $this->readyExceptNextAction($config, $itinerary),
            default => 'manual_ticketing_only',
        };
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $itinerary
     */
    protected function readyExceptNextAction(array $config, array $itinerary): string
    {
        if (! ($config['sabre_ticketing_enabled'] ?? false)) {
            return 'adapter_not_implemented';
        }

        if (($config['sabre_ticketing_live_call_source'] ?? '') === 'not_configured'
            || ! ($config['sabre_ticketing_live_call_enabled'] ?? false)) {
            return 'ticketing_config_disabled';
        }

        if (($itinerary['pnr_itinerary_snapshot_segment_count'] ?? 0) === 0) {
            return 'sync_pnr_itinerary';
        }

        return 'run_ticketing_endpoint_discovery';
    }

    protected function isSabreBooking(Booking $booking): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));

        return $provider === SupplierProvider::Sabre->value;
    }

    protected function safeBookingReference(Booking $booking): ?string
    {
        $ref = trim((string) ($booking->booking_reference ?? ''));

        return $ref !== '' ? $ref : null;
    }

    protected function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s !== '' ? $s : null;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    protected function printReport(array $report): void
    {
        foreach ($report as $section => $value) {
            if (is_array($value)) {
                $this->line($section.'=');
                $this->printNested($value, '  ');

                continue;
            }
            $this->line($section.'='.$this->scalarLine($value));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function printNested(array $data, string $indent): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->line($indent.$key.'=');
                $this->printNested($value, $indent.'  ');

                continue;
            }
            $this->line($indent.$key.'='.$this->scalarLine($value));
        }
    }

    protected function scalarLine(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }

        return (string) $value;
    }
}
