<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use Illuminate\Console\Command;

class SabreCompareBookingEndpointsCommand extends Command
{
    protected $signature = 'sabre:compare-booking-endpoints
                            {--booking= : Booking ID}
                            {--skip-trip-orders : Omit /v1/trip/orders/createBooking from the inspect matrix}
                            {--send : Perform exactly one live POST (requires --endpoint and --style)}
                            {--endpoint= : Full path beginning with / (e.g. /v2/passengers/create or /v2.5.0/passenger/records?mode=create)}
                            {--style= : Payload style (e.g. traditional_pnr_create_passenger_name_record_v1)}';

    protected $description = '[local/testing only] B38/P4: Inspect matrix of Sabre booking REST paths × payload styles (P4 mixed/interline Passenger Records experiments on v2.4/v2.5); optional single --send with explicit --endpoint + --style (no raw bodies or booking PNR persistence)';

    public function handle(SabreBookingService $sabreBooking): int
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

        $send = (bool) $this->option('send');
        $skipTrip = (bool) $this->option('skip-trip-orders');
        $endpointOpt = $this->option('endpoint');
        $styleOpt = $this->option('style');
        $endpointStr = is_string($endpointOpt) ? trim($endpointOpt) : '';
        $styleStr = is_string($styleOpt) ? trim($styleOpt) : '';

        $this->line('Connectivity: matrix is inspect-only unless --send with explicit --endpoint and --style (one POST). Ticketing stays disabled.');
        $this->newLine();

        $rows = $sabreBooking->compareBookingEndpointsForCommand($booking, $send, $skipTrip, $endpointStr !== '' ? $endpointStr : null, $styleStr !== '' ? $styleStr : null);
        foreach ($rows as $row) {
            foreach ($row as $k => $v) {
                if (is_bool($v)) {
                    $this->line($k.'='.($v ? 'true' : 'false'));
                } elseif (is_array($v)) {
                    $this->line($k.'='.json_encode($v));
                } else {
                    $this->line($k.'='.(string) $v);
                }
            }
            $this->line('---');
        }

        return self::SUCCESS;
    }
}
