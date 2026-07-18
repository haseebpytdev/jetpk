<?php

namespace Tests\Feature\Jetpk;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageAsset;
use App\Models\ClientPageSetting;
use App\Models\User;
use App\Services\Client\ClientPageContentResolver;
use App\Support\Audits\JetpkHomepageContentAuditService;
use App\Support\Client\ClientPageKeys;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class JetpkSupportCtaCssBackgroundTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seedJetpkAirports();
        $this->seedJetpkAgency();
    }

    /**
     * @return array<string, mixed>
     */
    private function publishedSupportPayload(string $mode = 'uploaded_overlay'): array
    {
        return [
            'support_cta' => [
                'enabled' => '1',
                'title' => 'Need help?',
                'call_enabled' => '1',
                'call_url' => 'tel:+923111222427',
                'chat_enabled' => '1',
                'chat_url' => '/support',
                'background_mode' => $mode,
                'overlay_strength' => 'medium',
            ],
        ];
    }

    public function test_uploaded_overlay_renders_css_variable_and_mode_classes(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedSupportCta($profile, 'uploaded_overlay', 'desktop.jpg', 'mobile.jpg');

        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('support-cta--mode-uploaded_overlay', false);
        $response->assertSee('support-cta--has-bg', false);
        $response->assertSee('support-cta--overlay-medium', false);
        $response->assertSee('--jp-support-bg: url(', false);
        $response->assertSee('--jp-support-bg-mobile: url(', false);
        $response->assertSee('href="tel:+923111222427"', false);
    }

    public function test_gradient_mode_does_not_emit_uploaded_background_classes(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedSupportCta($profile, 'gradient', 'desktop.jpg');

        $this->get('/')
            ->assertOk()
            ->assertSee('support-cta--mode-gradient', false)
            ->assertDontSee('support-cta--has-bg', false)
            ->assertDontSee('--jp-support-bg:', false);
    }

    public function test_uploaded_mode_renders_image_without_overlay_mode_class(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedSupportCta($profile, 'uploaded', 'desktop.jpg');

        $this->get('/')
            ->assertOk()
            ->assertSee('support-cta--mode-uploaded', false)
            ->assertSee('support-cta--has-bg', false)
            ->assertSee('--jp-support-bg: url(', false);
    }

    public function test_mobile_image_falls_back_to_desktop_when_mobile_asset_missing(): void
    {
        $profile = $this->makeJetpkProfile();
        $asset = $this->seedPublishedSupportCta($profile, 'uploaded_overlay', 'desktop-only.jpg');

        $html = $this->get('/')->assertOk()->getContent();
        $desktopUrl = app(\App\Support\Client\JetpkHomepageSectionData::class)->assetUrl('support_cta_background');
        $this->assertNotNull($desktopUrl);
        $this->assertStringContainsString("--jp-support-bg-mobile: url('".e($desktopUrl)."')", $html);
        $this->assertSame($asset->id, ClientPageAsset::query()->where('asset_key', 'support_cta_background')->value('id'));
    }

    public function test_media_audit_passes_for_valid_uploaded_overlay_support_cta(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedSupportCta($profile, 'uploaded_overlay', 'audit-pass.jpg');

        $result = app(JetpkHomepageContentAuditService::class)->auditMedia($profile);

        $this->assertSame(0, $result['fail_count']);
        $slot = collect($result['slots'])->firstWhere('slot', 'support_cta_background');
        $this->assertNotNull($slot);
        $this->assertTrue($slot['render_contract_ok']);
        $this->assertSame('pass', $slot['status']);
    }

    public function test_media_audit_fails_when_uploaded_mode_has_no_usable_asset(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, $this->publishedSupportPayload('uploaded'));

        $result = app(JetpkHomepageContentAuditService::class)->auditMedia($profile);
        $slot = collect($result['slots'])->firstWhere('slot', 'support_cta_background');

        $this->assertNotNull($slot);
        $this->assertFalse($slot['render_contract_ok']);
        $this->assertSame('fail', $slot['status']);
        $this->assertGreaterThan(0, $result['fail_count']);
    }

    public function test_publish_retains_support_cta_css_contract_and_media_versioning(): void
    {
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
                'support_cta' => $this->publishedSupportPayload('uploaded_overlay')['support_cta'],
            ],
            'support_cta_background_file' => UploadedFile::fake()->image('publish-bg.jpg'),
        ])->assertRedirect();

        app(ClientPageContentResolver::class)->publish($profile, ClientPageKeys::HOME, $admin->id);

        $url = app(\App\Support\Client\JetpkHomepageSectionData::class)->assetUrl('support_cta_background');
        $this->assertNotNull($url);
        $this->assertStringContainsString('v=', $url);

        $this->get('/')
            ->assertOk()
            ->assertSee('support-cta--mode-uploaded_overlay', false)
            ->assertSee('--jp-support-bg: url(', false)
            ->assertSee('href="tel:+923111222427"', false);
    }

    private function seedPublishedSupportCta(
        \App\Models\ClientProfile $profile,
        string $mode,
        string $desktopFilename,
        ?string $mobileFilename = null,
    ): ClientPageAsset {
        $desktop = ClientPageAsset::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::HOME,
            'asset_key' => 'support_cta_background',
            'disk' => 'public',
            'path' => 'jetpk/homepage/support-cta/test/'.$desktopFilename,
            'public_url' => '/storage/jetpk/homepage/support-cta/test/'.$desktopFilename,
            'meta_json' => [],
        ]);
        Storage::disk('public')->put($desktop->path, 'desktop-image');
        $this->mirrorToPublicStorage($desktop->path);

        if ($mobileFilename !== null) {
            $mobile = ClientPageAsset::query()->create([
                'client_profile_id' => $profile->id,
                'page_key' => ClientPageKeys::HOME,
                'asset_key' => 'support_cta_background_mobile',
                'disk' => 'public',
                'path' => 'jetpk/homepage/support-cta/test/'.$mobileFilename,
                'public_url' => '/storage/jetpk/homepage/support-cta/test/'.$mobileFilename,
                'meta_json' => [],
            ]);
            Storage::disk('public')->put($mobile->path, 'mobile-image');
            $this->mirrorToPublicStorage($mobile->path);
        }

        ClientPageSetting::query()->updateOrCreate(
            [
                'client_profile_id' => $profile->id,
                'page_key' => ClientPageKeys::HOME,
                'status' => ClientPageSettingStatus::Published,
            ],
            [
                'content_json' => $this->publishedSupportPayload($mode),
                'published_at' => now(),
            ],
        );

        return $desktop;
    }

    private function mirrorToPublicStorage(string $relativePath): void
    {
        $target = public_path('storage/'.$relativePath);
        File::ensureDirectoryExists(dirname($target));
        File::put($target, Storage::disk('public')->get($relativePath));
    }
}
