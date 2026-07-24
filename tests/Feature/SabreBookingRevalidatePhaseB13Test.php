<?php

namespace Tests\Feature;

use App\Console\Commands\SabreCheckRevalidateEndpointsCommand;
use App\Console\Commands\SabreCompareRevalidateEndpointsCommand;
use App\Console\Commands\SabreCompareRevalidateStylesCommand;
use App\Enums\BookingStatus;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Exceptions\SabreRevalidateGatekeeperException;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingFareBreakdown;
use App\Models\BookingPassenger;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Services\Suppliers\Sabre\SabreRevalidationPayloadBuilder;
use App\Support\PublicBooking;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase B13 — Sabre revalidation/fare-linkage before Trip Orders createBooking.
 *
 * Covers:
 * - Revalidate payload includes segment ClassOfService + flight segment details (no raw PII)
 * - Revalidate response with fareBasisCode/priceQuoteReference populates booking payload fare_linkage
 * - Revalidation HTTP failure short-circuits createBooking with sabre_revalidation_failed
 * - Trip Orders inspect shows fare-linkage flags and revalidation/validating-carrier flags
 * - MANDATORY_DATA_MISSING safe_summary carries which linkage flags were false
 * - Ticketing remains disabled; no token/PCC/passenger/passport/contact leakage
 */
