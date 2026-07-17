<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountType;
use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageSetting;
use App\Models\User;
use App\Services\Client\ClientPageSettingDefaultService;
use App\Support\Client\ClientPageKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class ResetToDefaultTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['client_route_parity.enabled' => false]);
        $this->seedJetpkAirports();
    }

    private function admin(): User
    {
        return User::factory()->create(['account_type' => AccountType::PlatformAdmin, 'current_agency_id' => null]);
    }

    private function seedDefault(\App\Models\ClientProfile $profile): void
    {
        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id, 'page_key' => ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Published, 'content_json' => ['hero' => ['headline' => 'Default headline']],
        ]);
        app(ClientPageSettingDefaultService::class)->saveCurrentPublishedAsDefault(
            $profile, ClientPageKeys::HOME, visualApprovalConfirmed: true,
        );
    }

    public function test_reset_draft_does_not_require_the_publish_confirmation_checkbox(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedDefault($profile);
        $this->seedDraftHome($profile, ['hero' => ['headline' => 'Old draft']]);

        $this->actingAs($this->admin())
            ->post('/admin/page-settings/home/reset/draft', [])
            ->assertRedirect()
            ->assertSessionHas('status');
    }

    public function test_reset_and_publish_requires_its_own_explicit_confirmation(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedDefault($profile);

        $this->actingAs($this->admin())
            ->post('/admin/page-settings/home/reset/publish', [/* no reset_and_publish_confirmed */])
            ->assertSessionHasErrors('reset_and_publish_confirmed');

        // Published must still be whatever it was before — nothing happened.
        $published = ClientPageSetting::query()->where('client_profile_id', $profile->id)->where('status', ClientPageSettingStatus::Published)->first();
        $this->assertSame('Default headline', $published->content_json['hero']['headline']);
    }

    public function test_reset_and_publish_succeeds_with_confirmation(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedDefault($profile);
        $this->seedPublishedHome($profile, ['hero' => ['headline' => 'Something else entirely']]);

        $this->actingAs($this->admin())
            ->post('/admin/page-settings/home/reset/publish', ['reset_and_publish_confirmed' => '1'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $published = ClientPageSetting::query()->where('client_profile_id', $profile->id)->where('status', ClientPageSettingStatus::Published)->first();
        $this->assertSame('Default headline', $published->content_json['hero']['headline']);
    }

    public function test_preview_reset_requires_no_confirmation_and_writes_nothing(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedDefault($profile);
        $this->seedDraftHome($profile, ['hero' => ['headline' => 'Must not change']]);

        $this->actingAs($this->admin())
            ->post('/admin/page-settings/home/reset/preview', [])
            ->assertRedirect()
            ->assertSessionHas('status');

        $draft = ClientPageSetting::query()->where('client_profile_id', $profile->id)->where('status', ClientPageSettingStatus::Draft)->first();
        $this->assertSame('Must not change', $draft->content_json['hero']['headline']);
    }
}
