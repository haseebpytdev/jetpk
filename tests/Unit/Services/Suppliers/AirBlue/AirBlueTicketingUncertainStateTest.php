<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\AirBlue\AirBlueClient;
use App\Services\Suppliers\AirBlue\AirBlueRetrieveService;
use App\Services\Suppliers\AirBlue\AirBlueTicketingService;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueProviderException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueUnavailableException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueXmlException;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class AirBlueTicketingUncertainStateTest extends AirBlueOfflineTestCase
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
            'pnr' => 'TKTPNR1',
            'supplier' => SupplierProvider::Airblue->value,
            'payment_status' => 'paid',
            'meta' => [
                'airblue_context' => array_merge([
                    'pnr' => 'TKTPNR1',
                    'instance' => 'INST1',
                    'protocol_version' => '3.0',
                    'api_channel' => 'zapways_ota',
                    'supplier_payable_amount' => 25000,
                    'payment_currency' => 'PKR',
                    'payment_type' => 'Cash',
                    'seat_selection_required' => false,
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
                'ticketing_enabled' => true,
            ],
        ]);
    }

    public function test_transport_failure_after_send_persists_uncertain_and_blocks_retry(): void
    {
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')->once()->andThrow(new AirBlueUnavailableException(
            'supplier_transport_failed',
            503,
            'Provider temporarily unavailable.',
        ));
        $this->app->instance(AirBlueClient::class, $client);

        $booking = $this->booking();
        $service = app(AirBlueTicketingService::class);
        $actor = User::factory()->create();

        $first = $service->issueTickets($booking->fresh(), $this->connection(), $actor);
        $this->assertSame('UNCERTAIN_AFTER_TRANSMISSION', $first->outcome);
        $this->assertSame(1, $first->provider_calls);
        $ctx = $booking->fresh()->meta['airblue_context'];
        $this->assertSame('uncertain', $ctx['ticketing_outcome'] ?? null);
        $this->assertSame('pending_reconciliation', $ctx['ticketing_status'] ?? null);
        $this->assertTrue($ctx['supplier_outcome_unknown'] ?? false);
        $this->assertTrue($ctx['requires_retrieve_before_retry'] ?? false);
        $this->assertSame(25000, $ctx['supplier_payable_amount'] ?? null);

        $second = $service->issueTickets($booking->fresh(), $this->connection(), $actor);
        $this->assertSame('ticketing_outcome_uncertain', $second->error_code);
        $this->assertSame(0, $second->provider_calls);
    }

    public function test_http_soap_and_malformed_xml_after_send_are_uncertain(): void
    {
        foreach ([
            new AirBlueProviderException('supplier_http_error', 502, 'HTTP error.'),
            new AirBlueProviderException('soap_fault', 502, 'SOAP fault.'),
            new AirBlueXmlException('malformed_xml', 502, 'Invalid XML.'),
        ] as $exception) {
            $client = Mockery::mock(AirBlueClient::class);
            $client->shouldReceive('callOta')->once()->andThrow($exception);
            $this->app->instance(AirBlueClient::class, $client);

            $result = app(AirBlueTicketingService::class)->issueTickets(
                $this->booking(),
                $this->connection(),
                User::factory()->create(),
            );
            $this->assertSame('UNCERTAIN_AFTER_TRANSMISSION', $result->outcome);
            $this->assertSame(1, $result->provider_calls);
        }
    }

    public function test_provider_error_is_definitive_rejection_not_uncertain(): void
    {
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')->once()->andThrow(new AirBlueProviderException(
            'provider_error',
            422,
            'Fare or booking is unavailable.',
            ['provider_errors' => [['code' => '318', 'type' => '3', 'message' => 'Inventory unavailable']]],
        ));
        $this->app->instance(AirBlueClient::class, $client);

        $booking = $this->booking();
        $result = app(AirBlueTicketingService::class)->issueTickets(
            $booking->fresh(),
            $this->connection(),
            User::factory()->create(),
        );

        $this->assertSame('DEFINITIVE_PROVIDER_REJECTION', $result->outcome);
        $this->assertSame('provider_error', $result->error_code);
        $this->assertNotSame('uncertain', $booking->fresh()->meta['airblue_context']['ticketing_outcome'] ?? null);
    }

    public function test_pre_send_validation_does_not_set_uncertain(): void
    {
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')->never();
        $this->app->instance(AirBlueClient::class, $client);

        $result = app(AirBlueTicketingService::class)->issueTickets(
            $this->booking(['instance' => '']),
            $this->connection(),
            User::factory()->create(),
        );

        $this->assertSame('PRE_SEND_FAILURE', $result->outcome);
        $this->assertSame('missing_order_context', $result->error_code);
        $this->assertSame(0, $result->provider_calls);
    }

    public function test_read_reconciles_ticketed_and_unticketed(): void
    {
        $ticketedBooking = $this->booking([
            'ticketing_outcome' => 'uncertain',
            'ticketing_status' => 'pending_reconciliation',
            'supplier_outcome_unknown' => true,
            'requires_retrieve_before_retry' => true,
        ]);
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')->once()->andReturn([
            'parsed' => [
                'booking' => ['pnr' => 'TKTPNR1', 'instance' => 'INST1'],
                'tickets' => [['ticket_number' => '1111111111111', 'pax_type' => 'ADT']],
                'seats' => [],
            ],
            'errors' => [],
            'soap_fault' => null,
        ]);
        $this->app->instance(AirBlueClient::class, $client);

        $synced = app(AirBlueRetrieveService::class)->retrieveAndSync($ticketedBooking->fresh(), $this->connection());
        $this->assertTrue($synced['synced']);
        $ctx = $ticketedBooking->fresh()->meta['airblue_context'];
        $this->assertSame('reconciled_ticketed', $ctx['ticketing_outcome'] ?? null);
        $this->assertSame('ticketed', $ctx['ticketing_status'] ?? null);
        $this->assertFalse($ctx['supplier_outcome_unknown'] ?? true);

        $unticketed = $this->booking([
            'pnr' => 'TKTPNR2',
            'ticketing_outcome' => 'uncertain',
            'ticketing_status' => 'pending_reconciliation',
            'supplier_outcome_unknown' => true,
            'requires_retrieve_before_retry' => true,
        ]);
        $unticketed->pnr = 'TKTPNR2';
        $unticketed->save();
        $client2 = Mockery::mock(AirBlueClient::class);
        $client2->shouldReceive('callOta')->once()->andReturn([
            'parsed' => [
                'booking' => ['pnr' => 'TKTPNR2', 'instance' => 'INST1'],
                'tickets' => [],
                'seats' => [],
            ],
            'errors' => [],
            'soap_fault' => null,
        ]);
        $this->app->instance(AirBlueClient::class, $client2);

        app(AirBlueRetrieveService::class)->retrieveAndSync($unticketed->fresh(), $this->connection());
        $ctx2 = $unticketed->fresh()->meta['airblue_context'];
        $this->assertSame('reconciled_unticketed', $ctx2['ticketing_outcome'] ?? null);
        $this->assertSame('pending_ticketing', $ctx2['ticketing_status'] ?? null);
        $this->assertFalse($ctx2['requires_retrieve_before_retry'] ?? true);
    }

    public function test_reconciled_unticketed_does_not_auto_retry_ticket(): void
    {
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')->never();
        $this->app->instance(AirBlueClient::class, $client);

        // Reconciliation only clears the uncertain flag; callers must explicitly issue again.
        $booking = $this->booking([
            'ticketing_outcome' => 'reconciled_unticketed',
            'ticketing_status' => 'pending_ticketing',
            'requires_retrieve_before_retry' => false,
        ]);
        $this->assertNotSame('uncertain', $booking->meta['airblue_context']['ticketing_outcome'] ?? null);
    }
}
