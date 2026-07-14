<?php

namespace Tests\Unit\Support\FlightSearch;

use App\Support\FlightSearch\SabreMixedCarrierSearchResultsFilter;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SabreMixedCarrierSearchResultsFilterTest extends TestCase
{
    #[Test]
    public function test_same_carrier_connecting_remains_visible(): void
    {
        Config::set('suppliers.sabre.hide_mixed_carrier_search_results', true);
        $filter = app(SabreMixedCarrierSearchResultsFilter::class);

        $offers = [
            $this->offer(['PK', 'PK'], false),
            $this->offer(['PK'], false),
        ];

        $result = $filter->filterDisplayOffers($offers);
        $this->assertCount(2, $result['offers']);
        $this->assertSame(0, $result['diagnostics']['mixed_carrier_offers_filtered_count']);
    }

    #[Test]
    public function test_mixed_carrier_pk_ek_offer_is_hidden(): void
    {
        Config::set('suppliers.sabre.hide_mixed_carrier_search_results', true);
        $filter = app(SabreMixedCarrierSearchResultsFilter::class);

        $result = $filter->filterDisplayOffers([
            $this->offer(['PK', 'EK'], true),
            $this->offer(['PK'], false),
        ]);

        $this->assertCount(1, $result['offers']);
        $this->assertSame('PK', $result['offers'][0]['marketing_carrier_chain'][0]);
        $this->assertSame(1, $result['diagnostics']['mixed_carrier_offers_filtered_count']);
        $this->assertContains('PK+EK', $result['diagnostics']['mixed_carrier_filtered_carrier_chains']);
    }

    #[Test]
    public function test_codeshare_same_marketing_not_hidden(): void
    {
        Config::set('suppliers.sabre.hide_mixed_carrier_search_results', true);
        $filter = app(SabreMixedCarrierSearchResultsFilter::class);

        $offer = $this->offer(['PK'], false);
        $offer['segments'] = [
            ['airline_code' => 'PK', 'operating_airline_code' => 'GF', 'origin' => 'LHE', 'destination' => 'DXB'],
            ['airline_code' => 'PK', 'operating_airline_code' => 'PK', 'origin' => 'DXB', 'destination' => 'ISB'],
        ];
        $offer['operating_carrier_chain'] = ['GF', 'PK'];

        $result = $filter->filterDisplayOffers([$offer]);
        $this->assertCount(1, $result['offers']);
        $this->assertFalse($filter->isMixedCarrierOffer($offer));
    }

    #[Test]
    public function test_policy_disabled_shows_mixed_offers(): void
    {
        Config::set('suppliers.sabre.hide_mixed_carrier_search_results', false);
        $filter = app(SabreMixedCarrierSearchResultsFilter::class);

        $result = $filter->filterDisplayOffers([$this->offer(['PK', 'EK'], true)]);
        $this->assertCount(1, $result['offers']);
        $this->assertFalse($result['diagnostics']['mixed_carrier_filter_enabled']);
    }

    #[Test]
    public function test_internal_bypass_includes_mixed_offers(): void
    {
        Config::set('suppliers.sabre.hide_mixed_carrier_search_results', true);
        $filter = app(SabreMixedCarrierSearchResultsFilter::class);

        $result = $filter->filterDisplayOffers(
            [$this->offer(['PK', 'EK'], true)],
            ['include_mixed_carrier_results' => true],
        );

        $this->assertCount(1, $result['offers']);
    }

    #[Test]
    public function test_multicity_mixed_candidate_hidden_by_default(): void
    {
        Config::set('suppliers.sabre.hide_mixed_carrier_search_results', true);
        $filter = app(SabreMixedCarrierSearchResultsFilter::class);

        $result = $filter->filterMulticityPlanCandidates([[
            'classification' => 'multicity_mixed_carrier',
            'mixed_carrier' => true,
            'carrier_chain' => 'PK+EK',
        ]]);

        $this->assertSame([], $result['candidates']);
        $this->assertSame(1, $result['diagnostics']['mixed_carrier_offers_filtered_count']);
    }

    #[Test]
    public function test_all_offers_filtered_by_policy_detects_empty_results_case(): void
    {
        $filter = app(SabreMixedCarrierSearchResultsFilter::class);

        $this->assertTrue($filter->allOffersFilteredByPolicy([
            'mixed_carrier_filter_enabled' => true,
            'offers_before_mixed_filter' => 2,
            'offers_after_mixed_filter' => 0,
            'mixed_carrier_offers_filtered_count' => 2,
        ]));

        $this->assertFalse($filter->allOffersFilteredByPolicy([
            'mixed_carrier_filter_enabled' => true,
            'offers_before_mixed_filter' => 2,
            'offers_after_mixed_filter' => 1,
            'mixed_carrier_offers_filtered_count' => 1,
        ]));
    }

    #[Test]
    public function test_multicity_all_filtered_constants_are_distinct_from_one_way(): void
    {
        $this->assertSame(
            'multicity_all_offers_filtered_by_mixed_carrier_policy',
            SabreMixedCarrierSearchResultsFilter::BLOCK_REASON_MULTICITY_ALL_FILTERED,
        );
        $this->assertStringContainsString('multi-city', SabreMixedCarrierSearchResultsFilter::EMPTY_MULTICITY_RESULTS_CUSTOMER_MESSAGE);
        $this->assertStringContainsString('Sabre returned multi-city offers', SabreMixedCarrierSearchResultsFilter::MULTICITY_ALL_FILTERED_ADMIN_MESSAGE);
        $this->assertNotSame(
            SabreMixedCarrierSearchResultsFilter::EMPTY_RESULTS_CUSTOMER_MESSAGE,
            SabreMixedCarrierSearchResultsFilter::EMPTY_MULTICITY_RESULTS_CUSTOMER_MESSAGE,
        );
    }

    /**
     * @param  list<string>  $marketing
     * @return array<string, mixed>
     */
    protected function offer(array $marketing, bool $mixedFlag): array
    {
        $segments = [];
        foreach ($marketing as $idx => $code) {
            $segments[] = [
                'airline_code' => $code,
                'origin' => $idx === 0 ? 'LHE' : 'DXB',
                'destination' => $idx === count($marketing) - 1 ? 'DXB' : 'DXB',
            ];
        }

        return [
            'offer_id' => 'offer-'.implode('-', $marketing),
            'supplier_provider' => 'sabre',
            'mixed_carrier' => $mixedFlag,
            'marketing_carrier_chain' => $marketing,
            'segments' => $segments,
        ];
    }
}
