<?php

namespace Tests\Feature;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Support\Bookings\SupplierLifecycleContextResolver;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreNdcCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        Config::set('suppliers.sabre.ndc.enabled', false);
        Config::set('suppliers.sabre.ndc.order_create_enabled', false);
        Http::fake();
    }

    public function test_ndc_status_does_not_emit_legacy_env_disabled_blocker(): void
    {
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Live,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'settings' => ['sabre_ndc_enabled' => true, 'sabre_gds_enabled' => false],
            'credentials' => [
                'client_id' => 'status-test-client',
                'client_secret' => 'status-test-secret',
            ],
        ]);

        Artisan::call('sabre:ndc-status', ['--connection' => (string) $connection->id]);
        $decoded = json_decode(trim(Artisan::output()), true);
        $this->assertIsArray($decoded);
        $this->assertTrue($decoded['effective_ndc_enabled']);
        $this->assertNotContains('sabre_ndc_disabled', $decoded['blockers']);
    }

    public function test_ndc_order_create_blocks_when_env_disabled(): void
    {
        $booking = $this->sabreBooking();
        Artisan::call('sabre:ndc-create-order', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);
        $output = Artisan::output();
        $this->assertStringContainsString('order_create_disabled_by_env', $output);
        $this->assertStringContainsString('live_supplier_call_attempted', $output);
        Http::assertNothingSent();
    }

    public function test_ndc_retrieve_handles_missing_order_id_safely(): void
    {
        $booking = $this->sabreBooking();
        Artisan::call('sabre:ndc-retrieve-order', [
            '--booking' => (string) $booking->id,
            '--dry-run' => true,
        ]);
        $decoded = json_decode(trim(Artisan::output()), true);
        $this->assertContains('missing_order_id', $decoded['blockers']);
        $this->assertFalse($decoded['live_supplier_call_attempted']);
    }

    public function test_ndc_capability_report_command_is_read_only(): void
    {
        Artisan::call('sabre:ndc-capability-report', ['--json' => true]);
        $decoded = json_decode(trim(Artisan::output()), true);
        $this->assertFalse($decoded['live_supplier_call_attempted']);
        $this->assertFalse($decoded['capabilities']['order_create']['enabled']);
        Http::assertNothingSent();
    }

    public function test_ndc_connection_probe_without_send_makes_no_http(): void
    {
        Artisan::call('sabre:ndc-connection-probe', ['--json' => true]);
        $decoded = json_decode(trim(Artisan::output()), true);
        $this->assertFalse($decoded['live_supplier_call_attempted']);
        $this->assertTrue($decoded['auth_probe_only']);
        Http::assertNothingSent();
    }

    public function test_gds_booking_meta_does_not_route_to_ndc_handler(): void
    {
        $booking = $this->sabreBooking(['distribution_channel' => 'gds']);
        $ctx = app(SupplierLifecycleContextResolver::class)->resolve($booking);
        $this->assertSame(
            SupplierLifecycleContextResolver::HANDLER_SABRE_GDS,
            $ctx['handler_key'],
        );
    }

    private function sabreBooking(array $metaExtra = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        return Booking::factory()->create([
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => array_merge(['supplier_provider' => SupplierProvider::Sabre->value], $metaExtra),
        ]);
    }
}
