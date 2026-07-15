<?php

namespace Tests\Feature\Ui;

use App\Enums\AccountType;
use App\Models\User;
use App\Support\Ui\UiVersionResolver;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class UiVersionArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OtaFoundationSeeder::class);

        Config::set('client_ui.preview_protection_enabled', false);
    }

    public function test_site_channel_defaults_to_v1(): void
    {
        $response = $this->getJson('/_test/ui-version')->assertOk();

        $response->assertJson([
            'channel' => 'site',
            'version' => 'v1',
            'preview' => null,
            'preview_active' => false,
            'resolved_view' => 'frontend.home',
        ]);
    }

    public function test_v1_path_prefix_resolves_site_v1_preview(): void
    {
        $response = $this->getJson('/v1/_test/ui-version')->assertOk();

        $response->assertJson([
            'channel' => 'site',
            'version' => 'v1',
            'preview' => 'v1',
            'preview_active' => false,
        ]);
    }

    public function test_v2_path_prefix_resolves_site_v2_when_active(): void
    {
        $response = $this->getJson('/v2/_test/ui-version')->assertOk();

        $response->assertJson([
            'channel' => 'site',
            'version' => 'v2',
            'preview' => 'v2',
            'preview_active' => true,
        ]);
    }

    public function test_v2_path_prefix_falls_back_to_v1_when_v2_not_active(): void
    {
        Config::set('ota-ui.channels.site.active_versions', ['v1']);
        Config::set('client_ui.allowed_versions', ['v1']);

        $response = $this->getJson('/v2/_test/ui-version')->assertOk();

        $response->assertJson([
            'channel' => 'site',
            'version' => 'v1',
            'preview' => null,
            'preview_active' => false,
        ]);
    }

    public function test_v2_home_overlay_resolves_when_present(): void
    {
        $resolver = new UiVersionResolver(Request::create('/v2', 'GET'));
        $resolver->setPathPrefixPreview('v2');
        $resolver->setPreviewNamespace(true);
        $resolver->resolve();

        $this->assertSame('v2', $resolver->effectiveVersion());
        $resolved = $resolver->resolveViewName('frontend.home');
        $this->assertSame('ui/site/v2/frontend/home', $resolved);
        $this->assertTrue(view()->exists($resolved));
    }

    public function test_missing_v2_view_falls_back_to_v1_canonical_path(): void
    {
        $resolver = new UiVersionResolver(Request::create('/v2', 'GET'));
        $resolver->setPathPrefixPreview('v2');
        $resolver->setPreviewNamespace(true);
        $resolver->resolve();

        $this->assertSame('v2', $resolver->effectiveVersion());
        $resolved = $resolver->resolveViewName('frontend.flights.results');
        $this->assertSame('frontend.flights.results', $resolved);
        $this->assertTrue(view()->exists($resolved));
    }

    public function test_admin_channel_resolves_independently_from_site(): void
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $response = $this->actingAs($admin)->getJson('/admin/_test/ui-version')->assertOk();

        $response->assertJson([
            'channel' => 'admin',
            'version' => 'v1',
            'preview' => null,
        ]);
    }

    public function test_admin_channel_supports_query_preview_when_allowed(): void
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $response = $this->actingAs($admin)->getJson('/admin/_test/ui-version?ui=v2')->assertOk();

        $response->assertJson([
            'channel' => 'admin',
            'version' => 'v2',
            'preview' => 'v2',
            'preview_active' => true,
        ]);
    }

    public function test_staff_channel_resolves_independently_from_site(): void
    {
        $staff = User::query()->where('account_type', AccountType::Staff)->first();
        $this->assertNotNull($staff);

        $response = $this->actingAs($staff)->getJson('/staff/_test/ui-version')->assertOk();

        $response->assertJson([
            'channel' => 'staff',
            'version' => 'v1',
            'preview' => null,
        ]);
    }

    public function test_staff_channel_supports_query_preview_when_allowed(): void
    {
        $staff = User::query()->where('account_type', AccountType::Staff)->first();
        $this->assertNotNull($staff);

        $response = $this->actingAs($staff)->getJson('/staff/_test/ui-version?ui=v2')->assertOk();

        $response->assertJson([
            'channel' => 'staff',
            'version' => 'v2',
            'preview' => 'v2',
            'preview_active' => true,
        ]);
    }

    public function test_customer_and_agent_routes_use_site_channel(): void
    {
        $customerResolver = new UiVersionResolver(Request::create('/customer', 'GET'));
        $this->assertSame('site', $customerResolver->channel());

        $agentResolver = new UiVersionResolver(Request::create('/agent', 'GET'));
        $this->assertSame('site', $agentResolver->channel());
    }

    public function test_admin_and_staff_defaults_do_not_change_when_site_default_changes(): void
    {
        Config::set('ota-ui.channels.site.default', 'v2');
        Config::set('client_ui.force_v1_default_until_verified', false);

        $adminResolver = new UiVersionResolver(Request::create('/admin', 'GET'));
        $adminResolver->resolve();
        $this->assertSame('v1', $adminResolver->effectiveVersion());

        $staffResolver = new UiVersionResolver(Request::create('/staff', 'GET'));
        $staffResolver->resolve();
        $this->assertSame('v1', $staffResolver->effectiveVersion());

        $siteResolver = new UiVersionResolver(Request::create('/', 'GET'));
        $siteResolver->resolve();
        $this->assertSame('v2', $siteResolver->effectiveVersion());
    }

    public function test_ui_version_audit_command_passes(): void
    {
        $this->artisan('ota:ui-version-audit')
            ->assertExitCode(0)
            ->expectsOutputToContain('fail=0');
    }

    public function test_ui_view_helper_returns_canonical_view_name(): void
    {
        $this->get('/_test/ui-version')->assertOk();

        $this->assertSame('frontend.home', ui_view('frontend.home')->name());
    }
}
