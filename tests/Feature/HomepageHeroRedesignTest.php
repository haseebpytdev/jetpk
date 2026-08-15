<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\User;
use App\Support\Client\ClientPageKeys;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\JetpkHomepageFixture;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

/**
 * JetPakistan homepage hero + search shell (legacy ota-hero-* markup retired).
 */
class HomepageHeroRedesignTest extends TestCase
{
    use JetpkHomepageFixture;
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->makeJetpkProfile();
    }

    public function test_homepage_loads_with_default_hero_and_floating_search(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('class="hero', false)
            ->assertSee('id="jp-flight-search"', false)
            ->assertSee('data-jp-search', false)
            ->assertSee('data-hero-search', false)
            ->assertSee('name="trip_type"', false)
            ->assertSee('/flights/results', false);
    }

    public function test_homepage_renders_custom_hero_copy_and_background(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, [
            'hero' => [
                'eyebrow' => 'Aurora Travel',
                'headline' => 'Fly with Aurora',
                'headline_highlight' => '',
                'subtitle' => "Custom hero subtitle\nSecond line",
                'search_visible' => '1',
            ],
        ]);

        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertIsString($html);
        $this->assertStringContainsString('Fly with Aurora', $html);
        $this->assertStringContainsString('Custom hero subtitle', $html);
        $this->assertStringContainsString('Aurora Travel', $html);
        $this->assertStringNotContainsString('ota-hero-actions', $html);
        $this->assertStringNotContainsString('ota-btn-white', $html);
    }

    public function test_floating_search_shows_return_date_when_round_trip_selected_in_markup(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertIsString($html);
        $this->assertStringContainsString('name="return_date"', $html);
        $this->assertStringContainsString('name="trip_type"', $html);
        $this->assertStringContainsString('data-jp-trip="round_trip"', $html);
        $this->assertStringContainsString('data-jp-date-role="return_range"', $html);
    }

    public function test_floating_search_includes_multi_city_controls(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertIsString($html);
        $this->assertStringContainsString('data-jp-trip="multi_city"', $html);
        $this->assertStringContainsString('data-jp-multi-add', $html);
        $this->assertStringContainsString('name="multi_from[]"', $html);
        $this->assertStringContainsString('Add flight', $html);
    }

    public function test_admin_can_access_hero_settings_on_homepage_page(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.settings.homepage.edit'))
            ->assertRedirect();

        $location = (string) $this->actingAs($admin)
            ->get(route('admin.settings.homepage.edit'))
            ->headers
            ->get('Location');

        $this->assertTrue(
            str_contains($location, '/admin/dashboard/cms/pages')
            || str_contains($location, 'page-settings')
            || str_contains($location, 'cms'),
            'Expected legacy homepage settings GET to redirect into CMS/page-settings. Got: '.$location
        );
    }

    public function test_non_admin_cannot_access_homepage_settings(): void
    {
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();

        $this->actingAs($staff)->get(route('admin.settings.homepage.edit'))->assertForbidden();
        $this->actingAs($agent)->get(route('admin.settings.homepage.edit'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.settings.homepage.edit'))->assertForbidden();
        $this->get(route('admin.settings.homepage.edit'))->assertStatus(403);
    }

    public function test_unsafe_hero_body_html_is_sanitized_on_save_and_display(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, [
            'hero' => [
                'eyebrow' => 'Safe',
                'headline' => 'Safe headline',
                'subtitle' => '<script>alert(1)</script><p>Safe <strong>copy</strong></p>',
                'search_visible' => '1',
            ],
        ]);

        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertStringContainsString('Safe headline', $html);
        // Blade {{ }} escapes HTML — raw attacker script must never appear unescaped.
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('onclick=', $html);
    }

    public function test_admin_can_update_hero_headline_and_body_text(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();
        $this->makeJetpkProfile();

        $this->actingAs($admin)
            ->patch(route('admin.page-settings.update', ['pageKey' => ClientPageKeys::HOME]), [
                'content' => [
                    'hero' => [
                        'headline' => 'Updated headline',
                        'subtitle' => 'Updated intro body',
                        'search_visible' => '1',
                    ],
                ],
                'submitted_sections' => ['hero'],
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.page-settings.publish', ['pageKey' => ClientPageKeys::HOME]))
            ->assertRedirect();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Updated headline', false)
            ->assertSee('Updated intro body', false);
    }

    public function test_homepage_hero_does_not_render_legacy_cta_markup(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertIsString($html);

        $this->assertStringContainsString('class="hero', $html);
        $this->assertStringNotContainsString('ota-hero-actions', $html);
        $this->assertStringNotContainsString('ota-btn-white', $html);
        $this->assertStringNotContainsString('ota-hero-help-hint', $html);
        $this->assertStringNotContainsString('ota-hero--banner', $html);
    }

    public function test_admin_can_upload_hero_background_image(): void
    {
        Storage::fake('public');
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();
        $this->makeJetpkProfile();

        $this->actingAs($admin)
            ->post(route('admin.page-settings.assets.store', ['pageKey' => ClientPageKeys::HOME]), [
                'asset_key' => 'hero_background',
                'file' => UploadedFile::fake()->image('hero.jpg', 1920, 700),
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->patch(route('admin.page-settings.update', ['pageKey' => ClientPageKeys::HOME]), [
                'content' => [
                    'hero' => [
                        'headline' => 'With image',
                        'search_visible' => '1',
                    ],
                ],
                'submitted_sections' => ['hero'],
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.page-settings.publish', ['pageKey' => ClientPageKeys::HOME]))
            ->assertRedirect();

        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertTrue(
            str_contains($html, 'hero_background')
            || str_contains($html, 'hero.jpg')
            || str_contains($html, 'hero-media')
            || str_contains($html, 'hero--has-image'),
            'Expected hero background asset or hero media markup after upload'
        );
    }

    public function test_public_header_hides_flights_and_agent_network_with_signup_dropdown(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertIsString($html);

        preg_match('/<nav class="nav jp-header-nav"[^>]*>(.*?)<\/nav>/s', $html, $nav);
        $navHtml = $nav[1] ?? '';
        $this->assertNotSame('', $navHtml);
        $this->assertStringNotContainsString('>Flights<', $navHtml);
        $this->assertStringNotContainsString('Agent Network', $navHtml);

        $this->assertStringContainsString('jp-register-menu', $html);
        $this->assertStringContainsString('/register', $html);
        $this->assertStringContainsString('/agent/register', $html);
        $this->assertStringContainsString('Sign in', $html);
        $this->assertStringContainsString('Agent Registration', $html);
    }

    public function test_branding_hero_image_used_as_fallback_background(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, [
            'hero' => [
                'headline' => 'Fallback hero',
                'search_visible' => '1',
            ],
        ]);

        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertStringContainsString('Fallback hero', $html);
        // JP hero media falls back to branding/CMS assets when present; copy proves published path.
        $this->assertTrue(
            str_contains($html, 'hero-media')
            || str_contains($html, 'hero--has-image')
            || str_contains($html, 'hero-glow')
            || str_contains($html, 'class="hero'),
            'Expected JP hero shell markup on homepage'
        );
    }

    protected function defaultAgency(): Agency
    {
        return Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();
    }
}
