<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use Illuminate\Console\Command;

class SabreCompareCreatebookingStylesCommand extends Command
{
    protected $signature = 'sabre:compare-createbooking-styles
                            {--booking= : Booking ID}
                            {--send : POST each style to Sabre (requires booking + live-call enabled and supplier_connection_id)}
                            {--send-all : With --send, explicitly allow POST for every compare style (omit --style)}
                            {--style= : Compare/send only this payload style (see SabreBookingPayloadBuilder::TRIP_ORDERS_CREATEBOOKING_COMPARE_STYLES)}';

    protected $description = '[local/testing only] Compare Trip Orders createBooking payload styles (shape; optional live POST per style; --send requires --style or --send-all)';

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
        $styleOpt = $this->option('style');
        $styleArg = is_string($styleOpt) && trim($styleOpt) !== '' ? trim($styleOpt) : null;
        $sendAll = (bool) $this->option('send-all');
        if ($send && $styleArg === null && ! $sendAll) {
            $this->components->error('Live send requires --style or --send-all.');

            return self::FAILURE;
        }

        $rows = $sabreBooking->compareTripOrdersCreateBookingStylesForCommand($booking, $send, $styleArg);
        if ($rows !== [] && ($rows[0]['status'] ?? '') === 'invalid_style') {
            $this->components->error((string) ($rows[0]['error_message'] ?? 'Invalid --style.'));

            return self::FAILURE;
        }

        foreach ($rows as $row) {
            if (! empty($row['blind_agency_phone_variant_warning']) && is_string($row['blind_agency_phone_variant_warning'])) {
                $this->components->warn($row['blind_agency_phone_variant_warning']);
            }
        }

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
