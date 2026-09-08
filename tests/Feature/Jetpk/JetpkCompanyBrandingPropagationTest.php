<?php

namespace Tests\Feature\Jetpk;

use App\Models\Agency;
use App\Models\AgencySetting;
use App\Services\PublicContent\PublicContentApiPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class JetpkCompanyBrandingPropagationTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploaded_logo_is_exposed_via_public_config_with_cache_bust(): void
    {
        Storage::fake('public');
        config([
            'ota.default_agency_slug' => 'jetpk-agency',
            'ota_client.slug' => 'jetpk',
            'ota_client.asset_profile' => 'jetpk',
            'ota_client.single_client_mode' => true,
            'ota_client.single_client_root' => true,
        ]);

        $agency = Agency::factory()->create(['slug' => 'jetpk-agency', 'name' => 'JetPakistan']);
        $settings = AgencySetting::query()->create([
            'agency_id' => $agency->id,
            'display_name' => 'JetPakistan',
        ]);

        $path = "agencies/{$agency->id}/branding/logo-test.png";
        Storage::disk('public')->put($path, 'png-bytes');
        $settings->logo_path = $path;
        $settings->save();

        $presenter = app(PublicContentApiPresenter::class);
        $config = $presenter->publicConfig();

        $this->assertIsString($config['logo_url'] ?? null);
        $this->assertStringContainsString('/storage/'.$path, (string) $config['logo_url']);
        $this->assertStringContainsString('v=', (string) $config['logo_url']);
    }

    public function test_jpeg_logo_upload_is_accepted_by_branding_controller(): void
    {
        Storage::fake('public');
        $this->withoutMiddleware();

        $agency = Agency::factory()->create(['slug' => config('ota.default_agency_slug', 'asif-travels')]);
        $admin = \App\Models\User::factory()->create([
            'account_type' => \App\Enums\AccountType::PlatformAdmin,
            'current_agency_id' => $agency->id,
        ]);

        $response = $this->actingAs($admin)->patch('/admin/settings/branding?format=json', [
            'logo' => UploadedFile::fake()->image('company-logo.jpg', 320, 120),
        ]);

        $response->assertOk();
        $settings = AgencySetting::query()->where('agency_id', $agency->id)->first();
        $this->assertNotNull($settings);
        $this->assertNotSame('', trim((string) $settings->logo_path));
        Storage::disk('public')->assertExists((string) $settings->logo_path);
    }

    public function test_invalid_favicon_type_is_rejected(): void
    {
        Storage::fake('public');
        $this->withoutMiddleware();

        $agency = Agency::factory()->create(['slug' => config('ota.default_agency_slug', 'asif-travels')]);
        $admin = \App\Models\User::factory()->create([
            'account_type' => \App\Enums\AccountType::PlatformAdmin,
            'current_agency_id' => $agency->id,
        ]);

        $this->actingAs($admin)->patch('/admin/settings/branding?format=json', [
            'favicon' => UploadedFile::fake()->image('favicon.jpg', 64, 64),
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_valid_png_favicon_upload_is_accepted(): void
    {
        Storage::fake('public');
        $this->withoutMiddleware();

        $agency = Agency::factory()->create(['slug' => config('ota.default_agency_slug', 'asif-travels')]);
        $admin = \App\Models\User::factory()->create([
            'account_type' => \App\Enums\AccountType::PlatformAdmin,
            'current_agency_id' => $agency->id,
        ]);

        $this->actingAs($admin)->patch('/admin/settings/branding?format=json', [
            'favicon' => UploadedFile::fake()->image('favicon.png', 64, 64),
        ])->assertOk();

        $settings = AgencySetting::query()->where('agency_id', $agency->id)->first();
        $this->assertNotNull($settings);
        $this->assertNotSame('', trim((string) $settings->favicon_path));
        Storage::disk('public')->assertExists((string) $settings->favicon_path);
    }
}
