<?php

namespace Tests\Feature\Client;

use App\Models\ClientPageSetting;
use App\Enums\ClientPageSettingStatus;
use App\Services\Client\ClientPageContentResolver;
use App\Services\Homepage\JetpkHomepageContentValidator;
use App\Support\Client\ClientPageKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\JetpkHomepageFixture;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

/**
 * JP-ADMIN-CMS-03 live-blocker regressions: featured-deal publish fields + preview URL contract.
 */
class JpAdminCms03BlockerClosureTest extends TestCase
{
    use JetpkHomepageFixture;
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['client_route_parity.enabled' => false]);
        \Illuminate\Support\Facades\Storage::fake('public');
        $this->seedJetpkAirports();
        $this->seedJetpkAgency();
        $this->seedCmsTestUsers(1, 5, 7, 42, 111, 222);
    }

    public function test_featured_deal_editorial_and_media_fields_survive_publish_normalization(): void
    {
        $profile = $this->makeJetpkProfile();
        $dealId = 'deal-blocker-1';
        $assetKey = 'featured_deal_'.$dealId;

        $this->seedPublishedHome($profile, [
            'featured_deals' => [
                'enabled' => '1',
                'items' => [[
                    'id' => $dealId,
                    'airline' => 'PK',
                    'from' => 'LHE',
                    'to' => 'DXB',
                    'depart' => '08:00',
                    'arrive' => '10:30',
                    'dur' => '2h 30m',
                    'stops' => 0,
                    'price' => 49999,
                    'title' => 'Editorial Deal Title',
                    'badge' => 'Hot',
                    'description' => 'Keep me on publish',
                    'enabled' => '1',
                    'sort_order' => 1,
                    'image_asset_key' => $assetKey,
                    'image_alt' => 'Deal alt text',
                ]],
            ],
        ]);

        // Corrupt draft with editorial/media fields, then publish through validator path.
        $resolver = app(ClientPageContentResolver::class);
        $resolver->saveDraft($profile, ClientPageKeys::HOME, [
            'featured_deals' => [
                'enabled' => '1',
                'items' => [[
                    'id' => $dealId,
                    'airline' => 'PK',
                    'from' => 'LHE',
                    'to' => 'DXB',
                    'depart' => '08:00',
                    'arrive' => '10:30',
                    'dur' => '2h 30m',
                    'stops' => 0,
                    'price' => 49999,
                    'title' => 'Editorial Deal Title',
                    'badge' => 'Hot',
                    'description' => 'Keep me on publish',
                    'enabled' => '1',
                    'sort_order' => 1,
                    'image_asset_key' => $assetKey,
                    'image_alt' => 'Deal alt text',
                ]],
            ],
        ], 1);

        $resolver->publish($profile, ClientPageKeys::HOME, 1);

        $published = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', ClientPageKeys::HOME)
            ->where('status', ClientPageSettingStatus::Published)
            ->first();

        $this->assertNotNull($published);
        $item = data_get($published->content_json, 'featured_deals.items.0');
        $this->assertIsArray($item);
        $this->assertSame($dealId, $item['id'] ?? null);
        $this->assertSame('Editorial Deal Title', $item['title'] ?? null);
        $this->assertSame('Hot', $item['badge'] ?? null);
        $this->assertSame('Keep me on publish', $item['description'] ?? null);
        $this->assertSame($assetKey, $item['image_asset_key'] ?? null);
        $this->assertSame('Deal alt text', $item['image_alt'] ?? null);
    }

    public function test_featured_deal_rejects_path_traversal_asset_keys(): void
    {
        $validator = app(JetpkHomepageContentValidator::class);
        $normalized = $validator->validateAndNormalize(ClientPageKeys::HOME, [
            'featured_deals' => [
                'items' => [[
                    'airline' => 'PK',
                    'from' => 'LHE',
                    'to' => 'DXB',
                    'price' => 1000,
                    'image_asset_key' => '../secrets/../../evil',
                    'image_alt' => 'x',
                ]],
            ],
        ]);

        $key = data_get($normalized, 'featured_deals.items.0.image_asset_key');
        $this->assertSame('', $key);
        $this->assertNotEmpty(data_get($normalized, 'featured_deals.items.0.id'));
    }

    public function test_preview_session_json_returns_jp_preview_query_without_publishing(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, ['hero' => ['headline' => 'Published Only']]);
        app(ClientPageContentResolver::class)->saveDraft(
            $profile,
            ClientPageKeys::HOME,
            ['hero' => ['headline' => 'Draft Preview Marker']],
            1,
        );

        $admin = $this->platformAdmin();
        $response = $this->actingAs($admin)->postJson('/admin/page-settings/home/preview?format=json');

        $response->assertOk();
        $url = (string) ($response->json('previewUrl') ?? $response->json('preview_url') ?? '');
        $this->assertNotSame('', $url);
        $this->assertStringContainsString('jp_preview=1', $url);

        $published = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', ClientPageKeys::HOME)
            ->where('status', ClientPageSettingStatus::Published)
            ->first();
        $this->assertSame('Published Only', data_get($published?->content_json, 'hero.headline'));
    }
}
