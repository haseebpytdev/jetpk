<?php

namespace Tests\Feature;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingTicket;
use App\Models\SupplierConnection;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreGdsTicketServicingCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        Config::set('suppliers.sabre.void_enabled', false);
        Config::set('suppliers.sabre.refund_enabled', false);
        Http::fake();
    }

    public function test_void_command_blocks_when_env_disabled(): void
    {
        [$booking, $ticket] = $this->bookingWithTicket();
        Artisan::call('sabre:gds-void-ticket', [
            '--booking' => (string) $booking->id,
            '--ticket' => $ticket,
            '--dry-run' => true,
        ]);
        $output = Artisan::output();
        $this->assertStringContainsString('void_disabled_by_env', $output);
        $this->assertStringContainsString('live_supplier_call_attempted', $output);
        Http::assertNothingSent();
    }

    public function test_refund_command_blocks_when_env_disabled(): void
    {
        [$booking, $ticket] = $this->bookingWithTicket();
        Artisan::call('sabre:gds-refund-ticket', [
            '--booking' => (string) $booking->id,
            '--ticket' => $ticket,
            '--dry-run' => true,
        ]);
        $output = Artisan::output();
        $this->assertStringContainsString('refund_disabled_by_env', $output);
        Http::assertNothingSent();
    }

    public function test_ticket_documents_dry_run(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $connectionId = SupplierConnection::query()
            ->where('provider', SupplierProvider::Sabre->value)
            ->value('id');
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'pnr' => 'DOC1',
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $connectionId,
            ],
        ]);

        Artisan::call('sabre:gds-ticket-documents', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);

        $this->assertStringContainsString('dry_run_only', Artisan::output());
        Http::assertNothingSent();
    }

    /**
     * @return array{0: Booking, 1: string}
     */
    private function bookingWithTicket(): array
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'pnr' => 'VOID1',
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => ['supplier_provider' => SupplierProvider::Sabre->value],
        ]);
        BookingTicket::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'ticket_number' => '176-9999999999',
            'pnr' => 'VOID1',
            'provider' => SupplierProvider::Sabre->value,
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        return [$booking, '176-9999999999'];
    }
}
