<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\AirBlue\AirBlueCancelService;
use App\Services\Suppliers\AirBlue\AirBlueClient;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueCancellationException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueProviderException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueUnavailableException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueXmlException;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class AirBlueCancelMutationSafetyTest extends AirBlueOfflineTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function booking(array $context = []): Booking
    {
        $agency = Agency::query()->firstOrFail();

        return Booking::factory()->create([
            'agency_id' => $agency->id,
            'pnr' => 'CNCLPNR1',
            'supplier' => SupplierProvider::Airblue->value,
            'meta' => [
                'airblue_context' => array_merge([
                    'pnr' => 'CNCLPNR1',
                    'instance' => 'INST1',
                    'protocol_version' => '3.0',
                    'api_channel' => 'zapways_ota',
                    'ticketing_status' => 'pending_ticketing',
                ], $context),
            ],
        ]);
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

    public function test_ticketed_status_blocks_cancel_with_zero_provider_calls(): void
    {
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')->never();
        $this->app->instance(AirBlueClient::class, $client);

        $this->expectException(AirBlueCancellationException::class);
        $this->expectExceptionMessage('refund workflow');
        try {
            app(AirBlueCancelService::class)->cancelForBooking(
                $this->booking(['ticketing_status' => 'ticketed']),
                $this->connection(),
                User::factory()->create(),
            );
        } catch (AirBlueCancellationException $e) {
            $this->assertSame('ticketed_booking_requires_refund', $e->normalizedCode);
            throw $e;
        }
    }

    public function test_ticket_numbers_block_cancel(): void
    {
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')->never();
        $this->app->instance(AirBlueClient::class, $client);

        try {
            app(AirBlueCancelService::class)->cancelForBooking(
                $this->booking(['ticket_numbers' => ['1111111111111']]),
                $this->connection(),
                User::factory()->create(),
            );
            $this->fail('expected ticketed_booking_requires_refund');
        } catch (AirBlueCancellationException $e) {
            $this->assertSame('ticketed_booking_requires_refund', $e->normalizedCode);
        }
    }

    public function test_unticketed_cancel_still_allowed(): void
    {
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')->once()->andReturn([
            'parsed' => ['cancellation_status' => 'cancelled'],
            'errors' => [],
            'soap_fault' => null,
        ]);
        $this->app->instance(AirBlueClient::class, $client);

        $booking = $this->booking();
        $result = app(AirBlueCancelService::class)->cancelForBooking(
            $booking->fresh(),
            $this->connection(),
            User::factory()->create(),
        );

        $this->assertTrue($result['success'] ?? false);
        $this->assertTrue($booking->fresh()->meta['airblue_context']['cancel_committed'] ?? false);
    }

    public function test_duplicate_cancel_committed_guard_remains(): void
    {
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')->never();
        $this->app->instance(AirBlueClient::class, $client);

        try {
            app(AirBlueCancelService::class)->cancelForBooking(
                $this->booking(['cancel_committed' => true]),
                $this->connection(),
                User::factory()->create(),
            );
            $this->fail('expected duplicate_cancellation_guard');
        } catch (AirBlueCancellationException $e) {
            $this->assertSame('duplicate_cancellation_guard', $e->normalizedCode);
        }
    }

    public function test_transport_http_soap_malformed_after_send_are_uncertain_and_block_retry(): void
    {
        $cases = [
            new AirBlueUnavailableException('supplier_transport_failed', 503, 'Unavailable.'),
            new AirBlueProviderException('supplier_http_error', 502, 'HTTP error.'),
            new AirBlueProviderException('soap_fault', 502, 'SOAP fault.'),
            new AirBlueXmlException('malformed_xml', 502, 'Invalid XML.'),
        ];

        foreach ($cases as $exception) {
            $client = Mockery::mock(AirBlueClient::class);
            $client->shouldReceive('callOta')->once()->andThrow($exception);
            $this->app->instance(AirBlueClient::class, $client);

            $booking = $this->booking();
            try {
                app(AirBlueCancelService::class)->cancelForBooking(
                    $booking->fresh(),
                    $this->connection(),
                    User::factory()->create(),
                );
                $this->fail('expected uncertain cancel');
            } catch (AirBlueCancellationException $e) {
                $this->assertSame('uncertain', $booking->fresh()->meta['airblue_context']['cancellation_outcome'] ?? null);
            }

            $client2 = Mockery::mock(AirBlueClient::class);
            $client2->shouldReceive('callOta')->never();
            $this->app->instance(AirBlueClient::class, $client2);

            try {
                app(AirBlueCancelService::class)->cancelForBooking(
                    $booking->fresh(),
                    $this->connection(),
                    User::factory()->create(),
                );
                $this->fail('expected cancellation_outcome_uncertain');
            } catch (AirBlueCancellationException $e) {
                $this->assertSame('cancellation_outcome_uncertain', $e->normalizedCode);
            }
        }
    }

    public function test_provider_error_is_definitive_not_uncertain(): void
    {
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')->once()->andThrow(new AirBlueProviderException(
            'provider_error',
            422,
            'Cancel rejected.',
        ));
        $this->app->instance(AirBlueClient::class, $client);

        $booking = $this->booking();
        try {
            app(AirBlueCancelService::class)->cancelForBooking(
                $booking->fresh(),
                $this->connection(),
                User::factory()->create(),
            );
            $this->fail('expected provider_error');
        } catch (AirBlueCancellationException $e) {
            $this->assertSame('provider_error', $e->normalizedCode);
            $this->assertNotSame('uncertain', $booking->fresh()->meta['airblue_context']['cancellation_outcome'] ?? null);
        }
    }

    public function test_pre_send_missing_context_does_not_set_uncertain(): void
    {
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')->never();
        $this->app->instance(AirBlueClient::class, $client);

        $booking = $this->booking(['instance' => '']);
        try {
            app(AirBlueCancelService::class)->cancelForBooking(
                $booking->fresh(),
                $this->connection(),
                User::factory()->create(),
            );
            $this->fail('expected missing_order_context');
        } catch (AirBlueCancellationException $e) {
            $this->assertSame('missing_order_context', $e->normalizedCode);
            $this->assertNotSame('uncertain', $booking->fresh()->meta['airblue_context']['cancellation_outcome'] ?? null);
        }
    }

    public function test_refunded_booking_blocks_cancel(): void
    {
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')->never();
        $this->app->instance(AirBlueClient::class, $client);

        try {
            app(AirBlueCancelService::class)->cancelForBooking(
                $this->booking(['refund_status' => 'refunded', 'ticketing_status' => 'ticketed']),
                $this->connection(),
                User::factory()->create(),
            );
            $this->fail('expected refunded or ticketed block');
        } catch (AirBlueCancellationException $e) {
            $this->assertContains($e->normalizedCode, [
                'refunded_booking_cancel_blocked',
                'ticketed_booking_requires_refund',
            ]);
        }
    }
}
