<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Models\CmsPage;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsPagesJsonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_cms_pages_index_json_lists_pages(): void
    {
        [$admin] = $this->platformAdmin();
        CmsPage::query()->create([
            'title' => 'QA List Page',
            'slug' => 'qa-list-page-'.substr(sha1((string) microtime(true)), 0, 6),
            'content' => '<p>List body</p>',
            'status' => CmsPage::STATUS_DRAFT,
            'robots' => CmsPage::ROBOTS_INDEX,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->getJson('/admin/cms-pages?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['pages' => [['id', 'internalId', 'title', 'slug', 'status']]]);
    }

    public function test_cms_page_create_update_publish_round_trip(): void
    {
        [$admin] = $this->platformAdmin();
        $slug = 'qa-cms-'.substr(sha1((string) microtime(true)), 0, 8);

        $create = $this->actingAs($admin)->postJson('/admin/cms-pages?format=json', [
            'title' => 'QA CMS Draft',
            'slug' => $slug,
            'content' => '<p>Original body</p>',
            'status' => CmsPage::STATUS_DRAFT,
            'robots' => CmsPage::ROBOTS_INDEX,
        ])->assertOk()->assertJsonPath('ok', true);

        $pageId = (string) data_get($create->json(), 'page.internalId');
        $this->assertNotSame('', $pageId);

        $this->actingAs($admin)->patchJson('/admin/cms-pages/'.$pageId.'?format=json', [
            'title' => 'QA CMS Published',
            'slug' => $slug,
            'content' => '<p>Updated body</p>',
            'status' => CmsPage::STATUS_ACTIVE,
            'robots' => CmsPage::ROBOTS_INDEX,
            'seo_title' => 'QA SEO',
            'seo_description' => 'QA description',
        ])->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('page.status', 'published')
            ->assertJsonPath('page.title', 'QA CMS Published');

        $this->actingAs($admin)->getJson('/admin/cms-pages/'.$pageId.'/edit?format=json')
            ->assertOk()
            ->assertJsonPath('page.title', 'QA CMS Published')
            ->assertJsonPath('page.content', 'Updated body');

        $this->actingAs($admin)->patchJson('/admin/cms-pages/'.$pageId.'/archive?format=json')
            ->assertOk()
            ->assertJsonPath('page.status', 'archived');

        $this->actingAs($admin)->deleteJson('/admin/cms-pages/'.$pageId.'?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSoftDeleted('cms_pages', ['id' => (int) $pageId]);
    }

    public function test_cms_pages_nav_targets_next(): void
    {
        [$admin] = $this->platformAdmin();
        $payload = app(\App\Support\BackOffice\BackOfficeCapabilitiesPresenter::class)->present($admin, 'admin');
        $item = collect($payload['navigation'] ?? [])->firstWhere('key', 'cms-pages');
        $managed = collect($payload['navigation'] ?? [])->firstWhere('key', 'page-settings');

        $this->assertNotNull($item);
        $this->assertSame('/cms/pages', $item['href']);
        $this->assertSame('dashboard', $item['target'] ?? null);
        $this->assertNotNull($managed);
        $this->assertSame('/cms/sections', $managed['href']);
        $this->assertSame('dashboard', $managed['target'] ?? null);
    }

    /**
     * @return array{0: User}
     */
    protected function platformAdmin(): array
    {
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        if ($admin->account_type !== AccountType::PlatformAdmin) {
            $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();
            $admin = $admin->fresh();
        }

        return [$admin];
    }
}
