<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Services\Pricing\PricingRuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FxConversionLayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_pkr_fare_is_converted_to_pkr_using_live_rate_response(): void
    {
        Cache::flush();
        Http::fake([
            'https://api.frankfurter.app/latest*' => Http::response([
                'base' => 'USD',
                'date' => '2026-05-06',
                'rates' => ['PKR' => 280.0],
            ], 200),
        ]);

        $agency = Agency::factory()->create();
        $pricing = app(PricingRuleService::class)->calculateMarkup($agency, [
            'base_fare' => 100.0,
            'taxes' => 20.0,
            'currency' => 'USD',
        ], [
            'route' => 'LHE-DXB',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'supplier' => 'duffel',
            'source_channel' => 'public_guest',
        ]);

        $this->assertSame('converted', $pricing['conversion_status']);
        $this->assertSame('USD', $pricing['supplier_currency']);
        $this->assertSame('PKR', $pricing['pricing_currency']);
        $this->assertSame(280.0, (float) $pricing['fx_rate']);
        $this->assertSame(28000.0, (float) $pricing['base_fare']);
        $this->assertSame(5600.0, (float) $pricing['taxes']);
    }

    public function test_conversion_missing_keeps_non_pkr_currency_and_does_not_fake_rs_pricing(): void
    {
        Cache::flush();
        Http::fake([
            'https://api.frankfurter.app/latest*' => Http::response([], 503),
            'https://open.er-api.com/v6/latest/*' => Http::response([], 503),
        ]);

        $agency = Agency::factory()->create();
        $pricing = app(PricingRuleService::class)->calculateMarkup($agency, [
            'base_fare' => 100.0,
            'taxes' => 20.0,
            'currency' => 'USD',
        ], [
            'route' => 'LHE-DXB',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'supplier' => 'duffel',
            'source_channel' => 'public_guest',
        ]);

        $this->assertSame('conversion_missing', $pricing['conversion_status']);
        $this->assertSame('USD', $pricing['supplier_currency']);
        $this->assertSame('USD', $pricing['pricing_currency']);
        $this->assertNull($pricing['fx_rate']);
    }

    public function test_secondary_provider_is_used_when_primary_fails(): void
    {
        Cache::flush();
        Http::fake([
            'https://api.frankfurter.app/latest*' => Http::response([], 503),
            'https://open.er-api.com/v6/latest/USD' => Http::response([
                'result' => 'success',
                'rates' => ['PKR' => 279.5],
            ], 200),
        ]);

        $agency = Agency::factory()->create();
        $pricing = app(PricingRuleService::class)->calculateMarkup($agency, [
            'base_fare' => 100.0,
            'taxes' => 20.0,
            'currency' => 'USD',
        ], [
            'route' => 'LHE-DXB',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'supplier' => 'duffel',
            'source_channel' => 'public_guest',
        ]);

        $this->assertSame('converted', $pricing['conversion_status']);
        $this->assertSame('PKR', $pricing['pricing_currency']);
        $this->assertSame(279.5, (float) $pricing['fx_rate']);
    }
}
