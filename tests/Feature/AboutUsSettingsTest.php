<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AgencySetting;
use App\Models\User;
use App\Services\Agencies\AboutUsContentPresenter;
use App\Services\Agencies\AgencyBrandingService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class AboutUsSettingsTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
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

    public function test_public_about_us_renders_plain_formatted_content(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->findOrFail($admin->current_agency_id);

        app(AgencyBrandingService::class)->updateAboutUsSettings($agency, $admin, [
            'plain' => '<p>Published <em>about</em> paragraph.</p>',
            'html_override' => '',
            'html_active' => false,
            'updated_at' => now()->toIso8601String(),
        ]);

        $this->get(route('about'))
            ->assertOk()
            ->assertSee('Published <em>about</em> paragraph.', false)
            ->assertSee('data-about-custom="plain"', false)
            ->assertDontSee('Our story', false);
    }

    public function test_html_override_replaces_plain_content_on_public_page(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->patch(route('admin.settings.branding.about-us.update'), [
                'plain' => '<p>Plain should not show when HTML is active.</p>',
                'html_override' => '<h2>Override headline</h2><p>HTML body wins.</p>',
                'html_active' => 1,
            ])
            ->assertRedirect();

        $this->get(route('about'))
            ->assertOk()
            ->assertSee('Override headline', false)
            ->assertSee('<h2>Override headline</h2>', false)
            ->assertSee('data-about-custom="html"', false)
            ->assertDontSee('Plain should not show', false);
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

        $this->get(route('about'))
            ->assertOk()
            ->assertSee('Safe', false)
            ->assertDontSee('alert(1)', false)
            ->assertDontSee('onclick', false)
            ->assertDontSee('javascript:', false);
    }

    public function test_fallback_static_about_page_when_no_custom_content(): void
    {
        $this->get(route('about'))
            ->assertOk()
            ->assertSee('Our story', false)
            ->assertSee('Who we are', false)
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
