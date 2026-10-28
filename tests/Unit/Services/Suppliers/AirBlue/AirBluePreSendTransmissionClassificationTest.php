<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\AirBlue\AirBlueAmbiguousMutationClassifier;
use App\Services\Suppliers\AirBlue\AirBlueAncillaryService;
use App\Services\Suppliers\AirBlue\AirBlueCancelService;
use App\Services\Suppliers\AirBlue\AirBlueClient;
use App\Services\Suppliers\AirBlue\AirBlueRefundService;
use App\Services\Suppliers\AirBlue\AirBlueTicketingService;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueCancellationException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueUnavailableException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueValidationException;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

/**
 * Ensures local pre-network failures are not classified as post-send mutations.
 */
class AirBluePreSendTransmissionClassificationTest extends AirBlueOfflineTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    private function connection(array $credentialOverrides = []): SupplierConnection
    {
        return new SupplierConnection([
            'provider' => SupplierProvider::Airblue,
            'environment' => SupplierEnvironment::Sandbox,
            'status' => 'active',
            'credentials' => array_merge([
                'api_channel' => 'zapways_ota',
                'protocol_version' => '3.0',
                'client_id' => 'CLIENT',
                'client_key' => 'KEY',
                'agent_type' => '5',
                'agent_id' => 'AGENT',
                'agent_password' => 'secret',
                'ticketing_enabled' => true,
                'refund_enabled' => true,
            ], $credentialOverrides),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function booking(array $context = []): Booking
    {
        $agency = Agency::query()->firstOrFail();

        return Booking::factory()->create([
            'agency_id' => $agency->id,
            'pnr' => 'PREPNR01',
            'supplier' => SupplierProvider::Airblue->value,
            'payment_status' => 'paid',
            'meta' => [
                'airblue_context' => array_merge([
                    'pnr' => 'PREPNR01',
                    'instance' => 'INST1',
                    'protocol_version' => '3.0',
                    'api_channel' => 'zapways_ota',
                    'supplier_payable_amount' => 25000,
                    'payment_currency' => 'PKR',
                    'payment_type' => 'Cash',
                    'seat_selection_required' => false,
                    'ticketing_status' => 'pending_ticketing',
                ], $context),
            ],
        ]);
    }

    private function missingSoapAction(): AirBlueValidationException
    {
        return new AirBlueValidationException(
            'missing_soap_action',
            422,
            'SOAPAction for AirBlue operation is not configured.',
            AirBlueAmbiguousMutationClassifier::withTransmissionState(
                [],
                AirBlueAmbiguousMutationClassifier::TRANSMISSION_PRE_SEND,
            ),
        );
    }

    public function test_ticketing_missing_soap_action_is_pre_send_without_uncertain(): void
    {
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')->once()->andThrow($this->missingSoapAction());
        $this->app->instance(AirBlueClient::class, $client);

        $booking = $this->booking();
        $result = app(AirBlueTicketingService::class)->issueTickets(
            $booking->fresh(),
            $this->connection(),
            User::factory()->create(),
        );

        $this->assertSame('PRE_SEND_FAILURE', $result->outcome);
        $this->assertSame(0, $result->provider_calls);
        $this->assertSame('missing_soap_action', $result->error_code);
        $this->assertNotSame('uncertain', $booking->fresh()->meta['airblue_context']['ticketing_outcome'] ?? null);
    }

    public function test_type5_missing_soap_action_does_not_persist_uncertain(): void
    {
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')->once()->andThrow($this->missingSoapAction());
        $this->app->instance(AirBlueClient::class, $client);

        $booking = $this->booking();
        try {
            app(AirBlueAncillaryService::class)->modifySeats($this->connection(), [
                'booking' => $booking,
                'pnr' => 'PREPNR01',
                'instance' => 'INST1',
                'modification_type' => '5',
                'seat_changes' => [[
                    'row_number' => '12',
                    'seat_number' => 'A',
                    'traveler_ref_number_rph' => '1',
                    'flight_ref_number_rph' => '1',
                ]],
            ]);
            $this->fail('expected missing_soap_action');
        } catch (AirBlueValidationException $e) {
            $this->assertSame('missing_soap_action', $e->normalizedCode);
            $this->assertNotSame('uncertain', $booking->fresh()->meta['airblue_context']['seat_modify_outcome'] ?? null);
        }
    }

    public function test_cancel_missing_soap_action_does_not_persist_uncertain(): void
    {
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')->once()->andThrow($this->missingSoapAction());
        $this->app->instance(AirBlueClient::class, $client);

        $booking = $this->booking();
        try {
            app(AirBlueCancelService::class)->cancelForBooking(
                $booking->fresh(),
                $this->connection(),
                User::factory()->create(),
            );
            $this->fail('expected missing_soap_action');
        } catch (AirBlueCancellationException $e) {
            $this->assertSame('missing_soap_action', $e->normalizedCode);
            $this->assertNotSame('uncertain', $booking->fresh()->meta['airblue_context']['cancellation_outcome'] ?? null);
        }
    }

    public function test_type1_refund_missing_soap_action_is_pre_send(): void
    {
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')->once()->andThrow($this->missingSoapAction());
        $this->app->instance(AirBlueClient::class, $client);

        $booking = $this->booking([
            'ticketing_status' => 'ticketed',
            'ticket_numbers' => ['1111111111111'],
        ]);
        $result = app(AirBlueRefundService::class)->refundFull(
            $booking->fresh(),
            $this->connection(),
            User::factory()->create(),
        );

        $this->assertSame('PRE_SEND_FAILURE', $result->outcome);
        $this->assertSame(0, $result->provider_calls);
        $this->assertSame('missing_soap_action', $result->error_code);
        $this->assertNotSame('uncertain', $booking->fresh()->meta['airblue_context']['refund_status'] ?? null);
    }

    public function test_transport_after_send_still_uncertain_for_ticketing(): void
    {
        $client = Mockery::mock(AirBlueClient::class);
        $client->shouldReceive('callOta')->once()->andThrow(new AirBlueUnavailableException(
            'supplier_transport_failed',
            503,
            'Unavailable.',
            AirBlueAmbiguousMutationClassifier::withTransmissionState(
                [],
                AirBlueAmbiguousMutationClassifier::TRANSMISSION_SENT_OR_UNCERTAIN,
            ),
        ));
        $this->app->instance(AirBlueClient::class, $client);

        $booking = $this->booking();
        $result = app(AirBlueTicketingService::class)->issueTickets(
            $booking->fresh(),
            $this->connection(),
            User::factory()->create(),
        );

        $this->assertSame('UNCERTAIN_AFTER_TRANSMISSION', $result->outcome);
        $this->assertSame(1, $result->provider_calls);
        $this->assertSame('uncertain', $booking->fresh()->meta['airblue_context']['ticketing_outcome'] ?? null);
    }
}
