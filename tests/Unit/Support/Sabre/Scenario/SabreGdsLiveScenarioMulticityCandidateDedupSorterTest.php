<?php

namespace Tests\Unit\Support\Sabre\Scenario;

use App\Support\FlightSearch\SabreMixedCarrierSearchResultsFilter;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioMulticityCandidateDedupSorter;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioMulticityClassifier;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SabreGdsLiveScenarioMulticityCandidateDedupSorterTest extends TestCase
{
    #[Test]
    public function test_duplicate_qr_style_candidates_collapse_to_one(): void
    {
        $sorter = app(SabreGdsLiveScenarioMulticityCandidateDedupSorter::class);

        $result = $sorter->deduplicateAndSort([
            $this->qrStyleCandidate(['source_offer_id' => 'aaa111', 'internal_offer_key' => 'aaa111']),
            $this->qrStyleCandidate(['source_offer_id' => 'bbb222', 'internal_offer_key' => 'bbb222']),
            $this->qrStyleCandidate(['source_offer_id' => 'ccc333', 'internal_offer_key' => 'ccc333']),
        ]);

        $this->assertCount(1, $result['candidates']);
        $this->assertSame(3, $result['diagnostics']['multicity_candidates_before_dedup']);
        $this->assertSame(1, $result['diagnostics']['multicity_candidates_after_dedup']);
        $this->assertSame(2, $result['diagnostics']['multicity_duplicate_candidates_removed_count']);
        $this->assertTrue($result['diagnostics']['multicity_dedup_enabled']);
        $this->assertSame('v1', $result['diagnostics']['multicity_dedup_key_version']);
        $this->assertTrue($result['candidates'][0]['supplier_offer_key_present']);
    }

    #[Test]
    public function test_different_total_fare_remains_separate(): void
    {
        $sorter = app(SabreGdsLiveScenarioMulticityCandidateDedupSorter::class);

        $result = $sorter->deduplicateAndSort([
            $this->qrStyleCandidate(['total_fare' => 1043.1]),
            $this->qrStyleCandidate(['total_fare' => 1100.0]),
        ]);

        $this->assertCount(2, $result['candidates']);
        $this->assertSame(0, $result['diagnostics']['multicity_duplicate_candidates_removed_count']);
    }

    #[Test]
    public function test_different_brand_remains_separate(): void
    {
        $sorter = app(SabreGdsLiveScenarioMulticityCandidateDedupSorter::class);

        $result = $sorter->deduplicateAndSort([
            $this->qrStyleCandidate(['brand_code' => 'ECONVENIEN']),
            $this->qrStyleCandidate(['brand_code' => 'BUSINESS']),
        ]);

        $this->assertCount(2, $result['candidates']);
    }

    #[Test]
    public function test_different_validating_carrier_remains_separate(): void
    {
        $sorter = app(SabreGdsLiveScenarioMulticityCandidateDedupSorter::class);

        $result = $sorter->deduplicateAndSort([
            $this->qrStyleCandidate(['validating_carrier' => 'QR', 'carrier_chain' => 'QR']),
            $this->qrStyleCandidate(['validating_carrier' => 'EK', 'carrier_chain' => 'EK']),
        ]);

        $this->assertCount(2, $result['candidates']);
    }

    #[Test]
    public function test_candidates_sorted_by_fare_ascending_then_classification(): void
    {
        $sorter = app(SabreGdsLiveScenarioMulticityCandidateDedupSorter::class);

        $result = $sorter->deduplicateAndSort([
            $this->qrStyleCandidate([
                'total_fare' => 1200.0,
                'classification' => SabreGdsLiveScenarioMulticityClassifier::CATEGORY_DISCONTINUOUS,
            ]),
            $this->qrStyleCandidate([
                'total_fare' => 900.0,
                'classification' => SabreGdsLiveScenarioMulticityClassifier::CATEGORY_SAME_CARRIER,
            ]),
            $this->qrStyleCandidate([
                'total_fare' => 900.0,
                'classification' => SabreGdsLiveScenarioMulticityClassifier::CATEGORY_INTERLINE,
                'brand_code' => 'BUSINESS',
            ]),
        ]);

        $this->assertSame(900.0, $result['candidates'][0]['total_fare']);
        $this->assertSame(SabreGdsLiveScenarioMulticityClassifier::CATEGORY_SAME_CARRIER, $result['candidates'][0]['classification']);
        $this->assertSame(900.0, $result['candidates'][1]['total_fare']);
        $this->assertSame(SabreGdsLiveScenarioMulticityClassifier::CATEGORY_INTERLINE, $result['candidates'][1]['classification']);
        $this->assertSame(1200.0, $result['candidates'][2]['total_fare']);
    }

    #[Test]
    public function test_mixed_carrier_filter_applies_before_dedup_in_shop_service(): void
    {
        Config::set('suppliers.sabre.hide_mixed_carrier_search_results', true);
        $filter = app(SabreMixedCarrierSearchResultsFilter::class);
        $sorter = app(SabreGdsLiveScenarioMulticityCandidateDedupSorter::class);

        $same = $this->qrStyleCandidate();
        $mixed = $this->qrStyleCandidate([
            'classification' => SabreGdsLiveScenarioMulticityClassifier::CATEGORY_MIXED_CARRIER,
            'mixed_carrier' => true,
            'carrier_chain' => 'QR+EK',
            'segment_marketing_carriers' => ['QR', 'EK'],
        ]);

        $filtered = $filter->filterMulticityPlanCandidates([$same, $mixed, $same]);
        $deduped = $sorter->deduplicateAndSort($filtered['candidates']);

        $this->assertSame(1, $filtered['diagnostics']['mixed_carrier_offers_filtered_count']);
        $this->assertCount(1, $deduped['candidates']);
        $this->assertSame(2, $deduped['diagnostics']['multicity_candidates_before_dedup']);
        $this->assertSame(1, $deduped['diagnostics']['multicity_candidates_after_dedup']);
        $this->assertSame(1, $deduped['diagnostics']['multicity_duplicate_candidates_removed_count']);
    }

    #[Test]
    public function test_internal_bypass_skips_mixed_filter_but_still_dedups(): void
    {
        Config::set('suppliers.sabre.hide_mixed_carrier_search_results', true);
        $filter = app(SabreMixedCarrierSearchResultsFilter::class);
        $sorter = app(SabreGdsLiveScenarioMulticityCandidateDedupSorter::class);

        $mixed = $this->qrStyleCandidate([
            'classification' => SabreGdsLiveScenarioMulticityClassifier::CATEGORY_MIXED_CARRIER,
            'mixed_carrier' => true,
            'carrier_chain' => 'QR+EK',
            'segment_marketing_carriers' => ['QR', 'EK'],
        ]);

        $filtered = $filter->filterMulticityPlanCandidates(
            [$mixed, $mixed],
            ['include_mixed_carrier_results' => true],
        );
        $deduped = $sorter->deduplicateAndSort($filtered['candidates']);

        $this->assertSame(0, $filtered['diagnostics']['mixed_carrier_offers_filtered_count']);
        $this->assertCount(1, $deduped['candidates']);
        $this->assertSame(1, $deduped['diagnostics']['multicity_duplicate_candidates_removed_count']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function qrStyleCandidate(array $overrides = []): array
    {
        return array_merge([
            'trip_type' => 'multicity',
            'classification' => SabreGdsLiveScenarioMulticityClassifier::CATEGORY_SAME_CARRIER,
            'route_by_slice' => ['LHE-DOH', 'DOH-LHE'],
            'full_route_display' => 'LHE-DOH-LHE',
            'carrier_chain' => 'QR',
            'validating_carrier' => 'QR',
            'brand_code' => 'ECONVENIEN',
            'total_fare' => 1043.1,
            'currency' => 'USD',
            'fare_basis_codes_by_segment_count' => 2,
            'booking_classes_by_segment_count' => 2,
            'cabin_by_segment_count' => 2,
            'segment_marketing_carriers' => ['QR', 'QR'],
            'source_offer_id' => 'safe-offer-ref-1',
            'internal_offer_key' => 'safe-offer-ref-1',
            'supplier_offer_key_present' => true,
            'same_carrier' => true,
            'mixed_carrier' => false,
            'automatic_booking_allowed' => false,
            'pnr_attempted' => false,
        ], $overrides);
    }
}
