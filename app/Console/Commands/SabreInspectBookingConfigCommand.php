<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Services\Suppliers\Sabre\SabreBookingPayloadBuilder;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use Illuminate\Console\Command;

class SabreInspectBookingConfigCommand extends Command
{
    protected $signature = 'sabre:inspect-booking-config {--booking= : Booking ID}';

    protected $description = '[local/testing only] Show Sabre booking endpoint and config flags for a booking (no secrets)';

    public function handle(SabreBookingService $sabreBooking, SabreClient $sabreClient, SabreBookingPayloadBuilder $payloadBuilder): int
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

        $booking = Booking::query()->with(['contact', 'passengers'])->find((int) $bookingId);
        if ($booking === null) {
            $this->components->error('Booking not found.');

            return self::FAILURE;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $cid = isset($meta['supplier_connection_id']) && is_numeric($meta['supplier_connection_id'])
            ? (int) $meta['supplier_connection_id']
            : 0;
        $connection = $cid > 0 ? SupplierConnection::query()->find($cid) : null;

        $path = (string) config('suppliers.sabre.booking_path', '/v1/trip/orders/createBooking');
        $endpointHost = 'unknown';
        $endpointPath = $path !== '' && $path[0] === '/' ? $path : '/'.$path;
        if ($connection !== null && $connection->provider === SupplierProvider::Sabre) {
            $parts = $sabreClient->resolveEndpointParts($connection, $path);
            $endpointHost = $parts['endpoint_host'];
            $endpointPath = $parts['endpoint_path'];
        } else {
            $base = rtrim((string) config('suppliers.sabre.default_base_url'), '/');
            $h = parse_url(str_contains($base, '://') ? $base : 'https://'.$base, PHP_URL_HOST);
            if (is_string($h) && $h !== '') {
                $endpointHost = $h;
            }
        }

        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null)
            ? $meta['normalized_offer_snapshot']
            : (is_array($meta['flight_offer_snapshot'] ?? null) ? $meta['flight_offer_snapshot'] : []);
        $segments = is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : [];
        $segmentCount = count($segments);
        $passengerCount = $booking->passengers()->count();
        $contact = $booking->contact;

        $shape = $sabreBooking->inspectBookingPayloadShapeForCommand($booking);
        $validationOk = ($shape['validation_ok'] ?? false) === true;
        $hasBookingClass = (bool) ($shape['has_booking_class'] ?? false);
        $hasFareBasis = (bool) ($shape['has_fare_basis'] ?? false);
        $hasValidatingCarrier = (bool) ($shape['has_validating_carrier'] ?? false);
        $hasPassportDoc = (bool) ($shape['has_passport_doc'] ?? false);

        $revBefore = $sabreBooking->isRevalidationBeforeBookingEnabled();
        $allowBypass = $sabreBooking->isAllowCreateBookingWithoutRevalidation();
        $createbookingWithoutRevalidationAllowed = ! $revBefore || $allowBypass;

        $gatesOk = $sabreBooking->mayPerformLiveSabreBookingCall()
            && $cid > 0
            && $connection !== null
            && $connection->provider === SupplierProvider::Sabre
            && $validationOk;

        $canAttemptCreateBookingNow = $gatesOk && $createbookingWithoutRevalidationAllowed;

        $reasonIfBlocked = null;
        if (! $sabreBooking->isBookingEnabled()) {
            $reasonIfBlocked = 'sabre_booking_disabled';
        } elseif (! $sabreBooking->isBookingLiveCallEnabled()) {
            $reasonIfBlocked = 'sabre_booking_live_calls_disabled';
        } elseif ($cid <= 0 || $connection === null || $connection->provider !== SupplierProvider::Sabre) {
            $reasonIfBlocked = 'missing_or_invalid_supplier_connection';
        } elseif (! $validationOk) {
            $reasonIfBlocked = 'offer_validation_failed';
        } elseif (! $createbookingWithoutRevalidationAllowed) {
            $reasonIfBlocked = 'revalidation_required_without_bypass';
        }

