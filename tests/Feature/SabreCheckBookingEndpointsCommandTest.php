<?php

namespace Tests\Feature;

use App\Console\Commands\SabreCheckBookingEndpointsCommand;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreCheckBookingEndpointsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Config::set('app.env', 'testing');

        parent::tearDown();
    }

    public function test_sabre_check_booking_endpoints_command_is_registered(): void
    {
        $exit = Artisan::call('list');
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('sabre:check-booking-endpoints', Artisan::output());
    }

    public function test_check_booking_endpoints_aborts_when_app_env_not_allowed(): void
    {
        Config::set('app.env', 'production');

        $exit = Artisan::call('sabre:check-booking-endpoints');

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('only runs when APP_ENV is local or testing', Artisan::output());
    }

    public function test_access_result_maps_403_to_forbidden_and_422_to_reachable_validation_error(): void
    {
        $this->assertSame('forbidden', SabreCheckBookingEndpointsCommand::accessResultForStatus(403));
        $this->assertSame('reachable_validation_error', SabreCheckBookingEndpointsCommand::accessResultForStatus(422));
        $this->assertSame('reachable_validation_error', SabreCheckBookingEndpointsCommand::accessResultForStatus(400));
        $this->assertSame('ready', SabreCheckBookingEndpointsCommand::accessResultForStatus(200));
    }

    public function test_suppliers_config_declares_trip_orders_create_booking_as_default_booking_path(): void
    {
        $src = (string) file_get_contents(config_path('suppliers.php'));
        $this->assertStringContainsString("env('SABRE_BOOKING_PATH', env('SABRE_LEGACY_BOOKING_PATH', '/v1/trip/orders/createBooking'))", $src);
    }

    public function test_check_booking_endpoints_probes_paths_and_prints_safe_summary(): void
    {
        Config::set('suppliers.sabre.booking_path', '/custom/booking-probe');

        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            'https://example.sabre.test/custom/booking-probe' => Http::response([], 418),
            'https://example.sabre.test/v2/passengers/create' => Http::response([], 401),
            'https://example.sabre.test/v2.5.0/passenger/records' => Http::response([], 422),
            'https://example.sabre.test/v2.4.0/passenger/records' => Http::response([], 403),
            'https://example.sabre.test/v1/trip/orders/createBooking' => Http::response([], 404),
            'https://example.sabre.test/v1/trip/orders' => Http::response([], 405),
            'https://example.sabre.test/v1/trip/orders/getBooking' => Http::response([], 201),
        ]);
        Cache::flush();

        SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://example.sabre.test',
        ]);

        $exit = Artisan::call('sabre:check-booking-endpoints');
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Connectivity probe only', $out);
        $this->assertStringContainsStringIgnoringCase('label=Configured booking_path', $out);
        $this->assertStringContainsString('endpoint_path=/custom/booking-probe', $out);
        $this->assertStringContainsString('endpoint_path=/v2/passengers/create', $out);
        $this->assertStringContainsString('endpoint_path=/v2.5.0/passenger/records', $out);
        $this->assertStringContainsString('http_status=422', $out);
        $this->assertStringContainsString('access_result=reachable_validation_error', $out);
        $this->assertStringContainsString('http_status=403', $out);
        $this->assertStringContainsString('access_result=forbidden', $out);
        $this->assertStringContainsString('endpoint_path=/v1/trip/orders/getBooking', $out);
        $this->assertStringContainsString('http_status=201', $out);
        $this->assertStringContainsString('access_result=ready', $out);
        $this->assertStringContainsString('available=yes', $out);
        $this->assertStringNotContainsString('fake-token-for-tests-only', $out);
        $this->assertStringNotContainsString('Authorization', $out);
    }
}
