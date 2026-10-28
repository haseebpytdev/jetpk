<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Enums\AirBlueZapwaysProtocolVersion;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\AirBlue\AirBlueClient;
use App\Services\Suppliers\AirBlue\AirBlueOtaResponseNormalizer;
use App\Services\Suppliers\AirBlue\AirBlueOtaXmlBuilder;
use App\Services\Suppliers\AirBlue\AirBlueOtaXmlParser;
use App\Services\Suppliers\AirBlue\AirBlueRefundService;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueProviderException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueUnavailableException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueValidationException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueXmlException;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;

class AirBlueFullRefundType1Test extends AirBlueOfflineTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_type1_request_has_modification_type_one_and_pnr_without_instance(): void
    {
        $xml = (new AirBlueOtaXmlBuilder)->buildAirBookModifyRequest($this->v3Config(), [
            'pnr' => 'EUOPCL',
            'modification_type' => '1',
            'instance' => 'SHOULD_BE_IGNORED',
        ]);

        $this->assertStringContainsString('ModificationType="1"', $xml);
        $this->assertStringContainsString('<ota:AirBookModifyRQ ModificationType="1"></ota:AirBookModifyRQ>', $xml);
        $this->assertStringContainsString('BookingReferenceID ID="EUOPCL"', $xml);
        $this->assertStringNotContainsString('Instance=', $xml);
        $this->assertStringNotContainsString('SeatRequests', $xml);
        $this->assertStringNotContainsString('TravelerInfo', $xml);
        $this->assertStringContainsString('<ota:POS>', $xml);
        $this->assertStringContainsString('xmlns:zap="http://zapways.com/air/ota/3.0"', $xml);
    }

    public function test_type5_seat_modify_still_requires_instance_and_seat_payload(): void
    {
        $xml = (new AirBlueOtaXmlBuilder)->buildAirBookModifyRequest($this->v3Config(), [
            'pnr' => 'ABC123',
            'instance' => 'INST1',
            'modification_type' => '5',
            'seat_changes' => [[
                'row_number' => '12',
                'seat_number' => 'A',
                'traveler_ref_number_rph' => '1',
                'flight_ref_number_rph' => '1',
            ]],
        ]);

        $this->assertStringContainsString('ModificationType="5"', $xml);
        $this->assertStringContainsString('Instance="INST1"', $xml);
        $this->assertStringContainsString('<ota:SeatRequests>', $xml);

        $this->expectException(AirBlueValidationException::class);
        (new AirBlueOtaXmlBuilder)->buildAirBookModifyRequest($this->v3Config(), [
            'pnr' => 'ABC123',
            'modification_type' => '5',
        ]);
    }

    public function test_unsupported_modification_type_still_rejected(): void
    {
        $this->expectException(AirBlueValidationException::class);
        (new AirBlueOtaXmlBuilder)->buildAirBookModifyRequest($this->v3Config(), [
            'pnr' => 'ABC123',
            'modification_type' => '2',
        ]);
    }

    public function test_refund_response_parses_ticketing_status_negative_fare_and_refunded_seat_item(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/airblue/zapways/v3/ota_air_book_modify_full_refund_success.xml'));
        $this->assertIsString($xml);
        $parsed = (new AirBlueOtaXmlParser)->parse($xml);
        $body = $parsed['parsed'];

        $this->assertNotEmpty($body['ticketing_records']);
        $this->assertSame('REFUNDED', $body['ticketing_records'][0]['ticketing_status']);
        $this->assertSame('0002306438886', $body['ticketing_records'][0]['ticket_document_number']);
        $this->assertSame('1', $body['ticketing_records'][0]['flight_segment_ref_number']);
        $this->assertSame('T1', $body['ticketing_records'][0]['traveler_ref_number']);
        $this->assertSame('ADT', $body['ticketing_records'][0]['passenger_type_code']);

        $this->assertSame(-24500.0, $body['transaction_history'][0]['amount']);
        $this->assertSame('PKR', $body['transaction_history'][0]['currency']);
        $this->assertSame('Refunded', $body['seats'][0]['status']);
        $this->assertSame('Refunded', $body['items'][0]['status']);
    }

    public function test_normalize_refund_response_success(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/airblue/zapways/v3/ota_air_book_modify_full_refund_success.xml'));
        $parsed = (new AirBlueOtaXmlParser)->parse((string) $xml);
        $normalized = (new AirBlueOtaResponseNormalizer)->normalizeRefundResponse($parsed, [
            'protocol_version' => '3.0',
            'supplier_payable_amount' => 25000,
            'ticket_numbers' => ['0002306438886'],
            'ticketing_status' => 'ticketed',
        ]);

        $this->assertSame('refunded', $normalized['refund_status']);
        $this->assertSame(1, $normalized['refunded_ticket_count']);
        $this->assertSame(-24500.0, $normalized['refund_transaction_amount']);
        $this->assertSame('PKR', $normalized['refund_currency']);
        $this->assertTrue($normalized['seats_refunded']);
        $this->assertTrue($normalized['items_refunded']);
        $this->assertSame(25000, $normalized['provider_context']['supplier_payable_amount']);
        $this->assertSame(['0002306438886'], $normalized['provider_context']['ticket_numbers']);
    }

    public function test_refund_feature_disabled_fails_closed_with_zero_provider_calls(): void
    {
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')->never();
        $this->app->instance(AirBlueClient::class, $client);

        $result = app(AirBlueRefundService::class)->refundFull(
            $this->ticketedBooking(),
            $this->connection(['refund_enabled' => false]),
            User::factory()->create(),
        );

        $this->assertFalse($result->success);
        $this->assertSame('refund_feature_disabled', $result->error_code);
        $this->assertSame(0, $result->provider_calls);
        Http::assertNothingSent();
    }

    public function test_non_ticketed_booking_fails_closed(): void
    {
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')->never();
        $this->app->instance(AirBlueClient::class, $client);

        $agency = Agency::query()->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'pnr' => 'NOTIXPNR',
            'supplier' => SupplierProvider::Airblue->value,
            'meta' => [
                'airblue_context' => [
                    'pnr' => 'NOTIXPNR',
                    'protocol_version' => '3.0',
                    'ticketing_status' => 'pending_ticketing',
                ],
            ],
        ]);

        $result = app(AirBlueRefundService::class)->refundFull(
            $booking,
            $this->connection(['refund_enabled' => true, 'protocol_version' => '3.0']),
            User::factory()->create(),
        );

        $this->assertFalse($result->success);
        $this->assertSame('booking_not_ticketed', $result->error_code);
        $this->assertSame(0, $result->provider_calls);
    }

    public function test_protocol_mismatch_fails_closed(): void
    {
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')->never();
        $this->app->instance(AirBlueClient::class, $client);

        $result = app(AirBlueRefundService::class)->refundFull(
            $this->ticketedBooking(['protocol_version' => '3.0']),
            $this->connection(['refund_enabled' => true, 'protocol_version' => '2.0']),
            User::factory()->create(),
        );

        $this->assertFalse($result->success);
        $this->assertSame('protocol_version_mismatch', $result->error_code);
        $this->assertSame(0, $result->provider_calls);
    }

    public function test_successful_full_refund_persists_and_updates_supplier_booking(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/airblue/zapways/v3/ota_air_book_modify_full_refund_success.xml'));
        $parsed = (new AirBlueOtaXmlParser)->parse((string) $xml);

        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')
            ->once()
            ->withArgs(function ($connection, string $operation, string $requestXml, array $diag): bool {
                return $operation === 'air_book_modify'
                    && str_contains($requestXml, 'ModificationType="1"')
                    && ! str_contains($requestXml, 'Instance=')
                    && ($diag['request_context'] ?? '') === 'air_book_modify_full_refund';
            })
            ->andReturn($parsed);
        $this->app->instance(AirBlueClient::class, $client);

        $booking = $this->ticketedBooking(['protocol_version' => '3.0']);
        SupplierBooking::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Airblue->value,
            'status' => 'ticketed',
            'supplier_reference' => 'EUOPCL',
            'pnr' => 'SECRETPNR',
        ]);

        $result = app(AirBlueRefundService::class)->refundFull(
            $booking->fresh(),
            $this->connection(['refund_enabled' => true, 'protocol_version' => '3.0']),
            User::factory()->create(),
        );

        $this->assertTrue($result->success);
        $this->assertSame('refunded', $result->status);
        $this->assertSame('DEFINITIVE_SUCCESS', $result->outcome);
        $this->assertSame(1, $result->provider_calls);

        $fresh = $booking->fresh();
        $ctx = is_array($fresh->meta['airblue_context'] ?? null) ? $fresh->meta['airblue_context'] : [];
        $this->assertSame('refunded', $ctx['refund_status'] ?? null);
        $this->assertEquals(25000, $ctx['supplier_payable_amount'] ?? null);
        $this->assertEqualsWithDelta(-24500.0, (float) ($ctx['refund_transaction_amount'] ?? 0), 0.01);
        $this->assertSame('refunded', SupplierBooking::query()->where('booking_id', $booking->id)->value('status'));
    }

    public function test_duplicate_refund_guard_causes_zero_provider_calls(): void
    {
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')->never();
        $this->app->instance(AirBlueClient::class, $client);

        $booking = $this->ticketedBooking([
            'protocol_version' => '3.0',
            'refund_status' => 'refunded',
            'refunded_ticket_count' => 1,
        ]);

        $result = app(AirBlueRefundService::class)->refundFull(
            $booking,
            $this->connection(['refund_enabled' => true, 'protocol_version' => '3.0']),
            User::factory()->create(),
        );

        $this->assertFalse($result->success);
        $this->assertSame('duplicate_refund_guard', $result->error_code);
        $this->assertSame(0, $result->provider_calls);
    }

    public function test_uncertain_prior_refund_blocks_type1_retry(): void
    {
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')->never();
        $this->app->instance(AirBlueClient::class, $client);

        $booking = $this->ticketedBooking([
            'protocol_version' => '3.0',
            'refund_status' => 'uncertain',
        ]);

        $result = app(AirBlueRefundService::class)->refundFull(
            $booking,
            $this->connection(['refund_enabled' => true, 'protocol_version' => '3.0']),
            User::factory()->create(),
        );

        $this->assertFalse($result->success);
        $this->assertSame('UNCERTAIN_AFTER_TRANSMISSION', $result->outcome);
        $this->assertSame('refund_outcome_uncertain', $result->error_code);
        $this->assertSame(0, $result->provider_calls);
    }

    public function test_transport_failure_after_send_is_uncertain_and_not_retried(): void
    {
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')
            ->once()
            ->andThrow(new AirBlueUnavailableException(
                'supplier_transport_failed',
                503,
                'Provider temporarily unavailable.',
                ['correlation_id' => 'corr-refund-uncertain-1'],
            ));
        $this->app->instance(AirBlueClient::class, $client);

        $booking = $this->ticketedBooking(['protocol_version' => '3.0']);
        $service = app(AirBlueRefundService::class);
        $connection = $this->connection(['refund_enabled' => true, 'protocol_version' => '3.0']);
        $actor = User::factory()->create();

        $first = $service->refundFull($booking->fresh(), $connection, $actor);
        $this->assertSame('UNCERTAIN_AFTER_TRANSMISSION', $first->outcome);
        $this->assertSame(1, $first->provider_calls);
        $this->assertSame('uncertain', $booking->fresh()->meta['airblue_context']['refund_status'] ?? null);

        $second = $service->refundFull($booking->fresh(), $connection, $actor);
        $this->assertSame('UNCERTAIN_AFTER_TRANSMISSION', $second->outcome);
        $this->assertSame(0, $second->provider_calls);
    }

    public function test_xml_parse_failure_after_send_is_uncertain(): void
    {
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')
            ->once()
            ->andThrow(new AirBlueXmlException('malformed_xml', 502, 'Provider returned an invalid XML response.'));
        $this->app->instance(AirBlueClient::class, $client);

        $result = app(AirBlueRefundService::class)->refundFull(
            $this->ticketedBooking(['protocol_version' => '3.0']),
            $this->connection(['refund_enabled' => true, 'protocol_version' => '3.0']),
            User::factory()->create(),
        );

        $this->assertSame('UNCERTAIN_AFTER_TRANSMISSION', $result->outcome);
        $this->assertSame(1, $result->provider_calls);
    }

    public function test_http_error_after_send_is_uncertain_and_blocks_retry(): void
    {
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')
            ->once()
            ->andThrow(new AirBlueProviderException(
                'supplier_http_error',
                502,
                'Provider temporarily unavailable.',
                ['correlation_id' => 'corr-refund-http-1', 'http_status' => 502],
            ));
        $this->app->instance(AirBlueClient::class, $client);

        $booking = $this->ticketedBooking(['protocol_version' => '3.0']);
        $service = app(AirBlueRefundService::class);
        $connection = $this->connection(['refund_enabled' => true, 'protocol_version' => '3.0']);
        $actor = User::factory()->create();

        $first = $service->refundFull($booking->fresh(), $connection, $actor);
        $this->assertSame('UNCERTAIN_AFTER_TRANSMISSION', $first->outcome);
        $this->assertSame('supplier_http_error', $first->error_code);
        $this->assertSame(1, $first->provider_calls);
        $this->assertSame('uncertain', $booking->fresh()->meta['airblue_context']['refund_status'] ?? null);

        $second = $service->refundFull($booking->fresh(), $connection, $actor);
        $this->assertSame('UNCERTAIN_AFTER_TRANSMISSION', $second->outcome);
        $this->assertSame(0, $second->provider_calls);
    }

    public function test_definitive_provider_error_is_safe_normalized(): void
    {
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')
            ->once()
            ->andThrow(new AirBlueProviderException(
                'provider_error',
                422,
                'Fare or booking is unavailable.',
                [
                    'correlation_id' => 'corr-refund-err-1',
                    'provider_errors' => [[
                        'code' => '318',
                        'type' => '13',
                        'message' => 'Inventory unavailable for SECRETPNR / INST1. AgentPassword=SuperSecretValue',
                    ]],
                ],
            ));
        $this->app->instance(AirBlueClient::class, $client);

        $result = app(AirBlueRefundService::class)->refundFull(
            $this->ticketedBooking(['protocol_version' => '3.0', 'pnr' => 'SECRETPNR', 'instance' => 'INST1']),
            $this->connection(['refund_enabled' => true, 'protocol_version' => '3.0']),
            User::factory()->create(),
        );

        $this->assertFalse($result->success);
        $this->assertSame('DEFINITIVE_PROVIDER_REJECTION', $result->outcome);
        $this->assertSame('318', $result->safe_summary['provider_errors'][0]['code'] ?? null);
        $this->assertSame('13', $result->safe_summary['provider_errors'][0]['type'] ?? null);
        $message = (string) ($result->safe_summary['provider_errors'][0]['message'] ?? '');
        $this->assertStringNotContainsString('SECRETPNR', $message);
        $this->assertStringNotContainsString('SuperSecretValue', $message);
        $this->assertStringContainsString('[REDACTED', $message);
    }

    /**
     * @param  array<string, mixed>  $contextOverrides
     */
    private function ticketedBooking(array $contextOverrides = []): Booking
    {
        $agency = Agency::query()->firstOrFail();
        $context = array_merge([
            'pnr' => 'SECRETPNR',
            'instance' => 'INST1',
            'protocol_version' => '3.0',
            'api_channel' => 'zapways_ota',
            'ticketing_status' => 'ticketed',
            'ticket_numbers' => ['0002306438886'],
            'supplier_payable_amount' => 25000,
            'payment_currency' => 'PKR',
            'payment_type' => 'Cash',
        ], $contextOverrides);

        return Booking::factory()->create([
            'agency_id' => $agency->id,
            'pnr' => (string) ($context['pnr'] ?? 'SECRETPNR'),
            'supplier' => SupplierProvider::Airblue->value,
            'payment_status' => 'paid',
            'meta' => ['airblue_context' => $context],
        ]);
    }

    /**
     * @param  array<string, mixed>  $credentialOverrides
     */
    private function connection(array $credentialOverrides = []): SupplierConnection
    {
        $agency = Agency::query()->firstOrFail();
        $protocol = (string) ($credentialOverrides['protocol_version'] ?? '3.0');
        $base = $protocol === '3.0'
            ? 'https://otatest4.zapways.com/v3.0/OTAAPI.asmx'
            : 'https://otatest4.zapways.com/v2.0/OTAAPI.asmx';

        return SupplierConnection::query()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Airblue,
            'name' => 'AirBlue Refund Fixture '.$protocol,
            'environment' => SupplierEnvironment::Sandbox,
            'base_url' => $base,
            'is_active' => true,
            'credentials' => array_merge([
                'api_channel' => 'zapways_ota',
                'protocol_version' => $protocol,
                'client_id' => 'fixture-client',
                'client_key' => 'fixture-key',
                'agent_type' => '5',
                'agent_id' => 'fixture-agent',
                'agent_password' => 'fixture-secret',
                'service_target' => 'Test',
                'refund_enabled' => false,
            ], $credentialOverrides),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function v3Config(): array
    {
        return [
            'protocol_version' => AirBlueZapwaysProtocolVersion::V3->value,
            'namespace' => AirBlueZapwaysProtocolVersion::V3->namespaceUri(),
            'client_id' => 'CLIENT',
            'client_key' => 'KEY',
            'agent_type' => '5',
            'agent_id' => 'AGENT',
            'agent_password' => 'secret',
            'service_target' => 'Test',
            'service_version' => '1.04',
            'currency' => 'PKR',
        ];
    }
}
