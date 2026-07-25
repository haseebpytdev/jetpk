<?php

namespace Tests\Feature\Jetpk;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageAsset;
use App\Models\ClientPageSetting;
use App\Services\Client\ClientPageContentResolver;
use App\Support\Client\ClientPageKeys;
use App\Support\Client\ClientPageMediaConsumption;
use App\Support\Client\ClientPublicWebrootPath;
use App\Support\Client\JetpkHomepageSectionData;
use App\Support\Homepage\JetpkHeroLcpPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class FrontendThemeCmsMediaDeploymentReadinessTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    /** @var list<string> */
    private const APPROVED_RUNTIME_FILES = [
        'app/Services/Client/ClientPageContentResolver.php',
        'app/Support/Client/Bootstrap/homepage.bootstrap.php',
        'app/Support/Client/ClientPageBootstrapTemplate.php',
        'app/Support/Client/ClientPageMediaConsumption.php',
        'app/Support/Client/ClientPagePublicFallbackCatalog.php',
        'app/Support/Homepage/JetpkHeroLcpPresenter.php',
        'app/Support/Client/JetpkHomepageSectionData.php',
        'resources/views/components/jp/brand-logo.blade.php',
        'resources/views/themes/frontend/jetpakistan/layouts/frontend.blade.php',
        'resources/views/themes/frontend/jetpakistan/partials/footer.blade.php',
        'resources/views/themes/frontend/jetpakistan/frontend/home.blade.php',
        'resources/views/themes/frontend/jetpakistan/sections/hero.blade.php',
        'resources/views/themes/frontend/jetpakistan/sections/support-cta.blade.php',
        'resources/views/themes/frontend/jetpakistan/sections/groups.blade.php',
        'resources/views/themes/frontend/jetpakistan/sections/destinations.blade.php',
        'public/themes/frontend/jetpakistan/css/theme.css',
        'public/themes/frontend/jetpakistan/css/tokens.css',
        'public/themes/frontend/jetpakistan/css/forms.css',
        'public/themes/frontend/jetpakistan/js/theme.js',
    ];

    /** @var list<string> */
    private const APPROVED_VIEWS = [
        'components.jp.brand-logo',
        'themes.frontend.jetpakistan.layouts.frontend',
        'themes.frontend.jetpakistan.partials.footer',
        'themes.frontend.jetpakistan.frontend.home',
        'themes.frontend.jetpakistan.sections.hero',
        'themes.frontend.jetpakistan.sections.support-cta',
        'themes.frontend.jetpakistan.sections.groups',
        'themes.frontend.jetpakistan.sections.destinations',
    ];

    private string $webroot;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        $this->seedJetpkAirports();
        $this->seedJetpkAgency();

        $this->webroot = storage_path('app/testing/jetpk-frontend-theme-webroot');
        File::deleteDirectory($this->webroot);
        File::ensureDirectoryExists($this->webroot);
        config(['ota_client.public_webroot_path' => $this->webroot]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->webroot);
        parent::tearDown();
    }

    public function test_approved_runtime_files_exist_and_views_resolve(): void
    {
        foreach (self::APPROVED_RUNTIME_FILES as $path) {
            $this->assertFileExists(base_path($path), "Missing runtime file: {$path}");
        }

        foreach (self::APPROVED_VIEWS as $view) {
            $this->assertTrue(View::exists($view), "Missing approved view: {$view}");
        }
    }

    public function test_media_consumption_matrix_documents_picture_hero_and_css_support_cta(): void
    {
        $hero = collect(ClientPageMediaConsumption::matrix())
            ->first(fn (array $row): bool => $row['asset_key'] === 'hero_background');

        $this->assertNotNull($hero);
        $this->assertSame('picture.hero-media img', $hero['element']);

        $support = collect(ClientPageMediaConsumption::matrix())
            ->first(fn (array $row): bool => $row['asset_key'] === 'support_cta_background');
        $this->assertSame('--jp-support-bg', $support['element'] ?? '');
    }

    public function test_frontend_layout_references_theme_v58_assets(): void
    {
        $layout = file_get_contents(resource_path('views/themes/frontend/jetpakistan/layouts/frontend.blade.php'));
        $this->assertIsString($layout);
        $this->assertStringContainsString('$jpAssetVersion = 58', $layout);
        $this->assertStringContainsString('/css/theme.css?v={{ $jpAssetVersion }}', $layout);
        $this->assertStringContainsString('/js/theme.js?v={{ $jpAssetVersion }}', $layout);
        $this->assertStringNotContainsString('$jpCheckoutAssetVersion', $layout);
    }

    public function test_homepage_renders_cms_hero_picture_without_css_hero_variable(): void
    {
        $profile = $this->makeJetpkProfile();
        $relative = 'client-assets/jetpk-assets/pages/home/hero_background-deploy.jpg';
        Storage::fake('public');
        Storage::disk('public')->put($relative, $this->syntheticJpeg());

        ClientPageAsset::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::HOME,
            'asset_key' => 'hero_background',
            'disk' => 'public',
            'path' => $relative,
            'public_url' => Storage::disk('public')->url($relative),
        ]);

        $this->seedPublishedHome($profile, app(ClientPageContentResolver::class)->defaultHomeContent());

        $html = $this->getHomeHtml()->getContent();
        $this->assertIsString($html);
        $this->assertStringContainsString('<picture>', $html);
        $this->assertStringContainsString('class="hero-img"', $html);
        $this->assertStringNotContainsString('--jp-hero-bg', $html);
        $this->assertSame(1, substr_count($html, '<picture>'));
        Http::assertNothingSent();
    }

    public function test_support_cta_css_backgrounds_for_desktop_only_mobile_fallback_and_both(): void
    {
        Storage::fake('public');
        $profile = $this->makeJetpkProfile();
        $base = $this->representativeValidFourCardHomeContent();
        $base['support_cta'] = ['enabled' => '1', 'background_mode' => 'uploaded_overlay'];

        $desktop = 'client-assets/jetpk-assets/pages/home/support_cta_background-d.jpg';
        Storage::disk('public')->put($desktop, $this->syntheticJpeg());
        ClientPageAsset::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::HOME,
            'asset_key' => 'support_cta_background',
            'disk' => 'public',
            'path' => $desktop,
            'public_url' => Storage::disk('public')->url($desktop),
        ]);
        $this->seedPublishedHome($profile, $base);
        $html = (string) $this->getHomeHtml()->getContent();
        $this->assertStringContainsString('--jp-support-bg: url(', $html);
        $this->assertStringContainsString('--jp-support-bg-mobile: url(', $html);
        $this->assertStringContainsString('support_cta_background-d.jpg', $html);

        $mobile = 'client-assets/jetpk-assets/pages/home/support_cta_background_mobile-m.jpg';
        Storage::disk('public')->put($mobile, $this->syntheticJpeg());
        ClientPageAsset::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::HOME,
            'asset_key' => 'support_cta_background_mobile',
            'disk' => 'public',
            'path' => $mobile,
            'public_url' => Storage::disk('public')->url($mobile),
        ]);
        $htmlBoth = (string) $this->getHomeHtml()->getContent();
        $this->assertStringContainsString('support_cta_background_mobile-m.jpg', $htmlBoth);

        ClientPageAsset::query()->where('client_profile_id', $profile->id)->delete();
        $empty = (string) $this->getHomeHtml()->getContent();
        $this->assertStringNotContainsString('--jp-support-bg:', $empty);
        Http::assertNothingSent();
    }

    public function test_hero_and_branding_resolve_relative_and_https_urls_safely(): void
    {
        $profile = $this->makeJetpkProfile();
        Storage::fake('public');
        $relative = 'client-assets/jetpk-assets/pages/home/hero_background-https.jpg';
        Storage::disk('public')->put($relative, $this->syntheticJpeg());
        $relativeUrl = '/storage/'.$relative;

        ClientPageAsset::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::HOME,
            'asset_key' => 'hero_background',
            'disk' => 'public',
            'path' => $relative,
            'public_url' => $relativeUrl,
        ]);
        $this->seedPublishedHome($profile, app(ClientPageContentResolver::class)->defaultHomeContent());

        $resolved = app(JetpkHomepageSectionData::class)->assetUrl('hero_background');
        $this->assertNotNull($resolved);
        $this->assertStringContainsString('/storage/', $resolved);

        $presented = app(JetpkHeroLcpPresenter::class)->present('https://cdn.jetpakistan.test/hero.jpg');
        $this->assertNotNull($presented);
        $this->assertSame('https://cdn.jetpakistan.test/hero.jpg', $presented['fallback_url']);
    }

    public function test_malformed_support_cta_urls_are_not_emitted(): void
    {
        $profile = $this->makeJetpkProfile();
        $content = $this->representativeValidFourCardHomeContent();
        $content['support_cta'] = [
            'enabled' => '1',
            'background_mode' => 'gradient',
            'call_url' => 'javascript:alert(1)',
            'chat_url' => '#',
        ];
        $this->seedPublishedHome($profile, $content);

        $html = (string) $this->getHomeHtml()->getContent();
        $this->assertStringNotContainsString('javascript:alert', $html);
        $this->assertStringNotContainsString('href="#"', $html);
    }

    public function test_jetpk_branding_has_no_cross_client_leakage_and_logo_component_is_safe(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, app(ClientPageContentResolver::class)->defaultHomeContent());

        $html = (string) $this->getHomeHtml()->getContent();
        foreach (['Parwaaz', 'haseeb-master', 'ota.haseebasif.com', 'YoursDomain'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $html);
        }

        $logo = file_get_contents(resource_path('views/components/jp/brand-logo.blade.php'));
        $this->assertIsString($logo);
        $this->assertStringContainsString('uses_jetpk_company_branding()', $logo);
        $this->assertStringContainsString('width="220" height="40"', $logo);
        Http::assertNothingSent();
    }

    public function test_empty_cms_hero_uses_scene_fallback_without_hidden_image_download(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, app(ClientPageContentResolver::class)->defaultHomeContent());

        $html = (string) $this->getHomeHtml()->getContent();
        $this->assertStringContainsString('class="hero-scene"', $html);
        $this->assertStringNotContainsString('<picture>', $html);
        $this->assertStringNotContainsString('rel="preload"', $html);
    }

    public function test_existing_published_page_settings_remain_compatible(): void
    {
        $profile = $this->makeJetpkProfile();
        $content = $this->representativeValidFourCardHomeContent();
        $this->seedPublishedHome($profile, $content);

        $row = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', ClientPageKeys::HOME)
            ->where('status', ClientPageSettingStatus::Published)
            ->first();

        $this->assertNotNull($row);
        $this->getHomeHtml();
        $this->assertSame('Custom destinations title', data_get($row->content_json, 'destinations.title'));
    }

    public function test_public_webroot_mirror_path_is_configurable_for_cms_assets(): void
    {
        Storage::fake('public');
        $profile = $this->makeJetpkProfile();
        $relative = 'client-assets/jetpk-assets/pages/home/support_cta_background-mirror.jpg';
        Storage::disk('public')->put($relative, $this->syntheticJpeg());
        $asset = ClientPageAsset::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::HOME,
            'asset_key' => 'support_cta_background',
            'disk' => 'public',
            'path' => $relative,
            'public_url' => Storage::disk('public')->url($relative),
        ]);

        File::ensureDirectoryExists(dirname(ClientPublicWebrootPath::path('storage/'.$relative)));
        File::copy(
            Storage::disk('public')->path($relative),
            ClientPublicWebrootPath::path('storage/'.$relative),
        );

        $this->assertTrue(ClientPublicWebrootPath::isFile('storage/'.$asset->path));
    }

    private function getHomeHtml(): TestResponse
    {
        $route = Route::has('client.preview.home') ? 'client.preview.home' : 'client.parity.home.alias';
        $response = $this->get(route($route, ['clientSlug' => 'jetpk']));
        if ($response->isRedirect()) {
            $response = $this->followRedirects($response);
        }

        return $response->assertOk();
    }

    private function syntheticJpeg(): string
    {
        $img = imagecreatetruecolor(40, 40);
        ob_start();
        imagejpeg($img, null, 80);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);

        return $bytes;
    }
}
