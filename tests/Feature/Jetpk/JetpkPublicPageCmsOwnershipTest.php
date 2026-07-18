<?php

namespace Tests\Feature\Jetpk;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageSetting;
use App\Models\ClientProfile;
use App\Support\Client\ClientManagedPageReservedSlugs;
use App\Support\Client\ClientPageBootstrapTemplate;
use App\Support\Client\ClientPageKeys;
use App\Support\Client\ClientSafeHtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JetpkPublicPageCmsOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_template_is_not_used_as_public_runtime_source(): void
    {
        $profile = $this->jetpkProfile();
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
        $profile = $this->jetpkProfile();
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

  public function test_bootstrap_command_imports_missing_pages_with_execute(): void
    {
        $this->jetpkProfile();
        $this->artisan('jetpk:public-page-cms-bootstrap', ['--execute' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('client_page_settings', [
            'page_key' => ClientPageKeys::ABOUT,
            'status' => ClientPageSettingStatus::Published->value,
        ]);
    }

    private function jetpkProfile(): ClientProfile
    {
        return ClientProfile::query()->create([
            'slug' => 'jetpk',
            'name' => 'JetPakistan',
            'asset_profile' => 'jetpk-assets',
            'is_master_profile' => false,
            'is_active' => true,
        ]);
    }
}
