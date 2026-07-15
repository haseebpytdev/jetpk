<?php

namespace Tests\Unit\Support\Platform;

use App\Support\Platform\PlatformModuleRegistry;
use Tests\TestCase;

class PlatformModuleRegistryTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function expectedKeys(): array
    {
        return [
            'public_site',
            'public_flight_search',
            'customer_portal',
            'customer_registration',
            'customer_booking_lookup',
            'customer_checkout',
            'agent_portal',
            'agent_staff',
            'agent_applications',
            'agent_wallet',
            'agent_deposits',
            'agent_ledger',
            'agent_reports',
            'agent_support',
            'saved_travelers',
            'staff_portal',
            'admin_portal',
            'supplier_search',
            'supplier_booking',
            'sabre_gds',
            'sabre_ndc',
            'duffel_supplier',
            'ticketing',
            'payment_proofs',
            'notifications',
            'finance_reports',
            'support_system',
            'api_settings',
            'branding_settings',
            'markup_settings',
            'platform_module_control',
            'developer_control_panel',
        ];
    }

    public function test_registry_contains_all_expected_module_keys(): void
    {
        $keys = array_map(fn ($m) => $m->key, PlatformModuleRegistry::all());

        $this->assertEqualsCanonicalizing($this->expectedKeys(), $keys);
        $this->assertCount(32, $keys);

        foreach ($this->expectedKeys() as $expected) {
            $this->assertNotNull(PlatformModuleRegistry::find($expected), "Missing module: {$expected}");
        }
    }

    public function test_dependencies_for_agent_deposits_chain(): void
    {
        $deps = PlatformModuleRegistry::dependenciesFor('agent_deposits');

        $this->assertSame(['agent_wallet'], $deps['requiresAll']);
        $this->assertSame([], $deps['requiresAny']);
    }

    public function test_dependents_of_agent_portal_includes_wallet_and_staff(): void
    {
        $dependents = PlatformModuleRegistry::dependentsOf('agent_portal');

        $this->assertContains('agent_staff', $dependents);
        $this->assertContains('agent_wallet', $dependents);
        $this->assertContains('agent_reports', $dependents);
    }

    public function test_validate_dependencies_passes_when_all_enabled(): void
    {
        $states = [];
        foreach (PlatformModuleRegistry::all() as $module) {
            $states[$module->key] = true;
        }

        $result = PlatformModuleRegistry::validateDependencies($states);

        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->violations());
    }

    public function test_validate_dependencies_fails_when_and_dependency_missing(): void
    {
        $states = [];
        foreach (PlatformModuleRegistry::all() as $module) {
            $states[$module->key] = true;
        }

        $states['public_site'] = false;

        $result = PlatformModuleRegistry::validateDependencies($states);

        $this->assertFalse($result->isValid());
        $codes = array_column($result->violations(), 'code');
        $this->assertContains('missing_required', $codes);
    }

    public function test_validate_dependencies_fails_when_or_dependency_missing(): void
    {
        $states = [];
        foreach (PlatformModuleRegistry::all() as $module) {
            $states[$module->key] = true;
        }

        $states['public_flight_search'] = false;
        $states['customer_portal'] = false;

        $result = PlatformModuleRegistry::validateDependencies($states);

        $this->assertFalse($result->isValid());
        $codes = array_column($result->violations(), 'code');
        $this->assertContains('missing_any_of', $codes);
    }

    public function test_protected_modules_cannot_be_marked_disabled(): void
    {
        $states = [];
        foreach (PlatformModuleRegistry::all() as $module) {
            $states[$module->key] = true;
        }

        $states['admin_portal'] = false;
        $states['platform_module_control'] = false;

        $result = PlatformModuleRegistry::validateDependencies($states);

        $this->assertFalse($result->isValid());

        $protectedViolations = array_filter(
            $result->violations(),
            fn (array $v): bool => $v['code'] === 'protected_module'
        );

        $modules = array_column($protectedViolations, 'module');
        $this->assertContains('admin_portal', $modules);
        $this->assertContains('platform_module_control', $modules);

        $admin = PlatformModuleRegistry::find('admin_portal');
        $control = PlatformModuleRegistry::find('platform_module_control');

        $this->assertNotNull($admin);
        $this->assertNotNull($control);
        $this->assertTrue($admin->protected);
        $this->assertTrue($control->protected);
    }

    public function test_recommended_product_modes_are_non_empty(): void
    {
        $modes = PlatformModuleRegistry::recommendedProductModes();

        $this->assertArrayHasKey('b2b_only', $modes);
        $this->assertArrayHasKey('b2c_only', $modes);
        $this->assertArrayHasKey('b2b_b2c', $modes);
    }
}
