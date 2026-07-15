<?php

namespace Tests\Unit\Support\Pricing;

use App\Support\Pricing\PublicCustomerPricing;
use Tests\TestCase;

class PublicCustomerPricingTest extends TestCase
{
    public function test_no_markup_configured_totals_supplier_only(): void
    {
        $components = [
            'base_fare' => 59286.0,
            'taxes' => 99895.0,
            'supplier_total' => 159181.0,
            'admin_markup' => 0.0,
            'route_markup' => 0.0,
            'airline_markup' => 0.0,
            'agent_markup_or_commission' => 0.0,
            'service_fee' => 0.0,
            'final_total' => 159181.0,
            'applied_rules' => [],
        ];

        $sanitized = PublicCustomerPricing::sanitizeComponents($components, [
            'search_id' => 'search-1',
            'offer_id' => 'offer-1',
            'source_channel' => 'public_guest',
        ]);

        $this->assertSame(159181.0, $sanitized['final_total']);
        $this->assertSame(0.0, $sanitized['public_pricing_rejected_markup']);
        $this->assertTrue($sanitized['public_pricing_sanitized']);
    }

    public function test_seeded_route_markup_rejected_from_public_total(): void
    {
        $components = [
            'base_fare' => 59286.0,
            'taxes' => 99895.0,
            'supplier_total' => 159181.0,
            'admin_markup' => 0.0,
            'route_markup' => 1200.0,
            'airline_markup' => 0.0,
            'agent_markup_or_commission' => 0.0,
            'service_fee' => 0.0,
            'final_total' => 160381.0,
            'applied_rules' => [
                [
                    'id' => 2,
                    'name' => 'LHE-DXB fixed markup',
                    'rule_type' => 'route',
                    'bucket' => 'route_markup',
                    'amount' => 1200.0,
                ],
            ],
        ];

        $sanitized = PublicCustomerPricing::sanitizeComponents($components, [
            'search_id' => 'search-rt',
            'offer_id' => 'combo-1',
            'source_channel' => 'public_guest',
        ]);

        $this->assertSame(159181.0, $sanitized['final_total']);
        $this->assertSame(1200.0, $sanitized['public_pricing_rejected_markup']);
        $this->assertSame(0.0, $sanitized['route_markup']);
        $this->assertSame([], $sanitized['applied_rules']);
        $this->assertCount(1, $sanitized['public_pricing_rejected_rules']);
    }

    public function test_admin_dashboard_markup_included_in_public_total(): void
    {
        $components = [
            'base_fare' => 59286.0,
            'taxes' => 99895.0,
            'supplier_total' => 159181.0,
            'admin_markup' => 1200.0,
            'route_markup' => 0.0,
            'airline_markup' => 0.0,
            'agent_markup_or_commission' => 0.0,
            'service_fee' => 0.0,
            'final_total' => 160381.0,
            'applied_rules' => [
                [
                    'id' => 1,
                    'name' => 'Global markup',
                    'rule_type' => 'global',
                    'bucket' => 'admin_markup',
                    'amount' => 1200.0,
                ],
            ],
        ];

        $sanitized = PublicCustomerPricing::sanitizeComponents($components, [
            'source_channel' => 'public_guest',
        ]);

        $this->assertSame(160381.0, $sanitized['final_total']);
        $this->assertSame(1200.0, $sanitized['admin_markup']);
        $this->assertCount(1, $sanitized['applied_rules']);
        $this->assertSame('admin_markup', $sanitized['applied_rules'][0]['bucket']);
    }

    public function test_agent_portal_channel_skips_sanitization(): void
    {
        $components = [
            'base_fare' => 100000.0,
            'taxes' => 10000.0,
            'supplier_total' => 110000.0,
            'admin_markup' => 5500.0,
            'route_markup' => 1200.0,
            'airline_markup' => 0.0,
            'agent_markup_or_commission' => 0.0,
            'service_fee' => 800.0,
            'final_total' => 118500.0,
            'applied_rules' => [],
        ];

        $result = PublicCustomerPricing::sanitizeIfPublicChannel($components, 'agent_portal');

        $this->assertSame(118500.0, $result['final_total']);
        $this->assertSame(1200.0, $result['route_markup']);
        $this->assertArrayNotHasKey('public_pricing_sanitized', $result);
    }

    public function test_is_public_channel_recognizes_public_paths(): void
    {
        $this->assertTrue(PublicCustomerPricing::isPublicChannel('public_guest'));
        $this->assertTrue(PublicCustomerPricing::isPublicChannel('public_search'));
        $this->assertTrue(PublicCustomerPricing::isPublicChannel('public_web'));
        $this->assertFalse(PublicCustomerPricing::isPublicChannel('agent_portal'));
    }
}
