<?php

namespace Tests\Feature;

use App\Data\SupplierBookingResultData;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Support\Bookings\SabrePnrCertificationClassifier;
use App\Support\Bookings\SabrePnrCertificationSupport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class SabrePnrCertificationCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('app.env', 'testing');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_dry_run_one_way_direct_outputs_readiness_without_http(): void
    {
        Http::fake();
        $booking = $this->oneWayBooking(segmentCount: 1);

        $mock = Mockery::mock(SabreBookingService::class);
        $mock->shouldReceive('assessAutoPnrPricingContextReadinessForBooking')
            ->once()
            ->andReturn($this->incompletePricingReadiness());
        $mock->shouldReceive('inspectBookingPayloadShapeForCommand')
            ->once()
            ->andReturn([
                'validation_ok' => true,
                'wire_contract_valid' => true,
                'segment_count' => 1,
                'booking_schema' => 'create_passenger_name_record',
            ]);
        $mock->shouldNotReceive('createSupplierBookingForCertification');
        $this->app->instance(SabreBookingService::class, $mock);

        $exit = Artisan::call('sabre:certify-pnr', ['--booking' => $booking->id]);
        $payload = $this->decodeOutput();

        $this->assertSame(0, $exit);
        $this->assertSame('dry_run', $payload['mode']);
        $this->assertSame('one_way_direct', $payload['trip_type']);
        $this->assertTrue($payload['wire_contract_valid']);
        $this->assertTrue($payload['readiness']['has_passenger']);
        Http::assertNothingSent();
    }

    public function test_dry_run_round_trip_reports_complex_guard_would_defer_public(): void
    {
        Http::fake();
        $booking = Booking::factory()->create([
            'supplier' => 'sabre',
            'meta' => [
                'supplier_provider' => 'sabre',
                'search_criteria' => ['trip_type' => 'round_trip'],
                'flight_offer_snapshot' => [
                    'segments' => [
                        ['origin' => 'KHI', 'destination' => 'DXB', 'carrier' => 'EK', 'booking_class' => 'Y'],
                        ['origin' => 'DXB', 'destination' => 'KHI', 'carrier' => 'EK', 'booking_class' => 'Y'],
                    ],
                ],
            ],
        ]);
        BookingPassenger::factory()->create(['booking_id' => $booking->id, 'passenger_index' => 1]);

        $mock = Mockery::mock(SabreBookingService::class);
        $mock->shouldReceive('assessAutoPnrPricingContextReadinessForBooking')
            ->once()
            ->andReturn($this->incompletePricingReadiness());
        $mock->shouldReceive('inspectBookingPayloadShapeForCommand')->once()->andReturn([
            'validation_ok' => true,
            'wire_contract_valid' => true,
            'segment_count' => 2,
        ]);
        $this->app->instance(SabreBookingService::class, $mock);

        $exit = Artisan::call('sabre:certify-pnr', ['--booking' => $booking->id]);
        $payload = $this->decodeOutput();

        $this->assertSame(0, $exit);
        $this->assertSame('round_trip', $payload['trip_type']);
        $this->assertTrue($payload['r5_public_checkout_would_defer']);
        $this->assertSame(
            SabrePnrCertificationClassifier::COMPLEX_GUARD_WOULD_DEFER_PUBLIC,
            $payload['classification'],
        );
    }

    public function test_send_mocked_success_stores_pnr_and_expiry_when_returned(): void
    {
        $booking = $this->oneWayBooking(segmentCount: 1);
        $expires = now()->addHours(6)->toIso8601String();

        $mock = Mockery::mock(SabreBookingService::class);
        $this->expectPricingReadinessOnMock($mock);
        $mock->shouldReceive('createSupplierBookingForCertification')
            ->once()
            ->andReturnUsing(function () use ($booking, $expires) {
                $booking->forceFill(['pnr' => 'ABC123'])->save();

                return new SupplierBookingResultData(
                    success: true,
                    status: 'pending_ticketing',
                    provider: 'sabre',
                    pnr: 'ABC123',
                    safe_summary: [
                        'ticketing_time_limit' => $expires,
                        'http_status' => 200,
                    ],
                );
            });
        $this->app->instance(SabreBookingService::class, $mock);

        $exit = Artisan::call('sabre:certify-pnr', ['--booking' => $booking->id, '--mode' => 'send']);
        $this->assertSame(0, $exit);
        $payload = $this->decodeOutput();
        $booking->refresh();

        $this->assertSame('send', $payload['mode']);
        $this->assertTrue($payload['pnr_created']);
        $this->assertSame('ABC123', $payload['pnr']);
        $this->assertSame(SabrePnrCertificationClassifier::SUCCESS_PNR_CREATED, $payload['classification']);
        $this->assertNotNull($booking->meta[SabrePnrCertificationSupport::META_EXPIRES_AT] ?? null);
        $this->assertSame('create_response', $booking->meta[SabrePnrCertificationSupport::META_EXPIRY_SOURCE] ?? null);
    }

    public function test_send_mocked_success_without_expiry_stores_no_fake_expiry(): void
    {
        Http::fake();
        $booking = $this->oneWayBooking(segmentCount: 1);

        $mock = Mockery::mock(SabreBookingService::class);
        $this->expectPricingReadinessOnMock($mock);
        $mock->shouldReceive('createSupplierBookingForCertification')
            ->once()
            ->andReturnUsing(function () use ($booking) {
                $booking->forceFill(['pnr' => 'DEF456'])->save();

                return new SupplierBookingResultData(
                    success: true,
                    status: 'pending_ticketing',
                    provider: 'sabre',
                    pnr: 'DEF456',
                    safe_summary: ['http_status' => 200],
                );
            });
        $this->app->instance(SabreBookingService::class, $mock);

        $exit = Artisan::call('sabre:certify-pnr', ['--booking' => $booking->id, '--mode' => 'send']);
        $this->assertSame(0, $exit);
        $payload = $this->decodeOutput();
        $booking->refresh();

        $this->assertSame('send', $payload['mode']);
        $this->assertSame(SabrePnrCertificationClassifier::PNR_CREATED_NO_EXPIRY, $payload['classification']);
        $this->assertNull($booking->meta[SabrePnrCertificationSupport::META_EXPIRES_AT] ?? null);
    }

    public function test_uc_response_classifies_safely(): void
    {
        $booking = $this->oneWayBooking(segmentCount: 1);

        $mock = Mockery::mock(SabreBookingService::class);
        $this->expectPricingReadinessOnMock($mock);
        $mock->shouldReceive('createSupplierBookingForCertification')
            ->once()
            ->andReturn(new SupplierBookingResultData(
                success: false,
                status: 'manual_review',
                provider: 'sabre',
                error_code: 'sabre_booking_application_error',
                error_message: 'Host error',
                safe_summary: [
                    'response_error_messages' => [
                        'Segment SV739 returned status code UC',
                        'HALT_ON_STATUS_RECEIVED',
                    ],
                ],
            ));
        $this->app->instance(SabreBookingService::class, $mock);

        $exit = Artisan::call('sabre:certify-pnr', ['--booking' => $booking->id, '--mode' => 'send']);
        $this->assertSame(1, $exit);
        $payload = $this->decodeOutput();

        $this->assertSame('send', $payload['mode']);
        $this->assertFalse($payload['pnr_created']);
        $this->assertSame(SabrePnrCertificationClassifier::HOST_SELL_REJECTED_UC, $payload['classification']);
        $this->assertContains('UC', $payload['host_statuses']);
    }

    public function test_no_fares_response_classifies_safely(): void
    {
        $booking = $this->oneWayBooking(segmentCount: 1);

        $mock = Mockery::mock(SabreBookingService::class);
        $this->expectPricingReadinessOnMock($mock);
        $mock->shouldReceive('createSupplierBookingForCertification')
            ->once()
            ->andReturn(new SupplierBookingResultData(
                success: false,
                status: 'manual_review',
                provider: 'sabre',
                error_code: 'sabre_booking_application_error',
                safe_summary: [
                    'response_error_messages' => ['EnhancedAirBookRQ: *NO FARES/RBD/CARRIER'],
                ],
            ));
        $this->app->instance(SabreBookingService::class, $mock);

        $exit = Artisan::call('sabre:certify-pnr', ['--booking' => $booking->id, '--mode' => 'send']);
        $this->assertSame(1, $exit);
        $payload = $this->decodeOutput();

        $this->assertSame('send', $payload['mode']);
        $this->assertSame(SabrePnrCertificationClassifier::PNR_REQUIRES_MANUAL_SABRE_PRICING, $payload['classification']);
        $this->assertFalse($payload['pricing_context_ready']);
        $this->assertNotEmpty($payload['missing_pricing_context_fields']);
    }

    public function test_command_output_contains_no_raw_payload_token_or_passenger_pii(): void
    {
        $booking = $this->oneWayBooking(segmentCount: 1);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'secret-traveler@example.com',
            'phone' => '+923001234567',
            'name' => 'Contact',
        ]);
        $booking->passengers()->first()?->forceFill([
            'passport_number' => 'AB1234567',
            'first_name' => 'Ali',
            'last_name' => 'Khan',
        ])->save();

        $mock = Mockery::mock(SabreBookingService::class);
        $this->expectPricingReadinessOnMock($mock);
        $mock->shouldReceive('inspectBookingPayloadShapeForCommand')->once()->andReturn([
            'validation_ok' => true,
            'wire_contract_valid' => true,
            'segment_count' => 1,
        ]);
        $this->app->instance(SabreBookingService::class, $mock);

        Artisan::call('sabre:certify-pnr', ['--booking' => $booking->id]);
        $output = Artisan::output();

        $this->assertStringNotContainsString('secret-traveler@example.com', $output);
        $this->assertStringNotContainsString('AB1234567', $output);
        $this->assertStringNotContainsString('request_payload', strtolower($output));
        $this->assertStringNotContainsString('bearer', strtolower($output));
        $this->assertStringNotContainsString('Ali', $output);
    }

    public function test_send_multi_segment_auto_runs_certification_revalidate_first(): void
    {
        $booking = $this->oneWayBooking(segmentCount: 2);
        $booking->forceFill([
            'meta' => array_merge(is_array($booking->meta) ? $booking->meta : [], [
                'flight_offer_snapshot' => [
                    'segments' => [
                        ['origin' => 'LHE', 'destination' => 'KHI', 'carrier' => 'PK', 'booking_class' => 'O'],
                        ['origin' => 'KHI', 'destination' => 'DXB', 'carrier' => 'EK', 'operating_carrier' => 'FZ', 'booking_class' => 'Y'],
                    ],
                    'validating_carrier' => 'EK',
                ],
            ]),
        ])->save();

        $mock = Mockery::mock(SabreBookingService::class);
        $this->expectPricingReadinessOnMock($mock);
        $mock->shouldReceive('runCertificationRevalidateFirst')
            ->once()
            ->andReturn(['attempted' => true, 'success' => false, 'includes_sabre_error_27131' => false]);
        $mock->shouldNotReceive('persistCertificationRevalidateLinkageForBooking');
        $mock->shouldReceive('createSupplierBookingForCertification')
            ->once()
            ->andReturn(new SupplierBookingResultData(
                success: false,
                status: 'manual_review',
                provider: 'sabre',
                error_code: 'sabre_booking_application_error',
                safe_summary: ['response_error_messages' => ['EnhancedAirBookRQ: *NO FARES/RBD/CARRIER']],
            ));
        $this->app->instance(SabreBookingService::class, $mock);

        $exit = Artisan::call('sabre:certify-pnr', ['--booking' => $booking->id, '--mode' => 'send']);
        $payload = $this->decodeOutput();

        $this->assertSame(1, $exit);
        $this->assertTrue($payload['certification_revalidate_required']);
        $this->assertContains('multi_segment', $payload['certification_revalidate_reasons']);
        $this->assertTrue($payload['revalidate_first_attempted']);
        $this->assertFalse($payload['revalidate_success']);
    }

    public function test_send_records_certification_attempt_action(): void
    {
        Http::fake();
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => false,
            'suppliers.sabre.booking_schema' => 'create_passenger_name_record',
        ]);

        $booking = $this->oneWayBooking(segmentCount: 1);

        Artisan::call('sabre:certify-pnr', ['--booking' => $booking->id, '--mode' => 'send']);

        $this->assertDatabaseHas('supplier_booking_attempts', [
            'booking_id' => $booking->id,
            'action' => SabrePnrCertificationSupport::ACTION_CERTIFICATION,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeOutput(): array
    {
        $output = trim(Artisan::output());
        $line = '';
        foreach (preg_split('/\R/', trim($output)) ?: [] as $row) {
            if (str_starts_with($row, 'pnr_certification_json=')) {
                $line = $row;
                break;
            }
        }
        $this->assertStringStartsWith('pnr_certification_json=', $line);
        $decoded = json_decode(substr($line, strlen('pnr_certification_json=')), true);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    protected function incompletePricingReadiness(): array
    {
        return [
            'has_selected_passenger_info' => false,
            'has_pricing_information_ref' => false,
            'has_offer_reference' => false,
            'has_itinerary_reference' => false,
            'has_fare_component_refs' => false,
            'has_fare_component_desc_refs' => false,
            'has_validating_carrier' => true,
            'has_fare_basis_codes' => true,
            'has_revalidation_linkage_complete' => false,
            'auto_pnr_pricing_context_ready' => false,
            'missing_pricing_context_fields' => ['pricing_information_ref', 'offer_reference', 'itinerary_reference'],
        ];
    }

    protected function expectPricingReadinessOnMock(MockInterface $mock): void
    {
        $mock->shouldReceive('assessAutoPnrPricingContextReadinessForBooking')
            ->once()
            ->andReturn($this->incompletePricingReadiness());
    }

    protected function oneWayBooking(int $segmentCount = 1): Booking
    {
        $segments = [];
        for ($i = 0; $i < $segmentCount; $i++) {
            $segments[] = [
                'origin' => $i === 0 ? 'KHI' : 'DXB',
                'destination' => $i === 0 ? 'DXB' : 'LHE',
                'carrier' => 'EK',
                'booking_class' => 'Y',
                'fare_basis_code' => 'YLOW',
            ];
        }

        $booking = Booking::factory()->create([
            'supplier' => 'sabre',
            'meta' => [
                'supplier_provider' => 'sabre',
                'search_criteria' => ['trip_type' => 'one_way'],
                'flight_offer_snapshot' => [
                    'segments' => $segments,
                    'validating_carrier' => 'EK',
                    'total' => 50000,
                    'currency' => 'PKR',
                ],
            ],
        ]);
        BookingPassenger::factory()->create(['booking_id' => $booking->id, 'passenger_index' => 1]);

        return $booking;
    }
}
