<?php

namespace Tests\Feature\Platform;

use App\Exceptions\PlatformModuleDisabledException;
use App\Models\DeveloperUser;
use App\Models\PlatformModuleSetting;
use App\Services\Platform\PlatformModuleSettingsService;
use App\Support\Platform\PlatformModuleEnforcer;
use App\Support\Platform\PlatformModuleRegistry;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class PlatformModulePresetTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OtaFoundationSeeder::class);
        Config::set('ota-developer.enabled', true);
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_all_recommended_presets_pass_dependency_validation(): void
    {
        foreach (PlatformModuleRegistry::recommendedProductModes() as $presetKey => $mode) {
            $validation = PlatformModuleRegistry::validateDependencies($mode['modules']);
            $this->assertTrue(
                $validation->isValid(),
                "Preset {$presetKey} failed validation: ".json_encode($validation->violations()),
            );
        }
    }

    public function test_finalize_preset_keeps_protected_modules_enabled(): void
    {
        $modes = PlatformModuleRegistry::recommendedProductModes();
        $modules = PlatformModuleRegistry::finalizePresetModules(array_merge(
            $modes['maintenance_lite']['modules'],
            [
                'admin_portal' => false,
                'platform_module_control' => false,
                'developer_control_panel' => false,
            ],
        ));

        $this->assertTrue($modules['admin_portal']);
        $this->assertTrue($modules['platform_module_control']);
        $this->assertTrue($modules['developer_control_panel']);
    }

    public function test_public_search_only_preset_disables_checkout_supplier_booking_and_ticketing(): void
    {
        $developer = $this->developerUser();

        $this->withDeveloperSession($developer)
            ->post(route('dev.cp.modules.preset'), ['preset_key' => 'public_search_only'])
            ->assertRedirect(route('dev.cp.modules.index'))
            ->assertSessionHas('status');

        $service = app(PlatformModuleSettingsService::class);
        $service->forgetCache();

        $this->assertFalse($service->stateFor('customer_checkout'));
        $this->assertFalse($service->stateFor('supplier_booking'));
        $this->assertFalse($service->stateFor('ticketing'));
        $this->assertTrue($service->stateFor('public_flight_search'));
        $this->assertTrue($service->stateFor('supplier_search'));
    }

    public function test_b2b_only_preset_disables_customer_modules_but_keeps_agent_stack(): void
    {
        $developer = $this->developerUser();

        $this->withDeveloperSession($developer)
            ->post(route('dev.cp.modules.preset'), ['preset_key' => 'b2b_only'])
            ->assertSessionHas('status');

        $service = app(PlatformModuleSettingsService::class);
        $service->forgetCache();

        $this->assertFalse($service->stateFor('customer_checkout'));
        $this->assertFalse($service->stateFor('customer_portal'));
        $this->assertTrue($service->stateFor('agent_portal'));
        $this->assertTrue($service->stateFor('agent_wallet'));
        $this->assertTrue($service->stateFor('supplier_search'));
    }

    public function test_b2c_only_preset_disables_agent_modules_but_keeps_customer_stack(): void
    {
        $developer = $this->developerUser();

        $this->withDeveloperSession($developer)
            ->post(route('dev.cp.modules.preset'), ['preset_key' => 'b2c_only'])
            ->assertSessionHas('status');

        $service = app(PlatformModuleSettingsService::class);
        $service->forgetCache();

        $this->assertFalse($service->stateFor('agent_portal'));
        $this->assertTrue($service->stateFor('customer_checkout'));
        $this->assertTrue($service->stateFor('customer_portal'));
    }

    public function test_no_supplier_booking_preset_disables_booking_and_ticketing_only(): void
    {
        $developer = $this->developerUser();

        $this->withDeveloperSession($developer)
            ->post(route('dev.cp.modules.preset'), ['preset_key' => 'no_supplier_booking'])
            ->assertSessionHas('status');

        $service = app(PlatformModuleSettingsService::class);
        $service->forgetCache();

        $this->assertFalse($service->stateFor('supplier_booking'));
        $this->assertFalse($service->stateFor('ticketing'));
        $this->assertTrue($service->stateFor('supplier_search'));
        $this->assertTrue($service->stateFor('customer_checkout'));
    }

    public function test_no_wallet_deposits_preset_cascades_agent_finance_modules_off(): void
    {
        $developer = $this->developerUser();

        $this->withDeveloperSession($developer)
            ->post(route('dev.cp.modules.preset'), ['preset_key' => 'no_wallet_deposits'])
            ->assertSessionHas('status');

        $service = app(PlatformModuleSettingsService::class);
        $service->forgetCache();

        $this->assertFalse($service->stateFor('agent_wallet'));
        $this->assertFalse($service->stateFor('agent_deposits'));
        $this->assertFalse($service->stateFor('agent_ledger'));
        $this->assertTrue($service->stateFor('agent_portal'));
    }

    public function test_search_only_preset_blocks_customer_checkout_route(): void
    {
        $developer = $this->developerUser();

        $this->withDeveloperSession($developer)
            ->post(route('dev.cp.modules.preset'), ['preset_key' => 'public_search_only'])
            ->assertSessionHas('status');

        app(PlatformModuleSettingsService::class)->forgetCache();

        $this->get(route('booking.passengers'))
            ->assertForbidden();
    }

    public function test_preset_apply_flash_includes_change_counts(): void
    {
        PlatformModuleSetting::query()->create([
            'module_key' => 'agent_portal',
            'enabled' => true,
        ]);
        app(PlatformModuleSettingsService::class)->forgetCache();

        $developer = $this->developerUser();

        $this->withDeveloperSession($developer)
            ->post(route('dev.cp.modules.preset'), ['preset_key' => 'b2c_only'])
            ->assertRedirect(route('dev.cp.modules.index'))
            ->assertSessionHas('status');

        $status = session('status');
        $this->assertIsString($status);
        $this->assertStringContainsString('planned off', $status);
        $this->assertStringContainsString('planned on', $status);
    }

    public function test_emergency_reset_restores_registry_defaults(): void
    {
        $developer = $this->developerUser();
        PlatformModuleSetting::query()->create([
            'module_key' => 'ticketing',
            'enabled' => false,
        ]);

        $this->withDeveloperSession($developer)
            ->post(route('dev.cp.modules.emergency-reset'))
            ->assertSessionHas('status');

        $this->assertSame(0, PlatformModuleSetting::query()->count());
        $this->assertTrue(app(PlatformModuleSettingsService::class)->stateFor('ticketing'));
    }

    public function test_developer_cp_remains_accessible_after_maintenance_preset(): void
    {
        $developer = $this->developerUser();

        $this->withDeveloperSession($developer)
            ->post(route('dev.cp.modules.preset'), ['preset_key' => 'maintenance_lite'])
            ->assertSessionHas('status');

        $this->withDeveloperSession($developer)
            ->get(route('dev.cp.modules.index'))
            ->assertOk()
            ->assertSee('Maintenance lite', false);
    }

    public function test_platform_admin_without_dev_session_cannot_apply_preset(): void
    {
        $this->actingAs($this->platformAdmin())
            ->post(route('dev.cp.modules.preset'), ['preset_key' => 'b2c_only'])
            ->assertRedirect(route('dev.cp.login'));
    }

    public function test_admin_platform_modules_route_remains_404(): void
    {
        $this->actingAs($this->platformAdmin())
            ->get('/admin/platform/modules')
            ->assertNotFound();
    }

    public function test_supplier_booking_off_enforcer_after_no_supplier_booking_preset(): void
    {
        $developer = $this->developerUser();

        $this->withDeveloperSession($developer)
            ->post(route('dev.cp.modules.preset'), ['preset_key' => 'no_supplier_booking']);

        app(PlatformModuleSettingsService::class)->forgetCache();

        $blocked = app(PlatformModuleEnforcer::class)->supplierBookingBlockedMessage('duffel');
        $this->assertSame(
            PlatformModuleDisabledException::PUBLIC_MESSAGE,
            $blocked,
        );
    }

    private function withDeveloperSession(DeveloperUser $developer): static
    {
        return $this->withSession(['dev_cp_user_id' => $developer->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function developerUser(array $overrides = []): DeveloperUser
    {
        return DeveloperUser::query()->create(array_merge([
            'name' => 'Dev Preset 8Q',
            'email' => 'dev-preset-8q@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ], $overrides));
    }
}
