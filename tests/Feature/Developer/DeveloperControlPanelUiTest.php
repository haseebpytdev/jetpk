<?php

namespace Tests\Feature\Developer;

use App\Models\DeveloperUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class DeveloperControlPanelUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('ota-developer.enabled', true);
    }

    public function test_modules_page_shows_deployment_control_panel_ui(): void
    {
        $developer = DeveloperUser::query()->create([
            'name' => 'Dev Owner',
            'email' => 'dev-ui@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);

        $this->withSession(['dev_cp_user_id' => $developer->id])
            ->get(route('dev.cp.modules.index'))
            ->assertOk()
            ->assertSee('Deployment Control Panel', false)
            ->assertSee('data-testid="dev-cp-deployment-modes"', false)
            ->assertSee('data-testid="dev-cp-mode-b2b_b2c"', false)
            ->assertSee('Protected — cannot be disabled', false)
            ->assertSee('admin_portal', false)
            ->assertSee('developer_control_panel', false)
            ->assertDontSee('preview only — not enforced', false)
            ->assertDontSee('Nav preview only', false)
            ->assertDontSee('Product Mode Presets', false);
    }

    public function test_modules_page_shows_protected_lock_for_developer_control_panel(): void
    {
        $developer = DeveloperUser::query()->create([
            'name' => 'Dev Owner',
            'email' => 'dev-protected@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);

        $this->withSession(['dev_cp_user_id' => $developer->id])
            ->get(route('dev.cp.modules.index'))
            ->assertOk()
            ->assertSee('data-testid="dev-cp-module-developer_control_panel"', false)
            ->assertSee('data-testid="dev-cp-module-platform_module_control"', false)
            ->assertSee('ti ti-lock', false);
    }
}
