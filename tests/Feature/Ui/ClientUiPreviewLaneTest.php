<?php

namespace Tests\Feature\Ui;

use App\Enums\AccountType;
use App\Http\Middleware\ProtectClientUiPreview;
use App\Models\User;
use App\Support\Client\ReservedClientPreviewSlugs;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ClientUiPreviewLaneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OtaFoundationSeeder::class);

        Config::set('client_ui.preview_protection_enabled', true);
        Config::set('client_ui.preview_key', 'test-preview-secret');
    }

    public function test_v2_namespace_blocked_without_grant(): void
    {
        $this->get('/v2')->assertNotFound();
    }

    public function test_v2_namespace_allowed_with_preview_key(): void
    {
        $this->get('/v2?preview_key=test-preview-secret')
            ->assertOk();

        $this->get('/v2/login')
            ->assertOk();
    }

    public function test_v2_admin_maps_to_admin_dashboard(): void
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $this->actingAs($admin)
            ->get('/v2/admin')
            ->assertOk();
    }

    public function test_v2_post_is_blocked(): void
    {
        $this->withSession([ProtectClientUiPreview::class => true])
            ->post('/v2/login', ['email' => 'a@b.com', 'password' => 'secret'])
            ->assertNotFound();
    }

    public function test_ui_v2_sets_sticky_session(): void
    {
        $this->get('/ui/v2?preview_key=test-preview-secret')
            ->assertRedirect();

        $this->assertSame('v2', session('client_ui_preview_version'));
    }

    public function test_ui_reset_clears_preview_session(): void
    {
        $this->withSession([
            'client_ui_preview_version' => 'v2',
            'client_ui_preview_granted' => true,
        ])->get('/ui/reset')
            ->assertRedirect();

        $this->assertNull(session('client_ui_preview_version'));
        $this->assertNull(session('client_ui_preview_granted'));
    }

    public function test_v1_default_on_homepage(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('ui-version-v1', false);
    }

    public function test_v2_loads_cloned_css_path(): void
    {
        $response = $this->get('/v2?preview_key=test-preview-secret');

        $response->assertOk();
        $response->assertSee('css/v2/ota-public-v2.css', false);
    }

    public function test_ui_preserve_url_prefixes_in_namespace(): void
    {
        $this->get('/v2?preview_key=test-preview-secret')->assertOk();

        $this->assertSame('/v2/admin', ui_preserve_url('/admin'));
    }

    public function test_reserved_slugs_include_v2_and_ui(): void
    {
        $this->assertTrue(ReservedClientPreviewSlugs::isReserved('v2'));
        $this->assertTrue(ReservedClientPreviewSlugs::isReserved('ui'));
    }

    public function test_client_ui_version_status_command_passes(): void
    {
        $this->artisan('ota:client-ui-version-status')
            ->assertExitCode(0)
            ->expectsOutputToContain('fail=0');
    }
}
