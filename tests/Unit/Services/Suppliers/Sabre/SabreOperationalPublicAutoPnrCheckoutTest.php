<?php

namespace Tests\Unit\Services\Suppliers\Sabre;

use App\Enums\BookingStatus;
use App\Enums\SupplierConnectionStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabreOperationalPnrReadiness;
use App\Support\Bookings\SabreSafeRefreshContext;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreOperationalPublicAutoPnrCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->activateSabreConnection();
        $this->configureOperationalPnrBase();
        Cache::flush();
        Http::fake();
    }

    public function test_operational_flags_on_bypasses_certified_route_for_dry_run_create(): void
    {
        $booking = $this->operationalConnectingBooking();

        $result = app(SabreBookingService::class)->runPublicReviewDryRun(
            $booking->fresh(['passengers', 'contact', 'fareBreakdown', 'supplierBookings']),
        );

        $this->assertTrue($result['success'] ?? false);
        $this->assertSame('dry_run', $result['status'] ?? null);
        $this->assertFalse($result['live_call_attempted'] ?? true);

        $meta = is_array($booking->fresh()->meta) ? $booking->fresh()->meta : [];
        $this->assertTrue($meta['operational_auto_pnr_attempted'] ?? false);
        $this->assertSame('deferred', $meta['operational_auto_pnr_result'] ?? null);
        $this->assertTrue(($meta['operational_pnr_readiness']['would_attempt_pnr'] ?? false) === true);
    }

    public function test_operational_flags_off_defers_without_live_call(): void
    {
        config([
            'suppliers.sabre.verified_multiseg_auto_pnr_enabled' => false,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
        ]);
        $booking = $this->operationalConnectingBooking();

        $result = app(SabreBookingService::class)->runPublicReviewDryRun(
            $booking->fresh(['passengers', 'contact', 'fareBreakdown', 'supplierBookings']),
        );

        $this->assertFalse($result['success'] ?? true);
        $this->assertSame(SabreCertifiedRouteSelector::ERROR_CODE_PENDING, $result['error_code'] ?? null);
        $this->assertFalse($result['live_call_attempted'] ?? true);

        Http::assertNothingSent();
    }

    public function test_unknown_controlled_only_with_operational_flags_attempts_dry_run(): void
    {
        $booking = $this->unknownSameCarrierOperationalBooking();

        $result = app(SabreBookingService::class)->runPublicReviewDryRun(
            $booking->fresh(['passengers', 'contact', 'fareBreakdown', 'supplierBookings']),
        );

        $this->assertTrue($result['success'] ?? false);
        $this->assertSame('dry_run', $result['status'] ?? null);
        $this->assertFalse($result['live_call_attempted'] ?? true);
    }

    public function test_operational_live_success_persists_pnr_without_ticket(): void
    {
        $booking = $this->operationalConnectingBooking();
        $svc = app(SabreBookingService::class);

        $result = [
            'success' => true,
            'status' => 'pending_payment_or_ticketing',
            'pnr' => 'OPSPNR',
            'provider_booking_id' => 'OPSPNR',
            'live_call_attempted' => true,
            'passenger_count' => 1,
            'segment_count' => 2,
            'supplier_connection_id' => (int) data_get($booking->meta, 'supplier_connection_id'),
            'selected_offer_id' => 'ops-offer',
            'fare_amount' => 100.0,
            'fare_currency' => 'PKR',
            'booking_schema' => 'create_passenger_name_record',
            'payload_schema' => 'iati_like_cpnr_v2_4_gds',
        ];

        $svc->finalizePublicCheckoutSabreStorage($booking, $result);
        app(SabreOperationalPnrReadiness::class)->persistCheckoutMeta(
            $booking->fresh(['supplierBookings']),
            app(SabreOperationalPnrReadiness::class)->evaluate($booking->fresh(['supplierBookings'])),
            true,
            'created',
        );

        $booking->refresh();
        $this->assertSame('OPSPNR', $booking->pnr);
        $this->assertSame('pending_payment_or_ticketing', $booking->supplier_booking_status);
        $this->assertSame('pending_ticketing', SupplierBooking::query()->where('booking_id', $booking->id)->value('status'));
        $this->assertSame(0, $booking->tickets()->count());

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $this->assertSame('created', $meta['operational_auto_pnr_result'] ?? null);
    }

    public function test_operational_live_failure_remains_manual_review_without_crash(): void
    {
        $booking = $this->operationalConnectingBooking();
        $svc = app(SabreBookingService::class);

        $result = [
            'success' => false,
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'message' => SabreOperationalPnrReadiness::CUSTOMER_FAILURE_NOTICE,
            'live_call_attempted' => true,
            'passenger_count' => 1,
            'segment_count' => 2,
            'supplier_connection_id' => (int) data_get($booking->meta, 'supplier_connection_id'),
            'selected_offer_id' => 'ops-offer',
            'fare_amount' => 100.0,
            'fare_currency' => 'PKR',
            'booking_schema' => 'create_passenger_name_record',
            'payload_schema' => 'iati_like_cpnr_v2_4_gds',
            'response_error_messages' => ['EnhancedAirBookRQ: *NO FARES/RBD/CARRIER'],
        ];

        $svc->finalizePublicCheckoutSabreStorage($booking, $result);
        app(SabreOperationalPnrReadiness::class)->persistCheckoutMeta(
            $booking->fresh(['supplierBookings']),
            app(SabreOperationalPnrReadiness::class)->evaluate($booking->fresh(['supplierBookings'])),
            true,
            'failed',
            'sabre_booking_application_error',
        );

        $booking->refresh();
        $this->assertSame('', trim((string) ($booking->pnr ?? '')));
        $this->assertSame('manual_review', $booking->supplier_booking_status);
        $this->assertGreaterThanOrEqual(
            1,
            SupplierBookingAttempt::query()->where('booking_id', $booking->id)->where('status', 'needs_review')->count(),
        );

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $this->assertSame('failed', $meta['operational_auto_pnr_result'] ?? null);
    }

    public function test_ticketing_enabled_blocks_operational_attempt(): void
    {
        config(['suppliers.sabre.ticketing_enabled' => true]);
        $booking = $this->operationalConnectingBooking();

        app(SabreBookingService::class)->runPublicReviewDryRun(
            $booking->fresh(['passengers', 'contact', 'fareBreakdown', 'supplierBookings']),
        );

        $this->assertFalse(app(SabreOperationalPnrReadiness::class)->wouldAttemptPnr($booking->fresh()));
        $meta = is_array($booking->fresh()->meta) ? $booking->fresh()->meta : [];
        $this->assertNotSame('attempted', $meta['operational_auto_pnr_result'] ?? null);
        Http::assertNothingSent();
    }

    protected function configureOperationalPnrBase(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => true,
            'suppliers.sabre.verified_multiseg_auto_pnr_enabled' => true,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
            'suppliers.sabre.cpnr_iati_style_certified_gds_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => false,
            'suppliers.sabre.certified_route_selector_public_checkout_enabled' => true,
            'suppliers.sabre.booking_path' => '/v2.4.0/passenger/records?mode=create',
            'suppliers.sabre.revalidate_before_booking' => false,
            'suppliers.sabre.passenger_records_fresh_shop_guard_before_live' => false,
            'suppliers.sabre.refresh_offer_before_public_pnr' => false,
        ]);
    }

    protected function activateSabreConnection(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $conn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => [
                'client_id' => 'cid',
                'client_secret' => 'sec',
                'pcc' => 'TEST',
                'pseudo_city_code' => 'TEST',
                'target_city' => 'TEST',
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function operationalConnectingBooking(array $overrides = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();

        $booking = Booking::factory()->create(array_merge([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'unpaid',
            'confirmation_method' => 'pay_later_booking_request',
            'meta' => [
                'supplier_provider' => 'sabre',
                'supplier_connection_id' => $conn->id,
                'booking_method' => 'pay_later_booking_request',
                'confirmation_method' => 'pay_later_booking_request',
                'offer_validation_status' => 'valid',
                'search_criteria' => ['trip_type' => 'one_way', 'origin' => 'LHE', 'destination' => 'JED'],
                'normalized_offer_snapshot' => [
                    'supplier_provider' => 'sabre',
                    'supplier_connection_id' => $conn->id,
                    'id' => 'ops-offer',
                    'offer_id' => 'ops-offer',
                    'validating_carrier' => 'GF',
                    'segments' => [
                        [
                            'origin' => 'LHE',
                            'destination' => 'BAH',
                            'carrier' => 'GF',
                            'flight_number' => '767',
                            'booking_class' => 'W',
                            'fare_basis_code' => 'WDLIT3PK',
                            'departure_at' => '2026-07-29T22:00:00',
                            'arrival_at' => '2026-07-30T01:55:00',
                        ],
                        [
                            'origin' => 'BAH',
                            'destination' => 'JED',
                            'carrier' => 'GF',
                            'flight_number' => '171',
                            'booking_class' => 'W',
                            'fare_basis_code' => 'WDLIT3PK',
                            'departure_at' => '2026-07-30T10:05:00',
                            'arrival_at' => '2026-07-30T12:30:00',
                        ],
                    ],
                    'raw_payload' => [
                        'distribution_channel' => 'GDS',
                        'sabre_booking_context' => [
                            'itinerary_reference' => '1',
                            'pricing_information_index' => 0,
                            'booking_classes_by_segment' => ['W', 'W'],
                            'fare_basis_codes_by_segment' => ['WDLIT3PK', 'WDLIT3PK'],
                            'segment_slice_count' => 2,
                        ],
                    ],
                    'fare_breakdown' => [
                        'supplier_total' => 100.0,
                        'currency' => 'PKR',
                        'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
                    ],
                ],
            ],
        ], $overrides));

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $meta[SabreSafeRefreshContext::META_KEY] = app(SabreSafeRefreshContext::class)->buildFromCheckout($snapshot, [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'JED',
            'depart_date' => '2026-07-29',
            'adults' => 1,
        ], [
            'checkout_search_id' => 'ops-checkout-search',
            'checkout_offer_id' => 'ops-checkout-offer',
            'supplier_total' => 100.0,
            'supplier_currency' => 'PKR',
        ]);
        $booking->forceFill(['meta' => $meta])->save();

        BookingPassenger::factory()->for($booking)->create([
            'passenger_index' => 0,
            'is_lead_passenger' => true,
            'first_name' => 'Test',
            'last_name' => 'Passenger',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'gender' => 'male',
            'passenger_type' => 'adult',
            'passport_number' => 'AB1234567',
            'passport_expiry_date' => now()->addYears(2)->toDateString(),
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'guest@example.test',
            'phone' => '+923001234567',
        ]);

        return $booking->fresh(['passengers', 'contact', 'supplierBookings', 'fareBreakdown']);
    }

    protected function unknownSameCarrierOperationalBooking(): Booking
    {
        $booking = $this->operationalConnectingBooking();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $snapshot['validating_carrier'] = 'SV';
        $snapshot['segments'] = [
            [
                'origin' => 'ISB',
                'destination' => 'KHI',
                'carrier' => 'SV',
                'flight_number' => '701',
                'booking_class' => 'Q',
                'fare_basis_code' => 'QSV01',
                'departure_at' => '2026-07-23T08:00:00Z',
                'arrival_at' => '2026-07-23T10:00:00Z',
            ],
            [
                'origin' => 'KHI',
                'destination' => 'DXB',
                'carrier' => 'SV',
                'flight_number' => '702',
                'booking_class' => 'Q',
                'fare_basis_code' => 'QSV02',
                'departure_at' => '2026-07-24T02:30:00Z',
                'arrival_at' => '2026-07-24T05:30:00Z',
            ],
        ];
        $meta['normalized_offer_snapshot'] = $snapshot;
        $meta['search_criteria'] = ['trip_type' => 'one_way', 'origin' => 'ISB', 'destination' => 'DXB'];
        $meta[SabreSafeRefreshContext::META_KEY] = app(SabreSafeRefreshContext::class)->buildFromCheckout($snapshot, [
            'trip_type' => 'one_way',
            'origin' => 'ISB',
            'destination' => 'DXB',
            'depart_date' => '2026-07-23',
            'adults' => 1,
        ], [
            'checkout_search_id' => 'sv-ops-search',
            'checkout_offer_id' => 'sv-ops-offer',
            'supplier_total' => 100.0,
            'supplier_currency' => 'PKR',
        ]);
        $booking->forceFill(['meta' => $meta])->save();

        return $booking->fresh(['passengers', 'contact', 'supplierBookings', 'fareBreakdown']);
    }
}
