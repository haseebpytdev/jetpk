<?php

namespace Tests\Feature;

use App\Enums\ClientPageSettingStatus;
use App\Models\Agency;
use App\Models\AgencySetting;
use App\Models\ClientPageSetting;
use App\Models\User;
use App\Services\Agencies\AboutUsContentPresenter;
use App\Services\Agencies\AgencyBrandingService;
use App\Support\Client\ClientPageKeys;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\JetpkHomepageFixture;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class AboutUsSettingsTest extends TestCase
{
    use JetpkHomepageFixture;
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['client_route_parity.enabled' => false]);
        $this->seed(OtaFoundationSeeder::class);
        $this->seedJetpkAgency();
    }

    public function test_platform_admin_can_save_plain_about_us_content(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->patch(route('admin.settings.branding.about-us.update'), [
                'plain' => '<p>Custom <strong>About Us</strong> plain copy.</p>',
                'html_override' => '',
                'html_active' => 0,
            ])
            ->assertRedirect();

        $settings = AgencySetting::query()->where('agency_id', $admin->current_agency_id)->firstOrFail();
        $this->assertSame('<p>Custom <strong>About Us</strong> plain copy.</p>', $settings->meta['about_us']['plain'] ?? null);
        $this->assertFalse((bool) ($settings->meta['about_us']['html_active'] ?? true));
        $this->assertNotEmpty($settings->meta['about_us']['updated_at'] ?? '');
        $this->assertDatabaseHas('audit_logs', ['action' => 'agency.about_us_settings_updated']);
    }

    public function test_public_about_us_renders_published_cms_content(): void
    {
        $profile = $this->makeJetpkProfile();
        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::ABOUT,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => [
                'hero' => [
                    'kicker' => 'About JetPakistan',
                    'title' => 'Published about headline',
                    'description' => 'Published <em>about</em> paragraph.',
                ],
            ],
            'published_at' => now(),
        ]);

        $this->get(route('about'))
            ->assertOk()
            ->assertSee('Published about headline', false)
            ->assertSee('Published &lt;em&gt;about&lt;/em&gt; paragraph.', false)
            ->assertDontSee('data-about-custom', false);
    }

    public function test_published_cms_content_replaces_empty_about_shell(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $profile = $this->makeJetpkProfile();
        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::ABOUT,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => [
                'hero' => [
                    'title' => 'Override headline',
                    'description' => 'HTML body wins.',
                ],
                'feature_cards' => [
                    'enabled' => '1',
                    'items' => [
                        [
                            'id' => 'about-fc-1',
                            'title' => 'Override headline',
                            'body' => 'Feature card body.',
                            'enabled' => '1',
                            'sort_order' => 0,
                        ],
                    ],
                ],
            ],
            'published_at' => now(),
        ]);

        $this->get(route('about'))
            ->assertOk()
            ->assertSee('Override headline', false)
            ->assertSee('Feature card body.', false)
            ->assertDontSee('data-about-custom', false);
    }

    public function test_unsafe_script_and_event_handlers_are_stripped(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->patch(route('admin.settings.branding.about-us.update'), [
                'plain' => '',
                'html_override' => '<script>alert(1)</script><p onclick="alert(2)">Safe</p><a href="javascript:alert(3)">Link</a>',
                'html_active' => 1,
            ])
            ->assertRedirect();

        $settings = AgencySetting::query()->where('agency_id', $admin->current_agency_id)->firstOrFail();
        $stored = (string) ($settings->meta['about_us']['html_override'] ?? '');
        $this->assertStringNotContainsString('<script>', $stored);
        $this->assertStringNotContainsString('onclick', $stored);
        $this->assertStringNotContainsString('javascript:', $stored);

        $presented = app(AboutUsContentPresenter::class)->presentForPublic($settings);
        $this->assertSame('html', $presented['mode']);
        $this->assertStringContainsString('Safe', $presented['body_html']);
        $this->assertStringNotContainsString('alert(1)', $presented['body_html']);
        $this->assertStringNotContainsString('onclick', $presented['body_html']);
        $this->assertStringNotContainsString('javascript:', $presented['body_html']);
    }

    public function test_empty_cms_about_page_renders_shell_without_legacy_fallback_copy(): void
    {
        $this->makeJetpkProfile();

        $this->get(route('about'))
            ->assertOk()
            ->assertSee('jp-page--about', false)
            ->assertDontSee('Our story', false)
            ->assertDontSee('Who we are', false)
            ->assertDontSee('data-about-custom', false);
    }

    public function test_staff_and_guest_cannot_update_about_us_settings(): void
    {
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($staff)
            ->patch(route('admin.settings.branding.about-us.update'), ['plain' => 'x'])
            ->assertForbidden();

        $this->patch(route('admin.settings.branding.about-us.update'), ['plain' => 'x'])
            ->assertForbidden();
    }

    public function test_presenter_unit_sanitizes_plain_storage(): void
    {
        $presenter = app(AboutUsContentPresenter::class);
        $stored = $presenter->sanitizePlainForStorage('<script>x</script><p>OK</p>');
        $this->assertStringNotContainsString('script', $stored);
        $this->assertStringContainsString('<p>OK</p>', $stored);
    }

    public function test_legacy_agency_admin_cannot_update_about_us_settings(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $legacyAdmin = $this->legacyAgencyAdminFromSeed();

        $this->actingAs($legacyAdmin)
            ->patch(route('admin.settings.branding.about-us.update'), ['plain' => 'x'])
            ->assertForbidden();
    }
}
