<?php

namespace Tests\Feature\Jetpk;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageSetting;
use App\Services\Homepage\JetpkHomepageAssetService;
use App\Services\Homepage\JetpkHomepageContentValidator;
use App\Support\Client\ClientPageKeys;
use App\Support\Client\Homepage\JetpkHomepageHeroSizing;
use App\Support\Client\JetpkHomepageSectionData;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\JetpkHomepageFixture;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class JetpkHomepageSizingTest extends TestCase
{
    use JetpkHomepageFixture;
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ota-developer.enabled' => true]);
        config(['client_route_parity.enabled' => false]);
        Storage::fake('public');
        $this->seedJetpkAirports();
        $this->seedJetpkAgency();
    }

    public function test_homepage_renders_hero_and_search_sizing_css_variables(): void
    {
        $profile = $this->makeJetpkProfile();
        $content = $this->representativeValidFourCardHomeContent();
        $content['hero']['headline_size'] = '110';
        $content['hero']['search_ui_scale'] = '95';
        $this->seedPublishedHome($profile, $content);

        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertIsString($html);
        $this->assertStringContainsString('--jp-hero-headline-scale: 1.1', $html);
        $this->assertStringContainsString('--jp-search-ui-scale:', $html);
        $this->assertStringContainsString('--jp-header-top-offset', file_get_contents(public_path('themes/frontend/jetpakistan/css/theme.css')) ?: '');
        $this->assertStringContainsString('.jp-home .jp-site-header', file_get_contents(public_path('themes/frontend/jetpakistan/css/theme.css')) ?: '');
        $this->assertStringContainsString('grid-template-columns:repeat(4,minmax(0,280px))', file_get_contents(public_path('themes/frontend/jetpakistan/css/theme.css')) ?: '');
        $this->assertStringContainsString('justify-content:center', file_get_contents(public_path('themes/frontend/jetpakistan/css/theme.css')) ?: '');
    }

    public function test_draft_hero_sizing_does_not_leak_to_published_homepage(): void
    {
        $profile = $this->makeJetpkProfile();
        $published = $this->representativeValidFourCardHomeContent();
        $published['hero']['headline_size'] = '100';
        $this->seedPublishedHome($profile, $published);

        ClientPageSetting::query()->updateOrCreate(
            ['client_profile_id' => $profile->id, 'page_key' => ClientPageKeys::HOME, 'status' => ClientPageSettingStatus::Draft],
            ['content_json' => array_merge($published, ['hero' => array_merge($published['hero'], ['headline_size' => '140'])])],
        );

        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertIsString($html);
        $this->assertStringContainsString('--jp-hero-headline-scale: 1', $html);
        $this->assertStringNotContainsString('--jp-hero-headline-scale: 1.4', $html);
    }

    public function test_validator_normalizes_hero_sizing_on_save(): void
    {
        $validator = app(JetpkHomepageContentValidator::class);
        $normalized = $validator->validateAndNormalize(ClientPageKeys::HOME, [
            'hero' => [
                'headline_size' => '999',
                'search_ui_scale' => '10',
            ],
            'destinations' => ['items' => []],
            'routes' => ['items' => []],
        ]);

        $this->assertSame('140', data_get($normalized, 'hero.headline_size'));
        $this->assertSame('80', data_get($normalized, 'hero.search_ui_scale'));
    }

    public function test_destination_upload_resolves_by_stable_item_id_not_position(): void
    {
        $profile = $this->makeJetpkProfile();
        $content = $this->representativeValidFourCardHomeContent();
        $items = array_values($content['destinations']['items']);
        $first = $items[0];
        $first['id'] = 'seed-dxb';
        $items[0] = $first;
        $content['destinations']['items'] = $items;
        $this->seedPublishedHome($profile, $content);

        $asset = app(JetpkHomepageAssetService::class)->storeDestinationImage(
            $profile,
            'seed-dxb',
            UploadedFile::fake()->image('dxb.jpg', 800, 1000),
        );

        $destinations = app(JetpkHomepageSectionData::class)->destinationsForDisplay();
        $this->assertStringContainsString($asset->asset_key, $destinations[0]['image']);
        $this->assertSame('destination_seed_dxb', $asset->asset_key);
    }

    public function test_admin_hero_sizing_controls_exist(): void
    {
        $this->makeJetpkProfile();
        $admin = $this->platformAdmin();
        $this->actingAs($admin)
            ->get(route('admin.page-settings.edit', ['pageKey' => 'home']))
            ->assertOk()
            ->assertSee('Hero sizing', false)
            ->assertSee('name="content[hero][headline_size]"', false)
            ->assertSee('name="content[hero][search_ui_scale]"', false)
            ->assertSee('Search box size', false);
    }

    public function test_highlight_outline_css_preserved(): void
    {
        $css = file_get_contents(public_path('themes/frontend/jetpakistan/css/theme.css')) ?: '';
        $this->assertStringContainsString('-webkit-text-stroke:2px #0B1D2A', $css);
        $this->assertStringContainsString('.jp-home .hero h1 .gold', $css);
    }

    public function test_no_transform_scale_on_search_shell(): void
    {
        $css = file_get_contents(public_path('themes/frontend/jetpakistan/css/theme.css')) ?: '';
        $searchCss = file_get_contents(public_path('themes/frontend/jetpakistan/css/jp-search.css')) ?: '';
        $this->assertDoesNotMatchRegularExpression('/\.search[^{]*\{[^}]*transform:\s*scale/i', $css.$searchCss);
    }
}
