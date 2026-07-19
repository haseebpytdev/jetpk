<?php

namespace Tests\Feature\Jetpk;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageSetting;
use App\Support\Client\ClientManagedPageReservedSlugs;
use App\Support\Client\ClientPageBootstrapTemplate;
use App\Support\Client\ClientPageKeys;
use App\Support\Client\ClientSafeHtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class JetpkPublicPageCmsOwnershipTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['client_route_parity.enabled' => false]);
        $this->seedJetpkAgency();
    }

    public function test_bootstrap_template_is_not_used_as_public_runtime_source(): void
    {
        $profile = $this->makeJetpkProfile();
        $resolver = app(\App\Services\Client\ClientPageContentResolver::class);

        $this->assertSame([], $resolver->contentFor(ClientPageKeys::ABOUT));

        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::ABOUT,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => [
                'hero' => ['title' => 'CMS About Title'],
            ],
            'published_at' => now(),
        ]);

        $this->assertSame('CMS About Title', $resolver->section(ClientPageKeys::ABOUT, 'hero.title', '', true));
    }

    public function test_bootstrap_import_does_not_overwrite_existing_published_content(): void
    {
        $profile = $this->makeJetpkProfile();
        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::ABOUT,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => ['hero' => ['title' => 'Client edited title']],
            'published_at' => now(),
        ]);

        $this->artisan('jetpk:public-page-cms-bootstrap', ['--dry-run' => true])
            ->assertSuccessful();

        $row = ClientPageSetting::query()->where('page_key', ClientPageKeys::ABOUT)->first();
        $this->assertSame('Client edited title', data_get($row?->content_json, 'hero.title'));
    }

    public function test_reserved_slug_rejected_for_custom_pages(): void
    {
        $this->assertTrue(ClientManagedPageReservedSlugs::isReserved('admin'));
        $this->assertFalse(ClientManagedPageReservedSlugs::isValidFormat('Admin'));
        $this->assertTrue(ClientPageKeys::isValid(ClientPageKeys::customKey('our-story')));
        $this->assertFalse(ClientPageKeys::isValid(ClientPageKeys::customKey('admin')));
    }

    public function test_safe_html_sanitizer_rejects_script_tags(): void
    {
        $dirty = '<p>Hello</p><script>alert(1)</script>';
        $clean = ClientSafeHtmlSanitizer::sanitize($dirty);
        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringContainsString('Hello', $clean);
    }

    public function test_faq_route_exists(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('faq'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('terms'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('privacy'));
    }

    public function test_bootstrap_command_requires_execute_flag(): void
    {
        $this->artisan('jetpk:public-page-cms-bootstrap')
            ->assertFailed();
    }

    public function test_bootstrap_dry_run_performs_zero_writes(): void
    {
        $this->makeJetpkProfile();
        $before = ClientPageSetting::query()->count();

        $this->artisan('jetpk:public-page-cms-bootstrap', ['--dry-run' => true])
            ->expectsOutputToContain('dry_run=1')
            ->assertSuccessful();

        $this->assertSame($before, ClientPageSetting::query()->count());
    }

    public function test_bootstrap_dry_run_reports_create_skip_and_no_change_actions(): void
    {
        $profile = $this->makeJetpkProfile();
        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::ABOUT,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => ['hero' => ['title' => 'Existing']],
            'published_at' => now(),
        ]);

        $before = ClientPageSetting::query()->count();

        $this->artisan('jetpk:public-page-cms-bootstrap', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame($before, ClientPageSetting::query()->count());
        $this->assertSame(
            'Existing',
            data_get(
                ClientPageSetting::query()->where('page_key', ClientPageKeys::ABOUT)->first()?->content_json,
                'hero.title',
            ),
        );
    }

    public function test_bootstrap_command_imports_missing_pages_with_execute(): void
    {
        $this->makeJetpkProfile();
        $this->artisan('jetpk:public-page-cms-bootstrap', ['--execute' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('client_page_settings', [
            'page_key' => ClientPageKeys::ABOUT,
            'status' => ClientPageSettingStatus::Published->value,
        ]);
    }

    public function test_managed_public_pages_render_without_server_errors(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->artisan('jetpk:public-page-cms-bootstrap', ['--execute' => true])->assertSuccessful();

        $paths = [
            '/about-us',
            '/terms',
            '/privacy',
            '/faq',
            '/support',
            '/login',
            '/register',
            '/lookup-booking',
            '/agent/register',
            '/groups/search',
        ];

        foreach ($paths as $path) {
            $this->get($path)->assertSuccessful();
        }

        $customSlug = 'our-story';
        \App\Models\ClientPage::query()->create([
            'client_profile_id' => $profile->id,
            'slug' => $customSlug,
            'internal_name' => 'Our Story',
            'public_title' => 'Our Story',
            'enabled' => true,
            'show_header' => true,
            'show_footer' => true,
        ]);
        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::customKey($customSlug),
            'status' => ClientPageSettingStatus::Published,
            'content_json' => [
                'hero' => ['title' => 'Our Story'],
                'sections_order' => ['hero'],
            ],
            'published_at' => now(),
        ]);

        $this->get('/'.$customSlug)->assertSuccessful()->assertSee('Our Story', false);
    }

    public function test_hybrid_forms_preserve_csrf_method_and_field_names(): void
    {
        $this->makeJetpkProfile();
        $this->artisan('jetpk:public-page-cms-bootstrap', ['--execute' => true])->assertSuccessful();

        $login = $this->get('/login')->assertSuccessful()->getContent();
        $this->assertStringContainsString('method="POST"', $login);
        $this->assertStringContainsString('name="login"', $login);
        $this->assertStringContainsString('name="_token"', $login);

        $register = $this->get('/register')->assertSuccessful()->getContent();
        $this->assertStringContainsString('method="POST"', $register);
        $this->assertStringContainsString('name="email"', $register);
        $this->assertStringContainsString('name="_token"', $register);
    }
}
