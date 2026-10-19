<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Data\FlightSearchRequestData;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\AirBlue\AirBlueCancelService;
use App\Services\Suppliers\AirBlue\AirBlueClient;
use App\Services\Suppliers\AirBlue\AirBlueFlightSearchService;
use App\Services\Suppliers\AirBlue\AirBlueRetrieveService;
use App\Services\Suppliers\AirBlue\AirBlueTicketingService;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueValidationException;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class AirBlueExternalCallGateTest extends AirBlueOfflineTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        config(['suppliers.airblue.external_calls_enabled' => false]);
        Http::preventStrayRequests();
    }

    public function test_config_defaults_external_calls_disabled(): void
    {
        $this->assertFalse((bool) config('suppliers.airblue.external_calls_enabled'));
    }

    public function test_client_fails_closed_before_http(): void
    {
        $connection = $this->connection();

        try {
            app(AirBlueClient::class)->callOta($connection, 'air_low_fare_search', '<xml/>');
            $this->fail('Expected external_calls_disabled');
        } catch (AirBlueValidationException $exception) {
            $this->assertSame('external_calls_disabled', $exception->normalizedCode);
        }

        Http::assertNothingSent();
    }

    public function test_search_blocked_before_http(): void
    {
        $result = app(AirBlueFlightSearchService::class)->search(
            new FlightSearchRequestData(
                origin: 'KHI',
                destination: 'ISB',
                departure_date: '2026-11-01',
                adults: 1,
            ),
            $this->connection(),
        );

        $this->assertSame([], $result->offers);
        $this->assertSame('external_calls_disabled', $result->meta['error_code'] ?? null);
        Http::assertNothingSent();
    }

    public function test_book_blocked_before_http_via_client(): void
    {
        $this->expectClientGate('air_book');
    }

    public function test_retrieve_blocked_before_http(): void
    {
        $booking = $this->bookingWithOrderContext();
        $result = app(AirBlueRetrieveService::class)->retrieveAndSync($booking, $this->connection());

        $this->assertFalse($result['synced'] ?? true);
        $this->assertSame('external_calls_disabled', $result['reason'] ?? null);
        Http::assertNothingSent();
    }

    public function test_cancel_blocked_before_http(): void
    {
        $booking = $this->bookingWithOrderContext();
        $actor = User::factory()->create();

        try {
            app(AirBlueCancelService::class)->cancelForBooking($booking, $this->connection(), $actor);
            $this->fail('Expected cancel gate');
        } catch (AirBlueValidationException $exception) {
            $this->assertSame('external_calls_disabled', $exception->normalizedCode);
        } catch (\App\Services\Suppliers\AirBlue\Exceptions\AirBlueCancellationException $exception) {
            // Cancel service may wrap transport/validation failures.
            $this->assertSame('external_calls_disabled', $exception->normalizedCode);
        }

        Http::assertNothingSent();
    }

    public function test_ticket_blocked_by_feature_and_external_gate(): void
    {
        $booking = $this->bookingWithOrderContext();
        $actor = User::factory()->create();
        $connection = $this->connection([
            'ticketing_enabled' => false,
        ], 'AirBlue Ticket Gate A');

        $disabledFeature = app(AirBlueTicketingService::class)->issueTickets($booking, $connection, $actor);
        $this->assertFalse($disabledFeature->success);
        $this->assertSame('ticketing_feature_disabled', $disabledFeature->error_code);
        Http::assertNothingSent();

        $enabledFeature = $this->connection([
            'ticketing_enabled' => true,
        ], 'AirBlue Ticket Gate B');
        $disabledExternal = app(AirBlueTicketingService::class)->issueTickets($booking, $enabledFeature, $actor);
        $this->assertFalse($disabledExternal->success);
        $this->assertSame('external_calls_disabled', $disabledExternal->error_code);
        Http::assertNothingSent();
    }

    private function expectClientGate(string $operation): void
    {
        try {
            app(AirBlueClient::class)->callOta($this->connection(), $operation, '<xml/>');
            $this->fail('Expected external_calls_disabled for '.$operation);
        } catch (AirBlueValidationException $exception) {
            $this->assertSame('external_calls_disabled', $exception->normalizedCode);
        }

        Http::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $credentialOverrides
     */
    private function connection(array $credentialOverrides = [], ?string $name = null): SupplierConnection
    {
        $agency = Agency::query()->firstOrFail();
        static $seq = 0;
        $seq++;

        return SupplierConnection::query()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Airblue,
            'name' => $name ?? ('AirBlue External Gate '.$seq),
            'environment' => SupplierEnvironment::Sandbox,
            'is_active' => true,
            'credentials' => array_merge([
                'api_channel' => 'zapways_ota',
                'protocol_version' => '2.0',
                'client_id' => 'fixture-client',
                'client_key' => 'fixture-key',
                'agent_type' => '5',
                'agent_id' => 'fixture-agent',
                'agent_password' => 'fixture-secret',
                'booking_enabled' => true,
                'ticketing_enabled' => false,
                'cancel_enabled' => false,
                'certification_status' => 'pending',
            ], $credentialOverrides),
        ]);
    }

    private function bookingWithOrderContext(): Booking
    {
        $agency = Agency::query()->firstOrFail();

        return Booking::factory()->create([
            'agency_id' => $agency->id,
            'pnr' => 'ABPNR1',
            'supplier_reference' => 'ABPNR1',
            'meta' => [
                'supplier_provider' => SupplierProvider::Airblue->value,
                'airblue_context' => [
                    'pnr' => 'ABPNR1',
                    'instance' => 'INST9',
                    'protocol_version' => '2.0',
                    'supplier_payable_amount' => 14500,
                    'payment_currency' => 'PKR',
                ],
            ],
        ]);
    }
}
