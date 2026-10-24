<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\AirBlueClient;
use App\Services\Suppliers\AirBlue\AirBlueRetrieveService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;

class AirBlueRetrieveServiceTest extends AirBlueOfflineTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_missing_pnr_fails_without_requiring_instance(): void
    {
        $agency = Agency::query()->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'pnr' => null,
            'supplier_reference' => null,
            'meta' => ['airblue_context' => ['instance' => 'INST1']],
        ]);

        $result = app(AirBlueRetrieveService::class)->retrieveAndSync($booking, $this->connection());

        $this->assertFalse($result['synced']);
        $this->assertSame('missing_order_context', $result['reason']);
        Http::assertNothingSent();
    }

    public function test_normal_retrieve_uses_pnr_only_read_xml(): void
    {
        $captured = null;
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')
            ->once()
            ->withArgs(function ($connection, string $operation, string $xml) use (&$captured): bool {
                $captured = $xml;

                return $operation === 'read'
                    && str_contains($xml, '<ota:UniqueID ID="TESTPNR"/>')
                    && ! str_contains($xml, 'Type="14"')
                    && ! str_contains($xml, 'Instance=');
            })
            ->andReturn([
                'parsed' => [
                    'booking' => ['pnr' => 'TESTPNR', 'instance' => 'INST-FROM-PROVIDER'],
                ],
                'errors' => [],
                'soap_fault' => null,
            ]);

        $this->app->instance(AirBlueClient::class, $client);

        $agency = Agency::query()->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'pnr' => 'TESTPNR',
            'supplier' => SupplierProvider::Airblue->value,
            'meta' => [
                'airblue_context' => [
                    'pnr' => 'TESTPNR',
                    'instance' => 'SHOULD-NOT-BE-SENT-BY-DEFAULT',
                    'protocol_version' => '2.0',
                    'api_channel' => 'zapways_ota',
                ],
            ],
        ]);

        $result = app(AirBlueRetrieveService::class)->retrieveAndSync($booking->fresh(), $this->connection());

        $this->assertTrue($result['synced']);
        $this->assertIsString($captured);
        $this->assertStringContainsString('<ota:UniqueID ID="TESTPNR"/>', (string) $captured);
        $this->assertStringNotContainsString('SHOULD-NOT-BE-SENT-BY-DEFAULT', (string) $captured);
    }

    public function test_specific_instance_mode_without_instance_fails_closed(): void
    {
        $agency = Agency::query()->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'pnr' => 'TESTPNR',
            'supplier' => SupplierProvider::Airblue->value,
            'meta' => [
                'airblue_context' => [
                    'pnr' => 'TESTPNR',
                    'read_specific_instance' => true,
                    'protocol_version' => '2.0',
                    'api_channel' => 'zapways_ota',
                ],
            ],
        ]);

        $result = app(AirBlueRetrieveService::class)->retrieveAndSync($booking->fresh(), $this->connection());

        $this->assertFalse($result['synced']);
        $this->assertSame('missing_order_context', $result['reason']);
        Http::assertNothingSent();
    }

    public function test_specific_instance_mode_includes_instance(): void
    {
        $captured = null;
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')
            ->once()
            ->withArgs(function ($connection, string $operation, string $xml) use (&$captured): bool {
                $captured = $xml;

                return $operation === 'read'
                    && str_contains($xml, 'Instance="HIST1"')
                    && ! str_contains($xml, 'Type="14"');
            })
            ->andReturn([
                'parsed' => [
                    'booking' => ['pnr' => 'TESTPNR', 'instance' => 'HIST1'],
                ],
                'errors' => [],
                'soap_fault' => null,
            ]);

        $this->app->instance(AirBlueClient::class, $client);

        $agency = Agency::query()->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'pnr' => 'TESTPNR',
            'supplier' => SupplierProvider::Airblue->value,
            'meta' => [
                'airblue_context' => [
                    'pnr' => 'TESTPNR',
                    'instance' => 'HIST1',
                    'read_specific_instance' => true,
                    'protocol_version' => '2.0',
                    'api_channel' => 'zapways_ota',
                ],
            ],
        ]);

        $result = app(AirBlueRetrieveService::class)->retrieveAndSync($booking->fresh(), $this->connection());

        $this->assertTrue($result['synced']);
        $this->assertStringContainsString('Instance="HIST1"', (string) $captured);
    }

    private function connection(): SupplierConnection
    {
        $agency = Agency::query()->firstOrFail();

        return SupplierConnection::query()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Airblue,
            'name' => 'AirBlue Retrieve Fixture',
            'environment' => SupplierEnvironment::Sandbox,
            'base_url' => 'https://otatest4.zapways.com/v2.0/OTAAPI.asmx',
            'is_active' => true,
            'credentials' => [
                'api_channel' => 'zapways_ota',
                'protocol_version' => '2.0',
                'client_id' => 'fixture-client',
                'client_key' => 'fixture-key',
                'agent_type' => '5',
                'agent_id' => 'fixture-agent',
                'agent_password' => 'fixture-secret',
                'service_target' => 'Test',
            ],
        ]);
    }
}
