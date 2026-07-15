<?php

namespace Tests\Feature\Developer;

use App\Models\DeveloperUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class DeveloperControlPanelTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('ota-developer.enabled', true);
    }

    public function test_guest_cannot_access_developer_control_panel(): void
    {
        $this->get(route('dev.cp.index'))
            ->assertRedirect(route('dev.cp.login'));

        $this->get(route('dev.cp.modules.index'))
            ->assertRedirect(route('dev.cp.login'));
    }

    public function test_developer_login_page_loads_dedicated_copy(): void
    {
        $this->get(route('dev.cp.login'))
            ->assertOk()
            ->assertSee('Developer Control Panel Login', false)
            ->assertSee('restricted to the product owner/developer', false)
            ->assertSee('separate from client admin access', false)
            ->assertSee('No public registration', false)
            ->assertSee('No forgot-password recovery from this page', false)
            ->assertDontSee('Sign in to access your bookings', false)
            ->assertDontSee('Log in', false)
            ->assertDontSee('Forgot password', false)
            ->assertDontSee('password/reset', false)
            ->assertDontSee('Register', false);
    }

    public function test_invalid_credentials_show_generic_error(): void
    {
        $this->developerUser(['email' => 'dev@example.com']);

        $this->from(route('dev.cp.login'))
            ->post(route('dev.cp.login.store'), [
                'email' => 'dev@example.com',
                'password' => 'wrong-password',
            ])
            ->assertSessionHasErrors('email')
            ->assertSessionHasErrors(['email' => 'Invalid developer credentials.']);

        $this->from(route('dev.cp.login'))
            ->post(route('dev.cp.login.store'), [
                'email' => 'unknown@example.com',
                'password' => 'any-password',
            ])
            ->assertSessionHasErrors('email')
            ->assertSessionHasErrors(['email' => 'Invalid developer credentials.']);
    }

    public function test_inactive_developer_user_cannot_login(): void
    {
        $this->developerUser([
            'email' => 'inactive@example.com',
            'is_active' => false,
        ]);

        $this->post(route('dev.cp.login.store'), [
            'email' => 'inactive@example.com',
            'password' => 'secret-password',
        ])
            ->assertSessionHasErrors('email')
            ->assertSessionHasErrors(['email' => 'Invalid developer credentials.']);
    }

    public function test_valid_developer_user_can_login_and_access_developer_cp(): void
    {
        $developer = $this->developerUser([
            'email' => 'dev@example.com',
            'name' => 'Product Owner',
        ]);

        $this->post(route('dev.cp.login.store'), [
            'email' => 'dev@example.com',
            'password' => 'secret-password',
        ])
            ->assertRedirect(route('dev.cp.index'));

        $this->assertSame($developer->id, session('dev_cp_user_id'));

        $this->get(route('dev.cp.index'))
            ->assertOk()
            ->assertSee('id="dev-cp-shell"', false)
            ->assertSee('ota-dev-cp-layout', false)
            ->assertSee('Deployment Owner Controls', false)
            ->assertSee('deployment-level capabilities and are not client admin settings', false)
            ->assertSee('Product Owner', false)
            ->assertSee('dev@example.com', false)
            ->assertSee('Overview')
            ->assertSee('Platform Admins', false)
            ->assertDontSee('>Companies<', false)
            ->assertSee(route('dev.cp.modules.index'), false)
            ->assertSee(route('dev.cp.logout'), false);

        $developer->refresh();
        $this->assertNotNull($developer->last_login_at);
        $this->assertNotNull($developer->last_login_ip);
    }

    public function test_developer_cp_uses_dedicated_layout_not_admin_dashboard(): void
    {
        $developer = $this->developerUser();

        $this->withSession(['dev_cp_user_id' => $developer->id])
            ->get(route('dev.cp.index'))
            ->assertOk()
            ->assertSee('ota-dev-cp-layout', false)
            ->assertSee('Overview', false)
            ->assertDontSee('ota-sidebar-refined', false)
            ->assertDontSee('Settings hub', false)
            ->assertDontSee('Operator Console', false)
            ->assertDontSee('ops-admin-banner', false);
    }

    public function test_platform_admin_with_dev_session_does_not_see_admin_sidebar_on_dev_cp(): void
    {
        $admin = $this->platformAdmin();
        $developer = $this->developerUser();

        $this->actingAs($admin)
            ->withSession(['dev_cp_user_id' => $developer->id])
            ->get(route('dev.cp.index'))
            ->assertOk()
            ->assertSee('ota-dev-cp-layout', false)
            ->assertDontSee('Settings hub', false)
            ->assertDontSee('ota-sidebar-refined', false);
    }

    public function test_valid_developer_user_can_access_module_control_page(): void
    {
        $this->developerUser(['email' => 'dev@example.com']);

        $this->post(route('dev.cp.login.store'), [
            'email' => 'dev@example.com',
            'password' => 'secret-password',
        ]);

        $this->get(route('dev.cp.modules.index'))
            ->assertOk()
            ->assertSee('ota-dev-cp-layout', false)
            ->assertSee('id="dev-cp-shell"', false)
            ->assertSee('Modules', false)
            ->assertSee('Deployment Control Panel', false)
            ->assertSee('data-testid="dev-cp-deployment-modes"', false)
            ->assertSee('data-testid="dev-cp-deployment-packages"', false)
            ->assertSee('Deployment scope', false)
            ->assertSee('Save changes', false)
            ->assertSee('admin_portal', false)
            ->assertDontSee('preview only — not enforced', false)
            ->assertDontSee('Product Mode Presets', false)
            ->assertSee(route('dev.cp.logout'), false)
            ->assertDontSee('ota-sidebar-refined', false)
            ->assertDontSee('Settings hub', false);
    }

    public function test_module_control_page_has_preview_save_controls(): void
    {
        $developer = $this->developerUser();

        $this->withSession(['dev_cp_user_id' => $developer->id])
            ->get(route('dev.cp.modules.index'))
            ->assertOk()
            ->assertSee('Save changes', false)
            ->assertSee('Apply mode', false)
            ->assertSee('Reset to registry defaults', false)
            ->assertSee('Emergency all-enabled reset', false)
            ->assertSee('name="modules[public_site]"', false);
    }

    public function test_logout_clears_developer_session(): void
    {
        $developer = $this->developerUser();

        $this->withSession(['dev_cp_user_id' => $developer->id])
            ->post(route('dev.cp.logout'))
            ->assertRedirect(route('dev.cp.login'));

        $this->assertNull(session('dev_cp_user_id'));

        $this->get(route('dev.cp.index'))
            ->assertRedirect(route('dev.cp.login'));
    }

    public function test_dev_logout_preserves_normal_ota_session(): void
    {
        $admin = $this->platformAdmin();
        $developer = $this->developerUser();

        $this->actingAs($admin)
            ->withSession(['dev_cp_user_id' => $developer->id])
            ->post(route('dev.cp.logout'))
            ->assertRedirect(route('dev.cp.login'));

        $this->assertNull(session('dev_cp_user_id'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_ota_logged_in_user_without_dev_session_cannot_access_developer_cp(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->get(route('dev.cp.index'))
            ->assertRedirect(route('dev.cp.login'));

        $this->actingAs($admin)->get(route('dev.cp.modules.index'))
            ->assertRedirect(route('dev.cp.login'));
    }

    public function test_platform_admin_not_in_developer_users_cannot_access_developer_cp(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->withSession(['dev_cp_user_id' => 99999])
            ->get(route('dev.cp.index'))
            ->assertRedirect(route('dev.cp.login'));
    }

    public function test_developer_cp_hidden_when_disabled(): void
    {
        Config::set('ota-developer.enabled', false);

        $developer = $this->developerUser();

        $this->get(route('dev.cp.login'))->assertNotFound();
        $this->get(route('dev.cp.index'))->assertNotFound();
        $this->get(route('dev.cp.modules.index'))->assertNotFound();

        $this->withSession(['dev_cp_user_id' => $developer->id])
            ->get(route('dev.cp.index'))
            ->assertNotFound();
    }

    public function test_admin_platform_modules_route_no_longer_exists(): void
    {
        $this->assertFalse(Route::has('admin.platform.modules.index'));

        $admin = $this->platformAdmin();

        $this->actingAs($admin)->get('/admin/platform/modules')->assertNotFound();
    }

    public function test_settings_hub_does_not_list_platform_module_control_card(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertDontSee('Platform Module Control');
    }

    public function test_page_does_not_expose_secrets_or_developer_credentials(): void
    {
        $developer = $this->developerUser([
            'email' => 'secret-owner@example.com',
            'password' => 'secret-password',
        ]);

        $this->withSession(['dev_cp_user_id' => $developer->id]);

        $response = $this->get(route('dev.cp.modules.index'));
        $response->assertOk();

        $content = strtolower($response->getContent() ?? '');
        $this->assertStringNotContainsString('smtp_password', $content);
        $this->assertStringNotContainsString('client_secret', $content);
        $this->assertStringNotContainsString('"credentials"', $content);
        $this->assertStringNotContainsString('api_key', $content);
        $this->assertStringNotContainsString('ota_developer_emails', $content);
        $this->assertStringNotContainsString('secret-password', $content);
        $this->assertStringNotContainsString('$2y$', $content);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function developerUser(array $overrides = []): DeveloperUser
    {
        return DeveloperUser::query()->create(array_merge([
            'name' => 'Dev Owner',
            'email' => 'developer@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ], $overrides));
    }
}
