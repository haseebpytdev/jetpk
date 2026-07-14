<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\BookingManagementController;
use App\Models\Booking;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use Illuminate\Console\Command;

/**
 * Local/testing: structural key-path diff OTA traditional CPNR vs frozen IATI GDS template (no HTTP, no PII).
 */
class SabreDiagnoseCpnrVsIatiStructureCommand extends Command
{
    protected $signature = 'sabre:diagnose-cpnr-vs-iati-structure {--booking= : Sabre booking ID}';

    protected $description = '[local/testing only] Compare CreatePassengerNameRecordRQ key paths + key-name inventory (OTA wire vs frozen IATI GDS template). No passenger values.';

    public function handle(SabreBookingService $sabreBooking): int
    {
        if (! SabreInspectGate::allowed()) {
            $this->components->error('This command only runs when APP_ENV is local or testing.');

            return self::FAILURE;
        }

        $bookingId = $this->option('booking');
        if ($bookingId === null || $bookingId === '' || ! is_numeric($bookingId)) {
            $this->components->error('Pass --booking={id} with a numeric Sabre booking id.');

            return self::FAILURE;
        }

        $booking = Booking::query()->find((int) $bookingId);
        if ($booking === null) {
            $this->components->error('Booking not found.');

            return self::FAILURE;
        }

        $out = $sabreBooking->inspectTraditionalCpnrIatiStructureDiffForCommand($booking);
        $styleSel = $sabreBooking->inspectPassengerRecordsStyleSelectionForCommand($booking);
        if (! isset($styleSel['error'])) {
            $out['passenger_records_style_decision'] = $styleSel;
            if (isset($styleSel['freshness_strategy_decision_json'])) {
                $out['freshness_strategy_decision_json'] = $styleSel['freshness_strategy_decision_json'];
            }
        }
        $this->line(json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if (! isset($styleSel['error'])) {
            $merged = array_merge(
                is_array($styleSel) ? $styleSel : [],
                is_array($styleSel['freshness_strategy_decision_json'] ?? null) ? $styleSel['freshness_strategy_decision_json'] : [],
            );
            foreach (BookingManagementController::adminSafeSabreDiagnosticFieldsForOutput($merged) as $k => $v) {
                $this->line('admin_safe_'.$k.'='.$v);
            }
        }

        return isset($out['error']) ? self::FAILURE : self::SUCCESS;
    }
}
