<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\AirBlue\AirBlueClient;
use App\Services\Suppliers\AirBlue\AirBlueTicketingService;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueProviderException;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;

class AirBlueTicketingProviderErrorDiagnosticsTest extends AirBlueOfflineTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_provider_errors_are_preserved_sanitized_in_safe_summary(): void
    {
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')
            ->once()
            ->andThrow(new AirBlueProviderException(
                'provider_error',
                422,
                'Fare or booking is unavailable.',
                [
                    'correlation_id' => 'corr-ticketing-diag-1',
                    'provider_errors' => [
                        [
                            'code' => '318',
                            'type' => '3',
                            'message' => 'Inventory unavailable for SECRETPNR / INST1. AgentPassword=SuperSecretValue ClientKey=ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789ABCD john@example.com',
                        ],
                    ],
                ],
            ));
        $this->app->instance(AirBlueClient::class, $client);

        $agency = Agency::query()->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'pnr' => 'SECRETPNR',
            'supplier' => SupplierProvider::Airblue->value,
            'payment_status' => 'paid',
            'meta' => [
                'airblue_context' => [
                    'pnr' => 'SECRETPNR',
                    'instance' => 'INST1',
                    'protocol_version' => '2.0',
                    'api_channel' => 'zapways_ota',
                    'supplier_payable_amount' => 26000,
                    'payment_currency' => 'PKR',
                    'payment_type' => 'Cash',
                ],
            ],
        ]);
        $actor = User::factory()->create();

        $result = app(AirBlueTicketingService::class)->issueTickets($booking->fresh(), $this->connection(), $actor);

        $this->assertFalse($result->success);
        $this->assertSame('provider_error', $result->error_code);
        $this->assertSame('Fare or booking is unavailable.', $result->error_message);
        $this->assertSame('corr-ticketing-diag-1', $result->safe_summary['correlation_id'] ?? null);
        $this->assertIsArray($result->safe_summary['provider_errors'] ?? null);
        $this->assertSame('318', $result->safe_summary['provider_errors'][0]['code'] ?? null);
        $this->assertSame('3', $result->safe_summary['provider_errors'][0]['type'] ?? null);
        $message = (string) ($result->safe_summary['provider_errors'][0]['message'] ?? '');
        $this->assertStringContainsString('Inventory unavailable', $message);
        $this->assertStringContainsString('[REDACTED', $message);
        $this->assertStringNotContainsString('SuperSecretValue', $message);
        $this->assertStringNotContainsString('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789ABCD', $message);
        $this->assertStringNotContainsString('john@example.com', $message);
        $this->assertStringNotContainsString('SECRETPNR', $message);
        $this->assertStringNotContainsString('INST1', $message);
        $this->assertStringContainsString('[REDACTED_REFERENCE]', $message);
        $encoded = json_encode($result->safe_summary);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('AgentPassword=SuperSecret', $encoded);
        $this->assertStringNotContainsString('SECRETPNR', $encoded);
        $this->assertStringNotContainsString('<ota:', $encoded);
        $this->assertStringNotContainsString('GivenName', $encoded);
        Http::assertNothingSent();
    }

    private function connection(): SupplierConnection
    {
        $agency = Agency::query()->firstOrFail();

        return SupplierConnection::query()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Airblue,
            'name' => 'AirBlue Ticket Diagnostics Fixture',
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
                'ticketing_enabled' => true,
            ],
        ]);
    }
}
