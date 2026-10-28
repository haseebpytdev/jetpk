<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\AirBlueAncillaryService;
use App\Services\Suppliers\AirBlue\AirBlueClient;
use App\Services\Suppliers\AirBlue\AirBlueRetrieveService;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueProviderException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueUnavailableException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueValidationException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueXmlException;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class AirBlueAncillarySeatUncertainTest extends AirBlueOfflineTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    private function connection(): SupplierConnection
    {
        return new SupplierConnection([
            'provider' => SupplierProvider::Airblue,
            'environment' => SupplierEnvironment::Sandbox,
            'status' => 'active',
            'credentials' => [
                'api_channel' => 'zapways_ota',
                'protocol_version' => '3.0',
                'client_id' => 'CLIENT',
                'client_key' => 'KEY',
                'agent_type' => '5',
                'agent_id' => 'AGENT',
                'agent_password' => 'secret',
            ],
        ]);
    }

    private function booking(array $context = []): Booking
    {
        $agency = Agency::query()->firstOrFail();

        return Booking::factory()->create([
            'agency_id' => $agency->id,
            'pnr' => 'SEATPNR1',
            'supplier' => SupplierProvider::Airblue->value,
            'meta' => [
                'airblue_context' => array_merge([
                    'pnr' => 'SEATPNR1',
                    'instance' => 'INST1',
                    'protocol_version' => '3.0',
                ], $context),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function modifyContext(Booking $booking): array
    {
        return [
            'booking' => $booking,
            'pnr' => 'SEATPNR1',
            'instance' => 'INST1',
            'modification_type' => '5',
            'seat_changes' => [[
                'row_number' => '12',
                'seat_number' => 'A',
                'traveler_ref_number_rph' => '1',
                'flight_ref_number_rph' => '1',
            ]],
        ];
    }

    public function test_transport_failure_after_type5_persists_uncertain_and_blocks_retry(): void
    {
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')->once()->andThrow(new AirBlueUnavailableException(
            'supplier_transport_failed',
            503,
            'Unavailable.',
        ));
        $this->app->instance(AirBlueClient::class, $client);

        $booking = $this->booking();
        try {
            app(AirBlueAncillaryService::class)->modifySeats($this->connection(), $this->modifyContext($booking));
            $this->fail('expected uncertain');
        } catch (AirBlueValidationException $e) {
            $this->assertSame('supplier_transport_failed', $e->normalizedCode);
        }

        $ctx = $booking->fresh()->meta['airblue_context'];
        $this->assertSame('uncertain', $ctx['seat_modify_outcome'] ?? null);
        $this->assertTrue($ctx['seat_modify_requires_read'] ?? false);
        $this->assertSame('12', $ctx['seat_modify_expected']['row_number'] ?? null);
        $this->assertSame('A', $ctx['seat_modify_expected']['seat_number'] ?? null);

        $client2 = Mockery::mock(AirBlueClient::class);
        $client2->shouldReceive('callOta')->never();
        $this->app->instance(AirBlueClient::class, $client2);

        try {
            app(AirBlueAncillaryService::class)->modifySeats(
                $this->connection(),
                $this->modifyContext($booking->fresh()),
            );
            $this->fail('expected seat_modify_outcome_uncertain');
        } catch (AirBlueValidationException $e) {
            $this->assertSame('seat_modify_outcome_uncertain', $e->normalizedCode);
        }
    }

    public function test_http_soap_malformed_after_send_are_uncertain(): void
    {
        foreach ([
            new AirBlueProviderException('supplier_http_error', 502, 'HTTP'),
            new AirBlueProviderException('soap_fault', 502, 'SOAP'),
            new AirBlueXmlException('malformed_xml', 502, 'XML'),
        ] as $exception) {
            $client = Mockery::mock(AirBlueClient::class);
            $client->shouldReceive('callOta')->once()->andThrow($exception);
            $this->app->instance(AirBlueClient::class, $client);

            $booking = $this->booking();
            try {
                app(AirBlueAncillaryService::class)->modifySeats($this->connection(), $this->modifyContext($booking));
                $this->fail('expected uncertain');
            } catch (AirBlueValidationException $e) {
                $this->assertSame('uncertain', $booking->fresh()->meta['airblue_context']['seat_modify_outcome'] ?? null);
            }
        }
    }

    public function test_provider_error_is_definitive_not_uncertain(): void
    {
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')->once()->andThrow(new AirBlueProviderException(
            'provider_error',
            422,
            'Seat modify rejected.',
        ));
        $this->app->instance(AirBlueClient::class, $client);

        $booking = $this->booking();
        try {
            app(AirBlueAncillaryService::class)->modifySeats($this->connection(), $this->modifyContext($booking));
            $this->fail('expected provider_error');
        } catch (AirBlueValidationException $e) {
            $this->assertSame('provider_error', $e->normalizedCode);
            $this->assertNotSame('uncertain', $booking->fresh()->meta['airblue_context']['seat_modify_outcome'] ?? null);
        }
    }

    public function test_pre_send_validation_failure_is_not_uncertain(): void
    {
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')->never();
        $this->app->instance(AirBlueClient::class, $client);

        $booking = $this->booking();
        try {
            app(AirBlueAncillaryService::class)->modifySeats($this->connection(), [
                'booking' => $booking,
                'pnr' => 'SEATPNR1',
                'modification_type' => '5',
            ]);
            $this->fail('expected validation failure');
        } catch (AirBlueValidationException $e) {
            $this->assertNotSame('uncertain', $booking->fresh()->meta['airblue_context']['seat_modify_outcome'] ?? null);
        }
    }

    public function test_read_reconciles_applied_absent_and_wrong_association(): void
    {
        $applied = $this->booking([
            'seat_modify_outcome' => 'uncertain',
            'seat_modify_requires_read' => true,
            'seat_modify_expected' => [
                'row_number' => '12',
                'seat_number' => 'A',
                'traveler_ref_number_rph' => '1',
                'flight_ref_number_rph' => '1',
            ],
        ]);
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')->once()->andReturn([
            'parsed' => [
                'booking' => ['pnr' => 'SEATPNR1', 'instance' => 'INST1'],
                'tickets' => [],
                'seats' => [[
                    'seat_number' => 'A',
                    'row_number' => '12',
                    'status' => 'Confirmed',
                    'traveler_ref_number_rph' => '1',
                    'flight_ref_number_rph' => '1',
                ]],
            ],
            'errors' => [],
            'soap_fault' => null,
        ]);
        $this->app->instance(AirBlueClient::class, $client);
        app(AirBlueRetrieveService::class)->retrieveAndSync($applied->fresh(), $this->connection());
        $this->assertSame('reconciled_applied', $applied->fresh()->meta['airblue_context']['seat_modify_outcome'] ?? null);

        $absent = $this->booking([
            'pnr' => 'SEATPNR2',
            'seat_modify_outcome' => 'uncertain',
            'seat_modify_requires_read' => true,
            'seat_modify_expected' => [
                'row_number' => '12',
                'seat_number' => 'A',
                'traveler_ref_number_rph' => '1',
                'flight_ref_number_rph' => '1',
            ],
        ]);
        $absent->forceFill(['pnr' => 'SEATPNR2'])->save();
        $meta = $absent->meta;
        $meta['airblue_context']['pnr'] = 'SEATPNR2';
        $absent->meta = $meta;
        $absent->save();

        $client2 = Mockery::mock(AirBlueClient::class);
        $client2->shouldReceive('callOta')->once()->andReturn([
            'parsed' => [
                'booking' => ['pnr' => 'SEATPNR2', 'instance' => 'INST1'],
                'tickets' => [],
                'seats' => [],
            ],
            'errors' => [],
            'soap_fault' => null,
        ]);
        $this->app->instance(AirBlueClient::class, $client2);
        app(AirBlueRetrieveService::class)->retrieveAndSync($absent->fresh(), $this->connection());
        $this->assertSame('reconciled_not_applied', $absent->fresh()->meta['airblue_context']['seat_modify_outcome'] ?? null);

        $wrong = $this->booking([
            'pnr' => 'SEATPNR3',
            'seat_modify_outcome' => 'uncertain',
            'seat_modify_requires_read' => true,
            'seat_modify_expected' => [
                'row_number' => '12',
                'seat_number' => 'A',
                'traveler_ref_number_rph' => '1',
                'flight_ref_number_rph' => '1',
            ],
        ]);
        $wrong->forceFill(['pnr' => 'SEATPNR3'])->save();
        $meta = $wrong->meta;
        $meta['airblue_context']['pnr'] = 'SEATPNR3';
        $wrong->meta = $meta;
        $wrong->save();

        $client3 = Mockery::mock(AirBlueClient::class);
        $client3->shouldReceive('callOta')->once()->andReturn([
            'parsed' => [
                'booking' => ['pnr' => 'SEATPNR3', 'instance' => 'INST1'],
                'tickets' => [],
                'seats' => [[
                    'seat_number' => 'A',
                    'row_number' => '12',
                    'status' => 'Confirmed',
                    'traveler_ref_number_rph' => '2',
                    'flight_ref_number_rph' => '1',
                ]],
            ],
            'errors' => [],
            'soap_fault' => null,
        ]);
        $this->app->instance(AirBlueClient::class, $client3);
        app(AirBlueRetrieveService::class)->retrieveAndSync($wrong->fresh(), $this->connection());
        $this->assertSame('uncertain', $wrong->fresh()->meta['airblue_context']['seat_modify_outcome'] ?? null);
    }

    public function test_existing_success_gate_still_rejects_empty_seat_response(): void
    {
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')->once()->andReturn([
            'parsed' => ['seats' => []],
            'errors' => [],
            'soap_fault' => null,
        ]);
        $this->app->instance(AirBlueClient::class, $client);

        $this->expectException(AirBlueValidationException::class);
        app(AirBlueAncillaryService::class)->modifySeats($this->connection(), [
            'pnr' => 'SEATPNR1',
            'instance' => 'INST1',
            'modification_type' => '5',
            'seat_changes' => [[
                'row_number' => '12',
                'seat_number' => 'A',
                'traveler_ref_number_rph' => '1',
                'flight_ref_number_rph' => '1',
            ]],
        ]);
    }
}
