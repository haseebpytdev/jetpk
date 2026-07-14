<?php

namespace Tests\Feature;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\Pricing\PricingRuleService;
use App\Support\FlightSearch\SabreFareVerificationDigest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreFareVerificationPhaseS32Test extends TestCase
{
    use RefreshDatabase;

    public function test_pricing_engine_uses_explicit_supplier_total_over_base_plus_tax_sum(): void
    {
        $agency = Agency::factory()->create(['slug' => (string) config('ota.default_agency_slug')]);
        $pricing = app(PricingRuleService::class)->calculateMarkup($agency, [
            'base_fare' => 200000.0,
            'taxes' => 66106.0,
            'supplier_total' => 267106.0,
            'currency' => 'PKR',
        ], [
            'route' => 'LHE-DXB',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'airline' => 'pk',
            'supplier' => 'sabre',
            'source_channel' => 'public_guest',
        ]);

        $this->assertSame(267106.0, (float) $pricing['supplier_total']);
        $this->assertGreaterThan(267106.0, (float) $pricing['final_total']);
    }

    public function test_currency_code_pk_is_treated_as_pkr_for_pricing_fx(): void
    {
        $agency = Agency::factory()->create(['slug' => (string) config('ota.default_agency_slug')]);
        $pricing = app(PricingRuleService::class)->calculateMarkup($agency, [
            'base_fare' => 200000.0,
            'taxes' => 67106.0,
            'supplier_total' => 267106.0,
            'currency' => 'PK',
        ], [
            'route' => 'LHE-DXB',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'airline' => 'pk',
            'supplier' => 'sabre',
            'source_channel' => 'public_guest',
        ]);

        $this->assertSame('PKR', $pricing['pricing_currency']);
        $this->assertSame(267106.0, (float) $pricing['supplier_total_source']);
    }

    public function test_fare_verification_digest_flags_price_mismatch_when_pricing_drops_supplier_total(): void
    {
        $display = [
            'offer_id' => 'sha-offer-test',
            'supplier_provider' => 'sabre',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DXB', 'airline_code' => 'PK', 'flight_number' => '301'],
            ],
            'marketing_carrier_chain' => ['PK'],
            'flight_number' => '301',
            'airline_code' => 'PK',
            'markup' => 861.0,
            'service_fee' => 2499.0,
            'final_customer_price' => 25870.0,
            'currency' => 'PKR',
            'pricing_currency' => 'PKR',
            'conversion_status' => 'same_currency',
            'supplier_total_source' => 267106.0,
            'fare_breakdown' => [
                'base_fare' => 200000.0,
                'taxes' => 66106.0,
                'supplier_total' => 267106.0,
                'currency' => 'PKR',
                'supplier_fees' => 1000.0,
            ],
            'raw_payload' => [
                'sabre_fare_excerpt' => [
                    'total_price' => 267106.0,
                    'currency' => 'PKR',
                    'total_price_field' => 'totalFare.totalPrice',
                ],
            ],
            'pricing_components' => [
                'supplier_total' => 22410.0,
                'pricing_currency' => 'PKR',
                'final_total' => 25870.0,
            ],
        ];

        $digest = SabreFareVerificationDigest::buildFromDisplayOffer($display);
        $this->assertSame(SabreFareVerificationDigest::STATUS_PRICE_MISMATCH, $digest['fare_verification_status']);
    }

    public function test_fare_debug_payload_contains_no_secrets(): void
    {
        $digest = SabreFareVerificationDigest::buildFromDisplayOffer([
            'offer_id' => 'x',
            'supplier_provider' => 'sabre',
            'segments' => [],
            'airline_code' => 'PK',
            'markup' => 1,
            'service_fee' => 2,
            'final_customer_price' => 3,
            'currency' => 'PKR',
            'conversion_status' => 'same_currency',
            'supplier_total_source' => 3,
            'fare_breakdown' => ['supplier_total' => 3, 'currency' => 'PKR', 'base_fare' => 1, 'taxes' => 2, 'supplier_fees' => 0],
            'raw_payload' => ['sabre_fare_excerpt' => ['total_price' => 3, 'currency' => 'PKR']],
            'pricing_components' => ['supplier_total' => 3, 'pricing_currency' => 'PKR', 'final_total' => 6],
        ]);
        $dbg = SabreFareVerificationDigest::fareDebugForApi($digest);
        $this->assertArrayNotHasKey('authorization', $dbg);
        $this->assertArrayHasKey('short_offer_id', $dbg);
    }

    public function test_sabre_verify_fares_command_outputs_table(): void
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

        $exit = Artisan::call('sabre:verify-fares', [
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-09-01',
            '--carrier' => 'PK',
            '--limit' => '3',
        ]);
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('short_offer_id', Artisan::output());
    }
}