        $this->line('booking_id='.$booking->id);
        $this->line('provider='.SupplierProvider::Sabre->value);
        $this->line('endpoint_host='.$endpointHost);
        $this->line('endpoint_path='.$endpointPath);
        $this->line('booking_enabled='.($sabreBooking->isBookingEnabled() ? 'true' : 'false'));
        $this->line('booking_live_call_enabled='.($sabreBooking->isBookingLiveCallEnabled() ? 'true' : 'false'));
        $this->line('ticketing_enabled='.($sabreBooking->isTicketingEnabled() ? 'true' : 'false'));
        $this->line('revalidate_before_booking='.($revBefore ? 'true' : 'false'));
        $this->line('allow_createbooking_without_revalidation='.($allowBypass ? 'true' : 'false'));
        $this->line('can_attempt_createbooking_now='.($canAttemptCreateBookingNow ? 'true' : 'false'));
        $this->line('createbooking_without_revalidation_allowed='.($createbookingWithoutRevalidationAllowed ? 'true' : 'false'));
        $this->line('reason_if_blocked='.($reasonIfBlocked ?? 'none'));
        $this->line('segment_count='.$segmentCount);
        $this->line('passenger_count='.$passengerCount);
        $this->line('has_booking_class='.($hasBookingClass ? 'true' : 'false'));
        $this->line('has_fare_basis='.($hasFareBasis ? 'true' : 'false'));
        $this->line('has_validating_carrier='.($hasValidatingCarrier ? 'true' : 'false'));
        $this->line('has_contact_email='.($contact !== null && trim((string) $contact->email) !== '' ? 'true' : 'false'));
        $this->line('has_contact_phone='.($contact !== null && trim((string) $contact->phone) !== '' ? 'true' : 'false'));
        $this->line('has_passport_doc='.($hasPassportDoc ? 'true' : 'false'));
        $this->line('validation_ok='.($validationOk ? 'true' : 'false'));

        $payloadStyle = $payloadBuilder->resolveCreatebookingPayloadStyle();
        $this->line('active_createbooking_payload_style='.$payloadStyle);
        $this->line('booking_path='.$path);
        $agencyPhoneCfg = trim((string) config('suppliers.sabre.agency_phone', '')) !== '';
        $this->line('agency_phone_config_present='.($agencyPhoneCfg ? 'true' : 'false'));
        $agencyCountryCfg = trim((string) config('suppliers.sabre.agency_country', '')) !== ''
            || trim((string) config('suppliers.sabre.agency_phone_country_code', '')) !== '';
        $this->line('agency_country_config_present='.($agencyCountryCfg ? 'true' : 'false'));
        $pccPresent = $payloadBuilder->resolveSabrePseudoCityCodeForTripOrdersWire(['supplier_connection_id' => $cid]) !== '';
        $this->line('pcc_present='.($pccPresent ? 'true' : 'false'));

        $tripOrdersAgencyPhoneStillRejected = false;
        $latest = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::Sabre->value)
            ->orderByDesc('attempted_at')
            ->first();
        if ($latest !== null && is_array($latest->safe_summary)) {
            $ss = $latest->safe_summary;
            if (($ss['agency_phone_error'] ?? false) === true) {
                $tripOrdersAgencyPhoneStillRejected = true;
            } else {
                foreach ((array) ($ss['response_error_messages'] ?? []) as $m) {
                    if (is_string($m) && stripos($m, 'AGENCY_PHONE_MISSING') !== false) {
                        $tripOrdersAgencyPhoneStillRejected = true;
                        break;
                    }
                }
            }
        }
        $this->line('trip_orders_agency_phone_still_rejected='.($tripOrdersAgencyPhoneStillRejected ? 'true' : 'false'));

        $tripOrdersBookingPath = str_contains((string) $path, 'trip/orders/createBooking');
        $schemaTrim = trim((string) (config('suppliers.sabre.booking_schema') ?? ''));
        $cpnrStyle = SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1;
        $passengerRecordsContext = (! $tripOrdersBookingPath)
            || $payloadStyle === $cpnrStyle
            || $schemaTrim === 'create_passenger_name_record'
            || $schemaTrim === 'passenger_records_create_pnr';

        if ($tripOrdersAgencyPhoneStillRejected) {
            $suggestedFlow = 'traditional_pnr_candidate';
        } elseif ($passengerRecordsContext) {
            $suggestedFlow = ($payloadStyle === $cpnrStyle
                || $schemaTrim === 'create_passenger_name_record'
                || $schemaTrim === 'passenger_records_create_pnr')
                ? 'create_passenger_name_record'
                : 'passenger_records';
        } else {
            $suggestedFlow = 'trip_orders';
        }
        $this->line('suggested_booking_flow='.$suggestedFlow);
        $this->line('agency_phone_profile_hint='.($tripOrdersAgencyPhoneStillRejected
            ? 'Latest Sabre attempt still reports AGENCY_PHONE_MISSING — Sabre may require agency/PCC profile phone (TJR/office) even when JSON carries office phone; verify in Sabre admin and consider traditional PNR candidate endpoints (sabre:compare-booking-endpoints).'
            : 'none'));

        return self::SUCCESS;
    }
}
