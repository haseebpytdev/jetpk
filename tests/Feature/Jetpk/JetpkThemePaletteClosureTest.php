<?php

namespace Tests\Feature\Jetpk;

use App\Models\Agency;
use App\Models\AgencySetting;
use App\Services\Branding\JetpkThemePaletteService;
use App\Support\Branding\JetpkBrandPaletteCssResolver;
use App\Support\Branding\JetpkThemePaletteValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class JetpkThemePaletteClosureTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('ota_client.slug', 'jetpk');
        Config::set('ota_client.asset_profile', 'jetpk-assets');
        Config::set('ota_client.single_client_mode', true);
        Config::set('ota_client.single_client_root', true);
        $this->seedJetpkProfile();
    }

    private function seedJetpkProfile(): void
    {
        $profile = \App\Models\ClientProfile::query()->firstOrCreate(
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
        app(\App\Services\Client\CurrentClientContext::class)->set($profile);
        config(['ota.default_client_slug' => 'jetpk']);
    }

    public function test_defaults_match_approved_palette(): void
    {
        $defaults = app(JetpkThemePaletteService::class)->defaults();

        $this->assertSame('#63B32E', $defaults['day']['primary']);
        $this->assertSame('#19A7A6', $defaults['day']['accent']);
        $this->assertSame('#63B32E', $defaults['night']['primary']);
        $this->assertSame('#32BDD1', $defaults['night']['accent']);
    }

    public function test_day_and_night_palettes_persist_independently(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->findOrFail($admin->current_agency_id);
        $service = app(JetpkThemePaletteService::class);

        $service->savePalettes($agency, [
            'primary' => '#006B45',
            'accent' => '#19A7A6',
            'success' => '#63B32E',
            'page_bg' => '#EDF3F7',
            'surface' => '#FFFFFF',
            'text' => '#0B1D2A',
            'text_muted' => '#62788A',
            'border' => '#D7E2E9',
        ], [
            'primary' => '#52A832',
            'accent' => '#32BDD1',
            'success' => '#7BD23F',
            'page_bg' => '#070F18',
            'surface' => '#0A1420',
            'text' => '#F1F6F9',
            'text_muted' => '#91A7B5',
            'border' => '#1C3445',
        ]);

        $stored = $service->palettesForAgency($agency, false);
        $this->assertSame('#006B45', $stored['day']['primary']);
        $this->assertSame('#52A832', $stored['night']['primary']);

        $service->savePalettes($agency, [
            'primary' => '#005638',
            'accent' => '#19A7A6',
            'success' => '#63B32E',
            'page_bg' => '#EDF3F7',
            'surface' => '#FFFFFF',
            'text' => '#0B1D2A',
            'text_muted' => '#62788A',
            'border' => '#D7E2E9',
        ], $stored['night'], 'day');

        $stored = $service->palettesForAgency($agency, false);
        $this->assertSame('#005638', $stored['day']['primary']);
        $this->assertSame('#52A832', $stored['night']['primary']);
    }

    public function test_legacy_slate_primary_normalizes_to_green(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->findOrFail($admin->current_agency_id);
        AgencySetting::query()->updateOrCreate(
            ['agency_id' => $agency->id],
            ['primary_color' => '#0F172A', 'meta' => null],
        );

        $stored = app(JetpkThemePaletteService::class)->palettesForAgency($agency);
        $this->assertSame('#63B32E', $stored['day']['primary']);
        $this->assertSame('#63B32E', $stored['night']['primary']);
    }

    public function test_normalize_day_command_dry_run_reports_legacy_slate(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->findOrFail($admin->current_agency_id);
        AgencySetting::query()->updateOrCreate(
            ['agency_id' => $agency->id],
            [
                'primary_color' => '#0F172A',
                'meta' => [JetpkThemePaletteService::META_KEY => [
                    'day' => array_merge(app(JetpkThemePaletteService::class)->defaults()['day'], ['primary' => '#0F172A']),
                    'night' => app(JetpkThemePaletteService::class)->defaults()['night'],
                ]],
            ],
        );

        $this->artisan('jetpk:theme-palette-normalize-day-default', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('action=normalize')
            ->expectsOutputToContain('current=#0F172A')
            ->expectsOutputToContain('target=#63B32E');
    }

    public function test_booking_results_css_files_reference_jp_primary_tokens(): void
    {
        foreach ([
            'booking.css',
            'flight-cards.css',
            'jp-search.css',
            'results-base.css',
            'results.css',
        ] as $file) {
            $css = (string) file_get_contents(public_path('themes/frontend/jetpakistan/css/'.$file));
            $this->assertStringContainsString('--jp-primary', $css, $file);
            $this->assertStringContainsString('--jp-gradient-primary', $css, $file);
        }
    }

    public function test_legacy_orange_primary_migrates_to_green(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->findOrFail($admin->current_agency_id);
        AgencySetting::query()->updateOrCreate(
            ['agency_id' => $agency->id],
            ['primary_color' => '#EA7A1E', 'accent_color' => '#0BA5AD', 'meta' => null],
        );

        $stored = app(JetpkThemePaletteService::class)->palettesForAgency($agency);
        $this->assertSame('#63B32E', $stored['day']['primary']);
    }

    public function test_customized_values_are_preserved(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->findOrFail($admin->current_agency_id);
        $service = app(JetpkThemePaletteService::class);

        $service->savePalettes($agency, [
            'primary' => '#005638',
            'accent' => '#19A7A6',
            'success' => '#63B32E',
            'page_bg' => '#EDF3F7',
            'surface' => '#FFFFFF',
            'text' => '#0B1D2A',
            'text_muted' => '#62788A',
            'border' => '#D7E2E9',
        ], $service->defaults()['night']);

        AgencySetting::query()->where('agency_id', $agency->id)->update(['primary_color' => '#EA7A1E']);
        $stored = $service->palettesForAgency($agency, true);
        $this->assertSame('#005638', $stored['day']['primary']);
    }

    public function test_invalid_hex_and_injection_rejected(): void
    {
        $validator = app(JetpkThemePaletteValidator::class);
        $this->assertNotEmpty($validator->validateHexField('primary', '#GGGGGG'));
        $this->assertNotEmpty($validator->validateHexField('primary', '#006B45;{}'));
    }

    public function test_low_contrast_rejected(): void
    {
        $validator = app(JetpkThemePaletteValidator::class);
        $errors = $validator->validatePalette('day', [
            'primary' => '#FFFF00',
            'accent' => '#19A7A6',
            'success' => '#63B32E',
            'page_bg' => '#FFFFFF',
            'surface' => '#FFFFFF',
            'text' => '#FFFF00',
            'text_muted' => '#62788A',
            'border' => '#D7E2E9',
        ]);
        $this->assertNotEmpty($errors);
    }

    public function test_day_theme_outputs_jp_primary_variables(): void
    {
        $vars = app(JetpkBrandPaletteCssResolver::class)->variablesFromThemePalette('day', app(JetpkThemePaletteService::class)->defaults()['day']);
        $this->assertSame('#63B32E', $vars['--jp-primary']);
        $this->assertStringContainsString('linear-gradient', $vars['--jp-gradient-primary']);
        $this->assertSame('#63B32E', $vars['--brand']);
    }

    public function test_tokens_css_contains_no_raw_orange_primary(): void
    {
        $css = (string) file_get_contents(public_path('themes/frontend/jetpakistan/css/tokens.css'));
        $this->assertStringContainsString('--jp-primary', $css);
        $this->assertStringNotContainsString('#FB923C', $css);
        $this->assertStringNotContainsString('#EA7A1E', $css);
    }

    public function test_obsolete_day_primary_normalizes_on_read(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->findOrFail($admin->current_agency_id);
        AgencySetting::query()->updateOrCreate(
            ['agency_id' => $agency->id],
            [
                'primary_color' => '#006B45',
                'meta' => [
                    JetpkThemePaletteService::META_KEY => [
                        'day' => array_merge(app(JetpkThemePaletteService::class)->defaults()['day'], ['primary' => '#006B45']),
                        'night' => app(JetpkThemePaletteService::class)->defaults()['night'],
                    ],
                    config('jetpk-theme-palette.meta_day_customized_key') => false,
                ],
            ],
        );

        $stored = app(JetpkThemePaletteService::class)->palettesForAgency($agency);
        $this->assertSame('#63B32E', $stored['day']['primary']);
    }

    public function test_day_reset_persists_approved_primary_and_clears_customized(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->findOrFail($admin->current_agency_id);
        $service = app(JetpkThemePaletteService::class);

        AgencySetting::query()->updateOrCreate(
            ['agency_id' => $agency->id],
            [
                'primary_color' => '#006B45',
                'meta' => [
                    JetpkThemePaletteService::META_KEY => [
                        'day' => array_merge($service->defaults()['day'], ['primary' => '#006B45']),
                        'night' => $service->defaults()['night'],
                    ],
                    config('jetpk-theme-palette.meta_day_customized_key') => true,
                ],
            ],
        );

        $service->resetTheme($agency, 'day');

        $settings = AgencySetting::query()->where('agency_id', $agency->id)->firstOrFail();
        $meta = is_array($settings->meta) ? $settings->meta : [];
        $this->assertSame('#63B32E', $settings->primary_color);
        $this->assertSame('#63B32E', $meta[JetpkThemePaletteService::META_KEY]['day']['primary']);
        $this->assertFalse((bool) ($meta[config('jetpk-theme-palette.meta_day_customized_key')] ?? true));

        $stored = $service->palettesForAgency($agency);
        $this->assertSame('#63B32E', $stored['day']['primary']);
    }

    public function test_night_reset_persists_approved_primary(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->findOrFail($admin->current_agency_id);
        $service = app(JetpkThemePaletteService::class);

        $service->savePalettes($agency, $service->defaults()['day'], array_merge(
            $service->defaults()['night'],
            ['primary' => '#46C96F'],
        ));

        $service->resetTheme($agency, 'night');

        $stored = $service->palettesForAgency($agency);
        $this->assertSame('#63B32E', $stored['night']['primary']);
    }

    public function test_day_reset_survives_fresh_read_and_legacy_primary_color_column(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->findOrFail($admin->current_agency_id);
        $service = app(JetpkThemePaletteService::class);

        $service->resetTheme($agency, 'day');

        AgencySetting::query()->where('agency_id', $agency->id)->update(['primary_color' => '#006B45']);

        $stored = $service->palettesForAgency($agency);
        $this->assertSame('#63B32E', $stored['day']['primary']);
    }

    public function test_normalize_day_command_reports_obsolete_green(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->findOrFail($admin->current_agency_id);
        AgencySetting::query()->updateOrCreate(
            ['agency_id' => $agency->id],
            [
                'primary_color' => '#006B45',
                'meta' => [
                    JetpkThemePaletteService::META_KEY => [
                        'day' => array_merge(app(JetpkThemePaletteService::class)->defaults()['day'], ['primary' => '#006B45']),
                        'night' => app(JetpkThemePaletteService::class)->defaults()['night'],
                    ],
                ],
            ],
        );

        $this->artisan('jetpk:theme-palette-normalize-day-default', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('action=normalize')
            ->expectsOutputToContain('current=#006B45')
            ->expectsOutputToContain('target=#63B32E');
    }

    public function test_rendered_night_primary_css_block_uses_approved_green(): void
    {
        $this->platformAdmin();
        $blocks = app(\App\Support\Branding\JetpkCompanyBrandingResolver::class)->publicCssVariableBlocks();
        $this->assertSame('#63B32E', $blocks['night']['--jp-primary']);
        $this->assertSame('#63B32E', $blocks['day']['--jp-primary']);
    }

    public function test_theme_palette_settings_page_renders_day_and_night_sections(): void
    {
        $admin = $this->platformAdmin();
        $this->actingAs($admin)
            ->get(route('admin.settings.theme-palette.edit'))
            ->assertOk()
            ->assertSee('Day Theme Palette', false)
            ->assertSee('Night Theme Palette', false)
            ->assertSee('Primary Action', false)
            ->assertSee('Live preview', false);
    }

    public function test_admin_theme_palette_routes_are_registered(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admin.settings.theme-palette.edit'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admin.settings.theme-palette.update'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admin.settings.theme-palette.reset'));
    }
}
