<?php

namespace Tests\Feature\Jetpk;

use App\Enums\AccountType;
use App\Enums\ClientPageSettingStatus;
use App\Models\Agency;
use App\Models\AgencyMedia;
use App\Models\ClientPage;
use App\Models\ClientPageAsset;
use App\Models\ClientPageSetting;
use App\Models\CmsPage;
use App\Models\User;
use App\Services\Client\CurrentClientContext;
use App\Support\Client\ClientPageKeys;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

/**
 * Cluster D — structured page management: catalog, edit, draft/preview/publish,
 * duplicate, archive, attach-from-library, RBAC, XSS rejection, homepage preservation.
 */
class JetpkCmsPageManagementClusterDTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ota-developer.enabled' => true]);
        config(['client_route_parity.enabled' => false]);
        Storage::fake('public');
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->seedJetpkAirports();
        $this->seedJetpkAgency();
    }

    public function test_catalog_lists_existing_managed_pages(): void
    {
        $profile = $this->makeJetpkProfile();
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->getJson('/admin/page-settings/catalog?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonFragment(['key' => 'about'])
            ->assertJsonFragment(['key' => 'faq'])
            ->assertJsonFragment(['key' => 'support'])
            ->assertJsonFragment(['key' => 'terms'])
            ->assertJsonFragment(['key' => 'privacy'])
            ->assertJsonFragment(['key' => 'home']);

        unset($profile);
    }

    public function test_open_edit_save_draft_preview_publish_isolation(): void
    {
        $profile = $this->makeJetpkProfile();
        $admin = $this->platformAdmin();

        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::ABOUT,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => [
                'hero' => ['title' => 'Published About', 'description' => 'Live copy'],
                'seo' => ['title' => 'Published SEO'],
            ],
            'published_at' => now(),
        ]);

        $edit = $this->actingAs($admin)->getJson('/admin/page-settings/about?format=json');
        $edit->assertOk()->assertJsonPath('ok', true)->assertJsonPath('pageKey', 'about');
        $this->assertNotEmpty($edit->json('sections'));

        $draftTitle = 'Draft About Title '.uniqid();
        $this->actingAs($admin)->patchJson('/admin/page-settings/about?format=json', [
            'content' => [
                'hero' => ['title' => $draftTitle, 'description' => 'Draft only'],
                'seo' => ['title' => 'Draft SEO'],
            ],
        ])->assertOk()->assertJsonPath('ok', true);

        // Public remains published authority until publish.
        $published = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', ClientPageKeys::ABOUT)
            ->where('status', ClientPageSettingStatus::Published)
            ->first();
        $this->assertSame('Published About', data_get($published?->content_json, 'hero.title'));

        $preview = $this->actingAs($admin)->postJson('/admin/page-settings/about/preview?format=json');
        $preview->assertOk()->assertJsonPath('ok', true);
        $this->assertNotEmpty($preview->json('previewUrl') ?? $preview->json('preview_url'));

        $this->actingAs($admin)->postJson('/admin/page-settings/about/publish?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $published = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', ClientPageKeys::ABOUT)
            ->where('status', ClientPageSettingStatus::Published)
            ->first();
        $this->assertSame($draftTitle, data_get($published?->content_json, 'hero.title'));
    }

    public function test_duplicate_creates_unique_slug_draft_without_publishing(): void
    {
        $profile = $this->makeJetpkProfile();
        $admin = $this->platformAdmin();

        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::FAQ,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => [
                'hero' => ['title' => 'FAQ Live', 'description' => 'Help'],
                'categories' => ['enabled' => '1', 'items' => [['question' => 'Q1', 'answer' => 'A1']]],
                'seo' => ['title' => 'FAQ'],
            ],
            'published_at' => now(),
        ]);

        $dup = $this->actingAs($admin)->postJson('/admin/page-settings/faq/duplicate?format=json');
        $dup->assertOk()->assertJsonPath('ok', true)->assertJsonPath('status', 'draft');
        $newKey = (string) $dup->json('page_key');
        $slug = (string) $dup->json('slug');
        $this->assertStringStartsWith('custom:', $newKey);
        $this->assertSame('faq-copy', $slug);

        $this->assertDatabaseHas('client_pages', [
            'client_profile_id' => $profile->id,
            'slug' => 'faq-copy',
        ]);

        $draft = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', $newKey)
            ->where('status', ClientPageSettingStatus::Draft)
            ->first();
        $this->assertNotNull($draft);
        $this->assertSame('FAQ Live', data_get($draft->content_json, 'hero.title'));

        $this->assertNull(
            ClientPageSetting::query()
                ->where('client_profile_id', $profile->id)
                ->where('page_key', $newKey)
                ->where('status', ClientPageSettingStatus::Published)
                ->first()
        );

        // Original FAQ published unchanged.
        $orig = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', ClientPageKeys::FAQ)
            ->where('status', ClientPageSettingStatus::Published)
            ->first();
        $this->assertSame('FAQ Live', data_get($orig?->content_json, 'hero.title'));
    }

    public function test_archive_unpublishes_without_destroying_record(): void
    {
        $profile = $this->makeJetpkProfile();
        $admin = $this->platformAdmin();

        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::TERMS,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => ['legal' => ['title' => 'Terms'], 'seo' => ['title' => 'Terms']],
            'published_at' => now(),
        ]);
        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::TERMS,
            'status' => ClientPageSettingStatus::Draft,
            'content_json' => ['legal' => ['title' => 'Terms draft'], 'seo' => ['title' => 'Terms']],
            'settings_json' => [],
        ]);

        $this->actingAs($admin)->postJson('/admin/page-settings/terms/unpublish?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertNull(
            ClientPageSetting::query()
                ->where('client_profile_id', $profile->id)
                ->where('page_key', ClientPageKeys::TERMS)
                ->where('status', ClientPageSettingStatus::Published)
                ->first()
        );

        $draft = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', ClientPageKeys::TERMS)
            ->where('status', ClientPageSettingStatus::Draft)
            ->first();
        $this->assertNotNull($draft);
        $this->assertTrue((bool) data_get($draft->settings_json, 'archived'));
        $this->assertSame('Terms draft', data_get($draft->content_json, 'legal.title'));
    }

    public function test_homepage_cannot_be_unpublished_and_content_preserved(): void
    {
        $profile = $this->makeJetpkProfile();
        $admin = $this->platformAdmin();

        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => [
                'hero' => ['headline' => 'JetPakistan Home'],
                'routes' => ['items' => [['from' => 'KHI', 'to' => 'DXB']]],
                'destinations' => ['items' => [['code' => 'DXB', 'title' => 'Dubai']]],
                'featured_deals' => ['items' => [['title' => 'Deal']]],
                'support_cta' => ['phone_value' => '+92 300 0000000'],
            ],
            'published_at' => now(),
        ]);

        $this->actingAs($admin)->postJson('/admin/page-settings/home/unpublish?format=json')
            ->assertStatus(422)
            ->assertJsonPath('ok', false);

        $home = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', ClientPageKeys::HOME)
            ->where('status', ClientPageSettingStatus::Published)
            ->first();
        $this->assertSame('JetPakistan Home', data_get($home?->content_json, 'hero.headline'));
        $this->assertCount(1, data_get($home?->content_json, 'routes.items', []));
        $this->assertCount(1, data_get($home?->content_json, 'destinations.items', []));
        $this->assertCount(1, data_get($home?->content_json, 'featured_deals.items', []));
        $this->assertSame('+92 300 0000000', data_get($home?->content_json, 'support_cta.phone_value'));
    }

    public function test_attach_from_library_creates_client_page_asset_without_reupload_ux(): void
    {
        $profile = $this->makeJetpkProfile();
        $admin = $this->platformAdmin();
        $agency = Agency::query()->first() ?? Agency::factory()->create();

        Storage::disk('public')->put('agency-media/qa-hero.png', UploadedFile::fake()->image('qa-hero.png', 80, 60)->get());
        $media = AgencyMedia::query()->create([
            'agency_id' => $agency->id,
            'uploaded_by' => $admin->id,
            'collection' => 'general',
            'disk' => 'public',
            'file_path' => 'agency-media/qa-hero.png',
            'file_name' => 'qa-hero.png',
            'mime_type' => 'image/png',
            'size_bytes' => 1200,
            'alt_text' => 'QA hero alt',
        ]);

        $this->actingAs($admin)->postJson('/admin/page-settings/about/assets/attach?format=json', [
            'asset_key' => 'hero_background',
            'agency_media_id' => $media->id,
            'alt_text' => 'Attached alt',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('asset.agency_media_id', $media->id);

        $asset = ClientPageAsset::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', ClientPageKeys::ABOUT)
            ->where('asset_key', 'hero_background')
            ->first();
        $this->assertNotNull($asset);
        $this->assertSame($media->id, data_get($asset->meta_json, 'agency_media_id'));
        $this->assertTrue((bool) data_get($asset->meta_json, 'attached_from_library'));
        $this->assertSame('Attached alt', $asset->alt_text);
    }

    public function test_attach_rejects_path_traversal_media(): void
    {
        $this->makeJetpkProfile();
        $admin = $this->platformAdmin();
        $agency = Agency::query()->first() ?? Agency::factory()->create();

        $media = AgencyMedia::query()->create([
            'agency_id' => $agency->id,
            'uploaded_by' => $admin->id,
            'collection' => 'general',
            'disk' => 'public',
            'file_path' => '../secrets/passwd.png',
            'file_name' => 'passwd.png',
            'mime_type' => 'image/png',
            'size_bytes' => 10,
            'alt_text' => 'bad',
        ]);

        $this->actingAs($admin)->postJson('/admin/page-settings/about/assets/attach?format=json', [
            'asset_key' => 'hero_background',
            'agency_media_id' => $media->id,
        ])->assertStatus(422);
    }

    public function test_rbac_blocks_unauthorized_page_settings_mutations(): void
    {
        $this->makeJetpkProfile();
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => null,
        ]);

        $this->actingAs($customer)->getJson('/admin/page-settings/catalog?format=json')->assertForbidden();
        $this->actingAs($customer)->getJson('/admin/page-settings/about?format=json')->assertForbidden();
        $this->actingAs($customer)->patchJson('/admin/page-settings/about?format=json', [
            'content' => ['hero' => ['title' => 'x']],
        ])->assertForbidden();
        $this->actingAs($customer)->postJson('/admin/page-settings/about/publish?format=json')->assertForbidden();
        $this->actingAs($customer)->postJson('/admin/page-settings/about/duplicate?format=json')->assertForbidden();
        $this->actingAs($customer)->postJson('/admin/page-settings/about/unpublish?format=json')->assertForbidden();
        $this->actingAs($customer)->postJson('/admin/page-settings/about/assets/attach?format=json', [
            'asset_key' => 'hero_background',
            'agency_media_id' => 1,
        ])->assertForbidden();
    }

    public function test_cms_page_script_content_is_sanitized_on_store(): void
    {
        $this->makeJetpkProfile();
        $admin = $this->platformAdmin();

        $create = $this->actingAs($admin)->postJson('/admin/cms-pages?format=json', [
            'title' => 'XSS Probe',
            'slug' => 'xss-probe-page',
            'content' => '<section data-jp-block="paragraph"><p onclick="alert(1)">Hi</p><script>alert(1)</script></section>',
            'robots' => CmsPage::ROBOTS_NOINDEX,
            'status' => CmsPage::STATUS_DRAFT,
        ]);
        $create->assertOk()->assertJsonPath('ok', true);

        $page = CmsPage::query()->where('slug', 'xss-probe-page')->firstOrFail();
        $this->assertStringNotContainsString('<script', (string) $page->content);
        $this->assertStringNotContainsString('onclick=', (string) $page->content);
    }

    public function test_cms_page_duplicate_and_archive_remain_safe(): void
    {
        $this->makeJetpkProfile();
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->postJson('/admin/cms-pages?format=json', [
            'title' => 'Archive Source',
            'slug' => 'archive-source-page',
            'content' => '<section data-jp-block="heading"><h2>Keep</h2></section>',
            'robots' => CmsPage::ROBOTS_NOINDEX,
            'status' => CmsPage::STATUS_ACTIVE,
        ])->assertOk();

        $page = CmsPage::query()->where('slug', 'archive-source-page')->firstOrFail();
        $this->actingAs($admin)->postJson('/admin/cms-pages/'.$page->id.'/duplicate?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $copy = CmsPage::query()->where('slug', 'archive-source-page-copy')->firstOrFail();
        $this->assertSame(CmsPage::STATUS_DRAFT, $copy->status);

        $this->actingAs($admin)->patchJson('/admin/cms-pages/'.$page->id.'/archive?format=json')
            ->assertOk();
        $this->assertSame(CmsPage::STATUS_ARCHIVED, $page->fresh()->status);
        $this->assertNotNull(CmsPage::query()->find($page->id));
    }

    private function platformAdmin(): User
    {
        return User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);
    }
}
