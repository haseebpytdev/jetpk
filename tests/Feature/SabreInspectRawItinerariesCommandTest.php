<?php

namespace Tests\Feature;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreInspectRawItinerariesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.shop_path', '/v4/offers/shop');

        parent::tearDown();
    }

    public function test_command_blocked_outside_local_and_testing(): void
    {
        Config::set('app.env', 'production');

        $exit = Artisan::call('sabre:inspect-raw-itineraries');

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('only runs when APP_ENV is local or testing', Artisan::output());
    }

    public function test_command_prints_carrier_histogram_without_raw_response_body(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_segment_order_baggage_brand.json')), true);

        Http::fake([
            '*v2/auth/token*' => Http::response(['access_token' => 'sabre-test-token-abc', 'expires_in' => 3600], 200),
            '*v4/offers/shop*' => Http::response($fixture, 200),
        ]);

        SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => [
                'client_id' => 'oauth-id-marker',
                'client_secret' => 'oauth-secret-marker',
                'pcc' => 'PCCSECRET99',
            ],
            'base_url' => 'https://example.sabre.test',
        ]);

        $exit = Artisan::call('sabre:inspect-raw-itineraries', [
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-09-01',
            '--show-rejected' => true,
        ]);

        $this->assertSame(0, $exit);
        $out = Artisan::output();
        $this->assertStringContainsString('raw_carrier_histogram', $out);
        $this->assertStringContainsString('"PK"', $out);
        $this->assertStringContainsString('normalized_route_chain', $out);
        $this->assertStringContainsString('normalized_carrier_chain', $out);
        $this->assertStringNotContainsString('PCCSECRET99', $out);
        $this->assertStringNotContainsString('oauth-secret-marker', $out);
        $this->assertStringNotContainsString('sabre-test-token-abc', $out);
        $this->assertStringNotContainsString('Bearer', $out);
    }

    public function test_carrier_pk_filters_to_pk_touching_itineraries(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_segment_order_baggage_brand.json')), true);

        Http::fake([
            '*v2/auth/token*' => Http::response(['access_token' => 'token', 'expires_in' => 3600], 200),
            '*v4/offers/shop*' => Http::response($fixture, 200),
        ]);

        SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'a', 'client_secret' => 'b', 'pcc' => 'P'],
            'base_url' => 'https://example.sabre.test',
        ]);

        $exit = Artisan::call('sabre:inspect-raw-itineraries', [
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-09-01',
            '--carrier' => 'PK',
            '--limit' => '5',
        ]);

        $this->assertSame(0, $exit);
        $out = Artisan::output();
        $this->assertStringContainsString('PK+EK', $out);
        $this->assertStringContainsString('pk_raw_itinerary_count', $out);
    }

    public function test_pk_raw_itinerary_accepted_shows_normalizer_status_accepted(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_segment_order_baggage_brand.json')), true);

        Http::fake([
            '*v2/auth/token*' => Http::response(['access_token' => 'token', 'expires_in' => 3600], 200),
            '*v4/offers/shop*' => Http::response($fixture, 200),
        ]);

        SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'a', 'client_secret' => 'b', 'pcc' => 'P'],
            'base_url' => 'https://example.sabre.test',
        ]);

        $exit = Artisan::call('sabre:inspect-raw-itineraries', [
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-09-01',
            '--carrier' => 'PK',
        ]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('"normalizer_status": "accepted"', Artisan::output());
    }

    public function test_pk_raw_itinerary_rejected_shows_reject_reason_safely(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_multi_segment_lhe_doh.json')), true);
        $fixture['groupedItineraryResponse']['scheduleDescs'][1]['departure']['time'] = '2026-08-20T08:00:00';

        Http::fake([
            '*v2/auth/token*' => Http::response(['access_token' => 'token', 'expires_in' => 3600], 200),
            '*v4/offers/shop*' => Http::response($fixture, 200),
        ]);

        SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'a', 'client_secret' => 'b', 'pcc' => 'P'],
            'base_url' => 'https://example.sabre.test',
        ]);

        $exit = Artisan::call('sabre:inspect-raw-itineraries', [
            '--from' => 'LHE',
            '--to' => 'DOH',
            '--date' => '2026-09-01',
            '--carrier' => 'PK',
        ]);

        $this->assertSame(0, $exit);
        $out = Artisan::output();
        $this->assertStringContainsString('"normalizer_status": "rejected"', $out);
        $this->assertStringContainsString('"reject_reason": "segment_datetime_continuity_failed"', $out);
        $this->assertStringContainsString('reject_meta_safe', $out);
    }

    public function test_accepted_inspect_row_includes_pricing_fields_when_default_agency_exists(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_segment_order_baggage_brand.json')), true);

        Http::fake([
            '*v2/auth/token*' => Http::response(['access_token' => 'token', 'expires_in' => 3600], 200),
            '*v4/offers/shop*' => Http::response($fixture, 200),
        ]);

        $agency = Agency::factory()->create(['slug' => (string) config('ota.default_agency_slug')]);
        SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'a', 'client_secret' => 'b', 'pcc' => 'P'],
            'base_url' => 'https://example.sabre.test',
        ]);

        $exit = Artisan::call('sabre:inspect-raw-itineraries', [
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-09-01',
            '--carrier' => 'PK',
            '--limit' => '2',
        ]);

        $this->assertSame(0, $exit);
        $out = Artisan::output();
        $this->assertStringContainsString('"pricing_supplier_total"', $out);
        $this->assertStringContainsString('"fare_verification_status"', $out);
    }
}
