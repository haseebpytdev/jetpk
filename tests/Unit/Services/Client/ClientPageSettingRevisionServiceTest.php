<?php

namespace Tests\Unit\Services\Client;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageSetting;
use App\Models\ClientPageSettingRevision;
use App\Models\ClientProfile;
use App\Services\Client\ClientPageSettingRevisionService;
use App\Support\Client\ClientPageKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class ClientPageSettingRevisionServiceTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCmsTestUsers(42);
    }

    private function makeProfile(): ClientProfile
    {
        return ClientProfile::query()->create([
            'name' => 'Revision Test Client',
            'slug' => 'revision-test-'.uniqid(),
            'environment' => 'staging',
            'active_frontend_theme' => 'v1-classic',
            'asset_profile' => 'test-assets',
            'default_locale' => 'en',
            'timezone' => 'Asia/Karachi',
            'currency' => 'PKR',
            'is_master_profile' => false,
            'is_active' => true,
        ]);
    }

    public function test_snapshot_creates_a_revision_matching_source_content(): void
    {
        $profile = $this->makeProfile();
        $setting = ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => ['hero' => ['headline' => 'Snapshot me']],
        ]);

        $revision = app(ClientPageSettingRevisionService::class)->snapshot(
            $setting,
            ClientPageSettingRevision::REASON_BEFORE_PUBLISH,
            42,
        );

        $this->assertNotNull($revision);
        $this->assertSame($profile->id, $revision->client_profile_id);
        $this->assertSame(ClientPageKeys::HOME, $revision->page_key);
        $this->assertSame('published', $revision->source_status);
        $this->assertSame('Snapshot me', $revision->content_json['hero']['headline']);
        $this->assertSame(ClientPageSettingRevision::REASON_BEFORE_PUBLISH, $revision->revision_reason);
        $this->assertSame(42, $revision->created_by);
    }

    public function test_snapshot_returns_null_for_a_row_with_no_content(): void
    {
        $profile = $this->makeProfile();
        $setting = ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Draft,
            'content_json' => null,
        ]);

        $revision = app(ClientPageSettingRevisionService::class)->snapshot($setting, ClientPageSettingRevision::REASON_MANUAL_SNAPSHOT);

        $this->assertNull($revision);
    }

    public function test_snapshot_rejects_an_invalid_reason(): void
    {
        $profile = $this->makeProfile();
        $setting = ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => ['hero' => ['headline' => 'x']],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(ClientPageSettingRevisionService::class)->snapshot($setting, 'not_a_real_reason');
    }

    public function test_checksum_detects_tampering(): void
    {
        $profile = $this->makeProfile();
        $setting = ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => ['hero' => ['headline' => 'Original']],
        ]);

        $service = app(ClientPageSettingRevisionService::class);
        $revision = $service->snapshot($setting, ClientPageSettingRevision::REASON_MANUAL_SNAPSHOT);

        $this->assertTrue($service->verifyIntegrity($revision));

        // Simulate tampering via a raw query, bypassing the model's own immutability guard entirely —
        // this is what verifyIntegrity() exists to catch.
        \Illuminate\Support\Facades\DB::table('client_page_setting_revisions')
            ->where('id', $revision->id)
            ->update(['content_json' => json_encode(['hero' => ['headline' => 'Tampered']])]);

        $tamperedRevision = ClientPageSettingRevision::query()->find($revision->id);
        $this->assertFalse($service->verifyIntegrity($tamperedRevision));
    }

    public function test_list_for_is_scoped_to_the_correct_tenant_and_page(): void
    {
        $profileA = $this->makeProfile();
        $profileB = $this->makeProfile();
        $service = app(ClientPageSettingRevisionService::class);

        $settingA = ClientPageSetting::query()->create([
            'client_profile_id' => $profileA->id, 'page_key' => ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Published, 'content_json' => ['hero' => ['headline' => 'A']],
        ]);
        $settingB = ClientPageSetting::query()->create([
            'client_profile_id' => $profileB->id, 'page_key' => ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Published, 'content_json' => ['hero' => ['headline' => 'B']],
        ]);
        $settingAFooter = ClientPageSetting::query()->create([
            'client_profile_id' => $profileA->id, 'page_key' => ClientPageKeys::FOOTER,
            'status' => ClientPageSettingStatus::Published, 'content_json' => ['description' => ['text' => 'footer']],
        ]);

        $service->snapshot($settingA, ClientPageSettingRevision::REASON_MANUAL_SNAPSHOT);
        $service->snapshot($settingB, ClientPageSettingRevision::REASON_MANUAL_SNAPSHOT);
        $service->snapshot($settingAFooter, ClientPageSettingRevision::REASON_MANUAL_SNAPSHOT);

        $resultsA = $service->listFor($profileA, ClientPageKeys::HOME);

        $this->assertCount(1, $resultsA);
        $this->assertSame('A', $resultsA->first()->content_json['hero']['headline']);
    }

    /** The model-level guard, not just service-layer discipline, must block updates. */
    public function test_updating_an_existing_revision_throws(): void
    {
        $profile = $this->makeProfile();
        $setting = ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id, 'page_key' => ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Published, 'content_json' => ['hero' => ['headline' => 'x']],
        ]);
        $revision = app(ClientPageSettingRevisionService::class)->snapshot($setting, ClientPageSettingRevision::REASON_MANUAL_SNAPSHOT);

        $this->expectException(\LogicException::class);
        $revision->revision_reason = ClientPageSettingRevision::REASON_RESTORE;
        $revision->save();
    }

    public function test_deleting_an_existing_revision_throws(): void
    {
        $profile = $this->makeProfile();
        $setting = ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id, 'page_key' => ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Published, 'content_json' => ['hero' => ['headline' => 'x']],
        ]);
        $revision = app(ClientPageSettingRevisionService::class)->snapshot($setting, ClientPageSettingRevision::REASON_MANUAL_SNAPSHOT);

        $this->expectException(\LogicException::class);
        $revision->delete();
    }
}
