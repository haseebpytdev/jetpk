<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use App\Support\Bookings\SabrePnrCertificationSupport;
use Illuminate\Console\Command;

/**
 * P5: Certify alternative Sabre booking paths for mixed/interline fares (local/testing only).
 */
class SabreCertifyAlternativeBookingPathCommand extends Command
{
    protected $signature = 'sabre:certify-alternative-booking-path
                            {--booking= : Booking ID}
                            {--send : One live POST (requires --endpoint and --style)}
                            {--endpoint= : Full path (e.g. /v1/trip/orders/createBooking)}
                            {--style= : Payload style from P5 matrix}
                            {--revalidate-first : Run certification revalidation before Trip Orders send only}
                            {--json : Machine-readable output only}';

    protected $description = '[local/testing only] P5: Audit + inspect matrix for Trip Orders vs Passenger Records vs /v2/passengers/create on mixed/interline bookings; optional single --send (no booking PNR persistence)';

    public function handle(SabreBookingService $sabreBooking, SabrePnrCertificationSupport $certificationSupport): int
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

        $booking = Booking::query()->find((int) $bookingId);
        if ($booking === null) {
            $this->components->error('Booking not found.');

            return self::FAILURE;
        }

        if (! $certificationSupport->isSabreBooking($booking)) {
            $this->components->error('Booking is not a Sabre itinerary.');

            return self::FAILURE;
        }

        $send = (bool) $this->option('send');
        $endpointStr = is_string($this->option('endpoint')) ? trim($this->option('endpoint')) : '';
        $styleStr = is_string($this->option('style')) ? trim($this->option('style')) : '';
        $revalidateFirst = (bool) $this->option('revalidate-first');

        $audit = $sabreBooking->alternativeBookingPathAuditForCommand($booking);
        $certificationSupport->assertOutputSafe($audit);

        if (! $send) {
            $this->emitAudit($audit);
            $this->newLine();
            $this->line('P5 path matrix (inspect-only; use --send with --endpoint and --style for one live POST):');
            $rows = $sabreBooking->compareBookingEndpointsForCommand($booking, false, false, null, null, 'p5');
            foreach ($rows as $row) {
                $this->emitRow($row);
                $this->line('---');
            }

            return self::SUCCESS;
        }

        if ($endpointStr === '' || $styleStr === '') {
            $this->components->error('--send requires explicit --endpoint and --style from the P5 matrix.');

            return self::FAILURE;
        }

        $this->emitAudit($audit);
        $this->newLine();
        $rows = $sabreBooking->compareBookingEndpointsForCommand(
            $booking,
            true,
            false,
            $endpointStr,
            $styleStr,
            'p5',
            $revalidateFirst,
        );
        foreach ($rows as $row) {
            $this->emitRow($row);
        }

        $pnrCreated = false;
        foreach ($rows as $row) {
            if (($row['pnr_created'] ?? false) === true) {
                $pnrCreated = true;
                break;
            }
        }

        return $pnrCreated ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $audit
     */
    protected function emitAudit(array $audit): void
    {
        if ((bool) $this->option('json')) {
            $this->line('p5_audit_json='.json_encode($audit, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return;
        }

        $this->line('P5 alternative booking path audit (no raw payloads).');
        foreach ([
            'booking_id', 'configured_booking_path', 'effective_booking_schema', 'trip_orders_configured',
            'why_trip_orders_create_booking_not_default', 'trip_orders_latest_error',
            'trip_orders_likely_profile_level_agency_phone_issue', 'agency_phone_config_present',
            'pricing_context_ready', 'offer_refresh_acceptance_required', 'passengers_create_vs_passenger_records',
            'recommended_next_action',
        ] as $key) {
            if (! array_key_exists($key, $audit)) {
                continue;
            }
            $v = $audit[$key];
            if (is_bool($v)) {
                $this->line($key.'='.($v ? 'true' : 'false'));
            } elseif (is_array($v)) {
                $this->line($key.'='.json_encode($v, JSON_UNESCAPED_SLASHES));
            } else {
                $this->line($key.'='.(string) $v);
            }
        }
        if (isset($audit['readiness']) && is_array($audit['readiness'])) {
            $this->line('readiness='.json_encode($audit['readiness'], JSON_UNESCAPED_SLASHES));
        }
        if (isset($audit['iati_cpnr_structure_diff']) && is_array($audit['iati_cpnr_structure_diff'])) {
            $this->line('iati_cpnr_structure_diff='.json_encode($audit['iati_cpnr_structure_diff'], JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function emitRow(array $row): void
    {
        if ((bool) $this->option('json')) {
            $this->line('p5_path_json='.json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return;
        }
        foreach ($row as $k => $v) {
            if (is_bool($v)) {
                $this->line($k.'='.($v ? 'true' : 'false'));
            } elseif (is_array($v)) {
                $this->line($k.'='.json_encode($v, JSON_UNESCAPED_SLASHES));
            } elseif ($v === null) {
                $this->line($k.'=null');
            } else {
                $this->line($k.'='.(string) $v);
            }
        }
    }
}
