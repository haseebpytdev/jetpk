<?php

namespace Tests\Feature\Developer;

use App\Models\DeveloperUser;
use App\Models\PlatformModuleSetting;
use App\Models\PlatformModuleSettingChange;
use App\Services\Platform\PlatformModuleSettingsService;
use App\Support\Platform\PlatformModuleGate;
use App\Support\Platform\PlatformModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class PlatformModuleSettingsPersistenceTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('ota-developer.enabled', true);
    }

    public function test_empty_settings_table_uses_registry_defaults(): void
    {
        $service = app(PlatformModuleSettingsService::class);

        $this->assertTrue($service->stateFor('agent_portal'));
        $this->assertSame([], $service->overrides());
    }

    public function test_db_row_false_makes_state_for_false(): void
    {
        PlatformModuleSetting::query()->create([
            'module_key' => 'agent_portal',
            'enabled' => false,
        ]);

        $this->assertFalse(app(PlatformModuleSettingsService::class)->stateFor('agent_portal'));
    }

    public function test_deleting_override_reverts_to_registry_default(): void
    {
        $row = PlatformModuleSetting::query()->create([
            'module_key' => 'customer_portal',
            'enabled' => false,
        ]);

        $row->delete();
        Cache::forget(PlatformModuleSettingsService::CACHE_KEY);

        $this->assertTrue(app(PlatformModuleSettingsService::class)->stateFor('customer_portal'));
    }

    public function test_protected_module_cannot_be_disabled(): void
    {
        $developer = $this->developerUser();
        $modules = $this->allModuleStates(true);
        $modules['admin_portal'] = false;

        $this->withDeveloperSession($developer)
            ->post(route('dev.cp.modules.update'), ['modules' => $modules])
            ->assertRedirect(route('dev.cp.modules.index'))
            ->assertSessionHasErrors('modules');

        $this->assertDatabaseMissing('platform_module_settings', ['module_key' => 'admin_portal']);
    }

    public function test_invalid_dependency_is_rejected_without_db_write(): void
    {
        $developer = $this->developerUser();
        $modules = $this->allModuleStates(true);
        $modules['supplier_search'] = false;
        $modules['supplier_booking'] = true;

        $this->withDeveloperSession($developer)
            ->post(route('dev.cp.modules.update'), ['modules' => $modules])
            ->assertRedirect(route('dev.cp.modules.index'))
            ->assertSessionHasErrors('modules');

        $this->assertDatabaseMissing('platform_module_settings', ['module_key' => 'supplier_search']);
        $this->assertDatabaseMissing('platform_module_settings', ['module_key' => 'supplier_booking']);
        $this->assertSame(0, PlatformModuleSettingChange::query()->count());
    }

    public function test_valid_change_writes_settings_and_change_log(): void
    {
        $developer = $this->developerUser();
        $modules = $this->allModuleStates(true);
        $modules['public_flight_search'] = false;

        $this->withDeveloperSession($developer)
            ->post(route('dev.cp.modules.update'), [
                'modules' => $modules,
            ])
            ->assertRedirect(route('dev.cp.modules.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('platform_module_settings', [
            'module_key' => 'public_flight_search',
            'enabled' => false,
            'updated_by_developer_user_id' => $developer->id,
        ]);

        $change = PlatformModuleSettingChange::query()
            ->where('module_key', 'public_flight_search')
            ->first();

        $this->assertNotNull($change);
        $this->assertSame($developer->id, $change->developer_user_id);
        $this->assertTrue($change->old_enabled);
        $this->assertFalse($change->new_enabled);
        $this->assertSame('manual', $change->source);
        $this->assertNotNull($change->ip_address);
        $this->assertNotNull($change->user_agent);
    }

    public function test_apply_preset_writes_expected_changes_with_source_preset(): void
    {
        $developer = $this->developerUser();

        $this->withDeveloperSession($developer)
            ->post(route('dev.cp.modules.preset'), ['preset_key' => 'b2c_only'])
            ->assertRedirect(route('dev.cp.modules.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('platform_module_settings', [
            'module_key' => 'agent_portal',
            'enabled' => false,
        ]);

        $this->assertDatabaseMissing('platform_module_settings', [
            'module_key' => 'customer_portal',
        ]);
        $this->assertTrue(app(PlatformModuleSettingsService::class)->stateFor('customer_portal'));

        $this->assertTrue(
            PlatformModuleSettingChange::query()
                ->where('source', 'preset')
                ->where('preset_key', 'b2c_only')
                ->exists()
        );
    }

    public function test_reset_deletes_overrides_and_logs_source_reset(): void
    {
        $developer = $this->developerUser();
        PlatformModuleSetting::query()->create([
            'module_key' => 'agent_portal',
            'enabled' => false,
        ]);

        $this->withDeveloperSession($developer)
            ->post(route('dev.cp.modules.reset'))
            ->assertRedirect(route('dev.cp.modules.index'))
            ->assertSessionHas('status');

        $this->assertSame(0, PlatformModuleSetting::query()->count());
        $this->assertTrue(
            PlatformModuleSettingChange::query()
                ->where('module_key', 'agent_portal')
                ->where('source', 'reset')
                ->exists()
        );
    }

    public function test_emergency_reset_logs_source_emergency(): void
    {
        $developer = $this->developerUser();
        PlatformModuleSetting::query()->create([
            'module_key' => 'public_flight_search',
            'enabled' => false,
        ]);

        $this->withDeveloperSession($developer)
            ->post(route('dev.cp.modules.emergency-reset'))
            ->assertRedirect(route('dev.cp.modules.index'))
            ->assertSessionHas('status');

        $this->assertSame(0, PlatformModuleSetting::query()->count());
        $this->assertTrue(
            PlatformModuleSettingChange::query()
                ->where('module_key', 'public_flight_search')
                ->where('source', 'emergency')
                ->exists()
        );
    }

    public function test_cache_clears_on_write(): void
    {
        $developer = $this->developerUser();
        Cache::put(PlatformModuleSettingsService::CACHE_KEY, ['stale' => true], 3600);

        $modules = $this->allModuleStates(true);
        $modules['customer_checkout'] = false;

        $this->withDeveloperSession($developer)
            ->post(route('dev.cp.modules.update'), ['modules' => $modules])
            ->assertRedirect(route('dev.cp.modules.index'));

        $this->assertFalse(Cache::has(PlatformModuleSettingsService::CACHE_KEY));
    }

    public function test_developer_user_can_update_modules(): void
    {
        $developer = $this->developerUser();
        $modules = $this->allModuleStates(true);
        $modules['public_flight_search'] = false;

        $this->withDeveloperSession($developer)
            ->post(route('dev.cp.modules.update'), ['modules' => $modules])
            ->assertRedirect(route('dev.cp.modules.index'))
            ->assertSessionHas('status');
    }

    public function test_guest_cannot_update_modules(): void
    {
        $this->post(route('dev.cp.modules.update'), ['modules' => []])
            ->assertRedirect(route('dev.cp.login'));
    }

    public function test_platform_admin_without_dev_session_cannot_update_modules(): void
    {
        $admin = $this->platformAdmin();
        $modules = $this->allModuleStates(true);

        $this->actingAs($admin)
            ->post(route('dev.cp.modules.update'), ['modules' => $modules])
            ->assertRedirect(route('dev.cp.login'));
    }

    public function test_modules_page_shows_preview_warning_and_save_controls(): void
    {
        $developer = $this->developerUser();

        $this->withDeveloperSession($developer)
            ->get(route('dev.cp.modules.index'))
            ->assertOk()
            ->assertSee('Deployment Control Panel', false)
            ->assertSee('Deployment scope', false)
            ->assertSee('deployment module access', false)
            ->assertSee('sabre_gds', false)
            ->assertSee('Backend-enforced', false)
            ->assertSee('Save changes', false)
            ->assertSee('data-testid="dev-cp-deployment-packages"', false);
    }

    public function test_admin_platform_modules_route_remains_absent(): void
    {
        $this->assertFalse(Route::has('admin.platform.modules.index'));
    }

    public function test_gate_allows_and_visible_remain_true_for_db_disabled_module(): void
    {
        PlatformModuleSetting::query()->create([
            'module_key' => 'ticketing',
            'enabled' => false,
        ]);

        app(PlatformModuleSettingsService::class)->forgetCache();

        $this->assertTrue(PlatformModuleGate::allows('ticketing'));
        $this->assertFalse(PlatformModuleGate::visible('ticketing'));
    }

    public function test_reverting_to_registry_default_deletes_row(): void
    {
        $developer = $this->developerUser();
        PlatformModuleSetting::query()->create([
            'module_key' => 'agent_reports',
            'enabled' => false,
        ]);

        $modules = $this->allModuleStates(true);

        $this->withDeveloperSession($developer)
            ->post(route('dev.cp.modules.update'), ['modules' => $modules])
            ->assertRedirect(route('dev.cp.modules.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('platform_module_settings', ['module_key' => 'agent_reports']);
    }

    /**
     * @return array<string, bool>
     */
    private function allModuleStates(bool $enabled): array
    {
        $modules = [];
        foreach (PlatformModuleRegistry::all() as $module) {
            $modules[$module->key] = $enabled;
        }

        return $modules;
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
            'name' => 'Dev Owner',
            'email' => 'developer-persist@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ], $overrides));
    }
}
