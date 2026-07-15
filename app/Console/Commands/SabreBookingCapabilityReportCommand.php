<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use Illuminate\Console\Command;

class SabreBookingCapabilityReportCommand extends Command
{
    /**
     * Curated B40 repository hints (file path + safe key names only; no values).
     *
     * @var list<array{file: string, keys: list<string>}>
     */
    public const LOCAL_AGENCY_PHONE_HINTS = [
        ['file' => 'app/Services/Suppliers/Sabre/Booking/SabreBookingPayloadBuilder.php', 'keys' => [
            'AGENCY_PHONE_BODY_VARIANT_COMPARE_STYLES', 'wire_has_agency_phone', 'wire_agency_phone_paths',
            'wire_agency_phone_field_style', 'wire_agency_phone_ok', 'agencyContactInfo', 'agencyPhone',
            'phoneNumbers', 'phones', 'PhoneUseType', 'contactNumbers', 'pos.source.agencyPhone',
            'CreatePassengerNameRecordRQ', 'TravelItineraryAddInfo', 'buildTripOrdersCreateBookingEnvelope',
        ]],
        ['file' => 'app/Services/Suppliers/Sabre/Booking/SabreBookingService.php', 'keys' => [
            'agencyPhoneMissingClassifierForTripOrdersCompareRow', 'likely_profile_level_agency_phone_issue',
            'agency_phone_config_present', 'traditional_pnr_endpoints_forbidden', 'wire_has_agency_phone',
        ]],
        ['file' => 'config/suppliers.php', 'keys' => [
            'agency_phone', 'agency_phone_country_code', 'agency_phone_type', 'agency_phone_location',
            'agency_pos_phone_use_type', 'SABRE_AGENCY_PHONE',
        ]],
        ['file' => '.env.example', 'keys' => [
            'SABRE_AGENCY_PHONE', 'SABRE_CREATEBOOKING_PAYLOAD_STYLE', 'SABRE_BOOKING_PATH',
        ]],
        ['file' => 'app/Console/Commands/SabreInspectBookingConfigCommand.php', 'keys' => [
            'agency_phone_config_present', 'trip_orders_agency_phone_still_rejected', 'AGENCY_PHONE_MISSING',
        ]],
        ['file' => 'app/Services/Suppliers/Sabre/Core/SabreClient.php', 'keys' => [
            'pseudoCityCode', 'PCC', 'POS',
        ]],
        ['file' => 'app/Services/Suppliers/Sabre/Gds/SabreFlightSearchRequestBuilder.php', 'keys' => [
            'POS.PseudoCityCode',
        ]],
    ];

    protected $signature = 'sabre:booking-capability-report {--booking= : Booking ID}';

    protected $description = '[local/testing only] B40/B41/B42: Summarize Sabre Trip Orders vs traditional PNR capability from booking + stored attempts (no live HTTP; no secrets); optional expanded REST discovery summary when storage/app/sabre-booking-endpoint-discovery.json exists';

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

        $report = array_merge(
            $sabreBooking->bookingCapabilityReportForCommand($booking),
            ['local_agency_phone_hints_found' => self::LOCAL_AGENCY_PHONE_HINTS],
        );

        foreach ($report as $k => $v) {
            if ($k === 'traditional_endpoint_entitlement' && is_array($v)) {
                $this->line('traditional_endpoint_entitlement=');
                foreach ($v as $path => $status) {
                    $this->line('  '.$path.' => '.$status);
                }

                continue;
            }
            if ($k === 'expanded_endpoint_discovery_summary' && is_array($v)) {
                $this->line('expanded_endpoint_discovery_summary='.json_encode($v, JSON_UNESCAPED_SLASHES));

                continue;
            }
            if ($k === 'local_agency_phone_hints_found' && is_array($v)) {
                $this->line('local_agency_phone_hints_found='.json_encode($v));

                continue;
            }
            if (is_bool($v)) {
                $this->line($k.'='.($v ? 'true' : 'false'));
            } elseif (is_array($v)) {
                $this->line($k.'='.json_encode($v));
            } elseif ($v === null) {
                $this->line($k.'=null');
            } else {
                $this->line($k.'='.(string) $v);
            }
        }

        return self::SUCCESS;
    }
}
