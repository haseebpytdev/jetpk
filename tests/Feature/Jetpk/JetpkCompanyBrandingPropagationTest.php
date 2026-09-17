<?php

namespace Tests\Feature\Jetpk;

use App\Models\Agency;
use App\Models\AgencySetting;
use App\Services\PublicContent\PublicContentApiPresenter;
use App\Support\Branding\JetpkCompanyBrandingResolver;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

/**
 * Company Profile branding must propagate to resolver + public config API.
 */
class JetpkCompanyBrandingPropagationTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_company_profile_logo_and_favicon_reach_public_config(): void
    {
        Storage::fake('public');
        $this->seed(OtaFoundationSeeder::class);

        $admin = $this->platformAdmin();
        $agency = Agency::query()->findOrFail($admin->current_agency_id);

        $logo = UploadedFile::fake()->image('company-logo.png', 240, 80);
        $favicon = UploadedFile::fake()->image('company-favicon.png', 32, 32);

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $this->actingAs($admin)->patch(route('admin.settings.branding.update'), [
            'display_name' => 'JetPakistan QA Brand',
            'logo' => $logo,
            'favicon' => $favicon,
            'color_scheme' => 'green_umrah',
        ])->assertRedirect();

        $settings = AgencySetting::query()->where('agency_id', $agency->id)->firstOrFail();
        $this->assertNotEmpty($settings->logo_path);
        $this->assertNotEmpty($settings->favicon_path);
        $this->assertTrue(Storage::disk('public')->exists($settings->logo_path));
        $this->assertTrue(Storage::disk('public')->exists($settings->favicon_path));

        /** @var JetpkCompanyBrandingResolver $resolver */
        $resolver = app(JetpkCompanyBrandingResolver::class);
        $this->assertSame('JetPakistan QA Brand', $resolver->companyName());
        $this->assertNotNull($resolver->logoUrl());
        $this->assertNotNull($resolver->faviconUrl());
        $this->assertStringContainsString('storage/', (string) $resolver->logoUrl());
        $this->assertStringContainsString('storage/', (string) $resolver->faviconUrl());

        $config = app(PublicContentApiPresenter::class)->publicConfig();
        $this->assertSame('JetPakistan QA Brand', $config['brand_name']);
        $this->assertSame($resolver->logoUrl(), $config['logo_url']);
        $this->assertSame($resolver->faviconUrl(), $config['favicon_url']);
        $this->assertIsInt($config['header_logo_height']);
    }
}
