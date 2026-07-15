<?php

namespace Tests\Unit;

use App\Enums\SupplierProvider;
use App\Services\Suppliers\Sabre\Diagnostics\SabreRevalidationPayloadStyleComparator;
use App\Services\Suppliers\Sabre\SabreRevalidationPayloadBuilder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Sprint 11K-J — revalidation payload coverage summary + style comparison (no live Sabre).
 */
class SabreRevalidationPayloadCoveragePhase11KJTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function fixtureDraft(): array
    {
        return [
            'provider' => SupplierProvider::Sabre->value,
            'selected_offer_id' => '11kj-fixture-offer',
            'supplier_offer_id' => '11kj-fixture-offer',
            'validating_carrier' => 'EK',
            'fare' => ['amount' => 450.0, 'currency' => 'USD'],
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'departure_at' => '2026-09-01T10:00:00',
                    'arrival_at' => '2026-09-01T14:00:00',
                    'carrier' => 'EK',
                    'operating_airline_code' => 'EK',
                    'flight_number' => '615',
                    'booking_class' => 'K',
                    'fare_basis_code' => 'KLOWPK',
                ],
            ],
            'passengers' => [['type' => 'ADT']],
            '_sabre_shop_context' => [
                'itinerary_group_index' => 1,
                'itinerary_ref' => '10',
                'pricing_information_index' => 2,
                'leg_refs' => [3],
                'schedule_refs' => [9],
                'fare_component_refs' => [7],
                'pricing_information_ref' => 'pi-2',
            ],
            '_sabre_shop_identifiers' => [
                'pseudo_city_code' => 'TEST',
            ],
        ];
    }

    public function test_normalized_payload_coverage_summary_is_scalar_only(): void
    {
        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $payload = $builder->buildPayload($this->fixtureDraft(), 'bfm_revalidate_v1');
        $summary = $builder->normalizedPayloadCoverageSummary($payload);

        $this->assertSame('bfm_revalidate_v1', $summary['payload_style']);
        foreach ($summary as $key => $value) {
            $this->assertIsString($key);
            $this->assertTrue(
                is_bool($value) || is_int($value) || is_string($value),
                'coverage summary value must be scalar: '.$key,
            );
        }
        $this->assertArrayHasKey('has_pos', $summary);
        $this->assertArrayHasKey('has_50itins', $summary);
        $this->assertArrayHasKey('selected_offer_context_present', $summary);
        $this->assertArrayHasKey('pricing_context_present', $summary);
    }

    public function test_baseline_and_iati_like_coverage_differ_predictably(): void
    {
        $comparator = $this->app->make(SabreRevalidationPayloadStyleComparator::class);
        $report = $comparator->compareForDraft($this->fixtureDraft());

        $baseline = $report['styles']['bfm_revalidate_v1'] ?? [];
        $iati = $report['styles']['iati_like_bfm_revalidate_v1'] ?? [];
        $pricing = $report['styles']['bfm_revalidate_with_pricing_context'] ?? [];

        $this->assertSame('bfm_revalidate_v1', $report['recommended_production_default']);
        $this->assertTrue($report['production_default_unchanged']);
        $this->assertFalse($report['safe_to_enable_iati_like_via_config']);

        $this->assertTrue($baseline['has_vendor_pref'] ?? false);
        $this->assertTrue($baseline['has_price_request_information'] ?? false);
        $this->assertTrue($baseline['selected_offer_context_present'] ?? false);

        $this->assertTrue($iati['has_data_sources'] ?? false);
        $this->assertTrue($iati['has_50itins'] ?? false);
        $this->assertTrue($iati['has_seats_requested'] ?? false);
        $this->assertFalse($iati['has_price_request_information'] ?? true);
        $this->assertFalse($iati['has_vendor_pref'] ?? true);

        $this->assertTrue($pricing['pricing_context_present'] ?? false);
        $this->assertContains('has_data_sources', $report['iati_stronger_than_baseline_fields']);
        $this->assertContains('has_50itins', $report['iati_stronger_than_baseline_fields']);
        $this->assertContains('has_price_request_information', $report['baseline_stronger_than_iati_fields']);
        $this->assertContains('has_vendor_pref', $report['baseline_stronger_than_iati_fields']);
        $this->assertContains('selected_offer_context_present', $report['baseline_stronger_than_iati_fields']);
        $this->assertContains('pricing_context_present', $report['baseline_stronger_than_iati_fields']);

        $builder = $this->app->make(SabreRevalidationPayloadBuilder::class);
        $pricingPayload = $builder->buildPayload($this->fixtureDraft(), 'bfm_revalidate_with_pricing_context');
        $pricingSafe = $builder->safePayloadSummary($pricingPayload);
        $this->assertTrue($pricingSafe['has_reconstructed_pricing_context'] ?? false);
    }

    public function test_compare_payload_coverage_command_uses_fixture_without_http(): void
    {
        Http::fake();

        $exit = Artisan::call('sabre:compare-revalidate-payload-coverage', ['--fixture' => true]);
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('diagnostic_only=true', $out);
        $this->assertStringContainsString('live_sabre_http=false', $out);
        $this->assertStringContainsString('bfm_revalidate_v1', $out);
        $this->assertStringContainsString('iati_like_bfm_revalidate_v1', $out);
        $this->assertStringContainsString('recommended_production_default=bfm_revalidate_v1', $out);
        $this->assertStringNotContainsString('Authorization', $out);
        $this->assertStringNotContainsString('PseudoCityCode', $out);
        Http::assertNothingSent();
    }

    public function test_config_default_remains_bfm_revalidate_v1(): void
    {
        $this->assertSame('bfm_revalidate_v1', config('suppliers.sabre.revalidate_payload_style'));
    }
}
