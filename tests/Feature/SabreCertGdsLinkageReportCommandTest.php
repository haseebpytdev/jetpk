<?php

namespace Tests\Feature;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreCertGdsLinkageReportCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.cert_entitlement_matrix_enabled', false);
        parent::tearDown();
    }

    public function test_command_registered(): void
    {
        Artisan::call('list');
        $this->assertStringContainsString('sabre:cert-gds-linkage-report', Artisan::output());
    }

    public function test_blocked_in_production_without_flag(): void
    {
        Config::set('app.env', 'production');
        Config::set('suppliers.sabre.cert_entitlement_matrix_enabled', false);
        $conn = $this->seedSabreConnection('https://api.cert.platform.sabre.com');

        $exit = Artisan::call('sabre:cert-gds-linkage-report', [
            '--connection' => (string) $conn->id,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('sabre_cert_entitlement_matrix_disabled', Artisan::output());
    }

    public function test_blocked_on_live_platform_host(): void
    {
        Config::set('app.env', 'testing');
        $conn = $this->seedSabreConnection('https://api.platform.sabre.com');

        $exit = Artisan::call('sabre:cert-gds-linkage-report', [
            '--connection' => (string) $conn->id,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('blocks api.platform.sabre.com', Artisan::output());
        $this->assertFalse(SabreInspectGate::isCertSabreHost('https://api.platform.sabre.com'));
    }

    public function test_ow_direct_reports_single_segment_linkage(): void
    {
        Config::set('app.env', 'testing');
        $fixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_grouped_refs_response.json')),
            true,
        );
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($fixture, 200),
        ]);
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:cert-gds-linkage-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_direct',
            '--json' => true,
        ]);

        $this->assertSame(0, $exit);
        $payload = $this->decodeReportOutput(Artisan::output());
        $this->assertSame('cert_gds_linkage_v1', $payload['report_version']);
        $this->assertSame('ow_direct', $payload['scenario']);
        $this->assertGreaterThanOrEqual(1, $payload['reported_offer_count']);
        $offer = $payload['offers'][0];
        $this->assertSame(1, $offer['segment_count']);
        $this->assertArrayHasKey('auto_pnr_pricing_context_ready', $offer);
        $this->assertArrayHasKey('pricing_context_policy', $offer);
        $this->assertSame('gds', $offer['distribution_channel']);
    }

    public function test_ow_connecting_highlights_same_carrier_profile(): void
    {
        Config::set('app.env', 'testing');
        $fixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_two_segment_connecting_refs.json')),
            true,
        );
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($fixture, 200),
        ]);
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:cert-gds-linkage-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-09-10',
            '--scenario' => 'ow_connecting',
            '--json' => true,
        ]);

        $this->assertSame(0, $exit);
        $payload = $this->decodeReportOutput(Artisan::output());
        $this->assertGreaterThanOrEqual(1, $payload['reported_offer_count']);
        $offer = $payload['offers'][0];
        $this->assertSame(2, $offer['segment_count']);
        $this->assertSame('same_carrier', $offer['connecting_carrier_profile'] ?? null);
        $this->assertGreaterThanOrEqual(1, $offer['leg_refs_count']);
        $this->assertGreaterThanOrEqual(1, $offer['schedule_refs_count']);
    }

    public function test_return_scenario_requires_return_date(): void
    {
        Config::set('app.env', 'testing');
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:cert-gds-linkage-report', [
            '--connection' => (string) $conn->id,
            '--scenario' => 'return',
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('--return-date', Artisan::output());
    }

    public function test_return_scenario_reports_round_trip_candidates(): void
    {
        Config::set('app.env', 'testing');
        $fixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_round_trip_time_only_lhe_mel.json')),
            true,
        );
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($fixture, 200),
        ]);
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:cert-gds-linkage-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'MEL',
            '--date' => '2026-07-01',
            '--return-date' => '2026-07-15',
            '--scenario' => 'return',
            '--json' => true,
        ]);

        $this->assertSame(0, $exit);
        $payload = $this->decodeReportOutput(Artisan::output());
        $this->assertSame('round_trip', $payload['search']['trip_type']);
        $this->assertGreaterThanOrEqual(1, $payload['reported_offer_count']);
        $offer = $payload['offers'][0];
        $this->assertStringEndsWith('-LHE', (string) ($offer['route'] ?? ''));
    }

    public function test_ndc_distribution_channel_marks_cpnr_ineligible(): void
    {
        Config::set('app.env', 'testing');
        $fixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_grouped_refs_response.json')),
            true,
        );
        $fixture['groupedItineraryResponse']['itineraryGroups'][0]['itineraries'][0]['pricingInformation'][0]['pricingSubsource'] = 'NDC';
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($fixture, 200),
        ]);
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:cert-gds-linkage-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_direct',
            '--json' => true,
        ]);

        $this->assertSame(0, $exit);
        $payload = $this->decodeReportOutput(Artisan::output());
        $offer = $payload['offers'][0];
        $this->assertSame('ndc', $offer['distribution_channel']);
        $this->assertFalse($offer['cpnr_eligible']);
        $this->assertSame('unsupported_distribution_channel', $offer['cpnr_ineligible_reason']);
    }

    public function test_output_does_not_leak_secrets(): void
    {
        Config::set('app.env', 'testing');
        $fixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_grouped_refs_response.json')),
            true,
        );
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'LINKAGE_SECRET_TOKEN_VALUE', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($fixture, 200),
        ]);
        $conn = $this->seedSabreConnection();
        $conn->credentials = [
            'client_id' => 'link_ci_user',
            'client_secret' => 'link_ci_super_secret',
            'pcc' => 'TEST',
            'password' => 'link_ci_password',
        ];
        $conn->save();
        Cache::flush();

        Artisan::call('sabre:cert-gds-linkage-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-08-15',
            '--json' => true,
        ]);

        $encoded = json_encode($this->decodeReportOutput(Artisan::output()));
        $lower = strtolower((string) $encoded);
        $this->assertStringNotContainsString('linkage_secret_token_value', $lower);
        $this->assertStringNotContainsString('link_ci_super_secret', $lower);
        $this->assertStringNotContainsString('link_ci_password', $lower);
        $this->assertStringNotContainsString('bearer ', $lower);
        $this->assertStringNotContainsString('access_token', $lower);
    }

    protected function seedSabreConnection(string $baseUrl = 'https://api.cert.platform.sabre.com'): SupplierConnection
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Sabre)
            ->firstOrFail();
        $conn->base_url = $baseUrl;
        $conn->credentials = ['client_id' => 'link_ci', 'client_secret' => 'link_cs', 'pcc' => 'TEST'];
        $conn->save();
        Cache::flush();

        return $conn;
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeReportOutput(string $output): array
    {
        if (! preg_match('/cert_gds_linkage_report_json=(.+)/s', trim($output), $matches)) {
            $this->fail('Expected cert_gds_linkage_report_json= line in output: '.$output);
        }
        $decoded = json_decode($matches[1], true);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}