class SabreBookingRevalidatePhaseB13Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config([
            'suppliers.sabre.allow_createbooking_without_revalidation' => false,
            'suppliers.sabre.createbooking_payload_style' => 'trip_orders_create_booking_v1_current',
            'suppliers.sabre.certified_route_selector_public_checkout_enabled' => false,
            'suppliers.sabre.refresh_offer_before_public_pnr' => false,
        ]);
    }

    public function test_iati_like_bfm_revalidate_v1_emits_ota_only_wire_and_iati_shape(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $internalDraft = $this->sampleInternalDraftWithSegments(
            firstClass: 'Y',
            firstFareBasis: 'YOWPK',
            secondClass: 'K',
            secondFareBasis: 'KLITE1',
        );
        $internalDraft['_sabre_pseudo_city_code'] = 'LHE1';
        $internalDraft['passengers'] = [
            ['type' => 'ADT', 'first_name' => 'A', 'last_name' => 'B'],
            ['type' => 'CHD', 'first_name' => 'C', 'last_name' => 'D'],
        ];

        $payload = $builder->buildPayload($internalDraft, 'iati_like_bfm_revalidate_v1');
        $wire = $builder->wireableRequestPayload($payload);

        $this->assertSame(['OTA_AirLowFareSearchRQ'], array_keys($wire));
        $this->assertSame('4', data_get($wire, 'OTA_AirLowFareSearchRQ.Version'));
        $this->assertNull(data_get($wire, 'OTA_AirLowFareSearchRQ.@Version'));
        $this->assertSame('LHE1', data_get($wire, 'OTA_AirLowFareSearchRQ.POS.Source.0.PseudoCityCode'));
        $this->assertSame('1', data_get($wire, 'OTA_AirLowFareSearchRQ.POS.Source.0.RequestorID.Type'));
        $this->assertSame('1', data_get($wire, 'OTA_AirLowFareSearchRQ.POS.Source.0.RequestorID.ID'));
        $this->assertSame('TN', data_get($wire, 'OTA_AirLowFareSearchRQ.POS.Source.0.RequestorID.CompanyName.Code'));
        $this->assertSame('Disable', data_get($wire, 'OTA_AirLowFareSearchRQ.TravelPreferences.TPA_Extensions.DataSources.NDC'));
        $this->assertSame('Enable', data_get($wire, 'OTA_AirLowFareSearchRQ.TravelPreferences.TPA_Extensions.DataSources.ATPCO'));
        $this->assertSame('Enable', data_get($wire, 'OTA_AirLowFareSearchRQ.TravelPreferences.TPA_Extensions.DataSources.LCC'));
        $this->assertSame('50ITINS', data_get($wire, 'OTA_AirLowFareSearchRQ.TPA_Extensions.IntelliSellTransaction.RequestType.Name'));
        $this->assertNull(data_get($wire, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.TPA_Extensions.IntelliSellTransaction.RequestType.Name'));
        $this->assertSame([2], data_get($wire, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.SeatsRequested'));

        $ptq = data_get($wire, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.AirTravelerAvail.0.PassengerTypeQuantity');
        $this->assertIsArray($ptq);
        $codes = array_column($ptq, 'Code');
        $this->assertContains('ADT', $codes);
        $this->assertContains('CNN', $codes);
        $this->assertNotContains('CHD', $codes);

        $this->assertCount(1, data_get($wire, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation'));
        $odi = data_get($wire, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0');
        $this->assertIsArray($odi);
        $this->assertSame('1', $odi['RPH'] ?? null);
        $this->assertSame('2026-08-15T05:00:00', $odi['DepartureDateTime'] ?? null);
        $this->assertSame('LHE', data_get($odi, 'OriginLocation.LocationCode'));
        $this->assertSame('DXB', data_get($odi, 'DestinationLocation.LocationCode'));
        $this->assertSame('O', data_get($odi, 'TPA_Extensions.SegmentType.Code'));

        $flights = data_get($odi, 'TPA_Extensions.Flight');
        $this->assertIsArray($flights);
        $this->assertCount(2, $flights);

        $firstFlight = $flights[0];
        $this->assertIsArray($firstFlight);
        $this->assertSame('A', $firstFlight['Type'] ?? null);
        $this->assertSame('Y', $firstFlight['ClassOfService'] ?? null);
        $this->assertSame(303, $firstFlight['Number'] ?? null);
        $this->assertSame('PK', data_get($firstFlight, 'Airline.Marketing.Code'));
        $this->assertSame('PK', data_get($firstFlight, 'Airline.Operating.Code'));
        $this->assertNull(data_get($firstFlight, 'MarketingAirline.Code'));
        $this->assertSame('KHI', data_get($firstFlight, 'DestinationLocation.LocationCode'));

        $secondFlight = $flights[1];
        $this->assertSame(601, $secondFlight['Number'] ?? null);
        $this->assertSame('EK', data_get($secondFlight, 'Airline.Marketing.Code'));

        $this->assertSame('KHI', data_get($secondFlight, 'OriginLocation.LocationCode'));
        $this->assertSame('DXB', data_get($secondFlight, 'DestinationLocation.LocationCode'));

        $diagnostics = $builder->structuralPayloadDiagnostics($payload);
        $this->assertSame('iati_like_bfm_revalidate_v1', $diagnostics['revalidate_payload_style']);
        $this->assertTrue($diagnostics['has_pcc']);
        $this->assertTrue($diagnostics['has_datasources']);
        $this->assertTrue($diagnostics['has_intellisell_50itins']);
        $this->assertSame(2, $diagnostics['flight_node_count']);
        $this->assertSame(1, $diagnostics['odi_count']);
        $this->assertSame('2', $diagnostics['grouped_flight_nodes_per_odi']);
        $this->assertTrue($diagnostics['iati_like_segments_grouped']);
        $this->assertSame('under_24h', $diagnostics['max_connection_gap_bucket']);
        $this->assertSame('Y|K', $diagnostics['class_of_service_values_sanitized']);
        $this->assertNull(data_get($wire, 'OTA_AirLowFareSearchRQ.TravelPreferences.TPA_Extensions.VerificationItinCallLogic.Value'));
        $this->assertSame('303|601', $diagnostics['flight_number_values_sanitized']);
        $this->assertTrue($diagnostics['flight_node_number_uses_actual_flight_number']);
        $this->assertTrue($diagnostics['has_iati_airline_node']);
        $this->assertSame('array', $diagnostics['seats_requested_type']);
        $this->assertSame('root', $diagnostics['intellisell_location']);
        $this->assertSame('Version', $diagnostics['version_key_type']);

        $previewJson = json_encode($builder->sanitizeRevalidatePreviewTree($wire));
        $this->assertIsString($previewJson);
        $this->assertStringNotContainsString('LHE1', $previewJson, 'PCC must be redacted from preview export');
    }

    public function test_manager_like_bfm_revalidate_v1_adds_verification_itin_call_logic_on_iati_like_wire(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $internalDraft = $this->sampleInternalDraftWithSegments('Y', 'YOWPK', 'K', 'KLITE1');

        $payload = $builder->buildPayload($internalDraft, 'manager_like_bfm_revalidate_v1');
        $wire = $builder->wireableRequestPayload($payload);

        $this->assertSame(['OTA_AirLowFareSearchRQ'], array_keys($wire));
        $this->assertSame('B', data_get($wire, 'OTA_AirLowFareSearchRQ.TravelPreferences.TPA_Extensions.VerificationItinCallLogic.Value'));
        $this->assertIsArray(data_get($wire, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.TPA_Extensions.Flight'));
        $this->assertSame('manager_like_bfm_revalidate_v1', $payload['_ota_revalidate_payload_style'] ?? null);
        $this->assertSame('sabre_manager_like_bfm_revalidate_v1', $payload['_ota_payload_schema'] ?? null);
    }

    public function test_manager_like_bfm_revalidate_enriched_v1_adds_res_book_desig_fare_basis_and_operating(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $internalDraft = $this->sampleInternalDraftWithSegments('Y', 'YOWPK', 'K', 'KLITE1');

        $payload = $builder->buildPayload($internalDraft, 'manager_like_bfm_revalidate_enriched_v1');
        $wire = $builder->wireableRequestPayload($payload);

        $this->assertSame(['OTA_AirLowFareSearchRQ'], array_keys($wire));
        $this->assertSame('B', data_get($wire, 'OTA_AirLowFareSearchRQ.TravelPreferences.TPA_Extensions.VerificationItinCallLogic.Value'));
        $this->assertSame('50ITINS', data_get($wire, 'OTA_AirLowFareSearchRQ.TPA_Extensions.IntelliSellTransaction.RequestType.Name'));
        $this->assertSame([1], data_get($wire, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.SeatsRequested'));

        $firstFlight = data_get($wire, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.TPA_Extensions.Flight.0');
        $this->assertIsArray($firstFlight);
        $this->assertSame('Y', $firstFlight['ClassOfService'] ?? null);
        $this->assertSame('Y', $firstFlight['ResBookDesigCode'] ?? null);
        $this->assertSame('YOWPK', $firstFlight['FareBasisCode'] ?? null);
        $this->assertSame('PK', data_get($firstFlight, 'Airline.Marketing.Code'));
        $this->assertSame('PK', data_get($firstFlight, 'Airline.Operating.Code'));

        $secondFlight = data_get($wire, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.TPA_Extensions.Flight.1');
        $this->assertSame('K', $secondFlight['ResBookDesigCode'] ?? null);
        $this->assertSame('KLITE1', $secondFlight['FareBasisCode'] ?? null);
        $this->assertSame('EK', data_get($secondFlight, 'Airline.Operating.Code'));

        $this->assertSame('manager_like_bfm_revalidate_enriched_v1', $payload['_ota_revalidate_payload_style'] ?? null);
        $this->assertSame('sabre_manager_like_bfm_revalidate_enriched_v1', $payload['_ota_payload_schema'] ?? null);
    }

    public function test_iati_like_bfm_revalidate_v1_includes_pcc_from_supplier_connection(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $conn->update([
            'credentials' => array_merge(is_array($conn->credentials) ? $conn->credentials : [], ['pcc' => 'AB12']),
        ]);

        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $internalDraft = $this->sampleInternalDraftWithSegments(
            firstClass: 'Y',
            firstFareBasis: 'YOWPK',
            secondClass: 'K',
            secondFareBasis: 'KLITE1',
        );
        $internalDraft['supplier_connection_id'] = $conn->id;
        unset($internalDraft['_sabre_pseudo_city_code']);

        $payload = $builder->buildPayload($internalDraft, 'iati_like_bfm_revalidate_v1');
        $wire = $builder->wireableRequestPayload($payload);
        $this->assertSame('AB12', data_get($wire, 'OTA_AirLowFareSearchRQ.POS.Source.0.PseudoCityCode'));

        $diagnostics = $builder->structuralPayloadDiagnostics($payload);
        $this->assertTrue($diagnostics['has_pcc']);

        $previewJson = json_encode($builder->sanitizeRevalidatePreviewTree($wire));
        $this->assertIsString($previewJson);
        $this->assertStringNotContainsString('AB12', $previewJson, 'PCC must be redacted from preview export');
    }

    public function test_iati_like_bfm_revalidate_v1_groups_booking_16_style_connection_under_24h(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $internalDraft = $this->sampleInternalDraftWithSegments('O', 'YOWPK', 'Y', 'KLITE1');
        $internalDraft['segments'][0]['departure_at'] = '2026-05-28T05:00:00';
        $internalDraft['segments'][0]['arrival_at'] = '2026-05-28T06:45:00';
        $internalDraft['segments'][1]['departure_at'] = '2026-05-29T05:00:00';
        $internalDraft['segments'][1]['arrival_at'] = '2026-05-29T08:00:00';

        $wire = $builder->wireableRequestPayload($builder->buildPayload($internalDraft, 'iati_like_bfm_revalidate_v1'));

        $this->assertCount(1, data_get($wire, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation'));
        $flights = data_get($wire, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.TPA_Extensions.Flight');
        $this->assertCount(2, $flights);
        $this->assertSame('LHE', data_get($flights, '0.OriginLocation.LocationCode'));
        $this->assertSame('KHI', data_get($flights, '0.DestinationLocation.LocationCode'));
        $this->assertSame('KHI', data_get($flights, '1.OriginLocation.LocationCode'));
        $this->assertSame('DXB', data_get($flights, '1.DestinationLocation.LocationCode'));

        $diagnostics = $builder->structuralPayloadDiagnostics($builder->buildPayload($internalDraft, 'iati_like_bfm_revalidate_v1'));
        $this->assertSame(1, $diagnostics['odi_count']);
        $this->assertSame('2', $diagnostics['grouped_flight_nodes_per_odi']);
        $this->assertTrue($diagnostics['iati_like_segments_grouped']);
        $this->assertSame(1335, $diagnostics['max_connection_gap_minutes_sanitized']);
        $this->assertSame('under_24h', $diagnostics['max_connection_gap_bucket']);
    }

    public function test_iati_like_bfm_revalidate_v1_splits_odi_when_connection_gap_exceeds_24h(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $internalDraft = $this->sampleInternalDraftWithSegments('Y', 'YOWPK', 'K', 'KLITE1');
        $internalDraft['segments'][0]['arrival_at'] = '2026-08-15T06:45:00';
        $internalDraft['segments'][1]['departure_at'] = '2026-08-17T07:00:00';

        $payload = $builder->buildPayload($internalDraft, 'iati_like_bfm_revalidate_v1');
        $wire = $builder->wireableRequestPayload($payload);

        $this->assertCount(2, data_get($wire, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation'));
        $this->assertCount(1, data_get($wire, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.TPA_Extensions.Flight'));
        $this->assertCount(1, data_get($wire, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation.1.TPA_Extensions.Flight'));

        $diagnostics = $builder->structuralPayloadDiagnostics($payload);
        $this->assertSame(2, $diagnostics['odi_count']);
        $this->assertSame('1|1', $diagnostics['grouped_flight_nodes_per_odi']);
        $this->assertFalse($diagnostics['iati_like_segments_grouped']);
        $this->assertSame('over_24h', $diagnostics['max_connection_gap_bucket']);
    }

    public function test_iati_like_bfm_revalidate_v1_splits_odi_when_route_continuity_breaks(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $internalDraft = $this->sampleInternalDraftWithSegments('Y', 'YOWPK', 'K', 'KLITE1');
        $internalDraft['segments'][1]['origin'] = 'DXB';

        $payload = $builder->buildPayload($internalDraft, 'iati_like_bfm_revalidate_v1');
        $wire = $builder->wireableRequestPayload($payload);

        $this->assertCount(2, data_get($wire, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation'));

        $diagnostics = $builder->structuralPayloadDiagnostics($payload);
        $this->assertSame(2, $diagnostics['odi_count']);
        $this->assertSame('1|1', $diagnostics['grouped_flight_nodes_per_odi']);
        $this->assertFalse($diagnostics['iati_like_segments_grouped']);
        $this->assertFalse($payload['_ota_iati_grouping']['route_continuity_ok'] ?? true);
    }

    public function test_bfm_revalidate_v1_unchanged_still_includes_internal_shop_blocks(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $internalDraft = $this->sampleInternalDraftWithSegments(
            firstClass: 'Y',
            firstFareBasis: 'YOWPK',
            secondClass: 'K',
            secondFareBasis: 'KLITE1',
        );

        $payload = $builder->buildPayload($internalDraft, 'bfm_revalidate_v1');
        $this->assertArrayHasKey('shop_context', $payload);
        $this->assertArrayHasKey('fare_context', $payload);
        $this->assertSame('bfm_revalidate_v1', $payload['_ota_revalidate_payload_style'] ?? null);

        $wire = $builder->wireableRequestPayload($payload);
        $this->assertArrayHasKey('shop_context', $wire);
        $this->assertArrayHasKey('itinerary', $wire);
        $this->assertArrayHasKey('pricingInformation', $wire);
        $this->assertArrayHasKey('fare_context', $wire);
        $this->assertArrayHasKey('passenger_counts', $wire);
        $this->assertIsArray(data_get($wire, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.TPA_Extensions.Flight'));
        $this->assertNull(data_get($wire, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.FlightSegment'));
    }

    public function test_revalidation_payload_includes_segment_class_of_service_and_flight_segment_details(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);

        $internalDraft = $this->sampleInternalDraftWithSegments(
            firstClass: 'Y',
            firstFareBasis: 'YOWPK',
            secondClass: 'K',
            secondFareBasis: 'KLITE1',
        );

        $payload = $builder->buildPayload($internalDraft);
        $odis = data_get($payload, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation');
        $this->assertIsArray($odis);
        $this->assertCount(1, $odis);

        $firstSeg = data_get($odis, '0.TPA_Extensions.Flight.0');
        $this->assertIsArray($firstSeg);
        $this->assertSame('LHE', data_get($firstSeg, 'OriginLocation.LocationCode'));
        $this->assertSame('KHI', data_get($firstSeg, 'DestinationLocation.LocationCode'));
        $this->assertSame('Y', $firstSeg['ClassOfService'] ?? null);
        $this->assertArrayNotHasKey('ResBookDesigCode', $firstSeg);
        $this->assertArrayNotHasKey('FareBasisCode', $firstSeg);
        $this->assertSame('PK', data_get($firstSeg, 'Airline.Marketing'));
        $this->assertIsString(data_get($firstSeg, 'Airline.Marketing'));

        $secondSeg = data_get($odis, '0.TPA_Extensions.Flight.1');
        $this->assertIsArray($secondSeg);
        $this->assertSame('KHI', data_get($secondSeg, 'OriginLocation.LocationCode'));
        $this->assertSame('DXB', data_get($secondSeg, 'DestinationLocation.LocationCode'));
        $this->assertSame('K', $secondSeg['ClassOfService'] ?? null);
        $this->assertArrayNotHasKey('ResBookDesigCode', $secondSeg);
        $this->assertArrayNotHasKey('FareBasisCode', $secondSeg);

        $summary = $builder->safePayloadSummary($payload);
        $this->assertSame(2, $summary['segment_count']);
        $this->assertTrue($summary['has_booking_class']);
        $this->assertTrue($summary['has_fare_basis']);
        $this->assertTrue($summary['has_offer_reference']);
        $this->assertSame('PKR', $summary['currency']);

        $payloadJson = json_encode($payload);
        $this->assertIsString($payloadJson);
        foreach ($this->sensitiveTokens() as $needle) {
            $this->assertStringNotContainsString($needle, $payloadJson, 'Revalidation payload must not include sensitive value: '.$needle);
        }
    }

    public function test_revalidation_response_with_fare_basis_code_populates_booking_payload(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $linkage = $builder->extractFareLinkage([
            'pricedItineraries' => [
                [
                    'airItineraryPricingInfo' => [
                        'fareInfos' => [
                            [
                                'fareBasisCode' => 'YOWPK',
                                'departureAirport' => 'LHE',
                                'arrivalAirport' => 'KHI',
                                'bookingCode' => 'Y',
                            ],
                            [
                                'fareBasisCode' => 'KLITE1',
                                'departureAirport' => 'KHI',
                                'arrivalAirport' => 'DXB',
                                'bookingCode' => 'K',
                            ],
                        ],
                        'validatingCarrier' => 'PK',
                        'itinTotalFare' => [
                            'totalFare' => ['totalPrice' => 320.50, 'currencyCode' => 'USD'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame(['YOWPK', 'KLITE1'], $linkage['fare_basis_codes']);
        $this->assertSame('PK', $linkage['validating_carrier']);
        $this->assertSame(320.5, $linkage['revalidated_total']);
        $this->assertSame('USD', $linkage['revalidated_currency']);

        $digest = $builder->linkageDigest($linkage);
        $this->assertTrue($digest['has_fare_basis']);
        $this->assertTrue($digest['has_validating_carrier']);
        $this->assertTrue($digest['has_revalidated_fare']);
    }

    public function test_revalidation_response_with_price_quote_reference_populates_booking_payload(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $linkage = $builder->extractFareLinkage([
            'priceQuoteReference' => 'PQ-12345',
            'offerId' => 'OFFER-9876',
            'fareReference' => 'FAREREF-77',
            'revalidationReference' => 'REVAL-9',
        ]);

        $this->assertSame('PQ-12345', $linkage['price_quote_reference']);
        $this->assertSame('OFFER-9876', $linkage['offer_reference']);
        $this->assertSame('FAREREF-77', $linkage['fare_reference']);
        $this->assertSame('REVAL-9', $linkage['revalidation_reference']);

        $digest = $builder->linkageDigest($linkage);
        $this->assertTrue($digest['has_price_quote_reference']);
        $this->assertTrue($digest['has_offer_reference']);
        $this->assertTrue($digest['has_fare_reference']);
        $this->assertTrue($digest['has_revalidation_reference']);
    }

    public function test_revalidation_failure_short_circuits_create_booking_and_stores_attempt(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $sabreBase = rtrim((string) config('suppliers.sabre.default_base_url'), '/');
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');
        $bookingPath = (string) config('suppliers.sabre.booking_path', '/v1/trip/orders/createBooking');
        $bookingPath = $bookingPath !== '' && $bookingPath[0] === '/' ? $bookingPath : '/'.$bookingPath;
        $revalidatePath = '/v4/shop/flights/revalidate';

        Http::fake([
            $sabreBase.$tokenPath => Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200),
            $sabreBase.$revalidatePath => Http::response(['message' => 'revalidation failed'], 422),
            $sabreBase.$bookingPath => Http::response(['recordLocator' => 'SHOULD_NOT_BE_CALLED'], 200),
        ]);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => null,
            'suppliers.sabre.revalidate_before_booking' => true,
            'suppliers.sabre.revalidate_path' => $revalidatePath,
        ]);

        $booking = $this->seedLiveSabreBooking('revalidate-fail@example.com');

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later']);

        $attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();
        $this->assertNotNull($attempt);
        $this->assertSame('failed', $attempt->status);
        $this->assertSame('sabre_revalidation_failed', $attempt->error_code);

        $booking->refresh();
        $this->assertSame('manual_review', $booking->supplier_booking_status);
        $this->assertNull($booking->pnr);

        Http::assertSent(function ($request) use ($revalidatePath): bool {
            return $request instanceof Request && str_contains($request->url(), $revalidatePath);
        });
        Http::assertNotSent(function ($request) use ($bookingPath): bool {
            return $request instanceof Request && str_contains($request->url(), $bookingPath);
        });

        $summary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $this->assertSame(false, $summary['live_call_attempted'] ?? null);
        $this->assertTrue((bool) ($summary['revalidation_attempted'] ?? false));
        $this->assertSame('failed', $summary['revalidation_outcome'] ?? null);
        $summaryJson = json_encode($summary);
        $this->assertIsString($summaryJson);
        foreach ($this->sensitiveTokens() as $needle) {
            $this->assertStringNotContainsString($needle, $summaryJson, 'Attempt safe_summary must not include sensitive value: '.$needle);
        }
    }

    public function test_inspect_booking_payload_shows_fare_linkage_flags(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        config([
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => null,
        ]);

        $booking = $this->seedInspectableSabreBooking();

        Artisan::call('sabre:inspect-booking-payload', ['--booking' => (string) $booking->id]);
        $out = Artisan::output();

        $this->assertStringContainsString('has_revalidation_reference=', $out);
        $this->assertStringContainsString('has_revalidated_fare=', $out);
        $this->assertStringContainsString('has_revalidated_currency=', $out);
        $this->assertStringContainsString('has_fare_basis=true', $out);
        $this->assertStringContainsString('has_offer_reference=true', $out);
        $this->assertStringContainsString('has_validating_carrier=', $out);
        $this->assertStringContainsString('has_price_quote_reference=', $out);
        $this->assertStringContainsString('has_fare_reference=', $out);
        $this->assertStringContainsString('ticketing_enabled=false', $out);
        $this->assertStringNotContainsString('Authorization', $out);
    }

    public function test_mandatory_data_missing_safe_summary_includes_missing_linkage_flags(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $sabreBase = rtrim((string) config('suppliers.sabre.default_base_url'), '/');
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');
        $bookingPath = '/v1/trip/orders/createBooking';

        Http::fake([
            $sabreBase.$tokenPath => Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200),
            $sabreBase.$bookingPath => Http::response([
                'errors' => [
                    ['code' => 'MANDATORY_DATA_MISSING', 'title' => 'Missing', 'detail' => 'Fare linkage required'],
                ],
            ], 200),
        ]);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => null,
            'suppliers.sabre.revalidate_before_booking' => false,
            'suppliers.sabre.createbooking_payload_style' => 'trip_orders_flight_offer_v1',
        ]);

        $booking = $this->seedLiveSabreBooking('mdm-linkage@example.com');

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.confirmation'));

        $attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();
        $this->assertNotNull($attempt);
        $this->assertSame('needs_review', $attempt->status);
        $this->assertSame('sabre_booking_application_error', $attempt->error_code);

        $summary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $this->assertArrayHasKey('has_fare_basis', $summary);
        $this->assertArrayHasKey('has_fare_reference', $summary);
        $this->assertArrayHasKey('has_price_quote_reference', $summary);
        $this->assertArrayHasKey('has_offer_reference', $summary);
        $this->assertArrayHasKey('has_revalidation_reference', $summary);
        $this->assertArrayHasKey('has_end_transaction', $summary);
        $this->assertArrayHasKey('missing_linkage_flags', $summary);
        $this->assertIsArray($summary['missing_linkage_flags']);
        $this->assertTrue((bool) ($summary['has_flight_offer'] ?? false), 'Expected flightOffer payload style for MDM test');
        $this->assertTrue((bool) ($summary['has_required_booking_product_object'] ?? false));
        $this->assertSame('trip_orders_flight_offer_v1', (string) ($summary['payload_style'] ?? ''));
        $this->assertArrayHasKey('response_error_messages', $summary);

        $booking->refresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $this->assertTrue((bool) data_get($meta, 'sabre_checkout_outcome.mandatory_data_missing'));
    }

    public function test_ticketing_remains_disabled_with_revalidation_enabled(): void
    {
        config([
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.revalidate_before_booking' => true,
        ]);
        $this->assertFalse((bool) config('suppliers.sabre.ticketing_enabled'));
        $this->assertTrue((bool) config('suppliers.sabre.revalidate_before_booking'));
    }

    public function test_duffel_review_unaffected_when_revalidation_enabled(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.revalidate_before_booking' => true,
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $depart = now()->addDays(10)->toDateString();
        $offer = [
            'id' => 'duffel-rev-1',
            'supplier_provider' => 'duffel',
            'airline_code' => 'EK',
            'airline_name' => 'Emirates',
            'depart_at' => $depart.'T08:00:00Z',
            'arrive_at' => $depart.'T14:00:00Z',
            'total' => 100000,
            'currency' => 'PKR',
        ];

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Duffel->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Duffel->value,
                'requires_price_change_confirmation' => false,
                'protection_mode' => 'hold_price_guaranteed',
                'flight_offer_snapshot' => $offer,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);
        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'duffel-rev@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);
        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 80000,
            'taxes' => 10000,
            'fees' => 0,
            'markup' => 10000,
            'discount' => 0,
            'total' => 100000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.confirmation'));

        Http::assertNothingSent();
    }

    public function test_inspect_booking_revalidate_command_prints_summary_without_send(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        config([
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => null,
            'suppliers.sabre.revalidate_before_booking' => true,
            'suppliers.sabre.revalidate_path' => '/v4/shop/flights/revalidate',
        ]);
        $booking = $this->seedInspectableSabreBooking();

        Artisan::call('sabre:inspect-booking-revalidate', ['--booking' => (string) $booking->id]);
        $out = Artisan::output();

        $this->assertStringContainsString('payload_summary.segment_count=2', $out);
        $this->assertStringContainsString('payload_summary.payload_style=bfm_revalidate_v1', $out);
        $this->assertStringContainsString('payload_diagnostics.segment_count=2', $out);
        $this->assertStringContainsString('payload_diagnostics.has_ota_air_low_fare_search_rq=true', $out);
        $this->assertStringContainsString('payload_diagnostics.has_number=true', $out);
        $this->assertStringContainsString('payload_summary.has_booking_class=true', $out);
        $this->assertStringContainsString('payload_summary.has_fare_basis=true', $out);
        $this->assertStringContainsString('payload_summary.has_pricing_information_ref=true', $out);
        $this->assertStringContainsString('payload_summary.has_itinerary_reference=true', $out);
        $this->assertStringContainsString('payload_summary.has_offer_reference=true', $out);
        $this->assertStringContainsString('revalidate_before_booking_enabled=true', $out);
        $this->assertStringContainsString('revalidate_path=/v4/shop/flights/revalidate', $out);
        $this->assertStringContainsString('send=false', $out);
        $this->assertStringNotContainsString('Authorization', $out);
    }

    public function test_client_gds_revalidate_v1_uses_revalidate_itinerary_rq_and_segment_fields(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $draft = $this->sampleInternalDraftWithSegments('Y', 'YOWPK', 'K', 'KLITE1');
        $payload = $builder->buildPayload($draft, 'client_gds_revalidate_v1');
        $this->assertSame('client_gds_revalidate_v1', $payload['_ota_revalidate_payload_style'] ?? null);
        $this->assertArrayNotHasKey('OTA_AirLowFareSearchRQ', $payload);
        $this->assertSame('bfm_revalidate_v1', config('suppliers.sabre.revalidate_payload_style'));
        $rq = is_array($payload['RevalidateItineraryRQ'] ?? null) ? $payload['RevalidateItineraryRQ'] : [];
        $this->assertNotSame([], $rq);
        $segs = is_array($rq['FlightSegments'] ?? null) ? $rq['FlightSegments'] : [];
        $this->assertCount(2, $segs);
        $first = $segs[0];
        $this->assertSame('1', (string) ($first['Number'] ?? ''));
        $this->assertNotEmpty($first['DepartureDateTime'] ?? null);
        $this->assertNotEmpty($first['ArrivalDateTime'] ?? null);
        $this->assertSame('Y', $first['ClassOfService'] ?? null);
        $this->assertSame('LHE', data_get($first, 'OriginLocation.LocationCode'));
        $this->assertSame('KHI', data_get($first, 'DestinationLocation.LocationCode'));
        $this->assertSame('PK', data_get($first, 'MarketingAirline.Code'));
        $this->assertSame('PK', data_get($first, 'OperatingAirline.Code'));
        $this->assertSame('303', (string) ($first['FlightNumber'] ?? ''));

        $diag = $builder->structuralPayloadDiagnostics($payload);
        $this->assertTrue($diag['has_revalidate_itinerary']);
        $this->assertFalse($diag['has_ota_air_low_fare_search_rq']);
        $this->assertSame(2, $diag['segment_count']);
        $this->assertTrue($diag['has_number']);
        $this->assertTrue($diag['has_departure_datetime']);
        $this->assertTrue($diag['has_arrival_datetime']);
        $this->assertTrue($diag['has_class_of_service']);
        $this->assertTrue($diag['has_marketing_airline']);
        $this->assertTrue($diag['has_operating_airline']);
    }

    public function test_grouped_itinerary_response_prefers_current_itinerary_when_not_first(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);

        $linkage = $builder->extractFareLinkage([
            'groupedItineraryResponse' => [
                'itineraryGroups' => [
                    [
                        'itineraries' => [
                            $this->groupedItineraryFixture('first-itin', false, 'FIRSTFB', 'F', '111.00', 'USD', 'FIRST-OFFER'),
                            $this->groupedItineraryFixture('current-itin', true, 'CURRFB', 'T', '222.00', 'PKR', 'CURRENT-OFFER'),
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame('current-itin', $linkage['itinerary_reference'] ?? null);
        $this->assertSame('CURRENT-OFFER', $linkage['offer_reference'] ?? null);
        $this->assertSame(['CURRFB'], $linkage['fare_basis_codes'] ?? null);
        $this->assertSame('T', $linkage['class_of_service_first'] ?? null);
        $this->assertSame(222.0, $linkage['revalidated_total'] ?? null);
        $this->assertSame('PKR', $linkage['revalidated_currency'] ?? null);
    }

    public function test_grouped_itinerary_response_falls_back_to_first_itinerary_without_current_flag(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);

        $linkage = $builder->extractFareLinkage([
            'groupedItineraryResponse' => [
                'itineraryGroups' => [
                    [
                        'itineraries' => [
                            $this->groupedItineraryFixture('first-itin', false, 'FIRSTFB', 'F', '111.00', 'USD', 'FIRST-OFFER'),
                            $this->groupedItineraryFixture('second-itin', false, 'SECONDFB', 'S', '222.00', 'PKR', 'SECOND-OFFER'),
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame('first-itin', $linkage['itinerary_reference'] ?? null);
        $this->assertSame('FIRST-OFFER', $linkage['offer_reference'] ?? null);
        $this->assertSame(['FIRSTFB'], $linkage['fare_basis_codes'] ?? null);
        $this->assertSame('F', $linkage['class_of_service_first'] ?? null);
        $this->assertSame(111.0, $linkage['revalidated_total'] ?? null);
        $this->assertSame('USD', $linkage['revalidated_currency'] ?? null);
    }

    public function test_preview_json_redacted_block_excludes_tokens_and_pcc_like_keys(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        config(['suppliers.sabre.revalidate_path' => '/v4/shop/flights/revalidate']);
        $booking = $this->seedInspectableSabreBooking();
        Artisan::call('sabre:inspect-booking-revalidate', [
            '--booking' => (string) $booking->id,
            '--style' => 'client_gds_revalidate_v1',
            '--preview-json' => true,
        ]);
        $out = Artisan::output();
        $this->assertStringContainsString('preview.payload_style=client_gds_revalidate_v1', $out);
        $this->assertStringContainsString('preview.endpoint_path=/v4/shop/flights/revalidate', $out);
        foreach (['Bearer ', 'access_token', 'Authorization', 'passport', 'DateOfBirth'] as $needle) {
            $this->assertStringNotContainsString($needle, $out, 'preview must not leak: '.$needle);
        }
        $this->assertStringContainsString('payload_diagnostics.has_pcc=', $out);
        if (preg_match('/preview\.redacted_json_begin\r?\n(.*)\r?\npreview\.redacted_json_end/s', $out, $m) === 1) {
            $jsonBlock = $m[1];
            foreach (['PseudoCityCode', 'pcc', 'PseudoCity'] as $needle) {
                $this->assertStringNotContainsString($needle, $jsonBlock, 'redacted wire JSON must not leak: '.$needle);
            }
        } else {
            $this->fail('Expected preview.redacted_json_begin/end block in inspect output.');
        }
    }

    public function test_write_preview_writes_sanitized_document_without_ota_envelope_keys(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $booking = $this->seedInspectableSabreBooking();
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sabre-revalidate-preview-test-'.uniqid('', true).'.json';
        Artisan::call('sabre:inspect-booking-revalidate', [
            '--booking' => (string) $booking->id,
            '--style' => 'client_gds_revalidate_v1',
            '--write-preview' => $path,
        ]);
        $this->assertFileExists($path);
        $raw = (string) file_get_contents($path);
        foreach (['_ota_provider', '_ota_payload_schema', '_ota_revalidate_payload_style'] as $needle) {
            $this->assertStringNotContainsString($needle, $raw, 'wire export must omit internal '.$needle);
        }
        $this->assertStringNotContainsString('access_token', $raw);
        $decoded = json_decode($raw, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('meta', $decoded);
        $this->assertArrayHasKey('request_body', $decoded);
        $this->assertArrayHasKey('RevalidateItineraryRQ', $decoded['request_body']);
        @unlink($path);
    }

    public function test_inspect_send_http_200_persists_sabre_revalidate_inspect_meta(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        config(['suppliers.sabre.revalidate_path' => '/v4/shop/flights/revalidate']);
        $sabreBase = rtrim((string) config('suppliers.sabre.default_base_url'), '/');
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');
        $revalidatePath = '/v4/shop/flights/revalidate';

        Http::fake([
            $sabreBase.$tokenPath => Http::response(['access_token' => 'secret-token-do-not-leak', 'expires_in' => 3600], 200),
            $sabreBase.$revalidatePath => Http::response([
                'pricedItineraries' => [
                    [
                        'airItineraryPricingInfo' => [
                            'fareInfos' => [
                                [
                                    'fareBasisCode' => 'YOWPK',
                                    'departureAirport' => 'LHE',
                                    'arrivalAirport' => 'KHI',
                                    'bookingCode' => 'Y',
                                ],
                            ],
                            'validatingCarrier' => 'PK',
                            'itinTotalFare' => [
                                'totalFare' => ['totalPrice' => 400, 'currencyCode' => 'PKR'],
                            ],
                        ],
                    ],
                ],
                'revalidationReference' => 'REVAL-INSPECT-1',
                'ticketingTimeLimit' => '2026-12-31T23:59:00Z',
            ], 200),
        ]);

        $booking = $this->seedInspectableSabreBooking();
        $cid = (int) data_get($booking->meta, 'supplier_connection_id');
        $conn = SupplierConnection::query()->find($cid);
        $this->assertNotNull($conn);
        $conn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        Artisan::call('sabre:inspect-booking-revalidate', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--style' => 'client_gds_revalidate_v1',
        ]);

        $booking->refresh();
        $inspect = data_get($booking->meta, 'sabre_revalidate_inspect');
        $this->assertIsArray($inspect);
        $this->assertSame(200, $inspect['http_status'] ?? null);
        $this->assertTrue((bool) ($inspect['has_revalidated_fare'] ?? false));
        $this->assertTrue((bool) ($inspect['has_revalidated_currency'] ?? false));
        $this->assertTrue((bool) ($inspect['has_revalidation_reference'] ?? false));
        $this->assertTrue((bool) ($inspect['has_ticketing_time_limit'] ?? false));

        Http::assertSent(function ($request) use ($revalidatePath): bool {
            if (! $request instanceof Request || ! str_contains($request->url(), $revalidatePath)) {
                return false;
            }
            $data = json_decode((string) $request->body(), true);

            return is_array($data) && ! array_key_exists('_ota_provider', $data) && array_key_exists('RevalidateItineraryRQ', $data);
        });
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    protected function twoSegmentRevalidateSuccessBody(string $revalidationReference, float $totalPrice): array
    {
        return [
            'pricedItineraries' => [
                [
                    'airItineraryPricingInfo' => [
                        'fareInfos' => [
                            [
                                'fareBasisCode' => 'YOWPK',
                                'departureAirport' => 'LHE',
                                'arrivalAirport' => 'KHI',
                                'bookingCode' => 'Y',
                            ],
                            [
                                'fareBasisCode' => 'KLITE1',
                                'departureAirport' => 'KHI',
                                'arrivalAirport' => 'DXB',
                                'bookingCode' => 'K',
                            ],
                        ],
                        'validatingCarrier' => 'PK',
                        'itinTotalFare' => [
                            'totalFare' => ['totalPrice' => $totalPrice, 'currencyCode' => 'PKR'],
                        ],
                    ],
                ],
            ],
            'revalidationReference' => $revalidationReference,
        ];
    }

    protected function sampleInternalDraftWithSegments(
        string $firstClass,
        string $firstFareBasis,
        string $secondClass,
        string $secondFareBasis,
    ): array {
        $depart = '2026-08-15';

        return [
            'provider' => SupplierProvider::Sabre->value,
            'selected_offer_id' => 'offer-rev-1',
            'supplier_connection_id' => 0,
            'supplier_offer_id' => 'offer-rev-1',
            'validating_carrier' => 'PK',
            'fare_family' => null,
            'fare' => [
                'amount' => 320.50,
                'currency' => 'PKR',
                'base_fare' => 280.00,
                'taxes' => 40.50,
            ],
            'baggage_summary' => '1PC',
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'KHI',
                    'departure_at' => $depart.'T05:00:00',
                    'arrival_at' => $depart.'T06:45:00',
                    'carrier' => 'PK',
                    'operating_airline_code' => 'PK',
                    'flight_number' => '303',
                    'booking_class' => $firstClass,
                    'fare_basis_code' => $firstFareBasis,
                    'segment_cabin_code' => 'Y',
                ],
                [
                    'origin' => 'KHI',
                    'destination' => 'DXB',
                    'departure_at' => $depart.'T10:00:00',
                    'arrival_at' => $depart.'T11:30:00',
                    'carrier' => 'EK',
                    'operating_airline_code' => 'EK',
                    'flight_number' => '601',
                    'booking_class' => $secondClass,
                    'fare_basis_code' => $secondFareBasis,
                    'segment_cabin_code' => 'Y',
                ],
            ],
            'passengers' => [
                ['type' => 'ADT', 'first_name' => 'A', 'last_name' => 'B'],
            ],
            'contact' => ['email' => '', 'phone' => ''],
            '_sabre_shop_identifiers' => [
                'itinerary_id' => 'itin-x',
                'pricing_0_offerItemId' => 'offer-ref-99',
            ],
        ];
    }

    protected function groupedItineraryFixture(
        string $id,
        bool $current,
        string $fareBasis,
        string $bookingClass,
        string $total,
        string $currency,
        string $offerRef,
    ): array {
        return [
            'id' => $id,
            'currentItinerary' => $current,
            'pricingInformation' => [
                [
                    'offerItemRef' => $offerRef,
                    'priceQuoteReference' => 'pq-'.$id,
                    'fare' => [
                        'validatingCarrierCode' => 'EK',
                        'totalFare' => [
                            'totalPrice' => $total,
                            'currencyCode' => $currency,
                        ],
                        'passengerInfoList' => [
                            [
                                'passengerInfo' => [
                                    'fareComponents' => [
                                        [
                                            'fareBasisCode' => $fareBasis,
                                            'segments' => [
                                                [
                                                    'segment' => [
                                                        'bookingCode' => $bookingClass,
                                                        'fareBasisCode' => $fareBasis,
                                                        'departure' => ['locationCode' => 'LHE'],
                                                        'arrival' => ['locationCode' => 'DXB'],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function sabreIntlPassengerPassportFields(): array
    {
        return [
            'passport_number' => 'AB9999999',
            'passport_issuing_country' => 'PK',
            'passport_expiry_date' => '2035-12-31',
            'nationality' => 'PK',
            'document_type' => 'passport',
        ];
    }

    protected function seedLiveSabreBooking(string $email): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $depart = now()->addDays(10)->toDateString();
        $offer = [
            'id' => 'sabre-rev-offer-'.uniqid(),
            'supplier_provider' => 'sabre',
            'airline_code' => 'EK',
            'airline_name' => 'Emirates',
            'depart_at' => $depart.'T08:00:00Z',
            'arrive_at' => $depart.'T14:00:00Z',
            'total' => 100000,
            'currency' => 'PKR',
            'supplier_offer_id' => 'sabre-rev-live-offer',
            'fare_breakdown' => [
                'supplier_total' => 100000,
                'currency' => 'PKR',
                'base_fare' => 80000,
                'taxes' => 20000,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'KHI',
                    'departure_at' => $depart.'T05:00:00',
                    'arrival_at' => $depart.'T06:45:00',
                    'carrier' => 'PK',
                    'operating_airline_code' => 'PK',
                    'flight_number' => '303',
                    'booking_class' => 'Y',
                    'fare_basis_code' => 'YOWPK',
                ],
                [
                    'origin' => 'KHI',
                    'destination' => 'DXB',
                    'departure_at' => $depart.'T10:00:00',
                    'arrival_at' => $depart.'T11:30:00',
                    'carrier' => 'EK',
                    'operating_airline_code' => 'EK',
                    'flight_number' => '601',
                    'booking_class' => 'K',
                    'fare_basis_code' => 'KLITE1',
                ],
            ],
            'raw_payload' => [
                'sabre_shop_context' => [
                    'requested' => ['passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0]],
                ],
                'sabre_shop_identifiers' => [
                    'itinerary_id' => 'itin-live-rev',
                    'pricing_0_offerItemId' => 'offer-ref-live',
                ],
            ],
        ];

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $sabreConn->id,
                'requires_price_change_confirmation' => false,
                'protection_mode' => 'hold_price_guaranteed',
                'flight_offer_snapshot' => $offer,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingPassenger::factory()->create(array_merge([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ], $this->sabreIntlPassengerPassportFields()));

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => $email,
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 80000,
            'taxes' => 10000,
            'fees' => 0,
            'markup' => 10000,
            'discount' => 0,
            'total' => 100000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        return $booking;
    }

    protected function seedInspectableSabreBooking(): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();

        $depart = now()->addDays(12)->toDateString();
        $snapshot = [
            'offer_id' => 'inspect-rev-1',
            'supplier_offer_id' => 'inspect-rev-1',
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $sabreConn->id,
            'airline_code' => 'PK',
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'KHI',
                    'departure_at' => $depart.'T05:00:00',
                    'arrival_at' => $depart.'T06:45:00',
                    'carrier' => 'PK',
                    'flight_number' => '303',
                    'booking_class' => 'Y',
                    'fare_basis_code' => 'YOWPK',
                ],
                [
                    'origin' => 'KHI',
                    'destination' => 'DXB',
                    'departure_at' => $depart.'T22:00:00',
                    'arrival_at' => $depart.'T23:30:00',
                    'carrier' => 'EK',
                    'flight_number' => '601',
                    'booking_class' => 'K',
                    'fare_basis_code' => 'KLITE1',
                ],
            ],
            'fare_breakdown' => [
                'supplier_total' => 150000,
                'currency' => 'PKR',
                'base_fare' => 120000,
                'taxes' => 30000,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
            'baggage' => ['summary' => '1PC'],
            'raw_payload' => [
                'sabre_shop_context' => [
                    'leg_refs' => [1],
                    'itinerary_ref' => 'itin-merge-1',
                ],
                'sabre_shop_identifiers' => [
                    'itinerary_id' => 'itin-x',
                    'pricing_0_ref' => 'pi-ref-merge-77',
                    'pricing_0_offerItemId' => 'offer-ref-99',
                    'fare_basis_first' => 'YOWPK',
                ],
            ],
        ];

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $sabreConn->id,
                'normalized_offer_snapshot' => $snapshot,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => $depart,
                    'trip_type' => 'one_way',
                    'cabin' => 'economy',
                    'adults' => 1,
                    'children' => 0,
                    'infants' => 0,
                ],
            ],
        ]);

        BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'rev-inspect@example.com',
            'phone' => '+923001234567',
            'country' => 'Pakistan',
            'address_line' => null,
            'meta' => [],
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 120000,
            'taxes' => 30000,
            'fees' => 0,
            'markup' => 0,
            'discount' => 0,
            'total' => 150000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        return $booking;
    }

    public function test_access_result_for_probe_maps_http_status_and_timeout(): void
    {
        $this->assertSame('timeout', SabreCheckRevalidateEndpointsCommand::accessResultForProbe(0, true));
        $this->assertSame('unknown', SabreCheckRevalidateEndpointsCommand::accessResultForProbe(0, false));
        $this->assertSame('ready', SabreCheckRevalidateEndpointsCommand::accessResultForProbe(200, false));
        $this->assertSame('reachable_validation_error', SabreCheckRevalidateEndpointsCommand::accessResultForProbe(400, false));
        $this->assertSame('reachable_validation_error', SabreCheckRevalidateEndpointsCommand::accessResultForProbe(422, false));
        $this->assertSame('forbidden', SabreCheckRevalidateEndpointsCommand::accessResultForProbe(403, false));
        $this->assertSame('not_found', SabreCheckRevalidateEndpointsCommand::accessResultForProbe(404, false));
        $this->assertSame('method_not_allowed', SabreCheckRevalidateEndpointsCommand::accessResultForProbe(405, false));
        $this->assertSame('unknown', SabreCheckRevalidateEndpointsCommand::accessResultForProbe(500, false));
    }

    public function test_check_revalidate_endpoints_maps_400_to_reachable_validation_error_and_hides_token(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $conn->update([
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $sabreBase = rtrim((string) config('suppliers.sabre.default_base_url'), '/');
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');

        Http::fake(function (Request $request) use ($tokenPath) {
            $u = $request->url();
            if (str_contains($u, $tokenPath)) {
                return Http::response(['access_token' => 'NEVER_PRINT_THIS_ACCESS_TOKEN_VALUE', 'expires_in' => 3600], 200);
            }
            $path = (string) (parse_url($u, PHP_URL_PATH) ?: '');
            if ($path === '/v4/shop/flights/revalidate') {
                return Http::response(['errors' => [['code' => '27131', 'title' => 'probe']]], 400);
            }

            return Http::response([], 404);
        });

        Artisan::call('sabre:check-revalidate-endpoints', ['--connection' => (string) $conn->id]);
        $out = Artisan::output();
        $this->assertStringContainsString('access_result=reachable_validation_error', $out);
        $this->assertStringContainsString('http_status=400', $out);
        $this->assertStringNotContainsString('NEVER_PRINT_THIS_ACCESS_TOKEN_VALUE', $out);
    }

    public function test_inspect_booking_revalidate_send_with_path_override_posts_to_path_without_changing_config(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $sabreBase = rtrim((string) config('suppliers.sabre.default_base_url'), '/');
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');
        $customPath = '/v4/offers/shop';

        Http::fake([
            $sabreBase.$tokenPath => Http::response(['access_token' => 'do-not-leak-token', 'expires_in' => 3600], 200),
            $sabreBase.$customPath => Http::response(['errors' => [['code' => 'X1', 'detail' => 'bad']]], 400),
        ]);

        $beforePath = (string) config('suppliers.sabre.revalidate_path');
        $booking = $this->seedInspectableSabreBooking();
        $cid = (int) data_get($booking->meta, 'supplier_connection_id');
        $conn = SupplierConnection::query()->find($cid);
        $this->assertNotNull($conn);
        $conn->update([
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        Artisan::call('sabre:inspect-booking-revalidate', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--path' => $customPath,
            '--style' => 'bfm_revalidate_v1',
        ]);

        $this->assertSame($beforePath, config('suppliers.sabre.revalidate_path'));
        $out = Artisan::output();
        $this->assertStringContainsString('diag.endpoint_path='.$customPath, $out);
        $this->assertStringContainsString('diag.root_keys=', $out);
        $this->assertStringNotContainsString('do-not-leak-token', $out);

        Http::assertSent(function ($request) use ($customPath): bool {
            return $request instanceof Request && str_contains($request->url(), $customPath);
        });
    }

    public function test_inspect_booking_revalidate_contract_hint_on_offers_shop_client_gds_400(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $sabreBase = rtrim((string) config('suppliers.sabre.default_base_url'), '/');
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');
        $shopPath = '/v4/offers/shop';

        Http::fake([
            $sabreBase.$tokenPath => Http::response(['access_token' => 'hidden', 'expires_in' => 3600], 200),
            $sabreBase.$shopPath => Http::response(['errors' => [['code' => '999', 'title' => 'bad']]], 400),
        ]);

        $booking = $this->seedInspectableSabreBooking();
        $cid = (int) data_get($booking->meta, 'supplier_connection_id');
        $conn = SupplierConnection::query()->find($cid);
        $this->assertNotNull($conn);
        $conn->update([
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        Artisan::call('sabre:inspect-booking-revalidate', [
            '--booking' => (string) $booking->id,
            '--send' => true,
            '--path' => $shopPath,
            '--style' => 'client_gds_revalidate_v1',
        ]);

        $out = Artisan::output();
        $this->assertStringContainsString('contract_hint=Revalidation contract may require OTA_AirLowFareSearchRQ-style body or shop replay, not RevalidateItineraryRQ.', $out);
    }

    public function test_compare_revalidate_styles_output_hides_access_token_and_lists_matrix_paths(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');

        Http::fake(function (Request $request) use ($tokenPath) {
            $u = $request->url();
            if (str_contains($u, $tokenPath)) {
                return Http::response(['access_token' => 'NEVER_LEAK_COMPARE_MATRIX_TOKEN', 'expires_in' => 3600], 200);
            }
            $path = (string) (parse_url($u, PHP_URL_PATH) ?: '');

            return match ($path) {
                '/v4/offers/shop/revalidate', '/v5/offers/shop/revalidate', '/v4/shop/flights/revalidate' => Http::response(
                    ['errors' => [['code' => '27131', 'title' => 'probe']]],
                    400,
                ),
                default => Http::response([], 404),
            };
        });

        $booking = $this->seedInspectableSabreBooking();
        $cid = (int) data_get($booking->meta, 'supplier_connection_id');
        $conn = SupplierConnection::query()->find($cid);
        $this->assertNotNull($conn);
        $conn->update([
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        Artisan::call('sabre:compare-revalidate-styles', ['--booking' => (string) $booking->id]);
        $out = Artisan::output();
        $this->assertStringNotContainsString('NEVER_LEAK_COMPARE_MATRIX_TOKEN', $out);
        $this->assertStringNotContainsString('cid', $out);
        $this->assertStringNotContainsString('sec', $out);
        foreach (SabreCompareRevalidateStylesCommand::MATRIX_PATHS as $p) {
            $this->assertStringContainsString($p, $out);
        }
    }

    public function test_compare_revalidate_styles_prints_recommended_path_when_offers_v4_beats_bfm_baseline(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $sabreBase = rtrim((string) config('suppliers.sabre.default_base_url'), '/');
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');
        $okBody = $this->twoSegmentRevalidateSuccessBody('REVAL-CMP-1', 150000);

        Http::fake(function (Request $request) use ($tokenPath, $okBody) {
            $u = $request->url();
            if (str_contains($u, $tokenPath)) {
                return Http::response(['access_token' => 'token-x', 'expires_in' => 3600], 200);
            }
            $path = (string) (parse_url($u, PHP_URL_PATH) ?: '');

            return match ($path) {
                '/v4/offers/shop/revalidate' => Http::response($okBody, 200),
                '/v5/offers/shop/revalidate' => Http::response(['errors' => [['code' => '27131']]], 400),
                '/v4/shop/flights/revalidate' => Http::response(['errors' => [['code' => '27131']]], 400),
                default => Http::response([], 404),
            };
        });

        $booking = $this->seedInspectableSabreBooking();
        $cid = (int) data_get($booking->meta, 'supplier_connection_id');
        $conn = SupplierConnection::query()->find($cid);
        $this->assertNotNull($conn);
        $conn->update([
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        Artisan::call('sabre:compare-revalidate-styles', ['--booking' => (string) $booking->id]);
        $out = Artisan::output();
        $this->assertStringContainsString('recommended_revalidate_path=/v4/offers/shop/revalidate', $out);
        $this->assertStringNotContainsString('token-x', $out);
    }

    public function test_compare_revalidate_styles_omits_recommendation_when_baseline_bfm_strictly_better(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');
        $okBody = $this->twoSegmentRevalidateSuccessBody('REVAL-BFM-WINS', 150000);

        Http::fake(function (Request $request) use ($tokenPath, $okBody) {
            $u = $request->url();
            if (str_contains($u, $tokenPath)) {
                return Http::response(['access_token' => 'token-bfm', 'expires_in' => 3600], 200);
            }
            $path = (string) (parse_url($u, PHP_URL_PATH) ?: '');

            return match ($path) {
                '/v4/offers/shop/revalidate', '/v5/offers/shop/revalidate' => Http::response(
                    ['errors' => [['code' => '27131']]], 400,
                ),
                '/v4/shop/flights/revalidate' => Http::response($okBody, 200),
                default => Http::response([], 404),
            };
        });

        $booking = $this->seedInspectableSabreBooking();
        $cid = (int) data_get($booking->meta, 'supplier_connection_id');
        $conn = SupplierConnection::query()->find($cid);
        $this->assertNotNull($conn);
        $conn->update([
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        Artisan::call('sabre:compare-revalidate-styles', ['--booking' => (string) $booking->id]);
        $out = Artisan::output();
        $this->assertStringContainsString('recommended_revalidate_path=(none', $out);
        $this->assertStringNotContainsString('recommended_revalidate_path=/v4/offers/shop/revalidate', $out);
    }

    public function test_revalidation_http_200_empty_skips_create_booking_and_records_reason(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $sabreBase = rtrim((string) config('suppliers.sabre.default_base_url'), '/');
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');
        $bookingPath = (string) config('suppliers.sabre.booking_path', '/v1/trip/orders/createBooking');
        $bookingPath = $bookingPath !== '' && $bookingPath[0] === '/' ? $bookingPath : '/'.$bookingPath;
        $revalidatePath = '/v4/offers/shop/revalidate';

        Http::fake([
            $sabreBase.$tokenPath => Http::response(['access_token' => 'tok-empty-body', 'expires_in' => 3600], 200),
            $sabreBase.$revalidatePath => Http::response([], 200),
            $sabreBase.$bookingPath => Http::response(['recordLocator' => 'SHOULD_NOT_BE_CALLED'], 200),
        ]);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking',
            'suppliers.sabre.booking_schema' => null,
            'suppliers.sabre.revalidate_before_booking' => true,
            'suppliers.sabre.revalidate_path' => $revalidatePath,
        ]);

        $booking = $this->seedLiveSabreBooking('revalidate-empty-200@example.com');

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->post(route('booking.review'), ['booking_method' => 'pay_later']);

        $attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();
        $this->assertNotNull($attempt);
        $this->assertSame('failed', $attempt->status);
        $this->assertSame('sabre_revalidation_failed', $attempt->error_code);
        $summary = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $this->assertSame('sabre_revalidation_empty_or_unusable_response', $summary['revalidation_reason_code'] ?? null);

        Http::assertNotSent(function ($request) use ($bookingPath): bool {
            return $request instanceof Request && str_contains($request->url(), $bookingPath);
        });
        $summaryJson = json_encode($summary);
        $this->assertIsString($summaryJson);
        $this->assertStringNotContainsString('tok-empty-body', $summaryJson);
    }

    public function test_gatekeeper_blocks_payload_missing_booking_class_before_http(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $draft = $this->sampleInternalDraftWithSegments('Y', 'YOWPK', 'K', 'KLITE1');
        $draft['segments'][1]['booking_class'] = '';
        $payload = $builder->buildPayload($draft);

        $this->expectException(SabreRevalidateGatekeeperException::class);
        $builder->assertGatekeeperOrThrow($payload, $draft);
    }

    public function test_evaluate_grouped_itinerary_messages_fails_on_mip_5053_http_200(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $failure = $builder->evaluateGroupedItineraryMessages([
            'groupedItineraryResponse' => [
                'messages' => [[
                    'severity' => 'Error',
                    'type' => 'MIP',
                    'code' => '5053',
                    'text' => 'NO COMBINABLE FARES FOR CLASS USED',
                ]],
            ],
        ]);

        $this->assertNotNull($failure);
        $this->assertTrue($failure['failed']);
        $this->assertSame('mip_5053', $failure['failure_class']);
    }

    public function test_run_revalidation_http_200_mip_5053_short_circuits_success(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $sabreBase = rtrim((string) config('suppliers.sabre.default_base_url'), '/');
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');
        $revalidatePath = '/v4/shop/flights/revalidate';

        Http::fake([
            $sabreBase.$tokenPath => Http::response(['access_token' => 'tok-mip', 'expires_in' => 3600], 200),
            $sabreBase.$revalidatePath => Http::response([
                'groupedItineraryResponse' => [
                    'messages' => [[
                        'severity' => 'Error',
                        'type' => 'MIP',
                        'code' => '5053',
                        'text' => 'NO FARES',
                    ]],
                ],
            ], 200),
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Sabre->value)
            ->firstOrFail();
        $conn->update([
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec', 'pcc' => 'TEST'],
        ]);

        $sabre = $this->app->make(SabreBookingService::class);
        $draft = $this->sampleInternalDraftWithSegments('Y', 'YOWPK', 'K', 'KLITE1');
        $draft['supplier_connection_id'] = $conn->id;
        $out = $sabre->runRevalidationBeforeBooking(
            $draft,
            $conn,
            null,
            $revalidatePath,
        );

        $this->assertFalse((bool) ($out['success'] ?? false));
        $this->assertSame('sabre_revalidation_application_warning_or_error', $out['reason_code'] ?? null);
        $this->assertSame('mip_5053', $out['revalidation_failure_class'] ?? null);
    }

    public function test_run_revalidation_http_200_partial_fare_basis_fails_tripwire(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $sabreBase = rtrim((string) config('suppliers.sabre.default_base_url'), '/');
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');
        $revalidatePath = '/v4/shop/flights/revalidate';

        Http::fake([
            $sabreBase.$tokenPath => Http::response(['access_token' => 'tok-fb', 'expires_in' => 3600], 200),
            $sabreBase.$revalidatePath => Http::response([
                'pricedItineraries' => [
                    [
                        'airItineraryPricingInfo' => [
                            'fareInfos' => [
                                [
                                    'fareBasisCode' => 'YOWPK',
                                    'departureAirport' => 'LHE',
                                    'arrivalAirport' => 'KHI',
                                    'bookingCode' => 'Y',
                                ],
                            ],
                            'validatingCarrier' => 'PK',
                            'itinTotalFare' => [
                                'totalFare' => ['totalPrice' => 320.50, 'currencyCode' => 'PKR'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Sabre->value)
            ->firstOrFail();
        $conn->update([
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec', 'pcc' => 'TEST'],
        ]);

        $sabre = $this->app->make(SabreBookingService::class);
        $draft = $this->sampleInternalDraftWithSegments('Y', 'YOWPK', 'K', 'KLITE1');
        $draft['supplier_connection_id'] = $conn->id;
        $out = $sabre->runRevalidationBeforeBooking(
            $draft,
            $conn,
            null,
            $revalidatePath,
        );

        $this->assertFalse((bool) ($out['success'] ?? false));
        $this->assertSame('sabre_revalidation_empty_or_unusable_response', $out['reason_code'] ?? null);
        $this->assertSame('fare_basis_incomplete', $out['revalidation_failure_class'] ?? null);
    }

    public function test_run_revalidation_http_200_warnings_yield_application_warning_reason(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $sabreBase = rtrim((string) config('suppliers.sabre.default_base_url'), '/');
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');
        $revalidatePath = '/v4/offers/shop/revalidate';

        Http::fake([
            $sabreBase.$tokenPath => Http::response(['access_token' => 'tok-warn', 'expires_in' => 3600], 200),
            $sabreBase.$revalidatePath => Http::response([
                'warnings' => [
                    [
                        'code' => 'APP-BLOCK',
                        'severity' => 'WARNING',
                        'title' => 'NO FARES FOR REQUESTED ITINERARY',
                        'detail' => 'NO FARES',
                    ],
                ],
            ], 200),
        ]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Sabre->value)
            ->firstOrFail();
        $conn->update([
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec', 'pcc' => 'TEST'],
        ]);

        $sabre = $this->app->make(SabreBookingService::class);
        $draft = $this->sampleInternalDraftWithSegments('Y', 'YOWPK', 'K', 'KLITE1');
        $draft['supplier_connection_id'] = $conn->id;
        $out = $sabre->runRevalidationBeforeBooking(
            $draft,
            $conn,
            null,
            $revalidatePath,
        );

        $this->assertFalse((bool) ($out['success'] ?? false));
        $this->assertSame('sabre_revalidation_application_warning_or_error', $out['reason_code'] ?? null);
        $this->assertNotSame([], $out['error_digest'] ?? []);
    }

    public function test_extract_fare_linkage_nested_data_total_fare_and_result_offer_id(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $linkage = $builder->extractFareLinkage([
            'data' => [
                'totalFare' => ['totalPrice' => 210.5, 'currency' => 'EUR'],
            ],
            'result' => [
                'offer' => ['id' => 'OFFER-DEEP-1'],
            ],
        ]);
        $this->assertSame(210.5, $linkage['revalidated_total'] ?? null);
        $this->assertSame('EUR', $linkage['revalidated_currency'] ?? null);
        $this->assertSame('OFFER-DEEP-1', $linkage['offer_reference'] ?? null);
        $digest = $builder->linkageDigest($linkage);
        $this->assertTrue($digest['has_revalidated_fare']);
        $this->assertTrue($digest['has_revalidated_currency']);
        $this->assertTrue($digest['has_offer_reference']);
    }

    public function test_digest_revalidate_response_structure_omits_risky_scalar_paths(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $raw = (string) json_encode([
            'totalPrice' => 88,
            'currency' => 'USD',
            'access_token' => 'must-not-appear-in-digest-output',
            'nested' => ['email' => 'no-leak@example.com', 'totalPrice' => 1],
        ]);
        $digest = $builder->digestRevalidateResponseStructure($raw, json_decode($raw, true));
        $blob = json_encode($digest);
        $this->assertIsString($blob);
        $this->assertStringNotContainsString('must-not-appear', $blob);
        $this->assertStringNotContainsString('no-leak@', $blob);
        $this->assertNotSame(0, (int) ($digest['candidate_count'] ?? 0));
    }

    public function test_compare_revalidate_styles_show_response_digest_hides_access_token(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');

        Http::fake(function (Request $request) use ($tokenPath) {
            $u = $request->url();
            if (str_contains($u, $tokenPath)) {
                return Http::response(['access_token' => 'NEVER_LEAK_DIGEST_MATRIX_TOKEN', 'expires_in' => 3600], 200);
            }
            $path = (string) (parse_url($u, PHP_URL_PATH) ?: '');

            return match ($path) {
                '/v4/offers/shop/revalidate', '/v5/offers/shop/revalidate', '/v4/shop/flights/revalidate' => Http::response(
                    ['meta' => ['version' => 1], 'totalPrice' => 42, 'currency' => 'USD'],
                    200,
                ),
                default => Http::response([], 404),
            };
        });

        $booking = $this->seedInspectableSabreBooking();
        $cid = (int) data_get($booking->meta, 'supplier_connection_id');
        $conn = SupplierConnection::query()->find($cid);
        $this->assertNotNull($conn);
        $conn->update([
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        Artisan::call('sabre:compare-revalidate-styles', [
            '--booking' => (string) $booking->id,
            '--show-response-digest' => true,
        ]);
        $out = Artisan::output();
        $this->assertStringNotContainsString('NEVER_LEAK_DIGEST_MATRIX_TOKEN', $out);
        $this->assertStringContainsString('response_top_level_keys', $out);
        $this->assertStringContainsString('candidate_count', $out);
        $this->assertStringContainsString('response_body_empty', $out);
    }

    public function test_b22_revalidation_payload_shop_replay_and_client_gds_variants(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $internalDraft = $this->sampleInternalDraftWithSegments('Y', 'YOWPK', 'K', 'KLITE1');

        $shopReplay = $builder->wireableRequestPayload($builder->buildPayload($internalDraft, 'shop_replay_selected_itinerary_v1'));
        $this->assertArrayHasKey('OTA_AirLowFareSearchRQ', $shopReplay);
        $this->assertArrayNotHasKey('shop_context', $shopReplay);
        $this->assertArrayNotHasKey('itinerary', $shopReplay);
        $this->assertSame('shop_replay_selected_itinerary_v1', data_get($builder->buildPayload($internalDraft, 'shop_replay_selected_itinerary_v1'), '_ota_revalidate_payload_style'));

        $noPos = $builder->wireableRequestPayload($builder->buildPayload($internalDraft, 'client_gds_revalidate_without_pos'));
        $this->assertArrayNotHasKey('POS', $noPos['RevalidateItineraryRQ'] ?? []);

        $noTp = $builder->wireableRequestPayload($builder->buildPayload($internalDraft, 'client_gds_revalidate_without_travel_preferences'));
        $this->assertArrayNotHasKey('TravelPreferences', $noTp['RevalidateItineraryRQ'] ?? []);

        $segOnly = $builder->wireableRequestPayload($builder->buildPayload($internalDraft, 'client_gds_revalidate_segments_only'));
        $rq = $segOnly['RevalidateItineraryRQ'] ?? [];
        $this->assertArrayHasKey('FlightSegments', $rq);
        $this->assertArrayHasKey('PassengerCounts', $rq);
        $this->assertArrayNotHasKey('TravelerInfoSummary', $rq);
    }

    public function test_compare_revalidate_endpoints_respects_max_calls(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');

        Http::fake(function (Request $request) use ($tokenPath) {
            $u = $request->url();
            if (str_contains($u, $tokenPath)) {
                return Http::response(['access_token' => 'NEVER_LEAK_MATRIX_TOKEN', 'expires_in' => 3600], 200);
            }

            return Http::response([], 200);
        });

        $booking = $this->seedInspectableSabreBooking();
        $cid = (int) data_get($booking->meta, 'supplier_connection_id');
        $conn = SupplierConnection::query()->find($cid);
        $this->assertNotNull($conn);
        $conn->update([
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        Artisan::call('sabre:compare-revalidate-endpoints', [
            '--booking' => (string) $booking->id,
            '--paths' => '/v4/offers/shop/revalidate',
            '--styles' => 'bfm_revalidate_v1,client_gds_revalidate_v1,bfm_revalidate_minimal_segments',
            '--max-calls' => '1',
        ]);

        $recorded = Http::recorded();
        $pairs = $recorded instanceof Collection ? $recorded->all() : (array) $recorded;
        $revalidatePosts = array_values(array_filter($pairs, static function (array $pair): bool {
            /** @var Request $req */
            $req = $pair[0];

            return $req instanceof Request
                && $req->method() === 'POST'
                && ! str_contains($req->url(), 'auth/token')
                && ! str_contains($req->url(), 'oauth')
                && str_contains($req->url(), 'revalidate');
        }));
        $this->assertCount(1, $revalidatePosts);
    }

    public function test_compare_revalidate_endpoints_recommends_best_scoring_row(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');
        $okBody = [
            'pricedItineraries' => [
                [
                    'airItineraryPricingInfo' => [
                        'fareInfos' => [
                            [
                                'fareBasisCode' => 'YOWPK',
                                'departureAirport' => 'LHE',
                                'arrivalAirport' => 'KHI',
                                'bookingCode' => 'Y',
                            ],
                        ],
                        'validatingCarrier' => 'PK',
                        'itinTotalFare' => [
                            'totalFare' => ['totalPrice' => 400, 'currencyCode' => 'PKR'],
                        ],
                    ],
                ],
            ],
            'revalidationReference' => 'REVAL-MX-1',
        ];

        Http::fake(function (Request $request) use ($tokenPath, $okBody) {
            $u = $request->url();
            if (str_contains($u, $tokenPath)) {
                return Http::response(['access_token' => 'tok-matrix', 'expires_in' => 3600], 200);
            }
            $path = (string) (parse_url($u, PHP_URL_PATH) ?: '');

            return match ($path) {
                '/v4/offers/shop/revalidate' => Http::response($okBody, 200),
                '/v4/shop/flights/revalidate' => Http::response([], 200),
                default => Http::response([], 404),
            };
        });

        $booking = $this->seedInspectableSabreBooking();
        $cid = (int) data_get($booking->meta, 'supplier_connection_id');
        $conn = SupplierConnection::query()->find($cid);
        $this->assertNotNull($conn);
        $conn->update([
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        Artisan::call('sabre:compare-revalidate-endpoints', [
            '--booking' => (string) $booking->id,
            '--paths' => '/v4/offers/shop/revalidate,/v4/shop/flights/revalidate',
            '--styles' => 'bfm_revalidate_v1',
            '--max-calls' => '10',
        ]);
        $out = Artisan::output();
        $this->assertStringContainsString('recommended_revalidate_path=/v4/offers/shop/revalidate', $out);
        $this->assertStringContainsString('recommended_revalidate_style=bfm_revalidate_v1', $out);
        $this->assertStringNotContainsString('tok-matrix', $out);
    }

    public function test_compare_revalidate_endpoints_does_not_recommend_empty_200_only_matrix(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');

        Http::fake(function (Request $request) use ($tokenPath) {
            $u = $request->url();
            if (str_contains($u, $tokenPath)) {
                return Http::response(['access_token' => 'tok-empty', 'expires_in' => 3600], 200);
            }

            return Http::response([], 200);
        });

        $booking = $this->seedInspectableSabreBooking();
        $cid = (int) data_get($booking->meta, 'supplier_connection_id');
        $conn = SupplierConnection::query()->find($cid);
        $this->assertNotNull($conn);
        $conn->update([
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        Artisan::call('sabre:compare-revalidate-endpoints', [
            '--booking' => (string) $booking->id,
            '--paths' => '/v4/offers/shop/revalidate',
            '--styles' => 'bfm_revalidate_v1',
            '--max-calls' => '5',
        ]);
        $out = Artisan::output();
        $this->assertStringContainsString('recommended_revalidate_path=(none — no usable revalidation response)', $out);
        $this->assertStringContainsString('payload_result', $out);
    }

    public function test_compare_revalidate_endpoints_json_report_has_no_access_token(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');

        Http::fake(function (Request $request) use ($tokenPath) {
            $u = $request->url();
            if (str_contains($u, $tokenPath)) {
                return Http::response(['access_token' => 'NEVER_IN_JSON_REPORT', 'expires_in' => 3600], 200);
            }

            return Http::response(['errors' => [['code' => '27131', 'title' => 'x']]], 400);
        });

        $booking = $this->seedInspectableSabreBooking();
        $cid = (int) data_get($booking->meta, 'supplier_connection_id');
        $conn = SupplierConnection::query()->find($cid);
        $this->assertNotNull($conn);
        $conn->update([
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $report = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sabre-revalidate-matrix-test-'.uniqid('', true).'.json';
        Artisan::call('sabre:compare-revalidate-endpoints', [
            '--booking' => (string) $booking->id,
            '--paths' => '/v4/shop/flights/revalidate',
            '--styles' => 'bfm_revalidate_v1',
            '--max-calls' => '2',
            '--write-report' => $report,
        ]);
        $this->assertFileExists($report);
        $blob = (string) file_get_contents($report);
        $this->assertStringNotContainsString('NEVER_IN_JSON_REPORT', $blob);
        $decoded = json_decode($blob, true);
        $this->assertIsArray($decoded);
        $this->assertTrue((bool) (($decoded['rows'][0] ?? [])['includes_27131'] ?? false));
        @unlink($report);
    }

    public function test_compare_revalidate_endpoints_never_hits_create_booking_path(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $sabreBase = rtrim((string) config('suppliers.sabre.default_base_url'), '/');
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');
        $bookingPath = (string) config('suppliers.sabre.booking_path', '/v1/trip/orders/createBooking');
        $bookingPath = $bookingPath !== '' && $bookingPath[0] === '/' ? $bookingPath : '/'.$bookingPath;

        Http::fake(function (Request $request) use ($tokenPath) {
            $u = $request->url();
            if (str_contains($u, $tokenPath)) {
                return Http::response(['access_token' => 't', 'expires_in' => 3600], 200);
            }
            if (str_contains($u, $bookingPath)) {
                return Http::response(['recordLocator' => 'NOPE'], 200);
            }

            return Http::response([], 200);
        });

        $booking = $this->seedInspectableSabreBooking();
        $cid = (int) data_get($booking->meta, 'supplier_connection_id');
        $conn = SupplierConnection::query()->find($cid);
        $this->assertNotNull($conn);
        $conn->update([
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        Artisan::call('sabre:compare-revalidate-endpoints', [
            '--booking' => (string) $booking->id,
            '--paths' => '/v4/offers/shop/revalidate',
            '--styles' => 'bfm_revalidate_v1',
            '--max-calls' => '3',
        ]);

        Http::assertNotSent(function ($request) use ($bookingPath): bool {
            return $request instanceof Request && str_contains($request->url(), $bookingPath);
        });
    }

    public function test_compare_revalidate_endpoints_lists_expected_style_catalog(): void
    {
        $this->assertContains('shop_replay_selected_itinerary_v1', SabreCompareRevalidateEndpointsCommand::ALL_STYLES);
        $this->assertContains('client_gds_revalidate_segments_only', SabreCompareRevalidateEndpointsCommand::ALL_STYLES);
    }

    /**
     * @return list<string>
     */
    protected function sensitiveTokens(): array
    {
        return [
            'Authorization', 'Bearer ', 'client_secret',
            'access_token',
        ];
    }
}
