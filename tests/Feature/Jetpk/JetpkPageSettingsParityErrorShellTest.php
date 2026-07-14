<?php

namespace Tests\Feature\Jetpk;

use App\Models\ClientProfile;
use App\Models\ClientProfileModule;
use App\Support\Branding\JetpkCompanyBrandingResolver;
use App\Support\Client\ClientErrorResponseResolver;
use App\Support\Client\ClientProfileConfigReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

/**
 * Rebased parity + error-shell closure (palette stack preserved).
 */
class JetpkPageSettingsParityErrorShellTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('ota_client.slug', 'jetpk');
        Config::set('ota_client.single_client_mode', true);
        Config::set('ota_client.single_client_root', true);
        Config::set('ota-developer.enabled', true);
        Config::set('client_route_parity.enabled', false);

        $profile = ClientProfile::query()->firstOrCreate(
            ['slug' => 'jetpk'],
            [
                'name' => 'Jet Pakistan',
                'environment' => 'staging',
                'active_frontend_theme' => 'jetpakistan',
                'active_admin_theme' => 'jetpakistan',
                'active_staff_theme' => 'jetpakistan',
                'asset_profile' => 'jetpk-assets',
                'default_locale' => 'en',
                'timezone' => 'Asia/Karachi',
                'currency' => 'PKR',
                'is_master_profile' => false,
                'is_active' => true,
            ],
        );

        foreach (ClientProfileConfigReader::MODULE_KEYS as $moduleKey) {
            ClientProfileModule::query()->firstOrCreate(
                [
                    'client_profile_id' => $profile->id,
                    'module_key' => $moduleKey,
                ],
                ['enabled' => true],
            );
        }

        app(\App\Services\Client\CurrentClientContext::class)->set($profile);
    }

    public function test_homepage_returns_ok_and_frontend_layout_renders(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('jp-site-main', false);
        $response->assertDontSee('Something went wrong', false);
    }

    public function test_theme_palette_admin_routes_remain_registered(): void
    {
        $this->assertTrue(Route::has('admin.settings.theme-palette.edit'));
        $this->assertTrue(Route::has('admin.settings.theme-palette.update'));
        $this->assertTrue(Route::has('admin.settings.theme-palette.reset'));
    }

    public function test_frontend_layout_uses_public_css_variable_blocks(): void
    {
        $layout = file_get_contents(resource_path('views/themes/frontend/jetpakistan/layouts/frontend.blade.php')) ?: '';
        $this->assertStringContainsString('publicCssVariableBlocks', $layout);
        $this->assertStringContainsString('$jpAssetVersion = 43', $layout);
    }

    public function test_public_palette_css_blocks_resolve_for_jetpk(): void
    {
        $this->platformAdmin();
        $blocks = app(JetpkCompanyBrandingResolver::class)->publicCssVariableBlocks();

        $this->assertArrayHasKey('night', $blocks);
        $this->assertArrayHasKey('day', $blocks);
        $this->assertArrayHasKey('--jp-primary', $blocks['night']);
        $this->assertSame('#63B32E', $blocks['night']['--jp-primary']);
    }

    public function test_route_list_completes(): void
    {
        $this->artisan('route:list', ['--name' => 'admin.page-settings'])
            ->assertSuccessful();
    }

    public function test_server_error_page_renders_once(): void
    {
        $html = client_error_response('500')->getContent();

        $this->assertSingleThemedErrorDocument($html);
    }

    public function test_public_404_error_page_renders_once(): void
    {
        $html = client_error_response('404')->getContent();

        $this->assertSingleThemedErrorDocument($html);
    }

    #[DataProvider('supportedErrorStatusProvider')]
    public function test_resolved_error_response_renders_single_themed_document(string $code, int $status): void
    {
        $data = $code === '403' ? ['message' => 'Access restricted for audit.'] : [];
        $response = client_error_response($code, $data, $status);
        $html = $response->getContent();

        $this->assertSame($status, $response->getStatusCode());
        $this->assertSingleThemedErrorDocument($html);
        $this->assertSame('themes.frontend.jetpakistan.errors.'.$code, client_error_view($code));
    }

    #[DataProvider('supportedErrorStatusProvider')]
    public function test_http_error_dispatch_renders_single_themed_document(string $code, int $status): void
    {
        Config::set('app.debug', false);

        $response = $this->get('/_test/error/'.$code);
        $html = $response->getContent();

        $response->assertStatus($status);
        $this->assertSingleThemedErrorDocument($html);
    }

    public function test_missing_route_404_renders_single_themed_document(): void
    {
        Config::set('app.debug', false);

        $response = $this->get('/this-route-does-not-exist-jetpk-error-dispatch');
        $html = $response->getContent();

        $response->assertNotFound();
        $this->assertSingleThemedErrorDocument($html);
    }

    public function test_generic_error_fallback_renders_single_document_when_client_resolution_unavailable(): void
    {
        Config::set('ota_client.slug', 'master');
        Config::set('ota_client.single_client_mode', false);
        Config::set('ota_client.single_client_root', false);
        app(\App\Services\Client\CurrentClientContext::class)->clear();

        $this->assertSame('errors.404', client_error_view('404'));

        $html = client_error_response('404', [], 404)->getContent();

        $counts = ClientErrorResponseResolver::countDocumentMarkers($html);
        $this->assertSame(1, $counts['doctype']);
        $this->assertSame(1, $counts['html']);
        $this->assertSame(1, $counts['head']);
        $this->assertSame(1, $counts['body']);
        $this->assertSame(0, $counts['panel']);
        $this->assertSame(1, $counts['generic_card']);
    }

    public function test_root_error_views_have_single_deterministic_extends(): void
    {
        foreach (ClientErrorResponseResolver::SUPPORTED_CODES as $code) {
            $source = file_get_contents(resource_path('views/errors/'.$code.'.blade.php')) ?: '';
            $this->assertStringContainsString("@extends('errors.layout')", $source, 'errors/'.$code);
            $this->assertStringNotContainsString('$clientErrorView', $source, 'errors/'.$code);
            $this->assertStringNotContainsString('@if ($clientErrorView', $source, 'errors/'.$code);
            $this->assertSame(1, substr_count($source, '@extends'), 'errors/'.$code);
        }
    }

    public function test_errors_500_view_does_not_dispatch_from_blade(): void
    {
        $source = file_get_contents(resource_path('views/errors/500.blade.php')) ?: '';
        $this->assertStringNotContainsString('view($clientErrorView', $source);
        $this->assertStringNotContainsString('@extends($clientErrorView)', $source);
        $this->assertStringContainsString("@extends('errors.layout')", $source);
    }

    public function test_error_shell_audit_passes_rendered_marker_checks(): void
    {
        $this->artisan('jetpk:error-shell-audit')
            ->assertSuccessful()
            ->expectsOutputToContain('fail=0');
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function supportedErrorStatusProvider(): array
    {
        return [
            '403' => ['403', 403],
            '404' => ['404', 404],
            '419' => ['419', 419],
            '429' => ['429', 429],
            '500' => ['500', 500],
            '503' => ['503', 503],
        ];
    }

    private function assertSingleThemedErrorDocument(string $html): void
    {
        $counts = ClientErrorResponseResolver::countDocumentMarkers($html);

        $this->assertSame(1, $counts['doctype'], 'doctype count');
        $this->assertSame(1, $counts['html'], 'html count');
        $this->assertSame(1, $counts['head'], 'head count');
        $this->assertSame(1, $counts['body'], 'body count');
        $this->assertSame(1, $counts['header'], 'header count');
        $this->assertSame(1, $counts['main'], 'main count');
        $this->assertSame(1, $counts['footer'], 'footer count');
        $this->assertSame(1, $counts['panel'], 'jp-error-panel count');
        $this->assertSame(0, $counts['generic_card'], 'generic card count');
        $this->assertSame(1, substr_count($html, 'id="jp-error-heading"'));
    }

    public function test_homepage_page_settings_routes_remain_registered(): void
    {
        $this->assertTrue(Route::has('admin.page-settings.index'));
        $this->assertTrue(Route::has('admin.page-settings.edit'));
        $this->assertTrue(Route::has('admin.page-settings.update'));
    }
}
