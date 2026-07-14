<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AgencyHomepageSection;
use App\Models\AgencySetting;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomepageHeroRedesignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_homepage_loads_with_default_hero_and_floating_search(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Book Flights With Confidence')
            ->assertSee('ota-hero--banner', false)
            ->assertSee('ota-hero-search-card', false)
            ->assertSee('ota-hero-search-row', false)
            ->assertSee('Leaving from')
            ->assertSee('data-trip-radio', false)
            ->assertSee('name="trip_type"', false)
            ->assertSee(route('flights.results'), false);
    }

    public function test_homepage_renders_custom_hero_copy_and_background(): void
    {
        $agency = $this->defaultAgency();
        AgencyHomepageSection::query()->updateOrCreate(
            ['agency_id' => $agency->id, 'section_key' => 'hero'],
            [
                'title' => 'Fly with Aurora',
                'subtitle' => "Custom hero subtitle\nSecond line",
                'content' => [
                    'badge' => 'Aurora Travel',
                    'primary_cta_label' => 'Search Flights',
                    'primary_cta_url' => '#ota-flight-search',
                    'secondary_cta_label' => 'Agents',
                    'secondary_cta_url' => '/agent/register',
                    'show_support_hint' => true,
                ],
                'image_path' => 'agencies/1/homepage/hero-bg.jpg',
            ],
        );

        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertIsString($html);
        $this->assertStringContainsString('Fly with Aurora', $html);
        $this->assertStringContainsString('Custom hero subtitle', $html);
        $this->assertStringContainsString('Second line', $html);
        $this->assertStringContainsString('Aurora Travel', $html);
        $this->assertStringContainsString('hero-bg.jpg', $html);

        preg_match('/<div class="ota-hero-copy">(.*?)<div class="ota-hero-search-wrap">/s', $html, $heroCopy);
        $heroCopyHtml = $heroCopy[1] ?? '';
        $this->assertNotSame('', $heroCopyHtml);
        $this->assertStringNotContainsString('ota-hero-actions', $heroCopyHtml);
        $this->assertStringNotContainsString('ota-hero-help-hint', $heroCopyHtml);
        $this->assertStringNotContainsString('ota-btn-white', $heroCopyHtml);
        $this->assertStringNotContainsString('Need booking help?', $heroCopyHtml);
        $this->assertStringNotContainsString('Search Flights', $heroCopyHtml);
        $this->assertStringNotContainsString('>Agents<', $heroCopyHtml);
        $this->assertStringNotContainsString('Customer Support', $heroCopyHtml);
    }

    public function test_floating_search_shows_return_date_when_round_trip_selected_in_markup(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertIsString($html);
        $this->assertStringContainsString('data-round-return', $html);
        $this->assertStringContainsString('name="return_date"', $html);
        $this->assertStringContainsString('value="round_trip"', $html);
    }

    public function test_floating_search_includes_multi_city_controls(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertIsString($html);
        $this->assertStringContainsString('data-trip-panel="multi_city"', $html);
        $this->assertStringContainsString('data-multi-add', $html);
        $this->assertStringContainsString('Add another flight', $html);
        $this->assertStringContainsString('name="multi_from[]"', $html);
    }

    public function test_admin_can_access_hero_settings_on_homepage_page(): void
    {
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.settings.homepage.edit'))
            ->assertOk()
            ->assertSee('Hero banner')
            ->assertSee('Hero headline')
            ->assertSee('Hero text / intro content')
            ->assertSee('1920')
            ->assertSee('760')
            ->assertDontSee('Primary button label')
            ->assertDontSee('Secondary button label')
            ->assertDontSee('Show support hint');
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
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.settings.homepage.update', 'hero'), [
                'title' => 'Safe headline',
                'subtitle' => '<script>alert(1)</script><p>Safe <strong>copy</strong></p>',
                'is_enabled' => 1,
            ])
            ->assertRedirect();

        $section = AgencyHomepageSection::query()
            ->where('agency_id', $admin->current_agency_id)
            ->where('section_key', 'hero')
            ->first();

        $this->assertNotNull($section);
        $stored = (string) $section->subtitle;
        $this->assertStringNotContainsString('<script>', $stored);
        $this->assertStringNotContainsString('alert(1)', $stored);
        $this->assertStringContainsString('<strong>copy</strong>', $stored);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Safe headline', false)
            ->assertSee('<strong>copy</strong>', false)
            ->assertDontSee('alert(1)', false);
    }

    public function test_admin_can_update_hero_headline_and_body_text(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.settings.homepage.update', 'hero'), [
                'title' => 'Updated headline',
                'subtitle' => 'Updated intro body',
                'is_enabled' => 1,
            ])
            ->assertRedirect();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Updated headline')
            ->assertSee('Updated intro body');
    }

    public function test_homepage_hero_does_not_render_legacy_cta_markup(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertIsString($html);

        preg_match('/<section[^>]*id="ota-home-hero"[^>]*>(.*?)<\/section>/s', $html, $hero);
        $heroHtml = $hero[1] ?? $html;

        $this->assertStringNotContainsString('ota-hero-actions', $heroHtml);
        $this->assertStringNotContainsString('ota-btn-white', $heroHtml);
        $this->assertStringNotContainsString('ota-hero-help-hint', $heroHtml);
    }

    public function test_admin_can_upload_hero_background_image(): void
    {
        Storage::fake('public');
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.settings.homepage.update', 'hero'), [
                'title' => 'With image',
                'image' => UploadedFile::fake()->image('hero.jpg', 1920, 700),
                'is_enabled' => 1,
            ])
            ->assertRedirect();

        $section = AgencyHomepageSection::query()
            ->where('agency_id', $admin->current_agency_id)
            ->where('section_key', 'hero')
            ->first();

        $this->assertNotNull($section?->image_path);
        Storage::disk('public')->assertExists($section->image_path);
    }

    public function test_public_header_hides_flights_and_agent_network_with_signup_dropdown(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertIsString($html);

        $this->assertMatchesRegularExpression(
            '/data-testid="public-nav-desktop"[^>]*>.*?<\/div>\s*<div class="ota-nav-actions"/s',
            $html,
        );
        preg_match('/data-testid="public-nav-desktop"[^>]*>(.*?)<\/div>\s*<div class="ota-nav-actions"/s', $html, $desktop);
        $desktopNav = $desktop[1] ?? '';
        $this->assertStringNotContainsString('>Flights<', $desktopNav);
        $this->assertStringNotContainsString('Agent Network', $desktopNav);

        preg_match('/data-testid="public-nav-mobile"[^>]*>(.*?)<\/nav>/s', $html, $mobile);
        $mobileNav = $mobile[1] ?? '';
        $this->assertStringNotContainsString('>Flights<', $mobileNav);
        $this->assertStringNotContainsString('Agent Network', $mobileNav);
        $this->assertStringContainsString('Agent Registration', $mobileNav);

        $this->assertStringContainsString('data-testid="public-nav-signup-menu"', $html);
        $this->assertStringContainsString('class="public-signup-menu"', $html);
        $this->assertStringNotContainsString('ota-nav-signup-split', $html);
        $this->assertStringNotContainsString('ota-nav-signup-split__toggle', $html);
        $this->assertStringContainsString('data-testid="public-nav-agent-registration"', $html);
        $this->assertStringContainsString(route('register'), $html);
        $this->assertStringContainsString(route('agent.register'), $html);
        $this->assertStringContainsString('>Sign Up<', $html);
        $this->assertStringContainsString('>Login<', $html);
    }

    public function test_branding_hero_image_used_as_fallback_background(): void
    {
        $agency = $this->defaultAgency();
        AgencySetting::query()->updateOrCreate(
            ['agency_id' => $agency->id],
            ['hero_image_path' => 'agencies/1/branding/hero-fallback.jpg'],
        );

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('hero-fallback.jpg');
    }

    protected function defaultAgency(): Agency
    {
        return Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();
    }
}
