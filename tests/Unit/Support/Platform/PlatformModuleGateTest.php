<?php

namespace Tests\Unit\Support\Platform;

use App\Models\PlatformModuleSetting;
use App\Services\Platform\PlatformModuleSettingsService;
use App\Support\Platform\PlatformModuleEnforcer;
use App\Support\Platform\PlatformModuleGate;
use App\Support\Platform\PlatformModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformModuleGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_allows_returns_true_for_all_registry_modules_by_default(): void
    {
        foreach (PlatformModuleRegistry::all() as $module) {
            $this->assertTrue(PlatformModuleGate::allows($module->key), "allows() for {$module->key}");
            $this->assertTrue(PlatformModuleGate::visible($module->key), "visible() for {$module->key} with no DB override");
        }
    }

    public function test_route_enabled_matches_visible_for_non_protected_modules_by_default(): void
    {
        foreach (PlatformModuleRegistry::all() as $module) {
            if ($module->protected) {
                continue;
            }

            $this->assertSame(
                PlatformModuleGate::visible($module->key),
                PlatformModuleGate::routeEnabled($module->key),
                "routeEnabled() vs visible() for {$module->key}"
            );
        }
    }

    public function test_route_enabled_unknown_key_is_false(): void
    {
        $this->assertFalse(PlatformModuleGate::routeEnabled('unknown_module_key'));
    }

    public function test_allows_true_and_route_enabled_false_for_planned_disabled_known_module(): void
    {
        PlatformModuleSetting::query()->create([
            'module_key' => 'agent_portal',
            'enabled' => false,
        ]);
        app(PlatformModuleSettingsService::class)->forgetCache();

        $this->assertTrue(PlatformModuleGate::allows('agent_portal'));
        $this->assertFalse(PlatformModuleGate::routeEnabled('agent_portal'));
        $this->assertFalse(app(PlatformModuleEnforcer::class)->routeEnabled('agent_portal'));
    }

    public function test_effective_status_shows_enforced_when_routes_blocked(): void
    {
        PlatformModuleSetting::query()->create([
            'module_key' => 'support_system',
            'enabled' => false,
        ]);
        app(PlatformModuleSettingsService::class)->forgetCache();

        $status = PlatformModuleGate::effectiveStatus('support_system');

        $this->assertTrue($status['gate_allows']);
        $this->assertFalse($status['visible']);
        $this->assertTrue($status['nav_hidden']);
        $this->assertTrue($status['enforced']);
        $this->assertStringContainsString('guarded routes blocked', $status['display']);
    }

    public function test_effective_status_protected_module_not_enforced_when_db_plans_off(): void
    {
        $status = PlatformModuleGate::effectiveStatus('admin_portal');

        $this->assertTrue($status['gate_allows']);
        $this->assertTrue($status['visible']);
        $this->assertFalse($status['nav_hidden']);
        $this->assertFalse($status['enforced']);
        $this->assertTrue($status['planned_enabled']);
    }

    public function test_allows_stays_true_but_visible_false_when_db_plans_module_off(): void
    {
        PlatformModuleSetting::query()->create([
            'module_key' => 'agent_portal',
            'enabled' => false,
        ]);
        app(PlatformModuleSettingsService::class)->forgetCache();

        $this->assertTrue(PlatformModuleGate::allows('agent_portal'));
        $this->assertFalse(PlatformModuleGate::visible('agent_portal'));

        $status = PlatformModuleGate::effectiveStatus('agent_portal');
        $this->assertTrue($status['gate_allows']);
        $this->assertFalse($status['visible']);
        $this->assertTrue($status['nav_hidden']);
        $this->assertTrue($status['enforced']);
        $this->assertFalse($status['planned_enabled']);
        $this->assertStringContainsString('nav hidden', $status['display']);
        $this->assertStringContainsString('guarded routes blocked', $status['display']);
    }

    public function test_protected_modules_stay_visible_when_db_plans_off(): void
    {
        foreach (['admin_portal', 'platform_module_control', 'developer_control_panel'] as $key) {
            PlatformModuleSetting::query()->create([
                'module_key' => $key,
                'enabled' => false,
            ]);
        }
        app(PlatformModuleSettingsService::class)->forgetCache();

        foreach (['admin_portal', 'platform_module_control', 'developer_control_panel'] as $key) {
            $this->assertTrue(PlatformModuleGate::visible($key), "visible() for protected {$key}");
            $this->assertTrue(PlatformModuleGate::allows($key), "allows() for protected {$key}");
        }
    }

    public function test_sabre_env_snapshot_exposes_only_safe_boolean_labels(): void
    {
        $status = PlatformModuleGate::effectiveStatus('sabre_gds');

        $labels = array_column($status['env_snapshot'], 'label');
        $this->assertContains('SABRE_BOOKING_ENABLED', $labels);
        $this->assertContains('SABRE_BOOKING_LIVE_CALL_ENABLED', $labels);
        $this->assertContains('SABRE_TICKETING_ENABLED', $labels);

        $serialized = json_encode($status['env_snapshot']);
        $this->assertIsString($serialized);
        $lower = strtolower($serialized);
        $this->assertStringNotContainsString('password', $lower);
        $this->assertStringNotContainsString('api_key', $lower);
        $this->assertStringNotContainsString('client_secret', $lower);
        $this->assertStringNotContainsString('credentials', $lower);
    }
}
