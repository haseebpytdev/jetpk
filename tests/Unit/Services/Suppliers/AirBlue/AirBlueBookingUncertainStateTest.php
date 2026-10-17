<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\AirBlue\AirBlueBookingService;
use App\Services\Suppliers\AirBlue\AirBlueClient;
use App\Services\Suppliers\AirBlue\AirBlueConfigResolver;
use App\Services\Suppliers\AirBlue\AirBlueOtaResponseNormalizer;
use App\Services\Suppliers\AirBlue\AirBlueOtaXmlBuilder;
use App\Services\Suppliers\AirBlue\AirBluePassengerPayloadBuilder;
use App\Services\Suppliers\AirBlue\AirBlueProtocolGuard;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueUnavailableException;
use App\Services\Suppliers\SupplierDiagnosticLogger;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class AirBlueBookingUncertainStateTest extends AirBlueOfflineTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_transport_timeout_marks_uncertain_and_blocks_retry(): void
    {
        $agency = Agency::query()->firstOrFail();
        $actor = User::factory()->create();
        $connection = SupplierConnection::query()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Airblue,
            'name' => 'AirBlue Uncertain Fixture',
            'environment' => SupplierEnvironment::Sandbox,
            'is_active' => true,
            'credentials' => [
                'api_channel' => 'zapways_ota',
                'protocol_version' => '2.0',
                'username' => 'fixture-user',
                'password' => 'fixture-pass',
                'client_id' => 'fixture-client',
            ],
        ]);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'meta' => [
                'airblue_context' => [
                    'protocol_version' => '2.0',
                    'priced_itinerary_xml' => '<PricedItinerary/>',
                ],
            ],
        ]);

        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')
            ->once()
            ->andThrow(new AirBlueUnavailableException(
                'supplier_transport_failed',
                503,
                'Provider temporarily unavailable.',
            ));

        $passengers = Mockery::mock(AirBluePassengerPayloadBuilder::class);
        $passengers->shouldReceive('buildPassengersFromBooking')->andReturn([['ptc' => 'ADT']]);
        $passengers->shouldReceive('buildContactFromBooking')->andReturn(['email' => 'fixture@example.test']);

        $configResolver = Mockery::mock(AirBlueConfigResolver::class);
        $configResolver->shouldReceive('resolveOta')->andReturn([
            'endpoint_url' => 'https://example.test/ota',
            'protocol_version' => '2.0',
            'is_test' => true,
        ]);

        $xmlBuilder = Mockery::mock(AirBlueOtaXmlBuilder::class);
        $xmlBuilder->shouldReceive('buildAirBookRequest')->andReturn('<AirBookRQ/>');

        $protocolGuard = Mockery::mock(AirBlueProtocolGuard::class);
        $protocolGuard->shouldReceive('assertCompatible')->once();

        $service = new AirBlueBookingService(
            $client,
            $configResolver,
            $passengers,
            $xmlBuilder,
            Mockery::mock(AirBlueOtaResponseNormalizer::class),
            $protocolGuard,
            app(SupplierDiagnosticLogger::class),
        );

        $first = $service->createSupplierBooking($booking, $connection, $actor);

        $this->assertFalse($first->success);
        $this->assertSame('booking_outcome_uncertain', $first->error_code);

        $booking->refresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $this->assertSame('uncertain', $meta['airblue_context']['booking_outcome'] ?? null);
        $this->assertTrue((bool) ($meta['airblue_context']['supplier_outcome_unknown'] ?? false));

        $this->assertTrue(
            SupplierBookingAttempt::query()
                ->where('booking_id', $booking->id)
                ->where('status', 'uncertain')
                ->exists(),
        );

        $this->assertTrue(
            SupplierBooking::query()
                ->where('booking_id', $booking->id)
                ->where('status', 'pending_reconciliation')
                ->exists(),
        );

        $second = $service->createSupplierBooking($booking, $connection, $actor);
        $this->assertFalse($second->success);
        $this->assertContains($second->error_code, ['booking_outcome_uncertain', 'duplicate_booking_guard']);
    }
}
