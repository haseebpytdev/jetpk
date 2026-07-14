<?php

namespace Tests\Feature;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreCheckServicesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Config::set('app.env', 'testing');

        parent::tearDown();
    }

    public function test_check_services_aborts_when_app_env_not_allowed(): void
    {
        Config::set('app.env', 'production');

        $exit = Artisan::call('sabre:check-services');

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('only runs when APP_ENV is local or testing', Artisan::output());
    }

    public function test_check_services_probes_endpoints_and_prints_safe_summary(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response([], 403),
            '*passengers/create*' => Http::response([], 401),
            '*shop/flights/fares*' => Http::response([], 404),
            '*flight/status*' => Http::response([], 200),
            '*utilities/airports*' => Http::response([], 200),
            '*utilities/airlines*' => Http::response([], 201),
        ]);
        Cache::flush();

        SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://example.sabre.test',
        ]);

        $exit = Artisan::call('sabre:check-services');
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('endpoint availability check', strtolower($out));
        $this->assertStringContainsString('not a real flight search', strtolower($out));
        $this->assertStringContainsString('service=Offers Shop (BFM)', $out);
        $this->assertStringContainsString('endpoint=/v4/offers/shop', $out);
        $this->assertStringContainsString('method=POST', $out);
        $this->assertStringContainsString('http_status=403', $out);
        $this->assertStringContainsString('available=yes', $out);
        $this->assertStringContainsString('ready=no', $out);
        $this->assertStringContainsString('service=Flight Status', $out);
        $this->assertStringContainsString('http_status=200', $out);
        $this->assertStringContainsString('ready=yes', $out);
        $this->assertStringNotContainsString('fake-token-for-tests-only', $out);
        $this->assertStringNotContainsString('Authorization', $out);
    }
}
