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

class SaveCurrentAsDefaultTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['client_route_parity.enabled' => false]);
    }

    private function admin(): User
    {
        return User::factory()->create(['account_type' => AccountType::PlatformAdmin, 'current_agency_id' => null]);
    }

    public function test_requires_the_visual_approval_checkbox(): void
    {
        $profile = $this->makeJetpkProfile();
        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id, 'page_key' => ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Published, 'content_json' => ['hero' => ['headline' => 'Live content']],
        ]);

        $this->actingAs($this->admin())
            ->post('/admin/page-settings/home/save-as-default', [/* no visual_approval_confirmed */])
            ->assertSessionHasErrors('visual_approval_confirmed');

        $this->assertNull(app(ClientPageSettingDefaultService::class)->getActive($profile, ClientPageKeys::HOME));
    }

    public function test_saves_default_when_checkbox_is_checked(): void
    {
        $profile = $this->makeJetpkProfile();
        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id, 'page_key' => ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Published, 'content_json' => ['hero' => ['headline' => 'Live content']],
        ]);

        $this->actingAs($this->admin())
            ->post('/admin/page-settings/home/save-as-default', [
                'visual_approval_confirmed' => '1',
                'label' => 'First launch default',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $default = app(ClientPageSettingDefaultService::class)->getActive($profile, ClientPageKeys::HOME);
        $this->assertNotNull($default);
        $this->assertSame('First launch default', $default->label);
        $this->assertSame('Live content', $default->content_json['hero']['headline']);
    }

    public function test_unauthenticated_request_is_redirected_not_allowed(): void
    {
        $this->makeJetpkProfile();

        $this->post('/admin/page-settings/home/save-as-default', ['visual_approval_confirmed' => '1'])
            ->assertRedirect();
    }
}
