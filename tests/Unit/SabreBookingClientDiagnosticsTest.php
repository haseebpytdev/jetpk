<?php

namespace Tests\Unit;

use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Core\SabreBookingClient;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Services\Suppliers\Sabre\SabreBookingPayloadBuilder;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\ConnectionException;
use Mockery;
use Tests\TestCase;

class SabreBookingClientDiagnosticsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_connection_exception_returns_flat_diagnostics_without_raw_payload(): void
    {
        $connection = new SupplierConnection;
        $connection->forceFill([
            'id' => 42,
            'base_url' => 'https://api-crt.cert.havail.sabre.com',
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Sandbox,
        ]);

        $sabreClient = Mockery::mock(SabreClient::class);
        $sabreClient->shouldReceive('resolveEndpointParts')
            ->once()
            ->andReturn(['endpoint_host' => 'api-crt.cert.havail.sabre.com', 'endpoint_path' => '/v2.5.0/passenger/records']);
        $sabreClient->shouldReceive('httpTimeoutSettings')
            ->once()
            ->andReturn(['timeout_seconds' => 30, 'connect_timeout_seconds' => 10]);
        $sabreClient->shouldReceive('postAuthenticatedJson')
            ->once()
            ->andThrow(new ConnectionException('Connection timed out after 30001 ms'));

        $bookingClient = new SabreBookingClient($sabreClient, app(SabreBookingPayloadBuilder::class));
        $out = $bookingClient->createPassengerRecordBooking($connection, ['ota_schema' => 'x'], [
            'booking_id' => 9,
            'passenger_count' => 2,
            'segment_count' => 3,
            'has_contact_email' => true,
            'has_contact_phone' => false,
        ]);

        $this->assertFalse($out['success']);
        $this->assertNull($out['http_status']);
        $this->assertTrue($out['live_call_attempted']);
        $diag = $out['booking_diagnostics'] ?? [];
        $this->assertSame('api-crt.cert.havail.sabre.com', $diag['endpoint_host']);
        $this->assertSame('/v2.5.0/passenger/records', $diag['endpoint_path']);
        $this->assertSame(30, $diag['timeout_seconds']);
        $this->assertSame(10, $diag['connect_timeout_seconds']);
        $this->assertSame(ConnectionException::class, $diag['exception_class']);
        $this->assertSame(42, $diag['supplier_connection_id']);
        $this->assertSame(9, $diag['booking_id']);
        $this->assertSame(2, $diag['passenger_count']);
        $this->assertSame(3, $diag['segment_count']);
        $this->assertTrue($diag['has_contact_email']);
        $this->assertFalse($diag['has_contact_phone']);

        $encoded = json_encode($diag);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('lead@', $encoded);
        $this->assertStringNotContainsString('Authorization', $encoded);
    }

    public function test_http_400_must_not_null_appends_hint_when_wire_payload_was_null_free(): void
    {
        $sabreClient = Mockery::mock(SabreClient::class);
        $psr = new Response(400, [], json_encode([
            'errors' => [['code' => 'INVALID_VALUE', 'message' => 'Validation Failed: must not be null']],
        ]));
        $response = new \Illuminate\Http\Client\Response($psr);
        $bookingClient = new SabreBookingClient($sabreClient, app(SabreBookingPayloadBuilder::class));
        $ref = new \ReflectionMethod(SabreBookingClient::class, 'normalizeBookingResponse');
        $ref->setAccessible(true);
        /** @var array<string, mixed> $out */
        $out = $ref->invoke($bookingClient, $response, 12, [
            'wire_payload_null_free' => true,
        ]);
        $this->assertFalse($out['success']);
        $this->assertStringContainsString('no JSON null values', (string) ($out['safe_message'] ?? ''));
    }
}
