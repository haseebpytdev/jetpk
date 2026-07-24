<?php

namespace Tests\Unit\Services\Client;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageSetting;
use App\Models\ClientPageSettingDefault;
use App\Models\ClientProfile;
use App\Services\Client\ClientPageSettingDefaultService;
use App\Support\Client\ClientPageKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class ClientPageSettingDefaultServiceTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCmsTestUsers(7);
    }

    private function makeProfile(): ClientProfile
    {
        return ClientProfile::query()->create([
            'name' => 'Default Test Client',
            'slug' => 'default-test-'.uniqid(),
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

    /** No default exists until someone explicitly saves one — never auto-seeded. */
    public function test_get_active_returns_null_when_nothing_has_ever_been_saved(): void
    {
        $profile = $this->makeProfile();

        $this->assertNull(app(ClientPageSettingDefaultService::class)->getActive($profile, ClientPageKeys::HOME));
    }

    public function test_save_current_published_as_default_requires_explicit_visual_approval(): void
    {
        $profile = $this->makeProfile();
        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id, 'page_key' => ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Published, 'content_json' => ['hero' => ['headline' => 'x']],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('visual-approval');
        app(ClientPageSettingDefaultService::class)->saveCurrentPublishedAsDefault($profile, ClientPageKeys::HOME, visualApprovalConfirmed: false);
    }

    public function test_save_current_published_as_default_requires_a_published_row_to_exist(): void
    {
        $profile = $this->makeProfile();
        // No Published row created at all.

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no Published content exists');
        app(ClientPageSettingDefaultService::class)->saveCurrentPublishedAsDefault($profile, ClientPageKeys::HOME, visualApprovalConfirmed: true);
    }

    public function test_save_current_published_as_default_succeeds_with_confirmation(): void
    {
        $profile = $this->makeProfile();
        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id, 'page_key' => ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Published, 'content_json' => ['hero' => ['headline' => 'Approved content']],
        ]);

        $default = app(ClientPageSettingDefaultService::class)->saveCurrentPublishedAsDefault(
            $profile, ClientPageKeys::HOME, visualApprovalConfirmed: true, label: 'Launch v1', userId: 7,
        );

        $this->assertTrue($default->is_active);
        $this->assertSame('Launch v1', $default->label);
        $this->assertSame('Approved content', $default->content_json['hero']['headline']);
        $this->assertSame(7, $default->created_by);
    }

    /** Replacing a default preserves the old one as inactive history, per spec action 7. */
    public function test_replacing_the_default_preserves_the_prior_one_as_inactive_history(): void
    {
        $profile = $this->makeProfile();
        $service = app(ClientPageSettingDefaultService::class);

        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id, 'page_key' => ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Published, 'content_json' => ['hero' => ['headline' => 'V1']],
        ]);
        $first = $service->saveCurrentPublishedAsDefault($profile, ClientPageKeys::HOME, visualApprovalConfirmed: true, label: 'V1');

        $second = $service->replaceActiveDefault($profile, ClientPageKeys::HOME, ['hero' => ['headline' => 'V2']], label: 'V2');

        $this->assertFalse($first->fresh()->is_active, 'The old default must be deactivated, not deleted');
        $this->assertTrue($second->is_active);

        $history = $service->history($profile, ClientPageKeys::HOME);
        $this->assertCount(2, $history, 'Both the old and new default must still exist in history');
        $this->assertSame('V1', $history->firstWhere('label', 'V1')?->content_json['hero']['headline']);
    }

    public function test_only_one_active_default_at_a_time(): void
    {
        $profile = $this->makeProfile();
        $service = app(ClientPageSettingDefaultService::class);

        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id, 'page_key' => ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Published, 'content_json' => ['hero' => ['headline' => 'V1']],
        ]);
        $service->saveCurrentPublishedAsDefault($profile, ClientPageKeys::HOME, visualApprovalConfirmed: true);
        $service->replaceActiveDefault($profile, ClientPageKeys::HOME, ['hero' => ['headline' => 'V2']]);
        $service->replaceActiveDefault($profile, ClientPageKeys::HOME, ['hero' => ['headline' => 'V3']]);

        $activeCount = ClientPageSettingDefault::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', ClientPageKeys::HOME)
            ->where('is_active', true)
            ->count();

        $this->assertSame(1, $activeCount);
    }

    /** Model-level guard: content is immutable, but label/is_active are legitimately editable. */
    public function test_content_json_is_immutable_but_label_is_editable(): void
    {
        $profile = $this->makeProfile();
        $default = ClientPageSettingDefault::query()->create([
            'client_profile_id' => $profile->id, 'page_key' => ClientPageKeys::HOME,
            'content_json' => ['hero' => ['headline' => 'x']], 'checksum' => 'abc', 'is_active' => true,
        ]);

        $default->label = 'Updated label';
        $default->save(); // must not throw
        $this->assertSame('Updated label', $default->fresh()->label);

        $this->expectException(\LogicException::class);
        $default->content_json = ['hero' => ['headline' => 'tampered']];
        $default->save();
    }

    public function test_default_is_scoped_to_the_correct_tenant(): void
    {
        $profileA = $this->makeProfile();
        $profileB = $this->makeProfile();
        $service = app(ClientPageSettingDefaultService::class);

        ClientPageSetting::query()->create([
            'client_profile_id' => $profileA->id, 'page_key' => ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Published, 'content_json' => ['hero' => ['headline' => 'A-content']],
        ]);
        $service->saveCurrentPublishedAsDefault($profileA, ClientPageKeys::HOME, visualApprovalConfirmed: true);

        $this->assertNull($service->getActive($profileB, ClientPageKeys::HOME), 'profileB must not see profileA\'s default');
    }
}
