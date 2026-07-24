<?php

namespace Tests\Feature\Jetpk;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageAsset;
use App\Models\ClientPageSetting;
use App\Models\User;
use App\Services\Client\ClientPageContentResolver;
use App\Support\Client\ClientPageKeys;
use App\Support\Client\ClientPublicWebrootPath;
use App\Support\Client\JetpkHomepageSectionData;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class JetpkHomepagePublishedMediaTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    private string $webroot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedJetpkAirports();
        $this->seedJetpkAgency();

        $this->webroot = storage_path('app/testing/jetpk-public-webroot');
        File::deleteDirectory($this->webroot);
        File::ensureDirectoryExists($this->webroot);
        config(['ota_client.public_webroot_path' => $this->webroot]);
    }

    protected function useRealPublicDisk(): void
    {
        $diskRoot = storage_path('app/testing/jetpk-public-disk');
        File::deleteDirectory($diskRoot);
        File::ensureDirectoryExists($diskRoot);
        config(['filesystems.disks.public.root' => $diskRoot]);
        app()->forgetInstance('filesystem.disk.public');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->webroot);
        parent::tearDown();
    }

    public function test_support_cta_upload_mirrors_to_public_webroot_and_sets_background_mode(): void
    {
        $this->useRealPublicDisk();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $profile = $this->makeJetpkProfile();
        $admin = User::factory()->create([
            'account_type' => \App\Enums\AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $this->actingAs($admin)->patch('/admin/page-settings/home', [
            'content' => [
                'destinations' => ['enabled' => '1', 'items' => []],
                'routes' => ['enabled' => '1', 'items' => []],
                'support_cta' => ['enabled' => '1', 'background_mode' => 'gradient'],
            ],
            'support_cta_background_file' => UploadedFile::fake()->image('banner.jpg'),
        ])->assertRedirect();

        $asset = ClientPageAsset::query()->where('asset_key', 'support_cta_background')->first();
        $this->assertNotNull($asset);
        $this->assertTrue(ClientPublicWebrootPath::isFile('storage/'.$asset->path));

        $draft = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('status', ClientPageSettingStatus::Draft)
            ->first();
        $this->assertSame('uploaded_overlay', data_get($draft->content_json, 'support_cta.background_mode'));
    }

    public function test_publishing_support_cta_media_renders_immediately_without_cache_clear(): void
    {
        $this->useRealPublicDisk();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $profile = $this->makeJetpkProfile();
        $admin = User::factory()->create([
            'account_type' => \App\Enums\AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);
        $resolver = app(ClientPageContentResolver::class);

        $this->seedPublishedHome($profile, [
            'support_cta' => ['enabled' => '1', 'title' => 'Support A', 'background_mode' => 'gradient'],
        ]);

        $urlA = app(JetpkHomepageSectionData::class)->assetUrl('support_cta_background');
        $this->assertNull($urlA);

        $this->actingAs($admin)->patch('/admin/page-settings/home', [
            'content' => [
                'destinations' => ['enabled' => '1', 'items' => []],
                'routes' => ['enabled' => '1', 'items' => []],
                'support_cta' => ['enabled' => '1', 'title' => 'Support B', 'background_mode' => 'gradient'],
            ],
            'support_cta_background_file' => UploadedFile::fake()->image('banner-a.jpg'),
        ])->assertRedirect();

        $urlDraft = app(JetpkHomepageSectionData::class)->assetUrl('support_cta_background');
        $this->assertNotNull($urlDraft);
        $this->get('/')->assertOk()->assertDontSee('--jp-support-bg', false);

        $resolver->publish($profile, ClientPageKeys::HOME, $admin->id);

        $urlB = app(JetpkHomepageSectionData::class)->assetUrl('support_cta_background');
        $this->assertNotNull($urlB);
        $this->assertNotSame($urlA, $urlB);
        $this->assertStringContainsString('v=', $urlB);

        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('--jp-support-bg: url(', false);
        $response->assertSee(htmlspecialchars($urlB, ENT_QUOTES), false);
    }

    public function test_replacing_support_cta_image_changes_versioned_url(): void
    {
        $this->useRealPublicDisk();
        $profile = $this->makeJetpkProfile();
        $service = app(\App\Services\Homepage\JetpkHomepageAssetService::class);
        $assetService = app(\App\Services\Client\ClientPageAssetService::class);

        $first = $service->storeSupportCtaImage($profile, 'desktop', UploadedFile::fake()->image('one.jpg'));
        $firstPath = (string) $first->path;
        $urlOne = app(ClientPageContentResolver::class)->assetUrl(ClientPageKeys::HOME, 'support_cta_background');
        $this->assertNotNull($urlOne);

        ClientPageAsset::query()->whereKey($first->id)->update(['updated_at' => now()->subMinute()]);

        $second = $service->storeSupportCtaImage($profile, 'desktop', UploadedFile::fake()->image('two.jpg'));
        $urlTwo = app(ClientPageContentResolver::class)->assetUrl(ClientPageKeys::HOME, 'support_cta_background');

        $this->assertNotSame($firstPath, $second->path);
        $this->assertNotSame($urlOne, $urlTwo);
        $this->assertStringContainsString('v=', $urlTwo);
        $this->assertSame($urlTwo, $assetService->versionedPublicUrl(Storage::disk('public')->url($second->path), $second));
    }

    public function test_publish_mirrors_all_homepage_assets_to_configured_webroot(): void
    {
        $this->useRealPublicDisk();
        $profile = $this->makeJetpkProfile();
        $user = User::factory()->create();
        $resolver = app(ClientPageContentResolver::class);
        $homepageAssets = app(\App\Services\Homepage\JetpkHomepageAssetService::class);

        $asset = $homepageAssets->storeSupportCtaImage($profile, 'desktop', UploadedFile::fake()->image('mirror.jpg'));
        $resolver->saveDraft($profile, ClientPageKeys::HOME, [
            'destinations' => ['enabled' => '1', 'items' => []],
            'routes' => ['enabled' => '1', 'items' => []],
            'support_cta' => ['enabled' => '1', 'background_mode' => 'uploaded_overlay'],
        ], $user->id);

        File::delete(ClientPublicWebrootPath::path('storage/'.$asset->path));

        $resolver->publish($profile, ClientPageKeys::HOME, $user->id);

        $this->assertTrue(ClientPublicWebrootPath::isFile('storage/'.$asset->path));
    }

    public function test_draft_only_support_cta_asset_does_not_change_published_background_mode_until_publish(): void
    {
        $this->useRealPublicDisk();
        $profile = $this->makeJetpkProfile();
        $user = User::factory()->create();
        $resolver = app(ClientPageContentResolver::class);
        $this->seedPublishedHome($profile, [
            'support_cta' => ['enabled' => '1', 'background_mode' => 'gradient'],
        ]);

        $resolver->saveDraft($profile, ClientPageKeys::HOME, [
            'destinations' => ['enabled' => '1', 'items' => []],
            'routes' => ['enabled' => '1', 'items' => []],
            'support_cta' => ['enabled' => '1', 'background_mode' => 'uploaded_overlay'],
        ], $user->id);

        app(\App\Services\Homepage\JetpkHomepageAssetService::class)
            ->storeSupportCtaImage($profile, 'desktop', UploadedFile::fake()->image('draft-only.jpg'));

        $published = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('status', ClientPageSettingStatus::Published)
            ->first();

        $this->assertSame('gradient', data_get($published->content_json, 'support_cta.background_mode'));
    }

    public function test_publish_syncs_support_cta_background_mode_when_asset_exists(): void
    {
        $this->useRealPublicDisk();
        $profile = $this->makeJetpkProfile();
        $user = User::factory()->create();
        $resolver = app(ClientPageContentResolver::class);

        app(\App\Services\Homepage\JetpkHomepageAssetService::class)
            ->storeSupportCtaImage($profile, 'desktop', UploadedFile::fake()->image('existing.jpg'));

        $resolver->saveDraft($profile, ClientPageKeys::HOME, [
            'destinations' => ['enabled' => '1', 'items' => []],
            'routes' => ['enabled' => '1', 'items' => []],
            'support_cta' => ['enabled' => '1', 'background_mode' => 'gradient'],
        ], $user->id);

        $published = $resolver->publish($profile, ClientPageKeys::HOME, $user->id);

        $this->assertSame('uploaded_overlay', data_get($published->content_json, 'support_cta.background_mode'));
    }
}
