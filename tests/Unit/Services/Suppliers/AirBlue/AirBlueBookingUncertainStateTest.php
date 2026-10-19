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

    public function test_transport_timeout_returns_uncertain_dto_without_persisting(): void
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
                'client_id' => 'fixture-client',
                'client_key' => 'fixture-key',
                'agent_type' => '5',
                'agent_id' => 'fixture-agent',
                'agent_password' => 'fixture-secret',
            ],
        ]);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'meta' => [
                'airblue_context' => [
                    'protocol_version' => '2.0',
                    'priced_itineraries' => [['segments' => [['flight_number' => '401']], 'fare_breakdowns' => [['base' => 1, 'taxes' => 1, 'total' => 2]]]],
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
        $this->assertSame('manual_review', $first->status);
        $this->assertSame('booking_outcome_uncertain', $first->error_code);
        $this->assertTrue((bool) ($first->safe_summary['supplier_outcome_unknown'] ?? false));
        $this->assertTrue((bool) ($first->safe_summary['requires_retrieve_before_retry'] ?? false));
        $this->assertSame('pending_reconciliation', $first->safe_summary['supplier_booking_status'] ?? null);

        // Provider service must not own canonical persistence.
        $this->assertSame(0, SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count());
        $this->assertSame(0, SupplierBooking::query()->where('booking_id', $booking->id)->count());

        $booking->refresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $this->assertNotSame('uncertain', $meta['airblue_context']['booking_outcome'] ?? null);
    }

    public function test_external_calls_disabled_is_ordinary_failure_not_uncertain(): void
    {
        $agency = Agency::query()->firstOrFail();
        $actor = User::factory()->create();
        $connection = SupplierConnection::query()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Airblue,
            'name' => 'AirBlue Gate Fixture',
            'environment' => SupplierEnvironment::Sandbox,
            'is_active' => true,
            'credentials' => [
                'api_channel' => 'zapways_ota',
                'protocol_version' => '2.0',
                'client_id' => 'fixture-client',
                'client_key' => 'fixture-key',
                'agent_type' => '5',
                'agent_id' => 'fixture-agent',
                'agent_password' => 'fixture-secret',
            ],
        ]);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'meta' => [
                'airblue_context' => [
                    'protocol_version' => '2.0',
                    'priced_itineraries' => [['segments' => [['flight_number' => '401']], 'fare_breakdowns' => [['base' => 1, 'taxes' => 1, 'total' => 2]]]],
                ],
            ],
        ]);

        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')
            ->once()
            ->andThrow(new \App\Services\Suppliers\AirBlue\Exceptions\AirBlueValidationException(
                'external_calls_disabled',
                503,
                'AirBlue external network calls are disabled.',
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

        $result = $service->createSupplierBooking($booking, $connection, $actor);

        $this->assertFalse($result->success);
        $this->assertSame('failed', $result->status);
        $this->assertSame('external_calls_disabled', $result->error_code);
        $this->assertNotSame('booking_outcome_uncertain', $result->error_code);
        $this->assertSame(0, SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count());
        $this->assertSame(0, SupplierBooking::query()->where('booking_id', $booking->id)->count());
    }
}
