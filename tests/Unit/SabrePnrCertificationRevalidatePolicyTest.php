<?php

namespace Tests\Unit;

use App\Services\Suppliers\Sabre\SabreFlightSearchNormalizer;
use App\Support\Bookings\SabrePnrCertificationSupport;
use Tests\TestCase;

class SabrePnrCertificationRevalidatePolicyTest extends TestCase
{
    public function test_simple_one_way_same_carrier_exempt_even_when_pricing_context_missing(): void
    {
        $support = app(SabrePnrCertificationSupport::class);
        $policy = $support->certificationRevalidatePolicy(
            [
                'auto_pnr_pricing_context_ready' => false,
                'missing_pricing_context_fields' => ['pricing_information_ref', 'offer_reference'],
            ],
            [
                'segment_count' => 1,
                'carrier_chain' => ['PK'],
                'validating_carrier' => 'PK',
                'has_codeshare_segment' => false,
                'validating_carrier_mismatch' => false,
            ],
        );

        $this->assertFalse($policy['required']);
        $this->assertTrue($policy['exempt']);
        $this->assertSame('simple_one_way_same_carrier', $policy['exempt_reason']);
    }

    public function test_multi_segment_requires_revalidation(): void
    {
        $support = app(SabrePnrCertificationSupport::class);
        $policy = $support->certificationRevalidatePolicy(
            ['auto_pnr_pricing_context_ready' => true],
            [
                'segment_count' => 2,
                'carrier_chain' => ['PK', 'EK'],
                'validating_carrier' => 'EK',
                'has_codeshare_segment' => true,
                'validating_carrier_mismatch' => true,
            ],
        );

        $this->assertTrue($policy['required']);
        $this->assertContains('multi_segment', $policy['reasons']);
        $this->assertContains('validating_carrier_mismatch', $policy['reasons']);
        $this->assertContains('codeshare_segment', $policy['reasons']);
    }

    public function test_normalizer_syncs_shop_context_linkage_from_identifiers_when_context_empty(): void
    {
        $normalizer = app(SabreFlightSearchNormalizer::class);
        $synced = $normalizer->syncShopContextLinkageFromIdentifiers(
            ['pricing_information_index' => 0, 'itinerary_ref' => '10'],
            [
                'pricing_0_offerItemId' => 'offer-item-live-99',
                'pricing_0_offer_ref' => 'offer-ref-live-99',
            ],
        );

        $this->assertSame('offer-item-live-99', $synced['pricing_information_ref'] ?? null);
        $this->assertSame('offer-ref-live-99', $synced['offer_ref'] ?? null);
    }
}
