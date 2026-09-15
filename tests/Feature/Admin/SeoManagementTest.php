<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountType;
use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageSetting;
use App\Models\CmsPage;
use App\Models\User;
use App\Services\Client\ClientPageContentResolver;
use App\Services\Client\ClientPageSeoResolver;
use App\Services\PublicContent\PublicContentApiPresenter;
use App\Services\Seo\SeoManagementService;
use App\Services\Seo\SeoVerificationResolver;
use App\Support\Client\ClientPageKeys;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class SeoManagementTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['client_route_parity.enabled' => false]);
        $this->seedJetpkAgency();
    }

    public function test_overview_requires_authentication(): void
    {
        $this->makeJetpkProfile();

        $this->get('/admin/seo')->assertRedirect('/login');
    }

    public function test_platform_admin_can_access_all_seo_screens(): void
    {
        $profile = $this->makeJetpkProfile();
        $admin = User::factory()->create(['account_type' => AccountType::PlatformAdmin]);

        $this->actingAs($admin)
            ->get('/admin/seo')
            ->assertOk()
            ->assertSee('SEO Management');

        foreach ([
            '/admin/seo/pages',
            '/admin/seo/global',
            '/admin/seo/social',
            '/admin/seo/schema',
            '/admin/seo/sitemap',
            '/admin/seo/verification',
            '/admin/seo/audit',
        ] as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }
    }

    public function test_customer_agent_and_unauthorized_staff_are_denied(): void
    {
        $this->makeJetpkProfile();

        $customer = User::factory()->create(['account_type' => AccountType::Customer]);
        $agent = User::factory()->create(['account_type' => AccountType::Agent]);
        $staff = User::factory()->create(['account_type' => AccountType::Staff]);

        $this->actingAs($customer)->get('/admin/seo')->assertForbidden();
        $this->actingAs($agent)->get('/admin/seo')->assertForbidden();
        $this->actingAs($staff)->get('/admin/seo')->assertForbidden();
    }

    public function test_managed_draft_save_does_not_change_public_seo_until_publish(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $profile = $this->makeJetpkProfile();
        $admin = User::factory()->create(['account_type' => AccountType::PlatformAdmin]);

        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::ABOUT,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => [
                'seo' => ['title' => 'Published About Title', 'description' => 'Published about description.'],
            ],
            'published_at' => now(),
        ]);

        $this->actingAs($admin)->patch('/admin/seo/pages/managed/about', [
            'title' => 'Draft About Title',
            'description' => 'Draft about description that is definitely longer than fifty chars.',
            'index' => '1',
            'follow' => '1',
        ])->assertRedirect();

        $live = app(ClientPageSeoResolver::class)->forPage(ClientPageKeys::ABOUT, 'Fallback', 'Fallback description.');
        $this->assertSame('Published About Title', $live['title']);

        $this->actingAs($admin)->post('/admin/seo/pages/managed/about/publish')->assertRedirect();

        $live = app(ClientPageSeoResolver::class)->forPage(ClientPageKeys::ABOUT, 'Fallback', 'Fallback description.');
        $this->assertSame('Draft About Title', $live['title']);
    }

    public function test_global_fallback_precedence_and_page_override(): void
    {
        $profile = $this->makeJetpkProfile();

        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::GLOBAL,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => [
                'seo' => [
                    'title' => 'Global Default Title',
                    'description' => 'Global default description for fallback testing.',
                ],
            ],
            'published_at' => now(),
        ]);

        $fallback = app(ClientPageSeoResolver::class)->forPage(
            ClientPageKeys::FAQ,
            'Application FAQ Fallback',
            'Application FAQ description fallback.',
        );
        $this->assertSame('Global Default Title', $fallback['title']);

        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::FAQ,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => [
                'seo' => ['title' => 'FAQ Page Override', 'description' => 'FAQ explicit description.'],
            ],
            'published_at' => now(),
        ]);

        $override = app(ClientPageSeoResolver::class)->forPage(
            ClientPageKeys::FAQ,
            'Application FAQ Fallback',
            'Application FAQ description fallback.',
        );
        $this->assertSame('FAQ Page Override', $override['title']);
    }

    public function test_cms_seo_update_persists_authoritative_fields(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->makeJetpkProfile();
        $admin = User::factory()->create(['account_type' => AccountType::PlatformAdmin]);
        $cmsPage = CmsPage::query()->create([
            'title' => 'Travel Tips',
            'slug' => 'travel-tips',
            'content' => '<p>Tips</p>',
            'status' => CmsPage::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin)->patch('/admin/seo/pages/cms/'.$cmsPage->id, [
            'title' => 'CMS SEO Title',
            'description' => 'CMS SEO description with enough length for validation checks here.',
            'canonical' => '/pages/travel-tips',
            'index' => '1',
            'follow' => '1',
        ])->assertRedirect();

        $cmsPage->refresh();
        $this->assertSame('CMS SEO Title', $cmsPage->seo_title);
        $this->assertSame('CMS SEO description with enough length for validation checks here.', $cmsPage->seo_description);

        $api = app(PublicContentApiPresenter::class)->cmsPage($cmsPage);
        $this->assertSame('CMS SEO Title', $api['seo']['title']);
    }

    public function test_canonical_validation_rejects_external_host(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->makeJetpkProfile();
        $admin = User::factory()->create(['account_type' => AccountType::PlatformAdmin]);

        $this->actingAs($admin)->patch('/admin/seo/pages/managed/about', [
            'title' => 'About',
            'description' => 'About description with enough length for the validation rules here.',
            'canonical' => 'https://evil.example/about-us',
            'index' => '1',
            'follow' => '1',
        ])->assertSessionHasErrors('canonical');
    }

    public function test_verification_resolver_uses_published_cms_before_env(): void
    {
        $profile = $this->makeJetpkProfile();
        config(['services.google.site_verification' => 'env-google-token']);

        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::GLOBAL,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => ['verification' => ['google' => 'cms-google-token']],
            'published_at' => now(),
        ]);

        $this->assertSame('cms-google-token', app(SeoVerificationResolver::class)->googleToken());
    }

    public function test_public_config_exposes_verification_tokens(): void
    {
        $profile = $this->makeJetpkProfile();
        config(['services.google.site_verification' => 'env-google-token']);

        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::GLOBAL,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => ['verification' => ['google' => 'cms-google-token']],
            'published_at' => now(),
        ]);

        $this->getJson(route('api.public.content.config'))
            ->assertOk()
            ->assertJsonPath('site_verification.google', 'cms-google-token');
    }

    public function test_sitemap_safety_rules_remain_intact(): void
    {
        $this->makeJetpkProfile();
        $paths = collect(app(PublicContentApiPresenter::class)->sitemapRoutes())->pluck('path')->all();

        $this->assertNotContains('/contact', $paths);
        $this->assertNotContains('/flights', $paths);
        $this->assertNotContains('/lookup-booking', $paths);
        $this->assertNotContains('/groups/search', $paths);
    }

    public function test_audit_lists_actionable_findings_with_severity(): void
    {
        $this->makeJetpkProfile();
        $findings = app(\App\Services\Seo\SeoAuditService::class)->runAudit();

        $this->assertNotEmpty($findings);
        $this->assertTrue(collect($findings)->every(fn (array $row): bool => isset($row['severity'], $row['message'])));
        $this->assertTrue(collect($findings)->contains(fn (array $row): bool => in_array($row['severity'], ['good', 'info', 'warning', 'error'], true)));
    }

    public function test_noindex_managed_page_is_excluded_from_live_sitemap(): void
    {
        $profile = $this->makeJetpkProfile();

        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::FAQ,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => [
                'seo' => ['title' => 'FAQ', 'description' => 'FAQ description.', 'robots' => 'noindex,follow'],
            ],
            'published_at' => now(),
        ]);

        $paths = collect(app(PublicContentApiPresenter::class)->sitemapRoutes())->pluck('path')->all();
        $this->assertNotContains('/faq', $paths);
    }

    public function test_overview_lists_managed_pages(): void
    {
        $this->makeJetpkProfile();
        $pages = app(SeoManagementService::class)->listPages();

        $this->assertTrue(collect($pages)->contains(fn (array $p): bool => ($p['page_key'] ?? '') === ClientPageKeys::ABOUT));
    }
}
