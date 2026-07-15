<?php

namespace Tests\Feature;

use App\Models\CmsPage;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class CmsPageTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_active_page_is_visible_at_public_slug_route(): void
    {
        $page = $this->createPage([
            'slug' => 'refund-policy',
            'title' => 'Refund Policy',
            'status' => CmsPage::STATUS_ACTIVE,
            'content' => '<p>Refund terms apply.</p>',
        ]);

        $this->get(route('pages.show', $page->slug))
            ->assertOk()
            ->assertSee('Refund Policy', false)
            ->assertSee('Refund terms apply.', false);
    }

    public function test_draft_page_returns_404_publicly(): void
    {
        $page = $this->createPage([
            'slug' => 'draft-policy',
            'status' => CmsPage::STATUS_DRAFT,
        ]);

        $this->get(route('pages.show', $page->slug))->assertNotFound();
    }

    public function test_archived_page_returns_404_publicly(): void
    {
        $page = $this->createPage([
            'slug' => 'archived-policy',
            'status' => CmsPage::STATUS_ARCHIVED,
        ]);

        $this->get(route('pages.show', $page->slug))->assertNotFound();
    }

    public function test_admin_preview_renders_draft_page(): void
    {
        $admin = $this->platformAdmin();
        $page = $this->createPage([
            'slug' => 'privacy-policy',
            'title' => 'Privacy Policy',
            'status' => CmsPage::STATUS_DRAFT,
            'content' => '<p>Draft privacy copy.</p>',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.cms-pages.preview', $page))
            ->assertOk()
            ->assertSee('Preview mode', false)
            ->assertSee('Privacy Policy', false)
            ->assertSee('Draft privacy copy.', false);
    }

    public function test_slug_uniqueness_validation_on_create(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();

        $this->createPage(['slug' => 'terms-and-conditions']);

        $this->actingAs($admin)
            ->post(route('admin.cms-pages.store'), [
                'title' => 'Duplicate Terms',
                'slug' => 'terms-and-conditions',
                'robots' => CmsPage::ROBOTS_INDEX,
                'status' => CmsPage::STATUS_DRAFT,
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_noindex_meta_appears_when_robots_noindex(): void
    {
        $page = $this->createPage([
            'slug' => 'payment-policy',
            'title' => 'Payment Policy',
            'status' => CmsPage::STATUS_ACTIVE,
            'robots' => CmsPage::ROBOTS_NOINDEX,
            'seo_description' => 'Payment policy summary.',
        ]);

        $this->get(route('pages.show', $page->slug))
            ->assertOk()
            ->assertSee('name="robots" content="noindex, nofollow"', false)
            ->assertSee('Payment policy summary.', false);
    }

    public function test_active_footer_cms_page_appears_on_homepage_footer(): void
    {
        $page = $this->createPage([
            'slug' => 'footer-terms',
            'title' => 'Terms of Service',
            'status' => CmsPage::STATUS_ACTIVE,
            'show_in_footer' => true,
            'footer_group' => 'policies',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Terms of Service', false)
            ->assertSee(route('pages.show', $page->slug), false);
    }

    public function test_draft_footer_cms_page_does_not_appear_on_homepage_footer(): void
    {
        $this->createPage([
            'slug' => 'footer-draft-policy',
            'title' => 'Draft Footer Policy',
            'status' => CmsPage::STATUS_DRAFT,
            'show_in_footer' => true,
            'footer_group' => 'policies',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Draft Footer Policy', false);
    }

    public function test_active_cms_page_with_show_in_footer_false_does_not_appear_on_homepage_footer(): void
    {
        $this->createPage([
            'slug' => 'hidden-footer-page',
            'title' => 'Hidden Footer Page',
            'status' => CmsPage::STATUS_ACTIVE,
            'show_in_footer' => false,
            'footer_group' => 'policies',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Hidden Footer Page', false);
    }

    public function test_footer_label_is_used_when_set(): void
    {
        $this->createPage([
            'slug' => 'footer-label-page',
            'title' => 'Internal Title',
            'footer_label' => 'Public Footer Label',
            'status' => CmsPage::STATUS_ACTIVE,
            'show_in_footer' => true,
            'footer_group' => 'company',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Public Footer Label', false)
            ->assertDontSee('Internal Title', false);
    }

    public function test_open_in_new_tab_adds_blank_target_and_noopener_on_homepage_footer(): void
    {
        $page = $this->createPage([
            'slug' => 'footer-external-policy',
            'title' => 'External Policy Link',
            'status' => CmsPage::STATUS_ACTIVE,
            'show_in_footer' => true,
            'footer_group' => 'policies',
            'open_in_new_tab' => true,
        ]);

        $html = $this->get(route('home'))->assertOk()->getContent();
        $url = e(route('pages.show', $page->slug));

        $this->assertMatchesRegularExpression(
            '/<a[^>]+href="'.preg_quote($url, '/').'"[^>]*target="_blank"[^>]*rel="noopener noreferrer"[^>]*>/',
            $html
        );
    }

    public function test_soft_deleted_page_returns_404_publicly(): void
    {
        $page = $this->createPage([
            'slug' => 'deleted-policy',
            'status' => CmsPage::STATUS_ACTIVE,
        ]);

        $page->delete();

        $this->get(route('pages.show', $page->slug))->assertNotFound();
    }

    public function test_archived_footer_cms_page_does_not_appear_on_homepage_footer(): void
    {
        $this->createPage([
            'slug' => 'footer-archived-policy',
            'title' => 'Archived Footer Policy',
            'status' => CmsPage::STATUS_ARCHIVED,
            'show_in_footer' => true,
            'footer_group' => 'policies',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Archived Footer Policy', false);
    }

    public function test_index_meta_appears_when_robots_index(): void
    {
        $page = $this->createPage([
            'slug' => 'indexed-policy',
            'status' => CmsPage::STATUS_ACTIVE,
            'robots' => CmsPage::ROBOTS_INDEX,
        ]);

        $this->get(route('pages.show', $page->slug))
            ->assertOk()
            ->assertSee('name="robots" content="index, follow"', false);
    }

    public function test_seo_fallbacks_use_title_excerpt_and_current_url(): void
    {
        $page = $this->createPage([
            'slug' => 'seo-fallback-page',
            'title' => 'Fallback Title',
            'excerpt' => 'Fallback excerpt for meta.',
            'status' => CmsPage::STATUS_ACTIVE,
            'robots' => CmsPage::ROBOTS_INDEX,
        ]);

        $this->get(route('pages.show', $page->slug))
            ->assertOk()
            ->assertSee('Fallback Title', false)
            ->assertSee('Fallback excerpt for meta.', false)
            ->assertSee('<link rel="canonical" href="'.route('pages.show', $page->slug).'"', false);
    }

    public function test_featured_image_url_helper_and_public_render(): void
    {
        Storage::fake('public');

        $page = $this->createPage([
            'slug' => 'featured-image-page',
            'title' => 'Featured Image Page',
            'status' => CmsPage::STATUS_ACTIVE,
        ]);

        $path = 'cms-pages/'.$page->id.'/hero.jpg';
        Storage::disk('public')->put($path, 'fake-image-bytes');
        $page->update(['featured_image_path' => $path]);

        $this->assertSame(asset('storage/'.$path), $page->fresh()->featuredImageUrl());

        $this->get(route('pages.show', $page->slug))
            ->assertOk()
            ->assertSee(asset('storage/'.$path), false);
    }

    public function test_homepage_footer_renders_when_cms_pages_table_is_empty(): void
    {
        $this->assertSame(0, CmsPage::query()->count());

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('About us', false);
    }

    public function test_hardcoded_footer_links_preserved_when_cms_links_merged(): void
    {
        $this->createPage([
            'slug' => 'company-cms-page',
            'title' => 'Company CMS Link',
            'status' => CmsPage::STATUS_ACTIVE,
            'show_in_footer' => true,
            'footer_group' => 'company',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('About us', false)
            ->assertSee('Company CMS Link', false);
    }

    public function test_guest_redirected_from_cms_admin_index(): void
    {
        $this->get(route('admin.cms-pages.index'))->assertRedirect(route('login'));
    }

    public function test_non_platform_users_cannot_access_cms_admin(): void
    {
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $customer = User::query()->where('email', 'customer@ota.demo')->firstOrFail();
        $agentStaff = User::factory()->agentStaff()->create([
            'current_agency_id' => $staff->current_agency_id,
        ]);
        $agencyAdmin = $this->legacyAgencyAdminFromSeed();

        $this->actingAs($staff)->get(route('admin.cms-pages.index'))->assertForbidden();
        $this->actingAs($agent)->get(route('admin.cms-pages.index'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.cms-pages.index'))->assertForbidden();
        $this->actingAs($agentStaff)->get(route('admin.cms-pages.index'))->assertForbidden();
        $this->actingAs($agencyAdmin)->get(route('admin.cms-pages.index'))->assertForbidden();
    }

    public function test_platform_admin_can_access_cms_admin_index_and_create_form(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->get(route('admin.cms-pages.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.cms-pages.create'))->assertOk();
    }

    public function test_platform_admin_can_preview_draft_via_admin_preview_route(): void
    {
        $admin = $this->platformAdmin();
        $page = $this->createPage([
            'slug' => 'admin-preview-draft',
            'status' => CmsPage::STATUS_DRAFT,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.cms-pages.preview', $page))
            ->assertOk()
            ->assertSee('Preview mode', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createPage(array $overrides = []): CmsPage
    {
        return CmsPage::query()->create(array_merge([
            'title' => 'Test Page',
            'slug' => 'test-page-'.uniqid(),
            'content' => null,
            'robots' => CmsPage::ROBOTS_INDEX,
            'status' => CmsPage::STATUS_DRAFT,
            'show_in_footer' => false,
            'footer_sort_order' => 0,
            'open_in_new_tab' => false,
        ], $overrides));
    }
}
