<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingFareBreakdown;
use App\Models\BookingPassenger;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Diagnostics\SabreBookingContinuityAuditor;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabreHostErrorClassifier;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreHostClassificationPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        config([
            'suppliers.sabre.refresh_offer_before_public_pnr' => false,
            'suppliers.sabre.revalidate_before_booking' => false,
            'suppliers.sabre.certified_route_selector_public_checkout_enabled' => false,
        ]);
    }

    public function test_no_fares_rbd_carrier_failure_persists_safe_host_classification(): void
    {
        $recordsPath = '/v2.5.0/passenger/records?mode=create';
        $this->stubSabreOAuthAndHttp(fn () => Http::response([
            'CreatePassengerNameRecordRS' => [
                'ApplicationResults' => [
                    'status' => 'Incomplete',
                    'Warning' => [[
                        'SystemSpecificResults' => [[
                            'Message' => [[
                                'code' => 'ERR.SP.PROVIDER_ERROR',
                                'content' => 'EnhancedAirBookRQ: *NO FARES/RBD/CARRIER',
                            ]],
                        ]],
                    ]],
                ],
            ],
        ], 200));
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.booking_path' => $recordsPath,
            'suppliers.sabre.booking_schema' => 'passenger_records_create_pnr',
            'suppliers.sabre.revalidate_before_booking' => false,
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.passenger_records_fresh_shop_guard_before_live' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
        ]);

        $booking = $this->seedLivePnrBooking('sabre-11kd-no-fares-offer');
        $statusBefore = $booking->status;

        $svc = app(SabreBookingService::class);
        $result = $svc->runPublicReviewDryRun($booking->fresh(['passengers', 'contact', 'fareBreakdown']));

        $this->assertSame('needs_review', $result['status'] ?? null);
        $this->assertSame('sabre_booking_application_error', $result['error_code'] ?? null);
        $this->assertTrue((bool) ($result['live_call_attempted'] ?? false));
        $this->assertArrayNotHasKey('sabre_host_classification', $result);

        $booking->refresh();
        $this->assertSame($statusBefore, $booking->status);
        $classification = data_get($booking->meta, 'sabre_checkout_outcome.sabre_host_classification');
        $this->assertIsArray($classification);
        $this->assertSame(SabreHostErrorClassifier::REASON_NO_FARES_RBD_CARRIER, $classification['safe_reason_code'] ?? null);
        $this->assertSame(
            SabreHostErrorClassifier::HOST_ERROR_FAMILY_NO_FARES_RBD_CARRIER,
            $classification['host_error_family'] ?? null
        );
        $this->assertSame(SabreHostErrorClassifier::CLASSIFIER_VERSION, $classification['classifier_version'] ?? null);
        $this->assertNotEmpty($classification['admin_summary'] ?? null);
        $this->assertNotEmpty($classification['recorded_at'] ?? null);
        $this->assertTrue($classification['live_call_attempted'] ?? false);

        $encoded = json_encode($classification, JSON_THROW_ON_ERROR);
        foreach ([
            'response_error_messages',
            'CreatePassengerNameRecordRQ',
            'PassengerName',
            'FormOfPayment',
            'Telephone',
            'NO FARES/RBD/CARRIER',
            '11kd-no-fares@example.com',
            '+923001112233',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $encoded, 'Classification leaked: '.$forbidden);
        }
    }

    public function test_finalize_checkout_storage_persists_host_classification_without_top_level_result_echo(): void
    {
        $booking = $this->makeSabreBooking($this->completeOneSegmentSnapshot(), [
            'normalized_offer_snapshot' => $this->completeOneSegmentSnapshot(),
        ]);
        $statusBefore = $booking->status;

        app(SabreBookingService::class)->finalizePublicCheckoutSabreStorage($booking, [
            'success' => false,
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'live_call_attempted' => true,
            'http_status' => 200,
            'booking_schema' => 'create_passenger_name_record',
            'payload_schema' => 'traditional_pnr_create_passenger_name_record_v1',
            'segment_count' => 1,
            'passenger_count' => 1,
            'response_error_messages' => ['EnhancedAirBookRQ: *NO FARES/RBD/CARRIER'],
            'response_error_codes' => ['ERR.SP.PROVIDER_ERROR'],
        ]);

        $booking->refresh();
        $this->assertSame($statusBefore, $booking->status);
        $classification = data_get($booking->meta, 'sabre_checkout_outcome.sabre_host_classification');
        $this->assertIsArray($classification);
        $this->assertSame(SabreHostErrorClassifier::REASON_NO_FARES_RBD_CARRIER, $classification['safe_reason_code'] ?? null);
        $this->assertArrayNotHasKey('response_error_messages', $classification);
    }

    public function test_certified_route_pending_does_not_persist_host_classification(): void
    {
        config([
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled' => false,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => false,
            'suppliers.sabre.certified_route_selector_public_checkout_enabled' => true,
        ]);
        Http::fake();

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $depart = now()->addDays(12)->toDateString();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $sabreConn->id,
                'flight_offer_snapshot' => [
                    'id' => '11kd-connecting-offer',
                    'supplier_provider' => 'sabre',
                    'supplier_connection_id' => $sabreConn->id,
                    'segments' => [
                        [
                            'origin' => 'LHE',
                            'destination' => 'DXB',
                            'carrier' => 'EK',
                            'flight_number' => '615',
                            'departure_at' => $depart.'T10:00:00Z',
                            'arrival_at' => $depart.'T14:00:00Z',
                            'booking_class' => 'K',
                        ],
                        [
                            'origin' => 'DXB',
                            'destination' => 'DOH',
                            'carrier' => 'EK',
                            'flight_number' => '847',
                            'departure_at' => $depart.'T18:00:00Z',
                            'arrival_at' => $depart.'T18:45:00Z',
                            'booking_class' => 'K',
                        ],
                    ],
                    'fare_breakdown' => [
                        'supplier_total' => 120000,
                        'currency' => 'PKR',
                        'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
                    ],
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
            'email' => '11kd-certified@example.com',
            'phone' => '+923001112244',
            'country' => 'Pakistan',
            'meta' => [],
        ]);
        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 100000,
            'taxes' => 10000,
            'fees' => 0,
            'markup' => 10000,
            'discount' => 0,
            'total' => 120000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        $result = app(SabreBookingService::class)->runPublicReviewDryRun(
            $booking->fresh(['passengers', 'contact', 'fareBreakdown'])
        );

        $this->assertSame(SabreCertifiedRouteSelector::ERROR_CODE_PENDING, $result['error_code'] ?? null);
        $this->assertFalse((bool) ($result['live_call_attempted'] ?? true));
        $booking->refresh();
        $this->assertNull(data_get($booking->meta, 'sabre_checkout_outcome.sabre_host_classification'));
    }

    public function test_continuity_auditor_reports_blocked_host_rejected_for_host_segment_status(): void
    {
        $snapshot = $this->completeOneSegmentSnapshot();
        $booking = $this->makeSabreBooking($snapshot, [
            'sabre_checkout_outcome' => [
                'status' => 'needs_review',
                'error_code' => 'sabre_booking_application_error',
                'live_call_attempted' => true,
                'airline_segment_status' => 'NN',
                'halt_on_status_received' => true,
                'sabre_host_classification' => SabreHostErrorClassifier::buildPersistedSlice(
                    [
                        'error_code' => 'sabre_booking_application_error',
                        'halt_on_status_received' => true,
                        'airline_segment_status' => 'NN',
                        'response_error_messages' => ['Flight EK623 returned status code NN'],
                    ],
                    [
                        'live_call_attempted' => true,
                        'segment_count' => 1,
                        'passenger_count' => 1,
                    ],
                ),
            ],
        ]);

        $report = $this->app->make(SabreBookingContinuityAuditor::class)->audit($booking);

        $this->assertTrue($report['host_outcome_overlay']['host_outcome_present'] ?? false);
        $this->assertTrue($report['host_outcome_overlay']['host_rejection_evidence_present'] ?? false);
        $this->assertSame(
            SabreBookingContinuityAuditor::HOST_ERROR_FAMILY_HOST_SEGMENT_STATUS,
            $report['host_outcome_overlay']['host_error_family'] ?? null
        );
        $this->assertSame(
            SabreHostErrorClassifier::REASON_HOST_SEGMENT_STATUS_UNCONFIRMED,
            $report['host_outcome_overlay']['host_safe_reason_code'] ?? null
        );
        $this->assertSame(
            SabreBookingContinuityAuditor::FINAL_REC_BLOCKED_HOST_REJECTED,
            $report['final_diagnostic_recommendation'] ?? null
        );
    }

    public function test_continuity_auditor_consumes_persisted_classification_for_host_overlay(): void
    {
        $snapshot = $this->completeOneSegmentSnapshot();
        $booking = $this->makeSabreBooking($snapshot, [
            'sabre_checkout_outcome' => [
                'status' => 'needs_review',
                'error_code' => 'sabre_booking_application_error',
                'live_call_attempted' => true,
                'sabre_host_classification' => SabreHostErrorClassifier::buildPersistedSlice(
                    [
                        'error_code' => 'sabre_booking_application_error',
                        'response_error_messages' => ['EnhancedAirBookRQ: *NO FARES/RBD/CARRIER'],
                    ],
                    [
                        'live_call_attempted' => true,
                        'segment_count' => 1,
                        'passenger_count' => 1,
                    ],
                ),
            ],
        ]);

        $report = $this->app->make(SabreBookingContinuityAuditor::class)->audit($booking);

        $this->assertTrue($report['host_outcome_overlay']['host_outcome_present'] ?? false);
        $this->assertTrue($report['host_outcome_overlay']['host_rejection_evidence_present'] ?? false);
        $this->assertSame(
            SabreBookingContinuityAuditor::HOST_ERROR_FAMILY_NO_FARES_RBD_CARRIER,
            $report['host_outcome_overlay']['host_error_family'] ?? null
        );
        $this->assertSame(
            SabreBookingContinuityAuditor::FINAL_REC_BLOCKED_HOST_REJECTED,
            $report['final_diagnostic_recommendation'] ?? null
        );
    }

    public function test_missing_rbd_local_blocker_precedes_host_overlay(): void
    {
        $snapshot = $this->completeOneSegmentSnapshot();
        unset($snapshot['segments'][0]['booking_class']);
        $raw = $snapshot['raw_payload'];
        unset($raw['sabre_booking_context']['booking_classes_by_segment']);
        unset($raw['sabre_shop_context']['booking_classes_by_segment']);
        $snapshot['raw_payload'] = $raw;

        $booking = $this->makeSabreBooking($snapshot, [
            'sabre_checkout_outcome' => [
                'status' => 'needs_review',
                'error_code' => 'sabre_booking_application_error',
                'live_call_attempted' => true,
                'sabre_host_classification' => SabreHostErrorClassifier::buildPersistedSlice(
                    [
                        'error_code' => 'sabre_booking_application_error',
                        'airline_segment_status' => 'UC',
                    ],
                    ['live_call_attempted' => true],
                ),
            ],
        ]);

        $report = $this->app->make(SabreBookingContinuityAuditor::class)->audit($booking);

        $this->assertSame('blocked_missing_rbd', $report['readiness_recommendation'] ?? null);
        $this->assertSame('blocked_missing_rbd', $report['final_diagnostic_recommendation'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    protected function completeOneSegmentSnapshot(): array
    {
        return [
            'offer_id' => '11kd-offer-1',
            'supplier_offer_id' => '11kd-offer-1',
            'supplier_provider' => 'sabre',
            'validating_carrier' => 'EK',
            'distribution_channel' => 'GDS',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2026-10-01T10:00:00',
                'arrival_at' => '2026-10-01T14:00:00',
                'carrier' => 'EK',
                'marketing_carrier' => 'EK',
                'operating_carrier' => 'EK',
                'flight_number' => '615',
                'booking_class' => 'K',
                'fare_basis_code' => 'KLOW',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 500,
                'currency' => 'USD',
                'base_fare' => 400,
                'taxes' => 100,
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
                'fare_basis_codes' => ['KLOW'],
            ],
            'raw_payload' => [
                'distribution_channel' => 'GDS',
                'shop_endpoint_path' => '/v4/offers/shop',
                'sabre_shop_context' => [
                    'distribution_channel' => 'GDS',
                    'shop_endpoint_path' => '/v4/offers/shop',
                    'itinerary_ref' => '10',
                    'pricing_information_index' => 2,
                    'leg_refs' => [3],
                    'schedule_refs' => [9],
                    'validating_carrier' => 'EK',
                    'booking_classes_by_segment' => ['K'],
                    'fare_basis_codes_by_segment' => ['KLOW'],
                ],
                'sabre_booking_context' => [
                    'itinerary_reference' => '10',
                    'pricing_information_index' => 2,
                    'validating_carrier' => 'EK',
                    'booking_classes_by_segment' => ['K'],
                    'fare_basis_codes_by_segment' => ['KLOW'],
                    'segment_slice_count' => 1,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $metaExtra
     */
    protected function makeSabreBooking(array $snapshot, array $metaExtra = []): Booking
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
        $snapshot['supplier_connection_id'] = $sabreConn->id;

        return Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => array_merge([
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $sabreConn->id,
                'normalized_offer_snapshot' => $snapshot,
            ], $metaExtra),
        ]);
    }

    protected function seedLivePnrBooking(string $offerId): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'base_url' => 'https://example.sabre.test',
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'sec'],
        ]);

        $depart = now()->addDays(10)->toDateString();
        $offer = [
            'id' => $offerId,
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $sabreConn->id,
            'airline_code' => 'SV',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'JED',
                'carrier' => 'SV',
                'flight_number' => '739',
                'departure_at' => $depart.'T09:00:00Z',
                'arrival_at' => $depart.'T12:00:00Z',
                'booking_class' => 'K',
            ]],
            'fare_breakdown' => [
                'supplier_total' => 95000,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
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
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
            'passport_number' => 'AB9999999',
            'passport_issuing_country' => 'PK',
            'passport_expiry_date' => '2035-12-31',
            'nationality' => 'PK',
            'document_type' => 'passport',
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => '11kd-no-fares@example.com',
            'phone' => '+923001112233',
            'country' => 'Pakistan',
            'meta' => [],
        ]);
        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 75000,
            'taxes' => 10000,
            'fees' => 0,
            'markup' => 10000,
            'discount' => 0,
            'total' => 95000,
            'currency' => 'PKR',
            'breakdown' => [],
        ]);

        return $booking;
    }

    /**
     * @param  callable(): mixed  $afterTokenResponder
     */
    protected function stubSabreOAuthAndHttp(callable $afterTokenResponder): void
    {
        Http::fake(function (Request $request, array $options) use ($afterTokenResponder) {
            $payload = $options['laravel_data'] ?? [];
            $tokenPath = strtolower((string) config('suppliers.sabre.token_path', '/v2/auth/token'));
            $isOAuth = str_contains(strtolower($request->url()), $tokenPath)
                || (is_array($payload) && array_key_exists('grant_type', $payload));

            if ($isOAuth) {
                return Http::response(['access_token' => 'tok-test-stub', 'expires_in' => 3600], 200);
            }

            return $afterTokenResponder();
        });
    }
}
