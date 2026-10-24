<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\AirBlueClient;
use App\Services\Suppliers\AirBlue\AirBlueRetrieveService;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueProviderException;
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

    public function test_supplier_http_error_returns_only_safe_allowlisted_diagnostics(): void
    {
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')
            ->once()
            ->andThrow(new AirBlueProviderException(
                'supplier_http_error',
                500,
                'Provider temporarily unavailable.',
                [
                    'correlation_id' => 'corr-read-1',
                    'http_status' => 500,
                    'fault_class' => 'soap_fault',
                    'fault_snippet' => 'Read rejected for TESTPNR / INST1 and john@example.com',
                    'request_xml' => '<secret>must-not-leak</secret>',
                    'credentials' => ['agent_password' => 'secret'],
                ],
            ));
        $this->app->instance(AirBlueClient::class, $client);

        $agency = Agency::query()->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'pnr' => 'TESTPNR',
            'supplier_reference' => 'TESTPNR',
            'supplier' => SupplierProvider::Airblue->value,
            'meta' => [
                'airblue_context' => [
                    'pnr' => 'TESTPNR',
                    'instance' => 'INST1',
                    'protocol_version' => '2.0',
                    'api_channel' => 'zapways_ota',
                ],
            ],
        ]);

        $result = app(AirBlueRetrieveService::class)->retrieveAndSync($booking->fresh(), $this->connection());

        $this->assertFalse($result['synced']);
        $this->assertSame('supplier_http_error', $result['reason']);
        $this->assertSame('corr-read-1', $result['diagnostic']['correlation_id'] ?? null);
        $this->assertSame(500, $result['diagnostic']['http_status'] ?? null);
        $this->assertSame('soap_fault', $result['diagnostic']['fault_class'] ?? null);
        $snippet = (string) ($result['diagnostic']['fault_snippet'] ?? '');
        $this->assertStringContainsString('[REDACTED_REFERENCE]', $snippet);
        $this->assertStringContainsString('[REDACTED_EMAIL]', $snippet);
        $this->assertStringNotContainsString('TESTPNR', $snippet);
        $this->assertStringNotContainsString('INST1', $snippet);
        $encoded = json_encode($result['diagnostic']);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('request_xml', $encoded);
        $this->assertStringNotContainsString('credentials', $encoded);
        $this->assertStringNotContainsString('must-not-leak', $encoded);
        $this->assertStringNotContainsString('secret', $encoded);
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
