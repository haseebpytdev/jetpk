<?php

namespace Tests\Feature;

use App\Console\Commands\SabreInspectShopPayloadCommand;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Diagnostics\SabreInspectSanitizer;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreInspectShopPayloadCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.shop_path', '/v4/offers/shop');
        Config::set('suppliers.sabre.branded_fares_search_enabled', false);
        Config::set('suppliers.sabre.branded_fares_probe_enabled', false);
        Config::set('suppliers.sabre.branded_fares_request_variant', 'current_tis_tpa');

        parent::tearDown();
    }

    public function test_gate_allows_only_local_and_testing(): void
    {
        $this->assertFalse(SabreInspectGate::allowed('production'));
        $this->assertFalse(SabreInspectGate::allowed('staging'));
        $this->assertTrue(SabreInspectGate::allowed('local'));
        $this->assertTrue(SabreInspectGate::allowed('testing'));
    }

    public function test_inspect_command_aborts_when_app_env_not_allowed(): void
    {
        Config::set('app.env', 'production');

        $exit = Artisan::call('sabre:inspect-shop-payload');

        $this->assertSame(1, $exit);
        $this->assertStringContainsString(
            'Production requires --confirm='.SabreInspectShopPayloadCommand::PRODUCTION_READONLY_CONFIRM_PHRASE,
            Artisan::output()
        );
    }

    public function test_production_readonly_preview_blocked_without_confirm(): void
    {
        Config::set('app.env', 'production');

        SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['pcc' => 'MYPCCX'],
            'base_url' => 'https://example.sabre.test',
        ]);

        $exit = Artisan::call('sabre:inspect-shop-payload', [
            '--variant' => 'minimal',
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-06-07',
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString(
            'Production requires --confirm='.SabreInspectShopPayloadCommand::PRODUCTION_READONLY_CONFIRM_PHRASE,
            Artisan::output()
        );
    }

    public function test_production_readonly_preview_blocked_with_invalid_confirm(): void
    {
        Config::set('app.env', 'production');

        SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['pcc' => 'MYPCCX'],
            'base_url' => 'https://example.sabre.test',
        ]);

        $exit = Artisan::call('sabre:inspect-shop-payload', [
            '--confirm' => 'WRONG-TOKEN',
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Invalid --confirm phrase', Artisan::output());
    }

    public function test_production_readonly_preview_allowed_with_confirm(): void
    {
        Config::set('app.env', 'production');

        SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => [
                'client_id' => 'sabre_inspect_test_client_id_marker',
                'client_secret' => 'csecret',
                'pcc' => 'MYPCCX',
            ],
            'base_url' => 'https://example.sabre.test',
        ]);

        $exit = Artisan::call('sabre:inspect-shop-payload', [
            '--confirm' => SabreInspectShopPayloadCommand::PRODUCTION_READONLY_CONFIRM_PHRASE,
            '--variant' => 'minimal',
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-06-07',
        ]);

        $this->assertSame(0, $exit);
        $out = Artisan::output();
        $this->assertStringContainsString('app_env=production', $out);
        $this->assertStringContainsString('live_supplier_call_attempted=false', $out);
        $this->assertStringContainsString('payload_preview_only=true', $out);
        $this->assertStringContainsString('variant=minimal', $out);
        $this->assertStringContainsString('sanitized_payload_preview=', $out);
        $this->assertStringContainsString('Read-only payload preview only', $out);
        $this->assertStringNotContainsString('MYPCCX', $out);
    }

    public function test_production_live_send_blocked_even_with_confirm(): void
    {
        Config::set('app.env', 'production');

        SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['pcc' => 'MYPCCX'],
            'base_url' => 'https://example.sabre.test',
        ]);

        $exit = Artisan::call('sabre:inspect-shop-payload', [
            '--confirm' => SabreInspectShopPayloadCommand::PRODUCTION_READONLY_CONFIRM_PHRASE,
            '--send' => true,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Production live send is not allowed by this command.', Artisan::output());
    }

    public function test_staging_env_still_blocked(): void
    {
        Config::set('app.env', 'staging');

        $exit = Artisan::call('sabre:inspect-shop-payload');

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('only runs when APP_ENV is local or testing', Artisan::output());
    }

    public function test_sanitizer_masks_pcc_and_sensitive_keys(): void
    {
        $payload = [
            'OTA_AirLowFareSearchRQ' => [
                'POS' => ['Source' => [['PseudoCityCode' => 'REALPCC99']]],
                'nested' => ['client_secret' => 'secret123'],
            ],
        ];
        $masked = SabreInspectSanitizer::maskShopPayload($payload);
        $blob = json_encode($masked);
        $this->assertStringNotContainsString('REALPCC99', $blob);
        $this->assertStringNotContainsString('secret123', $blob);
        $this->assertStringContainsString('***PCC***', $blob);
        $this->assertStringContainsString('***REDACTED***', $blob);
    }

    public function test_sanitize_error_body_extracts_nested_errors(): void
    {
        $json = [
            'message' => 'Bad Request',
            'provider_message' => '27131',
            'errors' => [[
                'status' => '400',
                'code' => '27131',
                'title' => 'Validation',
                'detail' => 'Something wrong',
            ]],
        ];
        $safe = SabreInspectSanitizer::sanitizeErrorBody($json);
        $this->assertSame('27131', $safe['errors'][0]['code'] ?? null);
        $this->assertArrayHasKey('message', $safe);
        $this->assertSame('27131', $safe['provider_message'] ?? null);
    }

    public function test_sanitize_error_body_coerces_numeric_provider_message(): void
    {
        $safe = SabreInspectSanitizer::sanitizeErrorBody(['provider_message' => 27131]);
        $this->assertSame('27131', $safe['provider_message'] ?? null);
    }

    public function test_inspect_command_outputs_masked_payload_without_credentials(): void
    {
        SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => [
                'client_id' => 'sabre_inspect_test_client_id_marker',
                'client_secret' => 'csecret',
                'pcc' => 'MYPCCX',
            ],
            'base_url' => 'https://example.sabre.test',
        ]);

        $exit = Artisan::call('sabre:inspect-shop-payload', [
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-06-07',
        ]);

        $this->assertSame(0, $exit);
        $out = Artisan::output();
        $this->assertStringContainsString('***PCC***', $out);
        $this->assertStringNotContainsString('MYPCCX', $out);
        $this->assertStringNotContainsString('csecret', $out);
        $this->assertStringNotContainsString('sabre_inspect_test_client_id_marker', $out);
        $this->assertStringContainsString('connection_id=', $out);
        $this->assertStringContainsString('variant=current', $out);
        $this->assertStringContainsString('branded_fares_search_enabled=false', $out);
        $this->assertStringContainsString('branded_fares_request_variant=current_tis_tpa', $out);
        $this->assertStringContainsString('branded_fare_qualifier_added=false', $out);
        $this->assertStringContainsString('branded_fare_qualifier_path=OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators', $out);
        $this->assertStringContainsString('branded_fare_indicators_keys=[]', $out);
        $this->assertStringContainsString('live_supplier_call_attempted=false', $out);
        $this->assertStringContainsString('payload_preview_only=true', $out);
        $this->assertStringContainsString('endpoint_host=', $out);
        $this->assertStringContainsString('endpoint_path=/v4/offers/shop', $out);
        $this->assertStringContainsString('sanitized_payload_preview=', $out);
        $this->assertStringContainsString('"Version": "4"', $out);
        $this->assertStringContainsString('"Cabin": "Y"', $out);
    }

    public function test_inspect_command_reflects_shop_path_config_override(): void
    {
        Config::set('suppliers.sabre.shop_path', '/v5/offers/shop');

        SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => [
                'client_id' => 'sabre_inspect_test_client_id_marker',
                'client_secret' => 'csecret',
                'pcc' => 'MYPCCX',
            ],
            'base_url' => 'https://example.sabre.test',
        ]);

        $exit = Artisan::call('sabre:inspect-shop-payload', [
            '--from' => 'LHE',
            '--to' => 'DXB',
        ]);

        $this->assertSame(0, $exit);
        $out = Artisan::output();
        $this->assertStringContainsString('variant=current', $out);
        $this->assertStringContainsString('endpoint_path=/v5/offers/shop', $out);
        $this->assertStringContainsString('"Version": "5"', $out);
        $this->assertStringContainsString('"Cabin": "Y"', $out);
    }

    public function test_inspect_command_rejects_invalid_variant(): void
    {
        SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['pcc' => 'X'],
            'base_url' => 'https://example.sabre.test',
        ]);

        $exit = Artisan::call('sabre:inspect-shop-payload', [
            '--variant' => 'nope',
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Invalid --variant', Artisan::output());
    }

    public function test_inspect_minimal_variant_uses_bfm_v4_trimmed_shape(): void
    {
        Config::set('suppliers.sabre.shop_path', '/v5/offers/shop');

        SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => [
                'client_id' => 'sabre_inspect_test_client_id_marker',
                'client_secret' => 'csecret',
                'pcc' => 'MYPCCX',
            ],
            'base_url' => 'https://example.sabre.test',
        ]);

        $exit = Artisan::call('sabre:inspect-shop-payload', [
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-06-07',
            '--variant' => 'minimal',
        ]);

        $this->assertSame(0, $exit);
        $out = Artisan::output();
        $this->assertStringContainsString('variant=minimal', $out);
        $this->assertStringContainsString('endpoint_path=/v5/offers/shop', $out);
        $this->assertStringContainsString('"Version": "4"', $out);
        $this->assertStringContainsString('"50ITINS"', $out);
        $this->assertStringContainsString('"Code": "ADT"', $out);
        $this->assertStringContainsString('***PCC***', $out);
        $this->assertStringNotContainsString('MYPCCX', $out);

        foreach ([
            'TravelPreferences',
            'PriceRequestInformation',
            'DataSources',
            'NumTrips',
            'PublicFare',
            'CodeContext',
            'LocationType',
            'DepartureWindow',
            'SegmentType',
            'CabinPref',
        ] as $excluded) {
            $this->assertStringNotContainsString('"'.$excluded.'"', $out, 'minimal preview must omit '.$excluded);
        }

        $this->assertStringNotContainsString('"Currency":', $out);
    }

    public function test_inspect_command_flag_false_omits_branded_fare_indicators(): void
    {
        Config::set('suppliers.sabre.branded_fares_search_enabled', false);

        SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['pcc' => 'MYPCCX'],
            'base_url' => 'https://example.sabre.test',
        ]);

        $exit = Artisan::call('sabre:inspect-shop-payload', [
            '--variant' => 'current',
        ]);

        $this->assertSame(0, $exit);
        $out = Artisan::output();
        $this->assertStringContainsString('branded_fares_search_enabled=false', $out);
        $this->assertStringContainsString('branded_fare_qualifier_added=false', $out);
        $this->assertStringNotContainsString('"BrandedFareIndicators"', $out);
    }

    public function test_inspect_command_flag_true_includes_branded_fare_indicators(): void
    {
        Config::set('suppliers.sabre.branded_fares_search_enabled', true);

        SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['pcc' => 'MYPCCX'],
            'base_url' => 'https://example.sabre.test',
        ]);

        $exit = Artisan::call('sabre:inspect-shop-payload', [
            '--variant' => 'current',
        ]);

        $this->assertSame(0, $exit);
        $out = Artisan::output();
        $this->assertStringContainsString('branded_fares_search_enabled=true', $out);
        $this->assertStringContainsString('branded_fares_request_variant=current_tis_tpa', $out);
        $this->assertStringContainsString('branded_fare_qualifier_added=true', $out);
        $this->assertStringContainsString('BrandedFareIndicators', $out);
    }

    public function test_inspect_command_reflects_iati_full_tis_tpa_variant_in_output(): void
    {
        Config::set('suppliers.sabre.branded_fares_search_enabled', true);
        Config::set('suppliers.sabre.branded_fares_request_variant', 'iati_full_tis_tpa');

        SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['pcc' => 'MYPCCX'],
            'base_url' => 'https://example.sabre.test',
        ]);

        $exit = Artisan::call('sabre:inspect-shop-payload', [
            '--variant' => 'minimal',
        ]);

        $this->assertSame(0, $exit);
        $out = Artisan::output();
        $this->assertStringContainsString('branded_fares_request_variant=iati_full_tis_tpa', $out);
        $this->assertStringContainsString('branded_fare_qualifier_added=true', $out);
        $this->assertStringContainsString('branded_fare_indicators_keys=["MultipleBrandedFares","ReturnBrandAncillaries","SingleBrandedFare"]', $out);
        $this->assertStringContainsString('ReturnBrandAncillaries', $out);
        $this->assertStringContainsString('"100ITINS"', $out);
        $this->assertStringContainsString('payload_preview_only=true', $out);
    }

    public function test_inspect_command_reflects_iati_exact_gds_v4_variant_in_output(): void
    {
        Config::set('suppliers.sabre.branded_fares_search_enabled', true);
        Config::set('suppliers.sabre.branded_fares_request_variant', 'iati_exact_gds_v4');

        SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['pcc' => 'MYPCCX'],
            'base_url' => 'https://example.sabre.test',
        ]);

        $exit = Artisan::call('sabre:inspect-shop-payload', [
            '--variant' => 'minimal',
        ]);

        $this->assertSame(0, $exit);
        $out = Artisan::output();
        $this->assertStringContainsString('branded_fares_request_variant=iati_exact_gds_v4', $out);
        $this->assertStringContainsString('iati_alignment_profile=true', $out);
        $this->assertStringContainsString('branded_fare_qualifier_added=true', $out);
        $this->assertStringContainsString('branded_fare_indicators_keys=["MultipleBrandedFares","ReturnBrandAncillaries","SingleBrandedFare"]', $out);
        $this->assertStringContainsString('ReturnBrandAncillaries', $out);
        $this->assertStringContainsString('"200ITINS"', $out);
        $this->assertStringContainsString('payload_preview_only=true', $out);
        $this->assertStringContainsString('live_supplier_call_attempted=false', $out);
        $this->assertStringContainsString('endpoint_path=/v4/offers/shop', $out);
        $this->assertStringNotContainsString('"DepartureWindow"', $out);
        $this->assertStringNotContainsString('"NumTrips"', $out);
        $this->assertStringNotContainsString('"PublicFare"', $out);
    }

    public function test_inspect_command_reflects_root_price_tpa_variant_in_output(): void
    {
        Config::set('suppliers.sabre.branded_fares_search_enabled', true);
        Config::set('suppliers.sabre.branded_fares_request_variant', 'root_price_tpa');

        SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['pcc' => 'MYPCCX'],
            'base_url' => 'https://example.sabre.test',
        ]);

        $exit = Artisan::call('sabre:inspect-shop-payload', [
            '--variant' => 'current',
        ]);

        $this->assertSame(0, $exit);
        $out = Artisan::output();
        $this->assertStringContainsString('branded_fares_request_variant=root_price_tpa', $out);
        $this->assertStringContainsString('branded_fare_qualifier_path=OTA_AirLowFareSearchRQ.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators', $out);
        $this->assertStringContainsString('branded_fare_qualifier_added=true', $out);
        $this->assertStringContainsString('live_supplier_call_attempted=false', $out);
        $this->assertStringContainsString('payload_preview_only=true', $out);
    }

    public function test_shop_payload_cert_send_gate_requires_probe_flag_on_production(): void
    {
        $connection = SupplierConnection::factory()->make([
            'base_url' => 'https://api-crt.cert.havail.sabre.com',
        ]);

        Config::set('suppliers.sabre.branded_fares_probe_enabled', false);
        $this->assertFalse(SabreInspectGate::shopPayloadCertSendAllowed($connection, 'production'));
        $this->assertSame(
            'shop_payload_cert_send_requires_probe_flag',
            SabreInspectGate::shopPayloadCertSendBlockReason($connection, 'production')
        );

        Config::set('suppliers.sabre.branded_fares_probe_enabled', true);
        $this->assertTrue(SabreInspectGate::shopPayloadCertSendAllowed($connection, 'production'));
    }

    public function test_shop_payload_cert_send_gate_blocks_live_production_host(): void
    {
        Config::set('suppliers.sabre.branded_fares_probe_enabled', true);

        $connection = SupplierConnection::factory()->make([
            'base_url' => 'https://api.platform.sabre.com',
        ]);

        $this->assertFalse(SabreInspectGate::shopPayloadCertSendAllowed($connection, 'production'));
        $this->assertSame(
            'shop_payload_cert_send_live_host_blocked',
            SabreInspectGate::shopPayloadCertSendBlockReason($connection, 'production')
        );
    }

    public function test_production_cert_send_blocked_without_probe_flag(): void
    {
        Config::set('app.env', 'production');
        Config::set('suppliers.sabre.branded_fares_probe_enabled', false);

        SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'a', 'client_secret' => 'b', 'pcc' => 'X'],
            'base_url' => 'https://api-crt.cert.havail.sabre.com',
        ]);

        $exit = Artisan::call('sabre:inspect-shop-payload', [
            '--confirm' => SabreInspectShopPayloadCommand::PRODUCTION_CERT_SEND_CONFIRM_PHRASE,
            '--send' => true,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('shop_payload_cert_send_requires_probe_flag', Artisan::output());
    }

    public function test_production_live_host_send_blocked_with_cert_confirm(): void
    {
        Config::set('app.env', 'production');
        Config::set('suppliers.sabre.branded_fares_probe_enabled', true);

        SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'a', 'client_secret' => 'b'],
            'base_url' => 'https://api.platform.sabre.com',
        ]);

        $exit = Artisan::call('sabre:inspect-shop-payload', [
            '--confirm' => SabreInspectShopPayloadCommand::PRODUCTION_CERT_SEND_CONFIRM_PHRASE,
            '--send' => true,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('shop_payload_cert_send_live_host_blocked', Artisan::output());
    }

    public function test_production_cert_send_with_summary_emits_branded_fare_diagnostics(): void
    {
        Config::set('app.env', 'production');
        Config::set('suppliers.sabre.branded_fares_search_enabled', true);
        Config::set('suppliers.sabre.branded_fares_probe_enabled', true);

        $shopResponse = $this->minimalBrandedShopResponseFixture();

        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'tok', 'expires_in' => 1800], 200),
            '*/v4/offers/shop' => Http::response($shopResponse, 200),
        ]);

        SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'a', 'client_secret' => 'b', 'pcc' => 'PCCX'],
            'base_url' => 'https://api-crt.cert.havail.sabre.com',
        ]);

        $exit = Artisan::call('sabre:inspect-shop-payload', [
            '--confirm' => SabreInspectShopPayloadCommand::PRODUCTION_CERT_SEND_CONFIRM_PHRASE,
            '--variant' => 'current',
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-08-15',
            '--send' => true,
            '--summary' => true,
        ]);

        $this->assertSame(0, $exit);
        $out = Artisan::output();
        $this->assertStringContainsString('cert_shop_send=true', $out);
        $this->assertStringContainsString('live_supplier_call_attempted=true', $out);
        $this->assertStringContainsString('payload_preview_only=false', $out);
        $this->assertStringContainsString('branded_fares_search_enabled=true', $out);
        $this->assertStringContainsString('branded_fares_request_variant=current_tis_tpa', $out);
        $this->assertStringContainsString('branded_fares_probe_enabled=true', $out);
        $this->assertStringContainsString('http_status=200', $out);
        $this->assertStringContainsString('branded_fares_probe_diagnostics=', $out);
        $this->assertStringContainsString('brand_field_paths_observed=', $out);
        $this->assertStringContainsString('pi_rows_with_brand_code', $out);
        $this->assertStringContainsString('distinct_brand_codes_count', $out);
        $this->assertStringNotContainsString('PCCX', $out);
    }

    public function test_local_send_summary_includes_branded_fare_probe_diagnostics(): void
    {
        Config::set('suppliers.sabre.branded_fares_search_enabled', true);
        Config::set('suppliers.sabre.branded_fares_probe_enabled', true);

        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'tok', 'expires_in' => 1800], 200),
            '*/v4/offers/shop' => Http::response($this->minimalBrandedShopResponseFixture(), 200),
        ]);

        SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'a', 'client_secret' => 'b', 'pcc' => 'PCCX'],
            'base_url' => 'https://api-crt.cert.havail.sabre.com',
        ]);

        $exit = Artisan::call('sabre:inspect-shop-payload', [
            '--variant' => 'current',
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-08-15',
            '--send' => true,
            '--summary' => true,
        ]);

        $this->assertSame(0, $exit);
        $out = Artisan::output();
        $this->assertStringContainsString('branded_fares_probe_diagnostics=', $out);
        $this->assertStringContainsString('"pi_rows_with_brand_code": 2', $out);
        $this->assertStringContainsString('"branded_fares_options_count": 2', $out);
    }

    public function test_local_send_summary_reports_descriptor_brand_diagnostics(): void
    {
        Config::set('suppliers.sabre.branded_fares_search_enabled', true);
        Config::set('suppliers.sabre.branded_fares_probe_enabled', true);

        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'tok', 'expires_in' => 1800], 200),
            '*/v4/offers/shop' => Http::response($this->descriptorBrandedShopResponseFixture(), 200),
        ]);

        SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'a', 'client_secret' => 'b', 'pcc' => 'PCCX'],
            'base_url' => 'https://api-crt.cert.havail.sabre.com',
        ]);

        $exit = Artisan::call('sabre:inspect-shop-payload', [
            '--variant' => 'current',
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-08-15',
            '--send' => true,
            '--summary' => true,
        ]);

        $this->assertSame(0, $exit);
        $out = Artisan::output();
        $this->assertStringContainsString('"fare_component_desc_count": 2', $out);
        $this->assertStringContainsString('"fare_component_descs_with_brand_count": 2', $out);
        $this->assertStringContainsString('"pi_rows_with_descriptor_brand_code": 2', $out);
        $this->assertStringContainsString('"pi_rows_with_inline_brand_code": 0', $out);
        $this->assertStringContainsString('"branded_fares_options_count": 2', $out);
        $this->assertStringContainsString('descriptor_brand_sample_keys', $out);
    }

    /**
     * @return array<string, mixed>
     */
    protected function descriptorBrandedShopResponseFixture(): array
    {
        $fixture = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_descriptor_brands.json')),
            true
        );
        $this->assertIsArray($fixture);

        return $fixture;
    }

    /**
     * @return array<string, mixed>
     */
    protected function minimalBrandedShopResponseFixture(): array
    {
        $fareBlock = static fn (int $totalCents, string $brandCode, string $brandName) => [
            'fare' => [
                'validatingCarrierCode' => 'EK',
                'totalFare' => [
                    'currency' => 'USD',
                    'totalPrice' => $totalCents,
                    'baseFareAmount' => (int) round($totalCents * 0.8),
                    'totalTaxAmount' => (int) round($totalCents * 0.2),
                ],
                'passengerInfoList' => [[
                    'passengerInfo' => [
                        'nonRefundable' => false,
                        'fareComponents' => [[
                            'brandCode' => $brandCode,
                            'fareFamilyName' => $brandName,
                        ]],
                    ],
                ]],
            ],
        ];

        return [
            'groupedItineraryResponse' => [
                'version' => '6',
                'scheduleDescs' => [
                    ['ref' => 1, 'departure' => ['airport' => 'LHE', 'time' => '2026-08-15T02:00:00'], 'arrival' => ['airport' => 'DXB', 'time' => '2026-08-15T05:00:00'], 'elapsedTime' => 180, 'carrier' => ['marketing' => 'EK', 'marketingFlightNumber' => '601']],
                ],
                'legDescs' => [
                    ['ref' => 1, 'elapsedTime' => 180, 'schedules' => [['ref' => 1]]],
                ],
                'itineraryGroups' => [
                    [
                        'itineraries' => [
                            [
                                'id' => 'bf3-itin-1',
                                'legs' => [['ref' => 1]],
                                'pricingInformation' => [
                                    $fareBlock(50000, 'ECO', 'Economy Saver'),
                                    $fareBlock(65000, 'FLX', 'Economy Flex'),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
