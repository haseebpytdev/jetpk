<?php

namespace Tests\Unit\Support\FlightSearch;

use App\Support\FlightSearch\PublicMulticityInquiryPolicy;
use App\Support\FlightSearch\PublicSabreMulticitySearchPostProcessor;
use App\Support\FlightSearch\SabreMixedCarrierSearchResultsFilter;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioMulticityClassifier;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicSabreMulticitySearchPostProcessorTest extends TestCase
{
    #[Test]
    public function test_duplicate_qr_style_offers_collapse_for_public_multicity_search(): void
    {
        Config::set('suppliers.sabre.hide_mixed_carrier_search_results', true);
        $processor = app(PublicSabreMulticitySearchPostProcessor::class);
        $criteria = [
            'trip_type' => 'multi_city',
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DOH', 'departure_date' => '2026-08-20'],
                ['origin' => 'DOH', 'destination' => 'LHE', 'departure_date' => '2026-08-27'],
            ],
        ];

        $offers = [
            $this->sabreOffer('offer-a', 1043.1, 'QR', 'ECONVENIEN'),
            $this->sabreOffer('offer-b', 1043.1, 'QR', 'ECONVENIEN'),
            $this->sabreOffer('offer-c', 1100.0, 'QR', 'ECONVENIEN'),
        ];

        $result = $processor->process($offers, $criteria);

        $this->assertCount(2, $result['offers']);
        $this->assertSame(3, $result['diagnostics']['multicity_candidates_before_dedup']);
        $this->assertSame(2, $result['diagnostics']['multicity_candidates_after_dedup']);
        $this->assertSame(1, $result['diagnostics']['multicity_duplicate_candidates_removed_count']);
        $this->assertTrue($result['offers'][0]['multicity_inquiry_only']);
        $this->assertSame(PublicMulticityInquiryPolicy::BLOCK_REASON, $result['offers'][0]['block_reason']);
        $this->assertTrue($result['offers'][0]['supplier_offer_key_present']);
        $this->assertSame('true_multicity_shop', $result['diagnostics']['multicity_search_path']);
    }

    #[Test]
    public function test_mixed_carrier_offers_are_hidden_before_dedup(): void
    {
        Config::set('suppliers.sabre.hide_mixed_carrier_search_results', true);
        $processor = app(PublicSabreMulticitySearchPostProcessor::class);
        $criteria = [
            'trip_type' => 'multi_city',
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DXB', 'departure_date' => '2026-08-20'],
                ['origin' => 'DXB', 'destination' => 'KHI', 'departure_date' => '2026-08-27'],
            ],
        ];

        $result = $processor->process([
            $this->sabreOffer('same-a', 900.0, 'PK', 'ECONOMY', ['PK', 'PK']),
            $this->sabreOffer('mixed-a', 850.0, 'PK', 'ECONOMY', ['PK', 'EK']),
        ], $criteria);

        $this->assertCount(1, $result['offers']);
        $this->assertSame(1, $result['diagnostics']['mixed_carrier_offers_filtered_count']);
        $this->assertSame('PK', $result['offers'][0]['carrier_chain']);
    }

    /**
     * @param  list<string>  $marketing
     * @return array<string, mixed>
     */
    protected function sabreOffer(string $id, float $fare, string $validating, string $brand, array $marketing = ['QR', 'QR']): array
    {
        $segments = [];
        foreach ($marketing as $idx => $code) {
            $segments[] = [
                'origin' => $idx === 0 ? 'LHE' : 'DOH',
                'destination' => $idx === count($marketing) - 1 ? 'LHE' : 'DOH',
                'departure_at' => '2026-08-20T08:00:00',
                'arrival_at' => '2026-08-20T10:00:00',
                'airline_code' => $code,
                'marketing_carrier' => $code,
            ];
        }

        return [
            'id' => $id,
            'offer_id' => $id,
            'supplier_provider' => 'sabre',
            'supplier_offer_id' => 'supplier-'.$id,
            'validating_carrier' => $validating,
            'marketing_carrier_chain' => $marketing,
            'mixed_carrier' => count(array_unique($marketing)) > 1,
            'classification' => count(array_unique($marketing)) > 1
                ? SabreGdsLiveScenarioMulticityClassifier::CATEGORY_MIXED_CARRIER
                : SabreGdsLiveScenarioMulticityClassifier::CATEGORY_SAME_CARRIER,
            'segments' => $segments,
            'fare_breakdown' => [
                'supplier_total' => $fare,
                'currency' => 'USD',
                'base_fare' => $fare * 0.85,
                'taxes' => $fare * 0.15,
            ],
            'final_customer_price' => $fare,
            'currency' => 'USD',
            'pricing_currency' => 'USD',
            'sabre_booking_context' => [
                'selected_brand_code' => $brand,
                'booking_classes_by_segment' => ['Y', 'Y'],
                'fare_basis_codes_by_segment' => ['VOWQR', 'VOWQR'],
                'cabin_by_segment' => ['Y', 'Y'],
            ],
        ];
    }
}
