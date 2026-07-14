<?php

namespace Tests\Unit\Support\Platform;

use App\Exceptions\PlatformModuleDisabledException;
use App\Models\PlatformModuleSetting;
use App\Services\Platform\PlatformModuleSettingsService;
use App\Support\Platform\PlatformModuleEnforcer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PlatformModuleEnforcerTest extends TestCase
{
    use RefreshDatabase;

    private PlatformModuleEnforcer $enforcer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enforcer = app(PlatformModuleEnforcer::class);
    }

    public function test_effective_module_enabled_true_for_default_enabled_module_with_no_db_row(): void
    {
        $this->assertTrue($this->enforcer->effectiveModuleEnabled('agent_portal'));
    }

    public function test_effective_module_enabled_false_for_db_planned_off_non_protected_module(): void
    {
        $this->planModuleOff('agent_portal');

        $this->assertFalse($this->enforcer->effectiveModuleEnabled('agent_portal'));
    }

    public function test_effective_module_enabled_true_for_protected_module_even_if_db_row_says_false(): void
    {
        $this->planModuleOff('admin_portal');

        $this->assertTrue($this->enforcer->effectiveModuleEnabled('admin_portal'));
    }

    public function test_effective_module_enabled_false_for_unknown_module_key(): void
    {
        $this->assertFalse($this->enforcer->effectiveModuleEnabled('not_a_real_module'));
    }

    public function test_ensure_module_enabled_throws_when_planned_off(): void
    {
        $this->planModuleOff('support_system');

        $this->expectException(PlatformModuleDisabledException::class);

        $this->enforcer->ensureModuleEnabled('support_system', 'test_action');
    }

    public function test_ensure_module_enabled_does_not_throw_when_enabled(): void
    {
        $this->enforcer->ensureModuleEnabled('support_system');

        $this->addToAssertionCount(1);
    }

    public function test_ensure_supplier_search_enabled_checks_supplier_search(): void
    {
        $this->planModuleOff('supplier_search');

        try {
            $this->enforcer->ensureSupplierSearchEnabled();
            $this->fail('Expected PlatformModuleDisabledException');
        } catch (PlatformModuleDisabledException $e) {
            $this->assertSame('supplier_search', $e->moduleKey());
        }
    }

    public function test_ensure_supplier_search_enabled_checks_provider_module_when_provider_provided(): void
    {
        $this->planModuleOff('sabre_gds');

        try {
            $this->enforcer->ensureSupplierSearchEnabled(provider: 'sabre');
            $this->fail('Expected PlatformModuleDisabledException');
        } catch (PlatformModuleDisabledException $e) {
            $this->assertSame('sabre_gds', $e->moduleKey());
        }
    }

    public function test_ensure_supplier_booking_enabled_checks_supplier_booking_and_provider_module(): void
    {
        $this->planModuleOff('duffel_supplier');

        try {
            $this->enforcer->ensureSupplierBookingEnabled(provider: 'duffel');
            $this->fail('Expected PlatformModuleDisabledException');
        } catch (PlatformModuleDisabledException $e) {
            $this->assertSame('duffel_supplier', $e->moduleKey());
        }
    }

    public function test_ensure_supplier_booking_enabled_skips_provider_when_manual_override(): void
    {
        $this->planModuleOff('sabre_gds');

        $this->enforcer->ensureSupplierBookingEnabled(provider: 'sabre', allowManualOverride: true);

        $this->addToAssertionCount(1);
    }

    public function test_ensure_ticketing_enabled_checks_ticketing_and_supplier_booking(): void
    {
        $this->planModuleOff('supplier_booking');

        try {
            $this->enforcer->ensureTicketingEnabled();
            $this->fail('Expected PlatformModuleDisabledException');
        } catch (PlatformModuleDisabledException $e) {
            $this->assertSame('supplier_booking', $e->moduleKey());
        }
    }

    public function test_ensure_payment_proofs_enabled_checks_payment_proofs(): void
    {
        $this->planModuleOff('payment_proofs');

        try {
            $this->enforcer->ensurePaymentProofsEnabled();
            $this->fail('Expected PlatformModuleDisabledException');
        } catch (PlatformModuleDisabledException $e) {
            $this->assertSame('payment_proofs', $e->moduleKey());
        }
    }

    public function test_ensure_agent_wallet_enabled_checks_agent_wallet(): void
    {
        $this->planModuleOff('agent_wallet');

        try {
            $this->enforcer->ensureAgentWalletEnabled();
            $this->fail('Expected PlatformModuleDisabledException');
        } catch (PlatformModuleDisabledException $e) {
            $this->assertSame('agent_wallet', $e->moduleKey());
        }
    }

    public function test_ensure_agent_deposits_enabled_checks_agent_deposits(): void
    {
        $this->planModuleOff('agent_deposits');

        try {
            $this->enforcer->ensureAgentDepositsEnabled();
            $this->fail('Expected PlatformModuleDisabledException');
        } catch (PlatformModuleDisabledException $e) {
            $this->assertSame('agent_deposits', $e->moduleKey());
        }
    }

    public function test_ensure_public_flight_search_enabled_checks_public_flight_search(): void
    {
        $this->planModuleOff('public_flight_search');

        try {
            $this->enforcer->ensurePublicFlightSearchEnabled();
            $this->fail('Expected PlatformModuleDisabledException');
        } catch (PlatformModuleDisabledException $e) {
            $this->assertSame('public_flight_search', $e->moduleKey());
        }
    }

    public function test_ensure_customer_checkout_enabled_checks_customer_checkout(): void
    {
        $this->planModuleOff('customer_checkout');

        try {
            $this->enforcer->ensureCustomerCheckoutEnabled();
            $this->fail('Expected PlatformModuleDisabledException');
        } catch (PlatformModuleDisabledException $e) {
            $this->assertSame('customer_checkout', $e->moduleKey());
        }
    }

    public function test_provider_module_key_maps_sabre_gds_ndc_and_duffel(): void
    {
        $this->assertNull($this->enforcer->providerModuleKey(null));
        $this->assertNull($this->enforcer->providerModuleKey(''));
        $this->assertSame('sabre_gds', $this->enforcer->providerModuleKey('sabre'));
        $this->assertSame('sabre_gds', $this->enforcer->providerModuleKey('sabre_gds'));
        $this->assertSame('sabre_gds', $this->enforcer->providerModuleKey('gds'));
        $this->assertSame('sabre_ndc', $this->enforcer->providerModuleKey('sabre_ndc'));
        $this->assertSame('sabre_ndc', $this->enforcer->providerModuleKey('ndc'));
        $this->assertSame('duffel_supplier', $this->enforcer->providerModuleKey('duffel'));
        $this->assertNull($this->enforcer->providerModuleKey('amadeus'));
    }

    public function test_provider_channel_enabled_respects_sabre_gds_vs_ndc(): void
    {
        $this->planModuleOff('sabre_ndc');

        $this->assertTrue($this->enforcer->providerChannelEnabled('sabre', 'GDS'));
        $this->assertFalse($this->enforcer->providerChannelEnabled('sabre', 'NDC'));
        $this->assertSame('sabre_gds', $this->enforcer->resolveProviderModuleKey('sabre', 'GDS'));
        $this->assertSame('sabre_ndc', $this->enforcer->resolveProviderModuleKey('sabre', 'NDC'));
    }

    public function test_distribution_channel_from_booking_meta_prefers_top_level_key(): void
    {
        $channel = $this->enforcer->distributionChannelFromBookingMeta([
            'distribution_channel' => 'NDC',
            'validated_offer_snapshot' => ['distribution_channel' => 'GDS'],
        ]);

        $this->assertSame('NDC', $channel);
    }

    public function test_distribution_channel_from_booking_meta_reads_validated_offer_snapshot(): void
    {
        $channel = $this->enforcer->distributionChannelFromBookingMeta([
            'validated_offer_snapshot' => ['distribution_channel' => 'NDC'],
        ]);

        $this->assertSame('NDC', $channel);
    }

    public function test_distribution_channel_from_booking_meta_reads_flight_offer_snapshot(): void
    {
        $channel = $this->enforcer->distributionChannelFromBookingMeta([
            'flight_offer_snapshot' => ['distribution_channel' => 'GDS'],
        ]);

        $this->assertSame('GDS', $channel);
    }

    public function test_sabre_gds_off_does_not_block_ndc_when_sabre_ndc_on(): void
    {
        $this->planModuleOff('sabre_gds');

        $this->assertTrue($this->enforcer->providerChannelEnabled('sabre', 'NDC'));
        $this->assertFalse($this->enforcer->providerChannelEnabled('sabre', 'GDS'));
    }

    public function test_both_sabre_gds_and_ndc_off_block_sabre_provider_channel_checks(): void
    {
        $this->planModuleOff('sabre_gds');
        $this->planModuleOff('sabre_ndc');

        $this->assertFalse($this->enforcer->providerChannelEnabled('sabre', 'GDS'));
        $this->assertFalse($this->enforcer->providerChannelEnabled('sabre', 'NDC'));
    }

    public function test_ensure_supplier_booking_enabled_blocks_sabre_ndc_when_only_ndc_off(): void
    {
        $this->planModuleOff('sabre_ndc');

        try {
            $this->enforcer->ensureSupplierBookingEnabled(provider: 'sabre', distributionChannel: 'NDC');
            $this->fail('Expected PlatformModuleDisabledException');
        } catch (PlatformModuleDisabledException $e) {
            $this->assertSame('sabre_ndc', $e->moduleKey());
        }

        $this->enforcer->ensureSupplierBookingEnabled(provider: 'sabre', distributionChannel: 'GDS');
        $this->addToAssertionCount(1);
    }

    public function test_route_enabled_matches_effective_module_enabled(): void
    {
        $this->planModuleOff('agent_reports');

        $this->assertFalse($this->enforcer->routeEnabled('agent_reports'));
        $this->assertSame(
            $this->enforcer->effectiveModuleEnabled('agent_reports'),
            $this->enforcer->routeEnabled('agent_reports')
        );
    }

    public function test_disabled_exception_renders_json_403_for_post(): void
    {
        Route::post('/_test/platform-module-enforcer-json', function (): void {
            app(PlatformModuleEnforcer::class)->ensureModuleEnabled('agent_portal');
        });

        $this->planModuleOff('agent_portal');

        $this->post('/_test/platform-module-enforcer-json')
            ->assertForbidden()
            ->assertJson([
                'message' => PlatformModuleDisabledException::PUBLIC_MESSAGE,
            ]);
    }

    public function test_disabled_exception_renders_json_403_for_json_request(): void
    {
        Route::get('/_test/platform-module-enforcer-json-get', function (): void {
            app(PlatformModuleEnforcer::class)->ensureModuleEnabled('agent_portal');
        });

        $this->planModuleOff('agent_portal');

        $this->getJson('/_test/platform-module-enforcer-json-get')
            ->assertForbidden()
            ->assertJson([
                'message' => PlatformModuleDisabledException::PUBLIC_MESSAGE,
            ]);
    }

    public function test_disabled_exception_renders_friendly_page_for_get_html_without_module_key_for_guest(): void
    {
        Route::get('/_test/platform-module-enforcer-html', function (): void {
            app(PlatformModuleEnforcer::class)->ensureModuleEnabled('agent_portal');
        });

        $this->planModuleOff('agent_portal');

        $this->get('/_test/platform-module-enforcer-html')
            ->assertForbidden()
            ->assertSee(PlatformModuleDisabledException::PUBLIC_MESSAGE, false)
            ->assertDontSee('agent_portal', false);
    }

    private function planModuleOff(string $key): void
    {
        PlatformModuleSetting::query()->create([
            'module_key' => $key,
            'enabled' => false,
        ]);
        app(PlatformModuleSettingsService::class)->forgetCache();
    }
}
