<?php

namespace Tests\Unit;

use App\Enums\SupplierConnectionStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingFareBreakdown;
use App\Models\BookingPassenger;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\Sabre\SabreBookingPayloadBuilder;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Support\Bookings\SabreOfferRefreshAcceptance;
use App\Support\Sabre\GdsPnrCreate\SabreGdsPnrCreateStrategyRegistry;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreBookingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'suppliers.sabre.booking_enabled' => false,
            'suppliers.sabre.booking_live_call_enabled' => false,
        ]);
    }

    protected function tearDown(): void
    {
        config([
            'suppliers.sabre.booking_enabled' => false,
            'suppliers.sabre.booking_live_call_enabled' => false,
        ]);
        parent::tearDown();
    }

    protected function validSabreOffer(): array
    {
        return [
            'supplier_provider' => 'sabre',
            'offer_id' => 'off-1',
            'supplier_offer_id' => 'off-1',
            'supplier_connection_id' => 2,
            'airline_code' => 'EK',
            'flight_number' => 'EK501',
            'segments' => [['origin' => 'LHE', 'destination' => 'DXB', 'departure_at' => '2026-06-01T00:00:00Z', 'arrival_at' => '2026-06-01T06:00:00Z']],
            'fare_breakdown' => [
                'supplier_total' => 100.0,
                'currency' => 'USD',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];
    }

    protected function samplePassengerData(): array
    {
        return [
            'contact' => ['email' => 'lead@example.com', 'phone' => '+10000000000'],
            'passengers' => [
                ['passenger_type' => 'adult', 'first_name' => 'Ada', 'last_name' => 'Lovelace'],
            ],
        ];
    }

    public function test_revalidate_offer_does_not_hit_network_when_booking_disabled(): void
    {
        Http::fake();

        $svc = $this->app->make(SabreBookingService::class);
        $r = $svc->revalidateOffer($this->validSabreOffer());
        $this->assertFalse($r->success);
        $this->assertSame('disabled', $r->status);

        Http::assertNothingSent();
    }

    public function test_revalidate_offer_returns_dry_run_when_booking_enabled_but_live_calls_disabled(): void
    {
        Http::fake();
        config(['suppliers.sabre.booking_enabled' => true, 'suppliers.sabre.booking_live_call_enabled' => false]);

        $svc = $this->app->make(SabreBookingService::class);
        $r = $svc->revalidateOffer($this->validSabreOffer());
        $this->assertFalse($r->success);
        $this->assertSame('dry_run', $r->status);

        Http::assertNothingSent();
    }

    public function test_validate_normalized_offer_rejects_non_sabre_provider(): void
    {
        $svc = $this->app->make(SabreBookingService::class);
        $r = $svc->validateNormalizedSabreOffer([
            'supplier_provider' => 'duffel',
            'supplier_offer_id' => 'x',
            'segments' => [['origin' => 'LHE', 'destination' => 'DXB']],
            'fare_breakdown' => [
                'supplier_total' => 100.0,
                'currency' => 'USD',
                'passenger_counts' => ['adults' => 1],
            ],
        ]);
        $this->assertFalse($r->success);
        $this->assertSame('validation_failed', $r->status);
        $this->assertFalse($svc->canBookOffer([
            'supplier_provider' => 'duffel',
            'supplier_offer_id' => 'x',
            'segments' => [['origin' => 'LHE', 'destination' => 'DXB']],
            'fare_breakdown' => [
                'supplier_total' => 100.0,
                'currency' => 'USD',
                'passenger_counts' => ['adults' => 1],
            ],
        ]));
    }

    public function test_validate_normalized_offer_returns_pending_revalidation_when_valid(): void
    {
        $svc = $this->app->make(SabreBookingService::class);
        $r = $svc->validateNormalizedSabreOffer($this->validSabreOffer());
        $this->assertTrue($r->success);
        $this->assertSame('pending_revalidation', $r->status);
        $this->assertTrue($svc->canBookOffer($this->validSabreOffer()));
    }

    public function test_create_booking_array_returns_disabled_when_booking_flag_off(): void
    {
        config(['suppliers.sabre.booking_enabled' => false]);

        $svc = $this->app->make(SabreBookingService::class);
        $r = $svc->createBooking($this->validSabreOffer(), $this->samplePassengerData());
        $this->assertFalse($r['success']);
        $this->assertSame('disabled', $r['status']);
    }

    public function test_create_booking_array_returns_dry_run_when_live_calls_disabled(): void
    {
        Http::fake();
        config(['suppliers.sabre.booking_enabled' => true, 'suppliers.sabre.booking_live_call_enabled' => false]);

        $svc = $this->app->make(SabreBookingService::class);
        $r = $svc->createBooking($this->validSabreOffer(), $this->samplePassengerData());
        $this->assertTrue($r['success']);
        $this->assertSame('dry_run', $r['status']);
        $this->assertFalse($r['live_call_allowed']);

        Http::assertNothingSent();
    }

    public function test_create_booking_live_posts_when_both_flags_true_and_returns_pnr(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/v2/auth/token')) {
                return Http::response(['access_token' => 'test-token', 'expires_in' => 3600], 200);
            }
            if (str_contains($request->url(), 'trip/orders/createBooking')) {
                return Http::response(['recordLocator' => 'PNRLVX'], 201);
            }

            return Http::response(['message' => 'unexpected'], 500);
        });
        config(['suppliers.sabre.booking_enabled' => true, 'suppliers.sabre.booking_live_call_enabled' => true]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'credentials' => ['client_id' => 'test-client', 'client_secret' => 'test-secret'],
        ]);

        $offer = array_merge($this->validSabreOffer(), ['supplier_connection_id' => $sabreConn->id]);

        $svc = $this->app->make(SabreBookingService::class);
        $r = $svc->createBooking($offer, $this->samplePassengerData());
        $this->assertTrue($r['success']);
        $this->assertSame('pending_payment_or_ticketing', $r['status']);
        $this->assertSame('PNRLVX', $r['pnr'] ?? null);

        Http::assertSent(fn (Request $req): bool => str_contains($req->url(), 'trip/orders/createBooking'));
    }

    public function test_create_booking_blocked_before_http_when_segment_times_invalid(): void
    {
        Http::fake();
        config(['suppliers.sabre.booking_enabled' => true, 'suppliers.sabre.booking_live_call_enabled' => true]);

        $offer = [
            'supplier_provider' => 'sabre',
            'offer_id' => 'off-bad',
            'supplier_offer_id' => 'off-bad',
            'supplier_connection_id' => 99,
            'airline_code' => 'PK',
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'KHI', 'departure_at' => '2026-05-30T05:00:00', 'arrival_at' => '2026-05-30T06:45:00'],
                ['origin' => 'KHI', 'destination' => 'DXB', 'departure_at' => '2026-05-29T05:00:00', 'arrival_at' => '2026-05-30T04:00:00'],
            ],
            'fare_breakdown' => [
                'supplier_total' => 200.0,
                'currency' => 'USD',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
        ];

        $svc = $this->app->make(SabreBookingService::class);
        $r = $svc->createBooking($offer, $this->samplePassengerData());
        $this->assertFalse($r['success']);
        $this->assertSame('failed', $r['status']);
        $this->assertFalse($r['live_call_attempted']);
        $this->assertSame('sabre_invalid_itinerary_timing', $r['error_code'] ?? null);
        $this->assertStringContainsString('invalid', strtolower((string) ($r['message'] ?? '')));

        Http::assertNothingSent();
    }

    public function test_validate_rejects_bad_chronology_offer(): void
    {
        $svc = $this->app->make(SabreBookingService::class);
        $offer = [
            'supplier_provider' => 'sabre',
            'supplier_offer_id' => 'x',
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'KHI', 'departure_at' => '2026-05-30T05:00:00', 'arrival_at' => '2026-05-30T06:45:00'],
                ['origin' => 'KHI', 'destination' => 'DXB', 'departure_at' => '2026-05-29T05:00:00', 'arrival_at' => '2026-05-30T04:00:00'],
            ],
            'fare_breakdown' => [
                'supplier_total' => 50.0,
                'currency' => 'USD',
                'passenger_counts' => ['adults' => 1],
            ],
        ];
        $gate = $svc->validateNormalizedSabreOffer($offer);
        $this->assertFalse($gate->success);
        $this->assertSame('sabre_invalid_itinerary_timing', $gate->safe_context['error_code'] ?? null);
    }

    public function test_validate_accepts_two_segment_lhe_khi_dxb_chronological(): void
    {
        $svc = $this->app->make(SabreBookingService::class);
        $offer = [
            'supplier_provider' => 'sabre',
            'supplier_offer_id' => 'x',
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'KHI', 'departure_at' => '2026-05-30T05:00:00', 'arrival_at' => '2026-05-30T06:45:00'],
                ['origin' => 'KHI', 'destination' => 'DXB', 'departure_at' => '2026-05-30T08:00:00', 'arrival_at' => '2026-05-30T11:00:00'],
            ],
            'fare_breakdown' => [
                'supplier_total' => 50.0,
                'currency' => 'USD',
                'passenger_counts' => ['adults' => 1],
            ],
        ];
        $this->assertTrue($svc->validateNormalizedSabreOffer($offer)->success);
    }

    public function test_create_booking_live_http_error_returns_safe_failed_status(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/v2/auth/token')) {
                return Http::response(['access_token' => 't', 'expires_in' => 3600], 200);
            }

            return Http::response(['message' => 'Invalid request'], 422);
        });
        config(['suppliers.sabre.booking_enabled' => true, 'suppliers.sabre.booking_live_call_enabled' => true, 'suppliers.sabre.booking_path' => '/v1/trip/orders/createBooking']);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'credentials' => ['client_id' => 'c', 'client_secret' => 's'],
        ]);

        $offer = array_merge($this->validSabreOffer(), ['supplier_connection_id' => $sabreConn->id]);

        $svc = $this->app->make(SabreBookingService::class);
        $r = $svc->createBooking($offer, $this->samplePassengerData());
        $this->assertFalse($r['success']);
        $this->assertSame('failed', $r['status']);
        $this->assertTrue($r['live_call_attempted']);
        $this->assertSame(422, (int) ($r['http_status'] ?? 0));
        $this->assertStringContainsString('validation', strtolower((string) ($r['message'] ?? '')));
        $this->assertNotEmpty($r['endpoint_host'] ?? null);
        $this->assertNotEmpty($r['endpoint_path'] ?? null);
    }

    public function test_prepare_booking_payload_contains_safe_structure(): void
    {
        $svc = $this->app->make(SabreBookingService::class);
        $payload = $svc->prepareBookingPayload($this->validSabreOffer(), $this->samplePassengerData());
        $this->assertTrue($payload['_valid']);
        $this->assertSame('sabre', $payload['provider']);
        $this->assertSame(2, $payload['supplier_connection_id']);
        $this->assertSame('off-1', $payload['supplier_offer_id']);
        $this->assertSame('USD', $payload['fare']['currency']);
        $this->assertCount(1, $payload['segments']);
        $this->assertSame('LHE', $payload['segments'][0]['origin']);
        $this->assertSame('DXB', $payload['segments'][0]['destination']);
        $this->assertCount(1, $payload['passengers']);
        $this->assertSame('ADT', $payload['passengers'][0]['type']);
        $this->assertSame('Ada', $payload['passengers'][0]['first_name']);
    }

    public function test_two_segment_traditional_wire_keeps_res_book_desig_code(): void
    {
        config([
            'suppliers.sabre.passenger_records_allow_verified_multi_segment' => false,
        ]);
        $depart = '2026-06-01';
        $offer = [
            'supplier_provider' => 'sabre',
            'supplier_offer_id' => 'two-seg-1',
            'supplier_connection_id' => 2,
            'origin' => 'LHE',
            'destination' => 'JED',
            'airline_code' => 'PK',
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'KHI',
                    'carrier' => 'PK',
                    'flight_number' => '301',
                    'departure_at' => $depart.'T05:00:00Z',
                    'arrival_at' => $depart.'T06:45:00Z',
                    'booking_class' => 'Y',
                ],
                [
                    'origin' => 'KHI',
                    'destination' => 'JED',
                    'carrier' => 'PK',
                    'flight_number' => '741',
                    'departure_at' => $depart.'T08:30:00Z',
                    'arrival_at' => $depart.'T12:00:00Z',
                    'booking_class' => 'Y',
                ],
            ],
            'fare_breakdown' => [
                'supplier_total' => 120000,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1],
            ],
            'raw_payload' => [
                'sabre_segment_order' => [
                    'segment_order_corrected' => true,
                ],
            ],
        ];
        $svc = $this->app->make(SabreBookingService::class);
        $draft = $svc->prepareBookingPayload($offer, $this->samplePassengerData());
        $this->assertTrue($draft['_valid']);
        $this->assertSame('Y', $draft['segments'][0]['booking_class'] ?? '');
        $builder = $this->app->make(SabreBookingPayloadBuilder::class);
        unset($draft['_valid']);
        $wire = $builder->buildTraditionalPnrCreatePassengerNameRecordV1Wire($draft, []);
        $summary = $builder->summarizeTraditionalPnrWirePostBody($builder->stripOtaInternalKeysFromBookingWire($wire));
        $this->assertTrue($summary['wire_flight_segment_has_res_book_desig_code'] ?? false, json_encode($summary['wire_invalid_traditional_pnr_contract_keys'] ?? []));

        config([
            'suppliers.sabre.booking_mode' => 'certified',
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => '/v2.5.0/passenger/records?mode=create',
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
            'suppliers.sabre.revalidate_before_booking' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => true,
        ]);
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/v2/auth/token')) {
                return Http::response(['access_token' => 't', 'expires_in' => 3600], 200);
            }

            return Http::response(['CreatePassengerNameRecordRS' => ['ApplicationResults' => ['status' => 'Complete']]], 200);
        });
        $this->seed(OtaFoundationSeeder::class);
        $sabreConn = SupplierConnection::query()->where('provider', 'sabre')->firstOrFail();
        $offer['supplier_connection_id'] = $sabreConn->id;
        $passengerData = array_merge($this->samplePassengerData(), [
            'passengers' => [[
                'passenger_type' => 'adult',
                'first_name' => 'Test',
                'last_name' => 'User',
                'passport_number' => 'AB9999999',
                'passport_issuing_country' => 'PK',
                'passport_expiry_date' => '2035-12-31',
                'nationality' => 'PK',
                'document_type' => 'passport',
            ]],
        ]);
        $r = $svc->createBooking($offer, $passengerData, 99);
        $this->assertSame('sabre_passenger_records_itinerary_guard', $r['error_code'] ?? null, json_encode($r));

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'meta' => [
                'supplier_provider' => 'sabre',
                'supplier_connection_id' => $sabreConn->id,
                'requires_price_change_confirmation' => false,
                'protection_mode' => 'hold_price_guaranteed',
                'flight_offer_snapshot' => $offer,
                'search_criteria' => [
                    'origin' => 'LHE',
                    'destination' => 'JED',
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
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
            'first_name' => 'Test',
            'last_name' => 'User',
            'passport_number' => 'AB9999999',
            'passport_issuing_country' => 'PK',
            'passport_expiry_date' => '2035-12-31',
            'nationality' => 'PK',
            'document_type' => 'passport',
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 't@example.com',
            'phone' => '+923001234567',
        ]);
        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 90000,
            'taxes' => 10000,
            'fees' => 0,
            'markup' => 10000,
            'discount' => 0,
            'total' => 120000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);
        $dry = $svc->runPublicReviewDryRun($booking->fresh(['passengers', 'contact', 'fareBreakdown']));
        $this->assertSame('sabre_passenger_records_itinerary_guard', $dry['error_code'] ?? null, json_encode($dry));
    }

    public function test_retrieve_and_cancel_return_dry_run_without_live_flags(): void
    {
        Http::fake();
        config(['suppliers.sabre.booking_enabled' => true, 'suppliers.sabre.booking_live_call_enabled' => false]);

        $svc = $this->app->make(SabreBookingService::class);
        $this->assertSame('dry_run', $svc->retrieveBooking('PNR123')['status']);
        $this->assertSame('dry_run', $svc->cancelBooking('PNR123')['status']);

        Http::assertNothingSent();
    }

    public function test_create_supplier_booking_maps_dry_run_to_live_calls_disabled_error(): void
    {
        Http::fake();
        $this->seed(OtaFoundationSeeder::class);
        config(['suppliers.sabre.booking_enabled' => true, 'suppliers.sabre.booking_live_call_enabled' => false]);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update(['is_active' => true, 'status' => SupplierConnectionStatus::Active]);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => 'sabre',
            'meta' => [
                'supplier_provider' => 'sabre',
                'supplier_connection_id' => $sabreConn->id,
                'normalized_offer_snapshot' => array_merge($this->validSabreOffer(), [
                    'supplier_connection_id' => $sabreConn->id,
                ]),
            ],
        ]);
        $booking->passengers()->create([
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
        ]);
        $booking->contact()->create([
            'email' => 'lead@example.com',
            'phone' => '+10000000000',
            'country' => null,
            'address_line' => null,
            'meta' => [],
        ]);

        $svc = $this->app->make(SabreBookingService::class);
        $result = $svc->createSupplierBooking($booking->fresh(['passengers', 'contact']), $admin, false);
        $this->assertFalse($result->success);
        $this->assertSame('dry_run', $result->status);
        $this->assertSame('sabre_booking_live_calls_disabled', $result->error_code);

        Http::assertNothingSent();
    }

    public function test_iati_like_freshness_strategy_waives_bfm_revalidation(): void
    {
        config([
            'suppliers.sabre.revalidate_before_booking' => true,
            'suppliers.sabre.refresh_offer_before_public_pnr' => true,
        ]);

        $offer = array_merge($this->validSabreOffer(), [
            'raw_payload' => [
                'sabre_booking_context' => ['ready_for_booking_payload' => true],
            ],
        ]);
        $svc = $this->app->make(SabreBookingService::class);
        $draft = $svc->prepareBookingPayload($offer, $this->samplePassengerData());
        $this->assertTrue($draft['_valid']);
        unset($draft['_valid']);

        $style = [
            'selected_payload_style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            'selected_style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            'iati_like_selected' => true,
            'eligible' => true,
            'segment_context_complete' => true,
            'rbd_complete' => true,
            'supplier_connection_present' => true,
        ];
        $decision = $svc->decideSabreBookingFreshnessStrategy($offer, $draft, null, $style);

        $this->assertSame('iati_cpnr_refresh_or_waiver', $decision['strategy']);
        $this->assertFalse($decision['revalidation_required']);
        $this->assertTrue($decision['revalidation_skipped']);
        $this->assertSame('iati_cpnr_revalidation_waived', $decision['revalidation_skip_reason']);
        $this->assertTrue($decision['refresh_required']);
        $this->assertTrue($decision['iati_like_selected']);
    }

    public function test_traditional_freshness_strategy_respects_revalidate_before_booking_when_not_pnr_only(): void
    {
        config([
            'suppliers.sabre.booking_mode' => 'certified',
            'suppliers.sabre.ticketing_enabled' => true,
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
            'suppliers.sabre.revalidate_before_booking' => true,
        ]);

        $svc = $this->app->make(SabreBookingService::class);
        $draft = $svc->prepareBookingPayload($this->validSabreOffer(), $this->samplePassengerData());
        unset($draft['_valid']);
        $style = [
            'selected_payload_style' => SabreBookingPayloadBuilder::TRADITIONAL_PNR_CREATE_PASSENGER_NAME_RECORD_V1,
            'iati_like_selected' => false,
        ];
        $decision = $svc->decideSabreBookingFreshnessStrategy($this->validSabreOffer(), $draft, null, $style);

        $this->assertSame('traditional_bfm_revalidation', $decision['strategy']);
        $this->assertTrue($decision['revalidation_required']);
        $this->assertFalse($decision['revalidation_skipped']);
    }

    public function test_gds_freshness_skips_revalidation_when_offer_refresh_satisfied(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'selected_fare_total' => 88602,
            'revalidated_fare_total' => 88602,
            'meta' => [
                'supplier_provider' => 'sabre',
                'offer_refresh_status' => 'refreshed',
            ],
        ]);

        config([
            'suppliers.sabre.booking_mode' => 'pnr_only',
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
            'suppliers.sabre.revalidate_before_booking' => true,
        ]);

        $svc = $this->app->make(SabreBookingService::class);
        $draft = $svc->prepareBookingPayload($this->validSabreOffer(), $this->samplePassengerData());
        unset($draft['_valid']);
        $style = [
            'selected_payload_style' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_PASSENGER_RECORDS_V2_5_GDS,
            'selected_strategy_code' => SabreGdsPnrCreateStrategyRegistry::STRATEGY_PASSENGER_RECORDS_V2_5_GDS,
            'iati_like_selected' => false,
            'segment_context_complete' => true,
            'rbd_complete' => true,
            'supplier_connection_present' => true,
        ];
        $decision = $svc->decideSabreBookingFreshnessStrategy($this->validSabreOffer(), $draft, null, $style, $booking);

        $this->assertSame('gds_offer_refresh_satisfied', $decision['strategy']);
        $this->assertFalse($decision['revalidation_required']);
        $this->assertTrue($decision['revalidation_skipped']);
        $this->assertSame('safe_offer_refresh_satisfied', $decision['revalidation_skip_reason']);
        $this->assertTrue($decision['freshness_satisfied']);
        $this->assertSame('offer_refresh', $decision['freshness_source']);
    }

    public function test_iati_fare_changed_pending_triggers_manual_review(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'meta' => [
                'supplier_provider' => 'sabre',
                'offer_refresh_status' => 'refreshed',
                SabreOfferRefreshAcceptance::META_REQUIRES_CONFIRMATION => true,
                SabreOfferRefreshAcceptance::META_PRICE_CHANGED => true,
                SabreOfferRefreshAcceptance::META_ACCEPTED => false,
            ],
        ]);

        $svc = $this->app->make(SabreBookingService::class);
        $draft = $svc->prepareBookingPayload($this->validSabreOffer(), $this->samplePassengerData());
        unset($draft['_valid']);
        $style = [
            'selected_payload_style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            'iati_like_selected' => true,
            'eligible' => true,
            'segment_context_complete' => true,
            'rbd_complete' => true,
            'supplier_connection_present' => true,
        ];
        $decision = $svc->decideSabreBookingFreshnessStrategy(
            array_merge($this->validSabreOffer(), ['raw_payload' => ['sabre_booking_context' => ['ready_for_booking_payload' => true]]]),
            $draft,
            null,
            $style,
            $booking,
        );

        $this->assertTrue($decision['manual_review_required']);
        $this->assertTrue($decision['blocks_booking']);
        $this->assertSame('fare_changed_review_required', $decision['reason_code']);
        $this->assertTrue($decision['fare_changed']);
    }

    public function test_freshness_diagnostic_slice_includes_strategy_fields(): void
    {
        $svc = $this->app->make(SabreBookingService::class);
        $decision = [
            'strategy' => 'iati_cpnr_refresh_or_waiver',
            'revalidation_required' => false,
            'revalidation_skipped' => true,
            'revalidation_skip_reason' => 'iati_cpnr_revalidation_waived',
            'iati_like_selected' => true,
        ];
        $decision['freshness_strategy'] = $decision['strategy'];
        $decision['freshness_blocks_booking'] = false;
        $slice = $svc->freshnessStrategyDiagnosticSlice($decision);

        $this->assertSame('iati_cpnr_refresh_or_waiver', $slice['freshness_strategy'] ?? $slice['strategy'] ?? null);
        $this->assertFalse($slice['revalidation_required']);
        $this->assertTrue($slice['iati_like_selected']);
    }
}
