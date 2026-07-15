<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountType;
use App\Models\ClientPageAsset;
use App\Models\ClientProfile;
use App\Models\ClientProfileModule;
use App\Models\User;
use App\Services\Client\CurrentClientContext;
use App\Support\Client\ClientPageMediaSchema;
use App\Support\Client\ClientProfileConfigReader;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientPageSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Config::set('ota-developer.enabled', true);
        \Illuminate\Support\Facades\Config::set('client_route_parity.enabled', false);
        Storage::fake('public');
    }

    public function test_page_settings_index_requires_admin(): void
    {
        $this->makeProfile([
            'slug' => 'jetpk',
            'name' => 'Jet Pakistan',
            'active_frontend_theme' => 'jetpakistan',
            'active_admin_theme' => 'jetpakistan',
            'active_staff_theme' => 'jetpakistan',
            'asset_profile' => 'jetpk-assets',
        ]);

        $this->get('/admin/page-settings')
            ->assertRedirect('/login');
    }

    public function test_page_settings_index_renders_for_platform_admin_in_preview(): void
    {
        $profile = $this->makeProfile([
            'slug' => 'jetpk',
            'name' => 'Jet Pakistan',
            'active_frontend_theme' => 'jetpakistan',
            'active_admin_theme' => 'jetpakistan',
            'active_staff_theme' => 'jetpakistan',
            'asset_profile' => 'jetpk-assets',
        ]);
        app(CurrentClientContext::class)->set($profile);

        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $this->actingAs($admin)
            ->get('/admin/page-settings')
            ->assertOk()
            ->assertSee('Page settings', false);
    }

    public function test_store_asset_persists_metadata_and_redirects_to_media_tab(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $profile = $this->makeProfile([
            'slug' => 'jetpk',
            'name' => 'Jet Pakistan',
            'active_frontend_theme' => 'jetpakistan',
            'active_admin_theme' => 'jetpakistan',
            'active_staff_theme' => 'jetpakistan',
            'asset_profile' => 'jetpk-assets',
        ]);
        app(CurrentClientContext::class)->set($profile);

        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $assetKey = ClientPageMediaSchema::groupedFor('home')['hero'][0]['key'] ?? 'hero_background';

        $response = $this->actingAs($admin)->post('/admin/page-settings/home/assets', [
            'asset_key' => $assetKey,
            'file' => UploadedFile::fake()->image('hero.jpg', 1200, 600),
            'alt_text' => 'Homepage hero',
        ]);

        $response
            ->assertRedirect('/admin/page-settings/home#media')
            ->assertSessionHas('status', 'Asset uploaded.');

        $asset = ClientPageAsset::query()->where('asset_key', $assetKey)->first();
        $this->assertNotNull($asset);
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('public')->exists((string) $asset->path));
        $this->assertSame('image/jpeg', $asset->meta_json['mime'] ?? null);
        $this->assertGreaterThan(0, $asset->meta_json['size'] ?? 0);
        $this->assertSame('hero.jpg', $asset->meta_json['original_name'] ?? null);
    }

    public function test_store_asset_validation_failure_redirects_to_media_tab_without_server_error(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $profile = $this->makeProfile([
            'slug' => 'jetpk',
            'name' => 'Jet Pakistan',
            'active_frontend_theme' => 'jetpakistan',
            'active_admin_theme' => 'jetpakistan',
            'active_staff_theme' => 'jetpakistan',
            'asset_profile' => 'jetpk-assets',
        ]);
        app(CurrentClientContext::class)->set($profile);

        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $this->actingAs($admin)->post('/admin/page-settings/home/assets', [
            'asset_key' => 'hero_background',
            'file' => UploadedFile::fake()->create('bad.pdf', 50, 'application/pdf'),
        ])
            ->assertRedirect('/admin/page-settings/home#media')
            ->assertSessionHasErrors('file');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeProfile(array $overrides = []): ClientProfile
    {
        $profile = ClientProfile::query()->create(array_merge([
            'name' => 'Test Client',
            'slug' => 'test-client-'.uniqid(),
            'domain' => null,
            'environment' => 'staging',
            'active_frontend_theme' => 'v1-classic',
            'active_admin_theme' => 'v1-classic',
            'active_staff_theme' => 'v1-classic',
            'asset_profile' => 'test-assets',
            'default_locale' => 'en',
            'timezone' => 'Asia/Karachi',
            'currency' => 'PKR',
            'is_master_profile' => false,
            'is_active' => true,
        ], $overrides));

        foreach (ClientProfileConfigReader::MODULE_KEYS as $moduleKey) {
            ClientProfileModule::query()->create([
                'client_profile_id' => $profile->id,
                'module_key' => $moduleKey,
                'enabled' => false,
            ]);
        }

        return $profile;
    }
}
