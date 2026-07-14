<?php

namespace Tests\Unit\Support\Bookings;

use App\Support\Bookings\CheckoutFareBreakdownPresenter;
use Tests\TestCase;

class CheckoutFareBreakdownPresenterTest extends TestCase
{
    public function test_converted_currency_uses_pkr_components_not_usd_passenger_rows(): void
    {
        $offer = [
            'total' => 160404.0,
            'currency' => 'PKR',
            'conversion_status' => 'converted',
            'pricing_components' => [
                'base_fare' => 120000.0,
                'taxes' => 40404.0,
                'final_total' => 160404.0,
                'conversion_status' => 'converted',
                'pricing_currency' => 'PKR',
                'service_fee' => 0.0,
                'admin_markup' => 0.0,
                'route_markup' => 0.0,
                'airline_markup' => 0.0,
                'agent_markup_or_commission' => 0.0,
                'applied_rules' => [],
            ],
            'fare_breakdown' => [
                'passenger_pricing_available' => true,
                'passenger_pricing' => [
                    [
                        'passenger_type' => 'adult',
                        'passenger_count' => 1,
                        'base_amount' => 500.0,
                        'tax_amount' => 72.0,
                        'total_amount' => 572.0,
                        'currency' => 'USD',
                    ],
                ],
            ],
        ];

        $presented = CheckoutFareBreakdownPresenter::present($offer, null, ['adults' => 1, 'children' => 0, 'infants' => 0]);

        $this->assertSame('simplified', $presented['mode']);
        $this->assertFalse($presented['show_passenger_mix']);
        $this->assertSame(160404.0, $presented['total']);
        $this->assertSame(0.0, $presented['fee_source']['service_fee']);
        $labels = array_column($presented['rows'], 'label');
        $this->assertContains('Base fare', $labels);
        $this->assertContains('Taxes & fees', $labels);
        $this->assertNotContains('Adult × 1', $labels);
    }

    public function test_zero_fees_are_hidden_from_rows(): void
    {
        $offer = [
            'total' => 50000.0,
            'pricing_components' => [
                'base_fare' => 45000.0,
                'taxes' => 5000.0,
                'final_total' => 50000.0,
                'conversion_status' => 'same_currency',
                'pricing_currency' => 'PKR',
                'service_fee' => 0.0,
                'admin_markup' => 0.0,
                'applied_rules' => [],
            ],
            'fare_breakdown' => [],
        ];

        $presented = CheckoutFareBreakdownPresenter::present($offer);

        $types = array_column($presented['rows'], 'type');
        $this->assertNotContains('service_fee', $types);
        $this->assertNotContains('markup', $types);
    }

    public function test_service_fee_shown_when_configured_with_applied_rule(): void
    {
        $offer = [
            'total' => 51200.0,
            'pricing_components' => [
                'base_fare' => 45000.0,
                'taxes' => 5000.0,
                'final_total' => 51200.0,
                'conversion_status' => 'same_currency',
                'pricing_currency' => 'PKR',
                'service_fee' => 1200.0,
                'admin_markup' => 0.0,
                'applied_rules' => [
                    ['id' => 1, 'name' => 'Portal service fee', 'bucket' => 'service_fee', 'amount' => 1200.0],
                ],
            ],
            'fare_breakdown' => [],
        ];

        $presented = CheckoutFareBreakdownPresenter::present($offer);

        $serviceRows = array_values(array_filter($presented['rows'], fn (array $row) => ($row['type'] ?? '') === 'service_fee'));
        $this->assertCount(1, $serviceRows);
        $this->assertSame(1200.0, $serviceRows[0]['amount']);
    }

    public function test_route_markup_only_does_not_show_agency_charges_row(): void
    {
        $offer = [
            'total' => 51200.0,
            'markup' => 1200.0,
            'pricing_components' => [
                'base_fare' => 45000.0,
                'taxes' => 5000.0,
                'final_total' => 51200.0,
                'conversion_status' => 'same_currency',
                'pricing_currency' => 'PKR',
                'admin_markup' => 0.0,
                'route_markup' => 1200.0,
                'service_fee' => 0.0,
                'applied_rules' => [
                    ['id' => 2, 'name' => 'LHE-DXB fixed markup', 'bucket' => 'route_markup', 'amount' => 1200.0],
                ],
            ],
            'fare_breakdown' => [],
        ];

        $presented = CheckoutFareBreakdownPresenter::present($offer);

        $labels = array_column($presented['rows'], 'label');
        $this->assertNotContains('Agency charges', $labels);
        $this->assertSame(50000.0, $presented['total']);
        $this->assertSame(0.0, $presented['reconciliation_delta']);
        $this->assertFalse($presented['fee_source']['markup_display_eligible']);
    }

    public function test_admin_markup_shown_with_applied_rule(): void
    {
        $offer = [
            'total' => 52500.0,
            'pricing_components' => [
                'base_fare' => 45000.0,
                'taxes' => 5000.0,
                'final_total' => 52500.0,
                'conversion_status' => 'same_currency',
                'pricing_currency' => 'PKR',
                'admin_markup' => 2500.0,
                'service_fee' => 0.0,
                'applied_rules' => [
                    ['id' => 3, 'name' => 'Global markup 5%', 'bucket' => 'admin_markup', 'amount' => 2500.0],
                ],
            ],
            'fare_breakdown' => [],
        ];

        $presented = CheckoutFareBreakdownPresenter::present($offer);

        $markupRows = array_values(array_filter($presented['rows'], fn (array $row) => ($row['type'] ?? '') === 'markup'));
        $this->assertCount(1, $markupRows);
        $this->assertSame(2500.0, $markupRows[0]['amount']);
    }

    public function test_legacy_offer_markup_without_pricing_components_is_hidden(): void
    {
        $offer = [
            'total' => 51200.0,
            'markup' => 1200.0,
            'service_fee' => 0.0,
            'base_fare' => 45000.0,
            'taxes' => 5000.0,
            'fare_breakdown' => [],
        ];

        $presented = CheckoutFareBreakdownPresenter::present($offer);

        $labels = array_column($presented['rows'], 'label');
        $this->assertNotContains('Agency charges', $labels);
    }

    public function test_trusted_pkr_passenger_rows_used_when_reconciled(): void
    {
        $offer = [
            'total' => 57200.0,
            'pricing_components' => [
                'final_total' => 57200.0,
                'conversion_status' => 'same_currency',
                'pricing_currency' => 'PKR',
                'service_fee' => 0.0,
                'admin_markup' => 0.0,
                'applied_rules' => [],
            ],
            'fare_breakdown' => [
                'passenger_pricing_available' => true,
                'passenger_pricing' => [
                    [
                        'passenger_type' => 'adult',
                        'passenger_count' => 1,
                        'total_amount' => 57200.0,
                        'currency' => 'PKR',
                    ],
                ],
            ],
        ];

        $presented = CheckoutFareBreakdownPresenter::present($offer, null, ['adults' => 1]);

        $this->assertSame('detailed', $presented['mode']);
        $this->assertTrue($presented['show_passenger_mix']);
        $labels = array_column($presented['rows'], 'label');
        $this->assertContains('Adult × 1', $labels);
    }
}
