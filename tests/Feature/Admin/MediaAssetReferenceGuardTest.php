<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountType;
use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageAsset;
use App\Models\ClientPageSetting;
use App\Models\User;
use App\Support\Client\ClientPageKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class MediaAssetReferenceGuardTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['client_route_parity.enabled' => false]);
        Storage::fake('public');
    }

    private function admin(): User
    {
        return User::factory()->create(['account_type' => AccountType::PlatformAdmin, 'current_agency_id' => null]);
    }

    public function test_deleting_a_referenced_asset_is_blocked_by_default(): void
    {
        $profile = $this->makeJetpkProfile();
        $asset = ClientPageAsset::query()->create([
            'client_profile_id' => $profile->id, 'page_key' => ClientPageKeys::HOME,
            'asset_key' => 'destination_referenced', 'disk' => 'public', 'path' => 'x.jpg',
        ]);
        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id, 'page_key' => ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => ['destinations' => ['items' => [['image_asset_key' => 'destination_referenced']]]],
        ]);

        $this->actingAs($this->admin())
            ->delete("/admin/page-settings/home/assets/{$asset->id}")
            ->assertSessionHasErrors('asset');

        $this->assertNotNull(ClientPageAsset::query()->find($asset->id), 'Referenced asset must survive the blocked deletion attempt');
    }

    public function test_force_flag_allows_deleting_a_referenced_asset(): void
    {
        $profile = $this->makeJetpkProfile();
        $asset = ClientPageAsset::query()->create([
            'client_profile_id' => $profile->id, 'page_key' => ClientPageKeys::HOME,
            'asset_key' => 'destination_referenced', 'disk' => 'public', 'path' => 'x.jpg',
        ]);
        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id, 'page_key' => ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => ['destinations' => ['items' => [['image_asset_key' => 'destination_referenced']]]],
        ]);

        $this->actingAs($this->admin())
            ->delete("/admin/page-settings/home/assets/{$asset->id}?force=1")
            ->assertSessionHas('status');

        $this->assertNull(ClientPageAsset::query()->find($asset->id));
    }

    public function test_deleting_an_unreferenced_asset_succeeds_without_force(): void
    {
        $profile = $this->makeJetpkProfile();
        $asset = ClientPageAsset::query()->create([
            'client_profile_id' => $profile->id, 'page_key' => ClientPageKeys::HOME,
            'asset_key' => 'destination_unused', 'disk' => 'public', 'path' => 'x.jpg',
        ]);

        $this->actingAs($this->admin())
            ->delete("/admin/page-settings/home/assets/{$asset->id}")
            ->assertSessionHas('status');

        $this->assertNull(ClientPageAsset::query()->find($asset->id));
    }
}
