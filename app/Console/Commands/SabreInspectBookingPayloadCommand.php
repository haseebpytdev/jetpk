<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\BookingManagementController;
use App\Models\Booking;
use App\Services\Suppliers\Sabre\SabreBookingPayloadBuilder;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use Illuminate\Console\Command;

class SabreInspectBookingPayloadCommand extends Command
{
    protected $signature = 'sabre:inspect-booking-payload
                            {--booking= : Booking ID}
                            {--preview-json : Redacted createBooking structure only (no PII)}
                            {--wire-preview-json : Redacted final HTTP wire JSON (post _ota strip, as SabreBookingClient POSTs)}
                            {--write-wire-preview= : Write redacted wire JSON to path (under storage/app if relative)}
                            {--style= : Override SABRE_CREATEBOOKING_PAYLOAD_STYLE for preview/wire output}
                            {--segment-sell-diagnostics : B75 safe Passenger Records AirBook segment sell rows (traditional CPNR wire; local/testing)}
                            {--fare-context-diagnostics : B78 safe fare/pricing/carrier snapshot + root AirPrice qualifier keys before CPNR (local/testing)}
                            {--airprice-brand-diagnostics : BF7-A sanitized AirPrice Brand shape summary (local/testing; no HTTP)}
                            {--note= : Optional operator note echoed in segment-sell-diagnostics JSON}';

    protected $description = '[local/testing only] Sanitized Sabre booking payload shape for a booking (no raw JSON, no PII values). B23 wire preview; B25 wire gender enum diagnostics; B26 wire_has_remarks / wire_remarks_count; B27 traveler/document field diagnostics (traveler_* booleans, wire_traveler_required_fields_valid) when using --wire-preview-json; B28 wire_null_*, wire_payload_null_free, wire_contract_valid / wire_invalid_contract_keys; B29 camel Trip Orders styles add wire_traveler_field_style, wire_has_givenName / wire_has_given_name, traveler_N_has_givenName / has_birthDate / has_passengerTypeCode / has_documentType / has_issuingCountry / has_expiryDate (values redacted in --wire-preview-json); B30 wire_segment_field_style, wire_segment_required_fields_valid, wire_invalid_segment_field_keys; B31 trip_orders_flight_details_sabre_v1 adds wire_has_passengerCode / wire_has_passengerTypeCode, traveler_N_has_passengerCode / has_passengerTypeCode, wire_traveler_field_style=sabreTripOrders (boolean presence only; wire JSON remains redacted); B32 same style adds wire_has_contactInfo / wire_has_contact / wire_contact_field_style / wire_has_contact_email / wire_has_contact_phone (no raw email/phone in meta lines); B33 adds wire_has_agency_phone / wire_agency_phone_field_style / wire_agency_phone_redacted / wire_has_customer_contact_phone / wire_agency_phone_ok (no raw agency phone); B34 adds wire_agency_phone_paths (safe dot paths only, no values) for alternate agency-phone compare styles; B35 adds wire_phone_use_type_values_sanitized (use-type codes only) for PNR-style phone rows; B36 adds wire_has_POS / wire_has_pos / wire_has_agency_block / wire_has_travelAgency / wire_has_customerInfo, wire_pcc_present, wire_agency_config_phone_present, wire_agency_country_config_present (booleans only); B37 adds traditional PNR phone-line compare styles + wire_phone_location_values_sanitized (3-letter codes only). B39: --style=traditional_pnr_create_passenger_name_record_v1 with --wire-preview-json emits CPNR-root wire diagnostics (wire_has_create_passenger_name_record_rq, wire_traditional_pnr_contract_valid, …). B75: --segment-sell-diagnostics prints JSON line segment_sell_diagnostics_json= with per-segment AirBook sell fields (0411 triage). B78: --fare-context-diagnostics prints fare_context_diagnostics_json= (snapshot fare/VC/pricing + root AirPrice OptionalQualifiers keys; *NO FARES triage).';

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

        $styleOpt = $this->option('style');
        $styleStr = is_string($styleOpt) && trim($styleOpt) !== '' ? trim($styleOpt) : null;
        $noteOpt = $this->option('note');
        $manualNote = is_string($noteOpt) && trim($noteOpt) !== '' ? trim($noteOpt) : null;

        if ($this->option('segment-sell-diagnostics')) {
            $diag = $sabreBooking->inspectPassengerRecordsAirBookSegmentSellDiagnosticsForCommand($booking, $manualNote);
            $this->line('segment_sell_diagnostics_json='.json_encode($diag, JSON_UNESCAPED_SLASHES));
            if (isset($diag['error'])) {
                return self::FAILURE;
            }
        }

        if ($this->option('fare-context-diagnostics')) {
            $fareCtx = $sabreBooking->inspectPassengerRecordsFareContextDiagnosticsForCommand($booking);
            $this->line('fare_context_diagnostics_json='.json_encode($fareCtx, JSON_UNESCAPED_SLASHES));
            if (isset($fareCtx['error'])) {
                return self::FAILURE;
            }
        }

        if ($this->option('airprice-brand-diagnostics')) {
            $brandDiag = $sabreBooking->inspectPassengerRecordsAirPriceBrandDiagnosticsForCommand($booking, $styleStr);
            $this->line('airprice_brand_diagnostics_json='.json_encode($brandDiag, JSON_UNESCAPED_SLASHES));
            if (isset($brandDiag['error']) && ($brandDiag['error'] ?? '') !== 'booking_not_sabre') {
                return self::FAILURE;
            }
        }

