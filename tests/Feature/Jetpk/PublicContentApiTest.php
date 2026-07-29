<?php

namespace Tests\Feature\Jetpk;

use App\Enums\ClientPageSettingStatus;
use App\Enums\SupportTicketCategory;
use App\Models\ClientPageSetting;
use App\Models\CmsPage;
use App\Models\SupportTicket;
use App\Support\Client\ClientPageKeys;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class PublicContentApiTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['client_route_parity.enabled' => false]);
        $this->seedJetpkAgency();
    }

    public function test_managed_page_json_returns_published_cms_content(): void
    {
        $profile = $this->makeJetpkProfile();
        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::ABOUT,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => [
                'hero' => ['title' => 'CMS About Title'],
            ],
            'published_at' => now(),
        ]);

        $response = $this->getJson(route('api.public.content.managed-page', ['pageKey' => 'about']));

        $response->assertOk()
            ->assertJsonPath('page_key', 'about')
            ->assertJsonPath('source', 'cms')
            ->assertJsonPath('content.hero.title', 'CMS About Title');
    }

    public function test_managed_page_json_returns_empty_source_without_published_content(): void
    {
        $this->makeJetpkProfile();

        $response = $this->getJson(route('api.public.content.managed-page', ['pageKey' => 'faq']));

        $response->assertOk()
            ->assertJsonPath('source', 'empty')
            ->assertJsonPath('content', []);
    }

    public function test_site_contact_json_uses_global_contact_resolver(): void
    {
        $this->makeJetpkProfile();

        $response = $this->getJson(route('api.public.content.site-contact'));

        $response->assertOk()
            ->assertJsonPath('source', 'laravel')
            ->assertJsonStructure(['contact' => ['phone', 'email', 'whatsapp', 'office', 'hours']]);
    }

    public function test_cms_page_json_requires_active_status(): void
    {
        CmsPage::query()->create([
            'title' => 'Travel Info',
            'slug' => 'travel-info',
            'content' => '<p>Safe content</p>',
            'status' => CmsPage::STATUS_DRAFT,
        ]);

        $this->getJson(route('api.public.content.cms-page', ['slug' => 'travel-info']))
            ->assertNotFound();
    }

    public function test_cms_page_json_returns_sanitized_content(): void
    {
        CmsPage::query()->create([
            'title' => 'Travel Info',
            'slug' => 'travel-info',
            'content' => '<p>Hello</p><script>alert(1)</script>',
            'status' => CmsPage::STATUS_ACTIVE,
        ]);

        $this->getJson(route('api.public.content.cms-page', ['slug' => 'travel-info']))
            ->assertOk()
            ->assertJsonPath('title', 'Travel Info')
            ->assertJsonMissing(['<script>']);
    }

    public function test_support_store_accepts_json_contact_submission(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        config([
            'services.turnstile.enabled' => false,
            'services.turnstile.site_key' => null,
            'services.turnstile.secret_key' => null,
        ]);

        $response = $this->postJson(route('support.store'), [
            'form_type' => 'contact',
            'name' => 'Contact Guest',
            'email' => 'contact@example.com',
            'body' => 'I would like more information about group fares.',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['ticket_reference']);

        $this->assertSame(1, SupportTicket::query()->count());
    }

    public function test_support_store_json_validation_errors_for_contact_form(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        config([
            'services.turnstile.enabled' => false,
            'services.turnstile.site_key' => null,
            'services.turnstile.secret_key' => null,
        ]);

        $this->postJson(route('support.store'), [
            'form_type' => 'contact',
            'name' => 'Contact Guest',
            'email' => 'not-an-email',
            'body' => '',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'body']);
    }

    public function test_support_categories_json_lists_enum_values(): void
    {
        $this->getJson(route('api.public.content.support-categories'))
            ->assertOk()
            ->assertJsonFragment([
                'value' => SupportTicketCategory::Booking->value,
                'label' => SupportTicketCategory::Booking->label(),
            ]);
    }

    public function test_public_config_json_returns_brand_and_contact(): void
    {
        $this->makeJetpkProfile();

        $this->getJson(route('api.public.content.config'))
            ->assertOk()
            ->assertJsonPath('source', 'laravel')
            ->assertJsonStructure([
                'brand_name',
                'domain',
                'contact' => ['phone', 'email'],
                'legal_paths' => ['terms', 'privacy'],
            ]);
    }

    public function test_sitemap_routes_json_lists_core_public_paths(): void
    {
        $this->makeJetpkProfile();

        $this->getJson(route('api.public.content.sitemap-routes'))
            ->assertOk()
            ->assertJsonPath('source', 'laravel')
            ->assertJsonFragment(['path' => '/about-us'])
            ->assertJsonFragment(['path' => '/contact']);
    }

    public function test_sitemap_xml_route_returns_valid_xml(): void
    {
        $this->makeJetpkProfile();

        $response = $this->get(route('sitemap'));
        $response->assertOk();
        $this->assertStringContainsString('<urlset', $response->getContent());
        $this->assertStringContainsString('/about-us', $response->getContent());
    }

    public function test_homepage_json_returns_empty_source_without_published_content(): void
    {
        $this->makeJetpkProfile();

        $this->getJson(route('api.public.content.homepage'))
            ->assertOk()
            ->assertJsonPath('source', 'empty')
            ->assertJsonPath('routes.enabled', false)
            ->assertJsonPath('destinations.enabled', false);
    }

    public function test_homepage_json_returns_published_sections_only(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, $this->representativeThreeCardHomeContent());

        $this->getJson(route('api.public.content.homepage'))
            ->assertOk()
            ->assertJsonPath('source', 'cms')
            ->assertJsonPath('hero.headline', 'Custom headline preserved')
            ->assertJsonPath('routes.enabled', true)
            ->assertJsonPath('why_book.enabled', false)
            ->assertJsonMissing(['fixture', 'sample']);
    }
}
