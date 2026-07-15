<?php

namespace Tests\Unit\Services\Platform;

use App\Models\DeveloperUser;
use App\Models\PlatformModuleSetting;
use App\Services\Platform\PlatformModuleSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PlatformModuleSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_validate_changes_rejects_unknown_module_key(): void
    {
        $validation = app(PlatformModuleSettingsService::class)->validateChanges([
            'not_a_real_module' => false,
        ]);

        $this->assertFalse($validation->isValid());
        $this->assertSame('unknown_module', $validation->violations()[0]['code']);
    }

    public function test_overrides_are_cached_until_forget(): void
    {
        PlatformModuleSetting::query()->create([
            'module_key' => 'agent_wallet',
            'enabled' => false,
        ]);

        $service = app(PlatformModuleSettingsService::class);
        $this->assertFalse($service->overrides()['agent_wallet']);

        PlatformModuleSetting::query()->where('module_key', 'agent_wallet')->update(['enabled' => true]);
        $this->assertFalse($service->overrides()['agent_wallet']);

        $service->forgetCache();
        $this->assertTrue($service->overrides()['agent_wallet']);
    }

    public function test_apply_changes_does_not_write_when_validation_fails(): void
    {
        $developer = DeveloperUser::query()->create([
            'name' => 'Dev',
            'email' => 'dev-unit@example.com',
            'password' => 'secret',
            'is_active' => true,
        ]);

        $request = Request::create('/dev/cp/modules', 'POST');
        $service = app(PlatformModuleSettingsService::class);

        $validation = $service->applyChanges(
            changes: ['platform_module_control' => false],
            actor: $developer,
            request: $request,
        );

        $this->assertFalse($validation->isValid());
        $this->assertSame(0, PlatformModuleSetting::query()->count());
    }

    public function test_effective_state_for_reports_db_override(): void
    {
        PlatformModuleSetting::query()->create([
            'module_key' => 'finance_reports',
            'enabled' => false,
        ]);

        Cache::forget(PlatformModuleSettingsService::CACHE_KEY);

        $state = app(PlatformModuleSettingsService::class)->effectiveStateFor('finance_reports');

        $this->assertTrue($state['registry_default']);
        $this->assertFalse($state['db_override']);
        $this->assertTrue($state['db_row_exists']);
        $this->assertFalse($state['effective_enabled']);
    }
}
