<?php

namespace Tests\Feature\Jetpk;

use App\Enums\AccountType;
use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageSetting;
use App\Models\ClientProfile;
use App\Models\ClientProfileModule;
use App\Models\User;
use App\Services\Client\ClientPageAdminContentResolver;
use App\Services\Client\CurrentClientContext;
use App\Support\Client\ClientPageKeys;
use App\Support\Client\ClientProfileConfigReader;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class JetpkPageSettingsParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('ota-developer.enabled', true);
        Config::set('client_route_parity.enabled', false);
    }

    public function test_about_edit_loads_public_fallback_when_no_rows_exist(): void
    {
        $profile = $this->makeJetpkProfile();
        app(CurrentClientContext::class)->set($profile);
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/admin/page-settings/about?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('editorMeta.form_source', ClientPageAdminContentResolver::SOURCE_PUBLIC_FALLBACK);

        $content = $response->json('content');
        $this->assertIsArray($content);
        $this->assertSame(
            'Cheap flights and secure online booking for Pakistan',
            data_get($content, 'hero.title'),
        );
        $this->assertSame('ota@jetpakistan.pk', data_get($content, 'contact.email'));
    }

    public function test_support_edit_loads_public_fallback_contact_details(): void
    {
        $profile = $this->makeJetpkProfile();
        app(CurrentClientContext::class)->set($profile);
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/admin/page-settings/support?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('editorMeta.form_source', ClientPageAdminContentResolver::SOURCE_PUBLIC_FALLBACK);

        $content = $response->json('content');
        $this->assertSame('Flight booking help, 24/7', data_get($content, 'hero.title'));
        $this->assertSame('0311 1222427', data_get($content, 'contact.phone'));
        $this->assertSame('923111222427', data_get($content, 'contact.whatsapp'));
    }

    public function test_published_content_loads_when_no_draft_exists(): void
    {
        $profile = $this->makeJetpkProfile();
        app(CurrentClientContext::class)->set($profile);

        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::ABOUT,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => [
                'hero' => [
                    'kicker' => 'Published kicker',
                    'title' => 'Published about title',
                    'description' => 'Published about description',
                ],
            ],
            'published_at' => now(),
        ]);

        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $this->actingAs($admin)
            ->getJson('/admin/page-settings/about?format=json')
            ->assertOk()
            ->assertJsonPath('editorMeta.form_source', ClientPageAdminContentResolver::SOURCE_PUBLISHED)
            ->assertJsonPath('content.hero.title', 'Published about title');
    }

    public function test_draft_takes_precedence_over_published_in_admin_form(): void
    {
        $profile = $this->makeJetpkProfile();
        app(CurrentClientContext::class)->set($profile);

        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::SUPPORT,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => ['hero' => ['title' => 'Published support title']],
            'published_at' => now(),
        ]);

        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::SUPPORT,
            'status' => ClientPageSettingStatus::Draft,
            'content_json' => ['hero' => ['title' => 'Draft support title']],
        ]);

        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/admin/page-settings/support?format=json')
            ->assertOk()
            ->assertJsonPath('editorMeta.form_source', ClientPageAdminContentResolver::SOURCE_DRAFT)
            ->assertJsonPath('content.hero.title', 'Draft support title');

        $this->assertNotSame('Published support title', data_get($response->json('content'), 'hero.title'));
    }

    public function test_intentional_empty_title_persists_through_save_and_publish(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $profile = $this->makeJetpkProfile();
        app(CurrentClientContext::class)->set($profile);
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);

        $this->actingAs($admin)->patch('/admin/page-settings/about', [
            'content' => [
                'hero' => [
                    'kicker' => '',
                    'title' => '',
                    'description' => '',
                ],
                'contact' => [
                    'phone' => '',
                    'email' => '',
                ],
            ],
        ])->assertRedirect('/admin/page-settings/about');

        $this->actingAs($admin)->post('/admin/page-settings/about/publish')
            ->assertRedirect('/admin/page-settings/about');

        $published = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', ClientPageKeys::ABOUT)
            ->where('status', ClientPageSettingStatus::Published)
            ->first();

        $this->assertNotNull($published);
        $this->assertSame('', data_get($published->content_json, 'hero.title'));
        $this->assertSame('', data_get($published->content_json, 'contact.phone'));

        $resolver = app(ClientPageAdminContentResolver::class);
        $this->assertSame('', $resolver->formContentFor($profile, ClientPageKeys::ABOUT)['hero']['title'] ?? 'missing');
    }

    public function test_backfill_dry_run_writes_nothing(): void
    {
        $profile = $this->makeJetpkProfile();

        $this->artisan('jetpk:page-settings-backfill-current-content', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame(0, ClientPageSetting::query()->where('client_profile_id', $profile->id)->count());
    }

    public function test_backfill_imports_missing_pages_and_is_idempotent(): void
    {
        $profile = $this->makeJetpkProfile();

        $this->artisan('jetpk:page-settings-backfill-current-content')
            ->assertSuccessful();

        $firstCount = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('status', ClientPageSettingStatus::Published)
            ->count();
        $this->assertGreaterThan(0, $firstCount);

        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::ABOUT,
            'status' => ClientPageSettingStatus::Draft,
            'content_json' => ['hero' => ['title' => 'Do not overwrite']],
        ]);

        $this->artisan('jetpk:page-settings-backfill-current-content')
            ->assertSuccessful();

        $aboutDraft = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', ClientPageKeys::ABOUT)
            ->where('status', ClientPageSettingStatus::Draft)
            ->first();

        $this->assertSame('Do not overwrite', data_get($aboutDraft?->content_json, 'hero.title'));
    }

    private function makeJetpkProfile(): ClientProfile
    {
        $profile = ClientProfile::query()->create([
            'name' => 'Jet Pakistan',
            'slug' => 'jetpk',
            'environment' => 'staging',
            'active_frontend_theme' => 'jetpakistan',
            'active_admin_theme' => 'jetpakistan',
            'active_staff_theme' => 'jetpakistan',
            'asset_profile' => 'jetpk-assets',
            'default_locale' => 'en',
            'timezone' => 'Asia/Karachi',
            'currency' => 'PKR',
            'is_master_profile' => false,
            'is_active' => true,
        ]);

        foreach (ClientProfileConfigReader::MODULE_KEYS as $moduleKey) {
            ClientProfileModule::query()->create([
                'client_profile_id' => $profile->id,
                'module_key' => $moduleKey,
                'enabled' => true,
            ]);
        }

        return $profile;
    }
}
