<?php

namespace Tests\Feature\FlightSearch;

use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use App\Support\FlightSearch\FlightOfferFallbackDetailsPresenter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class Wave8SelectedBrandPassengerPricingTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function sabreOfferWithBrands(): array
    {
        return [
            'offer_id' => 'sabre-offer-1',
            'supplier_provider' => 'sabre',
            'currency' => 'PKR',
            'pricing_currency' => 'PKR',
            'conversion_status' => 'converted',
            'supplier_currency' => 'USD',
            'displayed_price' => 142814,
            'final_customer_price' => 142814.0,
            'supplier_total' => 142814.0,
            'pricing_components' => [
                'base_fare' => 109358.0,
                'taxes' => 33456.0,
                'supplier_total' => 142814.0,
                'pricing_currency' => 'PKR',
                'conversion_status' => 'converted',
                'fx_rate' => 280.0,
                'final_total' => 142814.0,
                'service_fee' => 0.0,
                'admin_markup' => 0.0,
            ],
            'fare_breakdown' => [
                'currency' => 'PKR',
                'base_fare' => 109358.0,
                'taxes' => 33456.0,
                'supplier_total' => 142814.0,
                'passenger_pricing' => [
                    [
                        'passenger_type' => 'adult',
                        'passenger_count' => 1,
                        'base_amount' => 390.56,
                        'tax_amount' => 119.49,
                        'total_amount' => 510.05,
                        'currency' => 'USD',
                    ],
                ],
                'passenger_pricing_available' => true,
            ],
            'branded_fares' => [
                [
                    'name' => 'ECOLIGHT',
                    'option_key' => 'ecolight',
                    'brand_code' => 'EL',
                    'price_total' => 510.05,
                    'currency' => 'USD',
                    'pricing_information_index' => 0,
                    'selectable' => true,
                    'selection_key_authoritative' => true,
                    'can_select' => true,
                    'passenger_pricing' => [
                        [
                            'passenger_type' => 'adult',
                            'passenger_count' => 1,
                            'base_amount' => 390.56,
                            'tax_amount' => 119.49,
                            'total_amount' => 510.05,
                            'currency' => 'USD',
                        ],
                    ],
                    'passenger_pricing_available' => true,
                ],
                [
                    'name' => 'SMART',
                    'option_key' => 'smart',
                    'brand_code' => 'SM',
                    'price_total' => 522.59,
                    'currency' => 'USD',
                    'pricing_information_index' => 1,
                    'selectable' => true,
                    'selection_key_authoritative' => true,
                    'can_select' => true,
                    'passenger_pricing' => [
                        [
                            'passenger_type' => 'adult',
                            'passenger_count' => 1,
                            'base_amount' => 402.0,
                            'tax_amount' => 120.59,
                            'total_amount' => 522.59,
                            'currency' => 'USD',
                        ],
                    ],
                    'passenger_pricing_available' => true,
                ],
                [
                    'name' => 'FREEDOM',
                    'option_key' => 'freedom',
                    'brand_code' => 'FR',
                    'price_total' => 560.0,
                    'currency' => 'USD',
                    'pricing_information_index' => 2,
                    'selectable' => true,
                    'selection_key_authoritative' => true,
                    'can_select' => true,
                    'passenger_pricing' => [
                        [
                            'passenger_type' => 'adult',
                            'passenger_count' => 1,
                            'base_amount' => 430.0,
                            'tax_amount' => 130.0,
                            'total_amount' => 560.0,
                            'currency' => 'USD',
                        ],
                    ],
                    'passenger_pricing_available' => true,
                ],
            ],
            'fare_family_options' => [],
            'raw_payload' => [
                'sabre_shop_context' => ['pricing_information_index' => 0],
            ],
        ];
    }

    #[Test]
    public function test_selecting_smart_replaces_ecolight_passenger_rows(): void
    {
        $offer = $this->sabreOfferWithBrands();
        // Seed display options so findFareFamilyOptionByKey resolves keys.
        $offer['fare_family_options'] = $offer['branded_fares'];
        $offer['branded_fares_display_options'] = $offer['branded_fares'];
        $offer['fare_family_options_display'] = $offer['branded_fares'];

        $eco = FlightOfferDisplayPresenter::applySelectedFareFamilyOptionToOffer($offer, 'ecolight');
        $this->assertNull($eco['error_code']);
        $ecoFallback = FlightOfferFallbackDetailsPresenter::buildForOffer($eco['offer'], []);
        $ecoRows = $ecoFallback['fallback_details']['fare_breakdown']['passenger_pricing'] ?? null;
        $this->assertIsArray($ecoRows);
        $this->assertCount(1, $ecoRows);
        $ecoAdultTotal = (float) $ecoRows[0]['total_amount'];
        $this->assertEqualsWithDelta(142814.0, $ecoAdultTotal, 2.0);

        $smart = FlightOfferDisplayPresenter::applySelectedFareFamilyOptionToOffer($offer, 'smart');
        $this->assertNull($smart['error_code']);
        $smartOffer = $smart['offer'];
        $this->assertEqualsWithDelta(146325.0, (float) ($smartOffer['pricing_components']['supplier_total'] ?? 0), 2.0);

        $smartFallback = FlightOfferFallbackDetailsPresenter::buildForOffer($smartOffer, []);
        $smartRows = $smartFallback['fallback_details']['fare_breakdown']['passenger_pricing'] ?? null;
        $this->assertIsArray($smartRows, 'SMART must carry its own Adult PTC row');
        $this->assertCount(1, $smartRows);
        $smartAdultTotal = (float) $smartRows[0]['total_amount'];
        $this->assertEqualsWithDelta(146325.0, $smartAdultTotal, 2.0);
        $this->assertNotEquals($ecoAdultTotal, $smartAdultTotal);

        $base = (float) ($smartRows[0]['base_amount'] ?? 0);
        $tax = (float) ($smartRows[0]['tax_amount'] ?? 0);
        $this->assertGreaterThan(0, $base);
        $this->assertGreaterThan(0, $tax);
        $this->assertEqualsWithDelta($base + $tax, $smartAdultTotal, 2.0);
    }

    #[Test]
    public function test_brand_cycle_ecolight_smart_freedom_ecolight_has_no_stale_rows(): void
    {
        $offer = $this->sabreOfferWithBrands();
        $offer['fare_family_options'] = $offer['branded_fares'];
        $offer['branded_fares_display_options'] = $offer['branded_fares'];
        $offer['fare_family_options_display'] = $offer['branded_fares'];

        $expected = [
            'ecolight' => 142814.0,
            'smart' => 146325.0,
            'freedom' => 156800.0,
        ];

        foreach (['ecolight', 'smart', 'freedom', 'ecolight'] as $key) {
            $applied = FlightOfferDisplayPresenter::applySelectedFareFamilyOptionToOffer($offer, $key);
            $this->assertNull($applied['error_code'], $key);
            $fallback = FlightOfferFallbackDetailsPresenter::buildForOffer($applied['offer'], []);
            $rows = $fallback['fallback_details']['fare_breakdown']['passenger_pricing'] ?? null;
            $this->assertIsArray($rows, $key.' must expose passenger_pricing');
            $sum = array_sum(array_map(static fn (array $r): float => (float) ($r['total_amount'] ?? 0), $rows));
            $this->assertEqualsWithDelta($expected[$key], $sum, 2.0, $key.' PTC sum must match brand total');
        }
    }

    #[Test]
    public function test_multipax_brand_selection_preserves_ptc_quantities(): void
    {
        $offer = $this->sabreOfferWithBrands();
        $offer['branded_fares'][1]['passenger_pricing'] = [
            [
                'passenger_type' => 'adult',
                'passenger_count' => 2,
                'base_amount' => 400.0,
                'tax_amount' => 100.0,
                'total_amount' => 500.0,
                'currency' => 'USD',
            ],
            [
                'passenger_type' => 'child',
                'passenger_count' => 3,
                'base_amount' => 300.0,
                'tax_amount' => 60.0,
                'total_amount' => 360.0,
                'currency' => 'USD',
            ],
            [
                'passenger_type' => 'infant',
                'passenger_count' => 1,
                'base_amount' => 40.0,
                'tax_amount' => 10.0,
                'total_amount' => 50.0,
                'currency' => 'USD',
            ],
        ];
        $offer['branded_fares'][1]['price_total'] = 910.0; // 500+360+50
        $offer['fare_family_options'] = $offer['branded_fares'];
        $offer['branded_fares_display_options'] = $offer['branded_fares'];
        $offer['fare_family_options_display'] = $offer['branded_fares'];

        $applied = FlightOfferDisplayPresenter::applySelectedFareFamilyOptionToOffer($offer, 'smart');
        $this->assertNull($applied['error_code']);
        $fallback = FlightOfferFallbackDetailsPresenter::buildForOffer($applied['offer'], []);
        $rows = $fallback['fallback_details']['fare_breakdown']['passenger_pricing'] ?? null;
        $this->assertIsArray($rows);
        $this->assertCount(3, $rows);

        $byType = [];
        foreach ($rows as $row) {
            $byType[strtoupper((string) $row['passenger_type'])] = $row;
        }
        $this->assertSame(2, (int) $byType['ADULT']['passenger_count']);
        $this->assertSame(3, (int) $byType['CHILD']['passenger_count']);
        $this->assertSame(1, (int) $byType['INFANT']['passenger_count']);

        $sum = (float) $byType['ADULT']['total_amount']
            + (float) $byType['CHILD']['total_amount']
            + (float) $byType['INFANT']['total_amount'];
        $this->assertEqualsWithDelta(254800.0, $sum, 2.0); // 910 * 280
    }
}