        $writeWireEarly = $this->option('write-wire-preview');
        $quickDiagOnly = ($this->option('segment-sell-diagnostics') || $this->option('fare-context-diagnostics') || $this->option('airprice-brand-diagnostics'))
            && ! $this->option('wire-preview-json')
            && ! $this->option('preview-json')
            && (! is_string($writeWireEarly) || trim($writeWireEarly) === '');
        if ($quickDiagOnly) {
            return self::SUCCESS;
        }

        if ($this->option('preview-json') && $styleStr !== null
            && SabreBookingPayloadBuilder::isTraditionalPnrPassengerRecordsWireStyle($styleStr)) {
            $preview = $sabreBooking->previewRedactedTraditionalPnrForCommand($booking, $styleStr);
            $this->line(json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $writeWireOpt = $this->option('write-wire-preview');
        $wantWireBlock = (bool) $this->option('wire-preview-json')
            || (is_string($writeWireOpt) && trim($writeWireOpt) !== '');

        if ($wantWireBlock) {
            $preview = $sabreBooking->previewTripOrdersWireJsonForInspectCommand($booking, $styleStr);
            if (isset($preview['error'])) {
                $this->line('booking_id='.$booking->id);
                $this->line('wire_preview_error='.(string) $preview['error']);

                return self::FAILURE;
            }
            if ($styleStr === null || $styleStr === ''
                || SabreBookingPayloadBuilder::isTraditionalPnrPassengerRecordsWireStyle($styleStr)) {
                $styleSel = $sabreBooking->inspectPassengerRecordsStyleSelectionForCommand($booking);
                if (! isset($styleSel['error'])) {
                    $this->line('passenger_records_style_decision_json='.json_encode($styleSel, JSON_UNESCAPED_SLASHES));
                }
            }

            foreach ($preview as $k => $v) {
                if ($k === 'redacted_wire_request_body' || $k === 'booking_id' || $k === 'provider') {
                    continue;
                }
                if (is_bool($v)) {
                    $this->line($k.'='.($v ? 'true' : 'false'));
                } elseif (is_array($v)) {
                    $this->line($k.'='.json_encode($v));
                } else {
                    $this->line($k.'='.(string) $v);
                }
            }
            if ($this->option('wire-preview-json')) {
                $this->line('redacted_wire_request_body='.json_encode($preview['redacted_wire_request_body'] ?? [], JSON_UNESCAPED_SLASHES));
            }
            $writePath = $this->option('write-wire-preview');
            if (is_string($writePath) && trim($writePath) !== '') {
                $path = trim($writePath);
                if ($path !== '' && ! preg_match('~[\\\\/]~', $path)) {
                    $path = storage_path('app/'.$path);
                }
                $payload = [
                    'meta' => array_filter($preview, static fn ($x, $key): bool => $key !== 'redacted_wire_request_body', ARRAY_FILTER_USE_BOTH),
                    'wire_request_body' => $preview['redacted_wire_request_body'] ?? [],
                ];
                $dir = dirname($path);
                if (! is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                $this->line('write_wire_preview_ok=true');
                $this->line('write_wire_preview_path='.$path);
            }

            if ($this->option('preview-json')) {
                if ($styleStr !== null && SabreBookingPayloadBuilder::isTraditionalPnrPassengerRecordsWireStyle($styleStr)) {
                    $p2 = $sabreBooking->previewRedactedTraditionalPnrForCommand($booking, $styleStr);
                } else {
                    $p2 = $sabreBooking->previewRedactedTripOrdersCreateBookingForCommand($booking, $styleStr);
                }
                $this->line(json_encode($p2, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

            return self::SUCCESS;
        }

        if ($this->option('preview-json')) {
            $preview = $sabreBooking->previewRedactedTripOrdersCreateBookingForCommand($booking, $styleStr);
            $this->line(json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $shape = $sabreBooking->inspectBookingPayloadShapeForCommand($booking);
        if (($shape['error'] ?? null) === 'booking_not_sabre') {
            $this->line('booking_id='.$booking->id);
            $this->line('provider='.($shape['provider'] ?? ''));
            $this->line('error=booking_not_sabre');

            return self::SUCCESS;
        }

        ksort($shape);
        foreach ($shape as $k => $v) {
            if (is_bool($v)) {
                $this->line($k.'='.($v ? 'true' : 'false'));
            } elseif (is_scalar($v) || $v === null) {
                $this->line($k.'='.(string) $v);
            } elseif (is_array($v)) {
                $this->line($k.'='.json_encode($v));
            }
        }

        $styleSel = $sabreBooking->inspectPassengerRecordsStyleSelectionForCommand($booking);
        if (! isset($styleSel['error'])) {
            $merged = array_merge($shape, $styleSel, is_array($styleSel['freshness_strategy_decision_json'] ?? null) ? $styleSel['freshness_strategy_decision_json'] : []);
            foreach (BookingManagementController::adminSafeSabreDiagnosticFieldsForOutput($merged) as $k => $v) {
                $this->line('admin_safe_'.$k.'='.$v);
            }
        }

        return self::SUCCESS;
    }
}
