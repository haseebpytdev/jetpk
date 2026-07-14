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
use App\Support\Bookings\SabreVerifiedAutoPnrReadiness;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreVerifiedPublicAutoPnrCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->activateSabreConnection();
        $this->configureVerifiedAutoPnrBase();
        Cache::flush();
        Http::fake();
    }

    public function test_flag_off_gf_verified_defers_without_live_call(): void
    {
        config(['suppliers.sabre.verified_multiseg_auto_pnr_enabled' => false]);
        $booking = $this->gfVerifiedConnectingBooking();

        $result = app(SabreBookingService::class)->runPublicReviewDryRun(
            $booking->fresh(['passengers', 'contact', 'fareBreakdown', 'supplierBookings']),
        );

        $this->assertFalse($result['success'] ?? true);
        $this->assertSame(SabreCertifiedRouteSelector::ERROR_CODE_PENDING, $result['error_code'] ?? null);
        $this->assertFalse($result['live_call_attempted'] ?? true);

        $meta = is_array($booking->fresh()->meta) ? $booking->fresh()->meta : [];
        $this->assertSame('deferred', $meta['verified_multiseg_auto_pnr_result'] ?? null);
        $this->assertFalse($meta['verified_multiseg_auto_pnr_attempted'] ?? true);
        $this->assertSame(SabreOperationalPnrReadiness::REASON_BLOCKED_BY_FLAGS, $meta['operational_auto_pnr_reason_code'] ?? null);
        $this->assertFalse($meta['operational_auto_pnr_attempted'] ?? true);

        Http::assertNothingSent();
    }

    public function test_flag_on_gf_route_only_without_exact_evidence_defers_without_live_call(): void
    {
        config(['suppliers.sabre.verified_multiseg_auto_pnr_enabled' => true]);
        $booking = $this->gfVerifiedConnectingBooking();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $snapshot['segments'] = [
            [
                'origin' => 'LHE',
                'destination' => 'BAH',
                'carrier' => 'GF',
                'flight_number' => '999',
                'booking_class' => 'Q',
                'fare_basis_code' => 'QDLIT3GF',
                'departure_at' => '2026-07-23T08:00:00Z',
                'arrival_at' => '2026-07-23T10:00:00Z',
            ],
            [
                'origin' => 'BAH',
                'destination' => 'JED',
                'carrier' => 'GF',
                'flight_number' => '888',
                'booking_class' => 'Q',
                'fare_basis_code' => 'QDLIT3GF',
                'departure_at' => '2026-07-24T02:30:00Z',
                'arrival_at' => '2026-07-24T05:30:00Z',
            ],
        ];
        $meta['normalized_offer_snapshot'] = $snapshot;
        $meta[SabreSafeRefreshContext::META_KEY] = app(SabreSafeRefreshContext::class)->buildFromCheckout($snapshot, [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'JED',
            'depart_date' => '2026-07-23',
            'adults' => 1,
        ], [
            'checkout_search_id' => 'gf-route-only-checkout',
            'checkout_offer_id' => 'gf-route-only-offer',
            'supplier_total' => 100.0,
            'supplier_currency' => 'PKR',
        ]);
        $booking->forceFill(['meta' => $meta])->save();

        $result = app(SabreBookingService::class)->runPublicReviewDryRun(
            $booking->fresh(['passengers', 'contact', 'fareBreakdown', 'supplierBookings']),
        );

        $this->assertFalse($result['success'] ?? true);
        $this->assertSame(SabreCertifiedRouteSelector::ERROR_CODE_PENDING, $result['error_code'] ?? null);
        $this->assertFalse($result['live_call_attempted'] ?? true);

        $metaOut = is_array($booking->fresh()->meta) ? $booking->fresh()->meta : [];
        $this->assertSame(SabreVerifiedAutoPnrReadiness::REASON_INSUFFICIENT_FLIGHT_DATE_SELLABILITY_EVIDENCE, $metaOut['verified_multiseg_auto_pnr_reason_code'] ?? null);

        Http::assertNothingSent();
    }

    public function test_flag_on_booking_46_like_static_failed_defers_without_live_call(): void
    {
        config(['suppliers.sabre.verified_multiseg_auto_pnr_enabled' => true]);
        $booking = $this->gfBooking46LikeConnectingBooking();

        $result = app(SabreBookingService::class)->runPublicReviewDryRun(
            $booking->fresh(['passengers', 'contact', 'fareBreakdown', 'supplierBookings']),
        );

        $this->assertFalse($result['success'] ?? true);
        $this->assertSame(SabreCertifiedRouteSelector::ERROR_CODE_PENDING, $result['error_code'] ?? null);
        $this->assertFalse($result['live_call_attempted'] ?? true);

        $metaOut = is_array($booking->fresh()->meta) ? $booking->fresh()->meta : [];
        $this->assertSame(SabreVerifiedAutoPnrReadiness::REASON_FARE_RBD_CARRIER_NOT_SELLABLE, $metaOut['verified_multiseg_auto_pnr_reason_code'] ?? null);

        Http::assertNothingSent();
    }

    public function test_flag_on_gf_verified_bypasses_certified_route_for_dry_run_create(): void
    {
        config([
            'suppliers.sabre.verified_multiseg_auto_pnr_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => true,
        ]);
        $booking = $this->gfVerifiedConnectingBooking();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['confirmation_method'] = 'pay_later_booking_request';
        $meta['booking_method'] = 'pay_later_booking_request';
        $booking->forceFill([
            'payment_status' => 'unpaid',
            'confirmation_method' => 'pay_later_booking_request',
            'meta' => $meta,
        ])->save();

        $result = app(SabreBookingService::class)->runPublicReviewDryRun(
            $booking->fresh(['passengers', 'contact', 'fareBreakdown', 'supplierBookings']),
        );

        $this->assertTrue($result['success'] ?? false);
        $this->assertSame('dry_run', $result['status'] ?? null);
        $this->assertFalse($result['live_call_attempted'] ?? true);
        $this->assertStringNotContainsString(
            'automatic PNR is not enabled for public checkout yet',
            (string) ($result['message'] ?? ''),
        );

        $meta = is_array($booking->fresh()->meta) ? $booking->fresh()->meta : [];
        $this->assertTrue($meta['operational_auto_pnr_attempted'] ?? false);
        $this->assertSame('deferred', $meta['operational_auto_pnr_result'] ?? null);
        $this->assertArrayHasKey('operational_pnr_readiness', $meta);
        $this->assertSame('deferred', $meta['verified_multiseg_auto_pnr_result'] ?? null);

        $this->assertGreaterThanOrEqual(
            1,
            SupplierBookingAttempt::query()->where('booking_id', $booking->id)->where('status', 'dry_run')->count(),
        );
    }

    public function test_flag_on_gf_verified_live_success_persists_pnr_and_pending_ticketing(): void
    {
        config(['suppliers.sabre.verified_multiseg_auto_pnr_enabled' => true]);
        $booking = $this->gfVerifiedConnectingBooking();
        $svc = app(SabreBookingService::class);

        $result = [
            'success' => true,
            'status' => 'pending_payment_or_ticketing',
            'pnr' => 'SZFXWM',
            'provider_booking_id' => 'SZFXWM',
            'live_call_attempted' => true,
            'passenger_count' => 1,
            'segment_count' => 2,
            'supplier_connection_id' => (int) data_get($booking->meta, 'supplier_connection_id'),
            'selected_offer_id' => 'gf-verified-offer',
            'fare_amount' => 100.0,
            'fare_currency' => 'PKR',
            'booking_schema' => 'create_passenger_name_record',
            'payload_schema' => 'iati_like_cpnr_v2_4_gds',
        ];

        $svc->finalizePublicCheckoutSabreStorage($booking, $result);
        $readiness = app(SabreVerifiedAutoPnrReadiness::class)->evaluate($booking->fresh(['supplierBookings']));
        app(SabreVerifiedAutoPnrReadiness::class)->persistCheckoutMeta(
            $booking->fresh(),
            $readiness,
            true,
            'created',
        );

        $booking->refresh();
        $this->assertSame('SZFXWM', $booking->pnr);
        $this->assertSame('pending_payment_or_ticketing', $booking->supplier_booking_status);
        $this->assertGreaterThanOrEqual(1, SupplierBooking::query()->where('booking_id', $booking->id)->count());
        $this->assertSame('pending_ticketing', SupplierBooking::query()->where('booking_id', $booking->id)->value('status'));

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $this->assertSame('created', $meta['verified_multiseg_auto_pnr_result'] ?? null);
        $this->assertTrue($meta['verified_multiseg_auto_pnr_attempted'] ?? false);
    }

    public function test_flag_on_gf_verified_live_failure_persists_terminal_fare_rbd_meta(): void
    {
        config(['suppliers.sabre.verified_multiseg_auto_pnr_enabled' => true]);
        $booking = $this->gfVerifiedConnectingBooking();
        $svc = app(SabreBookingService::class);

        $result = [
            'success' => false,
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'message' => 'Sabre returned a response requiring staff review.',
            'live_call_attempted' => true,
            'passenger_count' => 1,
            'segment_count' => 2,
            'supplier_connection_id' => (int) data_get($booking->meta, 'supplier_connection_id'),
            'selected_offer_id' => 'gf-verified-offer',
            'fare_amount' => 100.0,
            'fare_currency' => 'PKR',
            'booking_schema' => 'create_passenger_name_record',
            'payload_schema' => 'iati_like_cpnr_v2_4_gds',
            'response_error_codes' => ['ERR.SP.PROVIDER_ERROR', 'WARN.SWS.HOST.ERROR_IN_RESPONSE'],
            'response_error_messages' => [
                'Unable to perform air booking step',
                'EnhancedAirBookRQ: *NO FARES/RBD/CARRIER',
            ],
            'create_segment_count' => 2,
            'create_air_price_present' => true,
        ];

        $svc->finalizePublicCheckoutSabreStorage($booking, $result);
        $readiness = app(SabreVerifiedAutoPnrReadiness::class)->evaluate($booking->fresh(['supplierBookings']));
        app(SabreVerifiedAutoPnrReadiness::class)->persistCheckoutMeta(
            $booking->fresh(),
            $readiness,
            true,
            'failed',
            SabreVerifiedAutoPnrReadiness::VERIFIED_AUTO_PNR_TERMINAL_FAILURE_REASON,
        );

        $booking->refresh();
        $this->assertSame('', trim((string) ($booking->pnr ?? '')));
        $this->assertSame('manual_review', $booking->supplier_booking_status);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $this->assertSame('failed', $meta['verified_multiseg_auto_pnr_result'] ?? null);
        $this->assertSame(SabreVerifiedAutoPnrReadiness::VERIFIED_AUTO_PNR_TERMINAL_FAILURE_REASON, $meta['verified_multiseg_auto_pnr_reason_code'] ?? null);
        $this->assertFalse(isset($meta['sabre_checkout_outcome']['sabre_host_classification']['raw_payload']));
    }

    public function test_flag_on_gf_verified_live_failure_defers_to_manual_review_without_crash(): void
    {
        config(['suppliers.sabre.verified_multiseg_auto_pnr_enabled' => true]);
        $booking = $this->gfVerifiedConnectingBooking();
        $svc = app(SabreBookingService::class);

        $result = [
            'success' => false,
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'message' => 'Sabre returned a response requiring staff review.',
            'live_call_attempted' => true,
            'passenger_count' => 1,
            'segment_count' => 2,
            'supplier_connection_id' => (int) data_get($booking->meta, 'supplier_connection_id'),
            'selected_offer_id' => 'gf-verified-offer',
            'fare_amount' => 100.0,
            'fare_currency' => 'PKR',
            'booking_schema' => 'create_passenger_name_record',
            'payload_schema' => 'iati_like_cpnr_v2_4_gds',
            'response_error_messages' => ['EnhancedAirBookRQ: FLIGHT NOOP FOR THIS FLIGHT/DATE'],
        ];

        $svc->finalizePublicCheckoutSabreStorage($booking, $result);
        $readiness = app(SabreVerifiedAutoPnrReadiness::class)->evaluate($booking->fresh(['supplierBookings']));
        app(SabreVerifiedAutoPnrReadiness::class)->persistCheckoutMeta(
            $booking->fresh(),
            $readiness,
            true,
            'failed',
        );

        $booking->refresh();
        $this->assertSame('', trim((string) ($booking->pnr ?? '')));
        $this->assertSame('manual_review', $booking->supplier_booking_status);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $this->assertSame('failed', $meta['verified_multiseg_auto_pnr_result'] ?? null);
        $this->assertFalse(isset($meta['sabre_checkout_outcome']['sabre_host_classification']['raw_payload']));
    }

    public function test_unknown_sv_connecting_defers_with_flag_on(): void
    {
        config(['suppliers.sabre.verified_multiseg_auto_pnr_enabled' => true]);
        $booking = $this->unknownSameCarrierConnectingBooking();

        $result = app(SabreBookingService::class)->runPublicReviewDryRun(
            $booking->fresh(['passengers', 'contact', 'fareBreakdown', 'supplierBookings']),
        );

        $this->assertFalse($result['success'] ?? true);
        $this->assertSame(SabreCertifiedRouteSelector::ERROR_CODE_PENDING, $result['error_code'] ?? null);
        $this->assertFalse($result['live_call_attempted'] ?? true);

        Http::assertNothingSent();
    }

    public function test_pk_host_noop_blocked_defers_with_flag_on(): void
    {
        config(['suppliers.sabre.verified_multiseg_auto_pnr_enabled' => true]);
        $booking = $this->pkHostNoopConnectingBooking();

        $result = app(SabreBookingService::class)->runPublicReviewDryRun(
            $booking->fresh(['passengers', 'contact', 'fareBreakdown', 'supplierBookings']),
        );

        $this->assertFalse($result['success'] ?? true);
        $this->assertFalse($result['live_call_attempted'] ?? true);

        Http::assertNothingSent();
    }

    public function test_ticketing_enabled_blocks_verified_auto_pnr_with_flag_on(): void
    {
        config([
            'suppliers.sabre.verified_multiseg_auto_pnr_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => true,
        ]);
        $booking = $this->gfVerifiedConnectingBooking();

        $result = app(SabreBookingService::class)->runPublicReviewDryRun(
            $booking->fresh(['passengers', 'contact', 'fareBreakdown', 'supplierBookings']),
        );

        $this->assertFalse($result['success'] ?? true);
        $this->assertSame(SabreCertifiedRouteSelector::ERROR_CODE_PENDING, $result['error_code'] ?? null);
        Http::assertNothingSent();
    }

    public function test_existing_pnr_blocks_verified_auto_pnr_create(): void
    {
        config(['suppliers.sabre.verified_multiseg_auto_pnr_enabled' => true]);
        $booking = $this->gfVerifiedConnectingBooking(['pnr' => 'SZFXWM']);

        $result = app(SabreBookingService::class)->runPublicReviewDryRun(
            $booking->fresh(['passengers', 'contact', 'fareBreakdown', 'supplierBookings']),
        );

        $this->assertFalse($result['success'] ?? true);
        $this->assertFalse($result['live_call_attempted'] ?? true);
        Http::assertNothingSent();
    }

    public function test_existing_supplier_booking_blocks_verified_auto_pnr_create(): void
    {
        config(['suppliers.sabre.verified_multiseg_auto_pnr_enabled' => true]);
        $booking = $this->gfVerifiedConnectingBooking();
        SupplierBooking::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => 'sabre',
            'status' => 'created',
            'pnr' => 'SUP123',
        ]);

        $result = app(SabreBookingService::class)->runPublicReviewDryRun(
            $booking->fresh(['passengers', 'contact', 'fareBreakdown', 'supplierBookings']),
        );

        $this->assertFalse($result['success'] ?? true);
        $this->assertFalse($result['live_call_attempted'] ?? true);
        Http::assertNothingSent();
    }

    protected function configureVerifiedAutoPnrBase(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
            'suppliers.sabre.verified_multiseg_auto_pnr_enabled' => false,
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
    protected function gfVerifiedConnectingBooking(array $overrides = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();

        $booking = Booking::factory()->create(array_merge([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'meta' => [
                'supplier_provider' => 'sabre',
                'supplier_connection_id' => $conn->id,
                'offer_validation_status' => 'valid',
                'search_criteria' => ['trip_type' => 'one_way'],
                'normalized_offer_snapshot' => [
                    'supplier_provider' => 'sabre',
                    'supplier_connection_id' => $conn->id,
                    'id' => 'gf-verified-offer',
                    'offer_id' => 'gf-verified-offer',
                    'supplier_offer_id' => 'gf-verified-offer',
                    'validating_carrier' => 'GF',
                    'total' => 100.0,
                    'currency' => 'PKR',
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
                        'sabre_shop_context' => [
                            'pricing_information_ref' => 'pi-1',
                            'offer_ref' => 'offer-1',
                            'itinerary_ref' => 'itin-1',
                            'validating_carrier' => 'GF',
                            'fare_basis_codes' => ['WDLIT3PK', 'WDLIT3PK'],
                        ],
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
            'checkout_search_id' => 'gf-verified-checkout-search',
            'checkout_offer_id' => 'gf-verified-checkout-offer',
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
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'guest@example.test',
            'phone' => '+923001234567',
        ]);

        return $booking->fresh(['passengers', 'contact', 'supplierBookings', 'fareBreakdown']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function gfBooking46LikeConnectingBooking(array $overrides = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();

        $booking = Booking::factory()->create(array_merge([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'meta' => [
                'supplier_provider' => 'sabre',
                'supplier_connection_id' => $conn->id,
                'offer_validation_status' => 'valid',
                'search_criteria' => ['trip_type' => 'one_way'],
                'normalized_offer_snapshot' => [
                    'supplier_provider' => 'sabre',
                    'supplier_connection_id' => $conn->id,
                    'id' => 'gf-booking-46-offer',
                    'offer_id' => 'gf-booking-46-offer',
                    'supplier_offer_id' => 'gf-booking-46-offer',
                    'validating_carrier' => 'GF',
                    'total' => 100.0,
                    'currency' => 'PKR',
                    'segments' => [
                        [
                            'origin' => 'LHE',
                            'destination' => 'BAH',
                            'carrier' => 'GF',
                            'flight_number' => '765',
                            'booking_class' => 'W',
                            'fare_basis_code' => 'WDLIT3PK',
                            'departure_at' => '2026-07-31T15:10:00',
                            'arrival_at' => '2026-07-31T18:40:00',
                        ],
                        [
                            'origin' => 'BAH',
                            'destination' => 'JED',
                            'carrier' => 'GF',
                            'flight_number' => '173',
                            'booking_class' => 'W',
                            'fare_basis_code' => 'WDLIT3PK',
                            'departure_at' => '2026-08-01T18:05:00',
                            'arrival_at' => '2026-08-01T20:30:00',
                        ],
                    ],
                    'raw_payload' => [
                        'distribution_channel' => 'GDS',
                        'sabre_shop_context' => [
                            'pricing_information_ref' => 'pi-1',
                            'offer_ref' => 'offer-1',
                            'itinerary_ref' => 'itin-1',
                            'validating_carrier' => 'GF',
                            'fare_basis_codes' => ['WDLIT3PK', 'WDLIT3PK'],
                        ],
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
            'depart_date' => '2026-07-31',
            'adults' => 1,
        ], [
            'checkout_search_id' => 'gf-booking-46-checkout-search',
            'checkout_offer_id' => 'gf-booking-46-checkout-offer',
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
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'guest@example.test',
            'phone' => '+923001234567',
        ]);

        return $booking->fresh(['passengers', 'contact', 'supplierBookings', 'fareBreakdown']);
    }

    protected function pkHostNoopConnectingBooking(): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'meta' => [
                'supplier_provider' => 'sabre',
                'supplier_connection_id' => $conn->id,
                'offer_validation_status' => 'valid',
                'search_criteria' => ['trip_type' => 'one_way'],
                'normalized_offer_snapshot' => [
                    'validating_carrier' => 'PK',
                    'segments' => [
                        [
                            'origin' => 'LHE',
                            'destination' => 'KHI',
                            'carrier' => 'PK',
                            'flight_number' => '301',
                            'booking_class' => 'V',
                            'fare_basis_code' => 'VDLIT3PK',
                            'departure_at' => '2026-07-23T08:00:00Z',
                            'arrival_at' => '2026-07-23T10:00:00Z',
                        ],
                        [
                            'origin' => 'KHI',
                            'destination' => 'JED',
                            'carrier' => 'PK',
                            'flight_number' => '741',
                            'booking_class' => 'V',
                            'fare_basis_code' => 'VDLIT3PK',
                            'departure_at' => '2026-07-24T02:30:00Z',
                            'arrival_at' => '2026-07-24T05:30:00Z',
                        ],
                    ],
                ],
            ],
        ]);

        BookingPassenger::factory()->for($booking)->create([
            'passenger_index' => 0,
            'is_lead_passenger' => true,
            'first_name' => 'Test',
            'last_name' => 'Passenger',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'gender' => 'male',
            'passenger_type' => 'adult',
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'guest@example.test',
            'phone' => '+923001234567',
        ]);

        return $booking->fresh(['passengers', 'contact', 'supplierBookings']);
    }

    protected function unknownSameCarrierConnectingBooking(): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'meta' => [
                'supplier_provider' => 'sabre',
                'supplier_connection_id' => $conn->id,
                'offer_validation_status' => 'valid',
                'search_criteria' => ['trip_type' => 'one_way'],
                'normalized_offer_snapshot' => [
                    'validating_carrier' => 'SV',
                    'segments' => [
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
                    ],
                ],
            ],
        ]);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : [];
        $meta[SabreSafeRefreshContext::META_KEY] = app(SabreSafeRefreshContext::class)->buildFromCheckout($snapshot, [
            'trip_type' => 'one_way',
            'origin' => 'ISB',
            'destination' => 'DXB',
            'depart_date' => '2026-07-23',
            'adults' => 1,
        ], [
            'checkout_search_id' => 'sv-unknown-checkout-search',
            'checkout_offer_id' => 'sv-unknown-checkout-offer',
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
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'guest@example.test',
            'phone' => '+923001234567',
        ]);

        return $booking->fresh(['passengers', 'contact', 'supplierBookings']);
    }
}
