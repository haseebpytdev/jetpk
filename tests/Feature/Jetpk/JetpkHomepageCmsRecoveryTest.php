<?php

namespace Tests\Feature\Jetpk;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageSetting;
use App\Services\Homepage\JetpkHomepageContentMergeService;
use App\Services\Homepage\JetpkHomepageContentRestoreService;
use App\Support\Client\ClientPageKeys;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class JetpkHomepageCmsRecoveryTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ota-developer.enabled' => true]);
        config(['client_route_parity.enabled' => false]);
        config(['mail.from.address' => 'ota@jetpakistan.pk']);
        config(['mail.from.name' => 'JetPakistan']);
        $this->seedJetpkAirports();
        $this->seedJetpkAgency();
    }

    public function test_restore_service_repairs_blank_feature_board_and_preserves_hero(): void
    {
        $profile = $this->makeJetpkProfile();
        $damaged = array_merge($this->representativeThreeCardHomeContent(), [
            'feature_board' => ['items' => [['value' => '', 'label' => '']]],
            'trust' => ['enabled' => '1', 'title' => '', 'cards' => []],
            'hero' => ['headline' => 'Must stay untouched'],
        ]);

        $service = app(JetpkHomepageContentRestoreService::class);
        $changes = $service->buildChangePlan($damaged);
        $repaired = $service->applyChangePlan($damaged, $changes);

        $this->assertSame('Must stay untouched', data_get($repaired, 'hero.headline'));
        $this->assertSame('400+', data_get($repaired, 'feature_board.items.0.value'));
        $this->assertNotSame('', data_get($repaired, 'trust.title'));
    }

    public function test_restore_service_preserves_route_and_destination_identities_and_prices(): void
    {
        $damaged = $this->representativeThreeCardHomeContent();
        $damaged['routes']['eyebrow'] = '';
        $damaged['destinations']['title'] = '';

        $service = app(JetpkHomepageContentRestoreService::class);
        $repaired = $service->applyChangePlan($damaged, $service->buildChangePlan($damaged));

        $this->assertSame('custom-route-1', data_get($repaired, 'routes.items.0.id'));
        $this->assertSame(11111, (int) data_get($repaired, 'routes.items.0.manual_fallback_price'));
        $this->assertSame('custom-dest-dxb', data_get($repaired, 'destinations.items.0.id'));
        $this->assertSame(44444, (int) data_get($repaired, 'destinations.items.0.manual_fallback_price'));
        $this->assertSame('Trending routes', data_get($repaired, 'routes.eyebrow'));
        $this->assertSame('Destinations on the rise.', data_get($repaired, 'destinations.title'));
    }

    public function test_merge_on_save_preserves_unrelated_sections_when_routes_panel_submitted(): void
    {
        $existing = [
            'routes' => ['enabled' => '1', 'title' => 'Old routes', 'items' => []],
            'support_cta' => ['enabled' => '1', 'title' => 'Keep support title', 'eyebrow' => 'Keep eyebrow'],
            '_fare_cache' => ['routes' => ['route-1' => ['resolved_fare' => 12345]]],
        ];

        $submitted = [
            'routes' => ['enabled' => '1', 'title' => 'Updated routes', 'items' => []],
            'support_cta' => ['enabled' => '1', 'title' => '', 'eyebrow' => ''],
        ];

        $merged = app(JetpkHomepageContentMergeService::class)->mergeOnSave(
            $existing,
            $submitted,
            ['routes'],
        );

        $this->assertSame('Updated routes', data_get($merged, 'routes.title'));
        $this->assertSame('Keep support title', data_get($merged, 'support_cta.title'));
        $this->assertSame('Keep eyebrow', data_get($merged, 'support_cta.eyebrow'));
        $this->assertSame(12345, (int) data_get($merged, '_fare_cache.routes.route-1.resolved_fare'));
    }

    public function test_saving_routes_does_not_blank_support_cta_in_database(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $profile = $this->makeJetpkProfile();
        $admin = $this->makeAdmin();

        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Draft,
            'content_json' => [
                'support_cta' => ['enabled' => '1', 'title' => 'Human help', 'eyebrow' => 'We pick up'],
                'routes' => ['enabled' => '1', 'items' => []],
            ],
        ]);

        $this->actingAs($admin)->patch('/admin/page-settings/home', [
            'submitted_sections' => ['routes'],
            'content' => [
                'routes' => [
                    'enabled' => '1',
                    'title' => 'Updated routes heading',
                    'items' => [[
                        'id' => 'route-1',
                        'from' => 'KHI',
                        'to' => 'DXB',
                        'enabled' => '1',
                        'sort_order' => 0,
                        'trip_type' => 'one_way',
                        'manual_fallback_price' => 50000,
                    ]],
                ],
                'support_cta' => ['enabled' => '1', 'title' => '', 'eyebrow' => ''],
            ],
        ])->assertRedirect();

        $draft = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('status', ClientPageSettingStatus::Draft)
            ->first();

        $this->assertSame('Human help', data_get($draft->content_json, 'support_cta.title'));
        $this->assertSame('Updated routes heading', data_get($draft->content_json, 'routes.title'));
    }

    public function test_canonical_business_email_audit_passes_for_jetpk_scoped_sources(): void
    {
        config(['mail.from.address' => 'ota@jetpakistan.pk']);
        config(['mail.from.name' => 'JetPakistan']);

        Artisan::call('jetpk:canonical-business-email-audit');
        $this->assertStringContainsString('fail_count=0', Artisan::output());
    }

    public function test_forensic_snapshot_without_rollback_json_is_rejected(): void
    {
        $dir = storage_path('app/audits/jetpk-homepage-cms-restore/test-forensic-only');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($dir.'/homepage-cms-snapshot.json', '{}');

        Artisan::call('jetpk:homepage-cms-restore', ['--rollback' => 'test-forensic-only']);
        $output = Artisan::output();
        $this->assertStringContainsString('forensic snapshot', $output);
        $this->assertSame(1, Artisan::call('jetpk:homepage-cms-restore', ['--rollback' => 'test-forensic-only']));
    }

    public function test_apply_backup_rollback_restores_original_content_hashes(): void
    {
        $profile = $this->makeJetpkProfile();
        $original = $this->representativeThreeCardHomeContent();
        $originalHash = hash('sha256', json_encode($original) ?: '');

        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Draft,
            'content_json' => $original,
        ]);
        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => $original,
            'published_at' => now(),
        ]);

        $exitApply = Artisan::call('jetpk:homepage-cms-restore', [
            '--profile' => 'jetpk',
            '--apply' => true,
        ]);
        $this->assertSame(0, $exitApply);

        $backupDirs = glob(storage_path('app/audits/jetpk-homepage-cms-restore/20*'));
        $this->assertNotEmpty($backupDirs);
        $stamp = basename(end($backupDirs));
        $this->assertFileExists(storage_path('app/audits/jetpk-homepage-cms-restore/'.$stamp.'/rollback.json'));

        ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', ClientPageKeys::HOME)
            ->update(['content_json' => ['hero' => ['headline' => 'Mutated']]]);

        $exitRollback = Artisan::call('jetpk:homepage-cms-restore', [
            '--profile' => 'jetpk',
            '--rollback' => $stamp,
        ]);
        $this->assertSame(0, $exitRollback);

        $draft = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('status', ClientPageSettingStatus::Draft)
            ->first();
        $restoredHash = hash('sha256', json_encode($draft->content_json) ?: '');
        $this->assertSame($originalHash, $restoredHash);
    }

    public function test_error_page_uses_jetpk_support_email_when_jetpk_tenant(): void
    {
        $this->makeJetpkProfile();

        $this->get('/_jetpk-test-route-that-does-not-exist')
            ->assertNotFound()
            ->assertSee('ota@jetpakistan.pk', false);
    }

    private function makeAdmin(): \App\Models\User
    {
        return \App\Models\User::factory()->create([
            'account_type' => \App\Enums\AccountType::PlatformAdmin,
            'current_agency_id' => null,
        ]);
    }
}
