<?php

namespace Tests\Unit\Support\Suppliers;

use App\Support\Suppliers\SabreNdcEntitlementEvidenceStore;
use App\Support\Suppliers\SabreNdcGroupedItineraryMessageExtractor;
use App\Support\Suppliers\SabreNdcOfferShopSafeErrorExtractor;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SabreNdcEntitlementEvidenceTest extends TestCase
{
    public function test_sabre_server_pattern_maps_numeric_text_to_message_code(): void
    {
        $extractor = new SabreNdcGroupedItineraryMessageExtractor;
        $result = $extractor->extract([
            'groupedItineraryResponse' => [
                'itineraryGroups' => [],
                'messages' => [[
                    'severity' => 'Info',
                    'type' => 'SERVER',
                    'code' => 'GCA14-ISELL-TN-00-2025-03-00-MXK4',
                    'text' => '27131',
                ]],
            ],
        ]);

        $this->assertSame('27131', $result['message_code']);
        $this->assertNull($result['message_text']);
        $this->assertSame('GCA14-ISELL-TN-00-2025-03-00-MXK4', $result['sabre_transaction_id']);
    }

    public function test_http_200_zero_offer_safe_fields_use_ndc_zero_offers_not_http_200(): void
    {
        $extractor = app(SabreNdcOfferShopSafeErrorExtractor::class);
        $result = $extractor->extract(200, [
            'groupedItineraryResponse' => [
                'itineraryGroups' => [],
                'messages' => [[
                    'type' => 'SERVER',
                    'code' => 'GCB14-ISELL-TN-00-2025-03-00-MXK4',
                    'text' => '27131',
                ]],
            ],
        ], null, [
            'offer_count_raw' => 0,
            'normalized_offer_count' => 0,
        ]);

        $this->assertSame('ndc_zero_offers', $result['safe_error_code']);
        $this->assertSame('sabre_ndc_zero_offers', $result['safe_error_family']);
        $this->assertNull($result['safe_error_message']);
        $this->assertSame('27131', $result['message_code']);
        $this->assertStringNotContainsString('ISELL', (string) $result['safe_error_family']);
    }

    public function test_matrix_store_records_message_code_list_with_27131(): void
    {
        Cache::flush();
        $store = new SabreNdcEntitlementEvidenceStore;
        $store->storeMatrix(5, ['cells' => 1, 'routes' => 1, 'days' => 1, 'variant' => 'ndc_v5_pos_pcc_source'], [[
            'http_status' => 200,
            'normalized_offer_count' => 0,
            'message_code' => '27131',
            'message_text' => null,
            'transaction_id' => 'GCA14-ISELL-TN-00-2025-03-00-MXK4',
        ]]);

        $summary = $store->buildEvidenceSummary(5);
        $this->assertSame(['27131'], $summary['last_matrix_message_codes']);
    }

    public function test_entitlement_gap_likely_when_ndc_only_zero_and_atpco_positive(): void
    {
        Cache::flush();
        $store = new SabreNdcEntitlementEvidenceStore;
        $store->storeVariantProbe(5, 'ndc_only', [
            'offer_count_raw' => 0,
            'normalized_offer_count' => 0,
            'message_code' => '27131',
            'http_status' => 200,
            'no_offer_reason' => 'ndc_zero_offers',
        ]);
        $store->storeVariantProbe(5, 'atpco_only_diagnostic', [
            'offer_count_raw' => 17,
            'normalized_offer_count' => 15,
            'http_status' => 200,
            'no_offer_reason' => 'sabre_ndc_search_success',
        ]);

        $summary = $store->buildEvidenceSummary(5);
        $this->assertTrue($summary['entitlement_gap_likely']);
        $this->assertSame(17, $summary['atpco_diagnostic_raw_offer_count']);
        $this->assertSame(0, $summary['ndc_only_raw_offer_count']);
    }
}
