<?php

namespace Tests\Unit\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Support\Bookings\SabreCpnrOperationalAllowNnPolicy;
use App\Support\Bookings\SabreCreateAttemptSafeCompare;
use App\Support\Bookings\SabreCreatePayloadSafeSummary;
use App\Support\Bookings\SabreSafeRefreshContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SabreCreatePayloadSafeSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summarize_create_payload_includes_two_segments_without_raw_request_or_response(): void
    {
        $offer = $this->pkConnectingOffer();
        $passengerData = [
            'contact' => ['email' => 'pax@example.com', 'phone' => '+923001234567'],
            'passengers' => [
                [
                    'passenger_type' => 'adult',
                    'first_name' => 'Test',
                    'last_name' => 'Traveler',
                    'gender' => 'male',
                    'date_of_birth' => '1990-01-01',
                ],
            ],
        ];

        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = $builder->buildInternalDraft($offer, $passengerData);
        $envelope = $builder->buildPassengerRecordsCpnrWireForStyle(
            $draft,
            [],
            SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
        );

        $summary = app(SabreCreatePayloadSafeSummary::class)->summarize(
            $envelope,
            array_values($offer['segments']),
            [
                'create_endpoint_path' => '/v2.4.0/passenger/records?mode=create',
                'create_payload_style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
                'create_segment_source' => 'refreshed_offer',
            ],
        );

        $this->assertSame('/v2.4.0/passenger/records?mode=create', $summary['create_endpoint_path']);
        $this->assertSame(SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS, $summary['create_payload_style']);
        $this->assertSame(2, $summary['create_segment_count']);
        $this->assertSame('refreshed_offer', $summary['create_segment_source']);
        $this->assertTrue($summary['create_ticketing_disabled']);
        $this->assertCount(2, $summary['create_segments_summary']);
        $this->assertSame('PK', $summary['create_segments_summary'][0]['carrier']);
        $this->assertSame('V', $summary['create_segments_summary'][0]['booking_class']);
        $this->assertSame('2026-07-23', $summary['create_segments_summary'][0]['departure_date']);
        $this->assertSame('2026-07-24', $summary['create_segments_summary'][1]['departure_date']);
        $this->assertTrue($summary['create_segment_linkage_present']);
        $this->assertTrue($summary['create_marriage_group_present']);
        $this->assertFalse($summary['create_action_code_present']);
        $this->assertSame(['NN'], $summary['create_status_codes']);
        $this->assertSame(['1'], $summary['create_number_in_party_values']);
        $this->assertSame(1, $summary['create_od_group_count']);
        $this->assertSame([2], $summary['create_segments_per_od_group']);
        $this->assertContains('NN', $summary['create_halt_on_status_codes']);
        $this->assertTrue($summary['create_nn_halt_fatal_without_policy']);
        $this->assertSame('NN', $summary['create_segment_sell_status_intent']);
        $this->assertFalse($summary['create_halt_on_status_nn_omitted']);
        $this->assertSame(SabreCpnrOperationalAllowNnPolicy::POLICY_DEFAULT_IATI_WITH_NN, $summary['create_halt_on_status_policy']);
        $this->assertTrue($summary['create_air_price_present']);
        $this->assertSame('E5A_SAFE_STRUCTURE_V1', $summary['create_payload_strategy_version']);
        $this->assertSame('PK', $summary['create_marketing_operating_carrier_summary'][0]['marketing']);
        $this->assertArrayNotHasKey('raw_payload', $summary['create_marketing_operating_carrier_summary'][0]);
        $slice = app(SabreCreatePayloadSafeSummary::class)->sliceForAttemptPersistence($summary);
        $this->assertSame([2], $slice['create_segments_per_od_group']);
        $this->assertFalse($slice['create_action_code_present']);
        $this->assertFalse(app(SabreCreatePayloadSafeSummary::class)->containsForbiddenKeys($summary));
        $this->assertArrayNotHasKey('CreatePassengerNameRecordRQ', $summary);
        $this->assertArrayNotHasKey('raw_payload', $summary);
        $this->assertArrayNotHasKey('response_body', $summary);
    }

    public function test_resolve_segment_source_prefers_refreshed_offer_after_controlled_refresh(): void
    {
        $agency = Agency::factory()->create();
        $offer = $this->pkConnectingOffer();
        $meta = [
            'supplier_provider' => SupplierProvider::Sabre->value,
            'offer_refresh_status' => 'refreshed',
            'offer_refresh_refreshed_at' => '2026-06-12T11:23:36+00:00',
            'normalized_offer_snapshot' => $offer,
            SabreSafeRefreshContext::META_KEY => app(SabreSafeRefreshContext::class)->buildFromCheckout($offer, [
                'trip_type' => 'one_way',
                'origin' => 'LHE',
                'destination' => 'JED',
                'depart_date' => '2026-07-23',
            ], ['supplier_total' => 50000.0, 'supplier_currency' => 'PKR']),
        ];
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'meta' => $meta,
        ]);

        $source = app(SabreCreatePayloadSafeSummary::class)->resolveSegmentSource($booking->id, $offer);

        $this->assertSame('refreshed_offer', $source);
    }

    public function test_create_payload_summary_persisted_on_live_application_error_attempt(): void
    {
        $agency = Agency::factory()->create();
        $booking = Booking::factory()->create(['agency_id' => $agency->id]);

        $service = app(SabreBookingService::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('mapCreateBookingArrayToSupplierResult');
        $method->setAccessible(true);
        $method->invoke($service, $booking, null, [
            'success' => false,
            'status' => 'needs_review',
            'message' => 'Host NOOP',
            'error_code' => 'sabre_booking_application_error',
            'live_call_attempted' => true,
            'http_status' => 200,
            'booking_schema' => 'create_passenger_name_record',
            'payload_schema' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            'endpoint_path' => '/v2.4.0/passenger/records?mode=create',
            'passenger_count' => 1,
            'segment_count' => 2,
            'response_error_codes' => ['0118'],
            'create_payload_safe_summary' => [
                'create_endpoint_path' => '/v2.4.0/passenger/records?mode=create',
                'create_payload_style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
                'create_segment_count' => 2,
                'create_segment_source' => 'refreshed_offer',
                'create_segments_summary' => [
                    [
                        'carrier' => 'PK',
                        'flight_number' => '301',
                        'origin' => 'LHE',
                        'destination' => 'KHI',
                        'departure_date' => '2026-07-23',
                        'departure_time' => '08:00',
                        'booking_class' => 'V',
                    ],
                    [
                        'carrier' => 'PK',
                        'flight_number' => '741',
                        'origin' => 'KHI',
                        'destination' => 'JED',
                        'departure_date' => '2026-07-24',
                        'departure_time' => '02:30',
                        'booking_class' => 'V',
                    ],
                ],
                'create_ticketing_disabled' => true,
            ],
        ]);

        $attempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($attempt);
        $safe = is_array($attempt->safe_summary) ? $attempt->safe_summary : [];
        $this->assertSame(2, $safe['create_segment_count']);
        $this->assertSame('refreshed_offer', $safe['create_segment_source']);
        $this->assertCount(2, $safe['create_segments_summary']);
        $this->assertFalse(app(SabreCreatePayloadSafeSummary::class)->containsForbiddenKeys($safe));
    }

    public function test_compare_helper_diffs_success_and_failure_create_summaries(): void
    {
        $agency = Agency::factory()->create();
        $booking40 = Booking::factory()->create(['agency_id' => $agency->id]);
        $booking43 = Booking::factory()->create(['agency_id' => $agency->id]);

        $successSummary = [
            'create_endpoint_path' => '/v2.4.0/passenger/records?mode=create',
            'create_payload_style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            'create_segment_count' => 1,
            'create_segment_source' => 'original_booking',
            'create_segments_summary' => [
                ['carrier' => 'GF', 'flight_number' => '123', 'booking_class' => 'Y'],
            ],
            'http_status' => 200,
            'pnr' => 'QPXBOE',
        ];
        $failSummary = [
            'create_endpoint_path' => '/v2.4.0/passenger/records?mode=create',
            'create_payload_style' => SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS,
            'create_segment_count' => 2,
            'create_segment_source' => 'refreshed_offer',
            'create_segments_summary' => [
                ['carrier' => 'PK', 'flight_number' => '301', 'booking_class' => 'V'],
                ['carrier' => 'PK', 'flight_number' => '741', 'booking_class' => 'V'],
            ],
            'http_status' => 200,
            'response_error_codes' => ['0118'],
            'host_warning_messages_truncated' => ['FLIGHT NOOP FOR THIS FLIGHT/DATE'],
        ];

        $attempt40 = SupplierBookingAttempt::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking40->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'success',
            'safe_summary' => $successSummary,
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);
        $attempt43 = SupplierBookingAttempt::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking43->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'safe_summary' => $failSummary,
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        $out = app(SabreCreateAttemptSafeCompare::class)->compareAttempts([
            $attempt40->id,
            $attempt43->id,
        ]);

        $this->assertTrue($out['field_diff']['comparable']);
        $this->assertGreaterThan(0, $out['field_diff']['diff_count']);
        $this->assertArrayHasKey('create_segment_count', $out['field_diff']['diffs']);
        $this->assertArrayHasKey('create_segments_summary', $out['field_diff']['diffs']);
    }

    public function test_compare_latest_create_attempt_ignores_retry_blocked_wrapper(): void
    {
        $agency = Agency::factory()->create();
        $booking43 = Booking::factory()->create(['agency_id' => $agency->id]);

        $hostNoopAttempt = SupplierBookingAttempt::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking43->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'needs_review',
            'error_code' => 'sabre_booking_application_error',
            'safe_summary' => [
                'create_segment_count' => 2,
                'create_segments_summary' => [
                    ['carrier' => 'PK', 'flight_number' => '301', 'booking_class' => 'V'],
                ],
                'response_error_messages' => ['EnhancedAirBookRQ: FLIGHT NOOP FOR THIS FLIGHT/DATE'],
            ],
            'attempted_at' => now()->subMinute(),
            'completed_at' => now()->subMinute(),
        ]);
        SupplierBookingAttempt::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking43->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'blocked',
            'error_code' => 'supplier_booking_retry_not_allowed',
            'safe_summary' => [
                'source' => 'admin',
                'prior_error_code' => 'sabre_booking_application_error',
            ],
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        $out = app(SabreCreateAttemptSafeCompare::class)->compareLatestCreateAttemptsForBookings([$booking43->id]);
        $rows = $out['attempts'] ?? [];
        $this->assertCount(1, $rows);
        $this->assertSame($hostNoopAttempt->id, $rows[0]['attempt_id']);
        $this->assertSame('needs_review', $rows[0]['status']);
        $this->assertArrayHasKey('create_segments_summary', $rows[0]);
    }

    /**
     * @return array<string, mixed>
     */
    private function pkConnectingOffer(): array
    {
        return [
            'id' => 'offer-pk-connect',
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => 1,
            'validating_carrier' => 'PK',
            'currency' => 'PKR',
            'fare_breakdown' => ['supplier_total' => 50000.0, 'currency' => 'PKR'],
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'KHI',
                    'departure_at' => '2026-07-23T08:00:00',
                    'arrival_at' => '2026-07-23T09:30:00',
                    'carrier' => 'PK',
                    'flight_number' => '301',
                    'booking_class' => 'V',
                    'fare_basis_code' => 'VLOWPK',
                ],
                [
                    'origin' => 'KHI',
                    'destination' => 'JED',
                    'departure_at' => '2026-07-24T02:30:00',
                    'arrival_at' => '2026-07-24T05:00:00',
                    'carrier' => 'PK',
                    'flight_number' => '741',
                    'booking_class' => 'V',
                    'fare_basis_code' => 'VLOWPK',
                ],
            ],
        ];
    }
}
