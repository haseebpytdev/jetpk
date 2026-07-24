<?php

namespace Tests\Unit\Services\Client;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageAsset;
use App\Models\ClientPageSetting;
use App\Models\ClientProfile;
use App\Services\Client\ClientPageResetService;
use App\Services\Client\ClientPageSettingDefaultService;
use App\Services\Client\ClientPageSettingRevisionService;
use App\Support\Client\ClientPageKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class ClientPageResetServiceTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedJetpkAirports();
        $this->seedCmsTestUsers(1, 5);
    }

    private function saveDefault(ClientProfile $profile, array $content, ?string $label = 'Test default'): void
    {
        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id, 'page_key' => ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Published, 'content_json' => $content,
        ]);
        app(ClientPageSettingDefaultService::class)->saveCurrentPublishedAsDefault(
            $profile, ClientPageKeys::HOME, visualApprovalConfirmed: true, label: $label,
        );
    }

    // -------------------------------------------------------------------
    // Missing default
    // -------------------------------------------------------------------

    public function test_preview_reset_reports_missing_default_clearly(): void
    {
        $profile = $this->makeJetpkProfile();

        $result = app(ClientPageResetService::class)->previewReset($profile, ClientPageKeys::HOME);

        $this->assertFalse($result['success']);
        $this->assertSame('missing_default', $result['error']);
        $this->assertStringContainsString('No saved default exists', $result['message']);
    }

    public function test_reset_draft_reports_missing_default_and_writes_nothing(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedDraftHome($profile, ['hero' => ['headline' => 'Untouched draft']]);

        $result = app(ClientPageResetService::class)->resetDraftToDefault($profile, ClientPageKeys::HOME, 1);

        $this->assertFalse($result['success']);
        $draft = ClientPageSetting::query()->where('client_profile_id', $profile->id)->where('status', ClientPageSettingStatus::Draft)->first();
        $this->assertSame('Untouched draft', $draft->content_json['hero']['headline']);
    }

    public function test_reset_and_publish_reports_missing_default_and_writes_nothing(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, ['hero' => ['headline' => 'Untouched published']]);

        $result = app(ClientPageResetService::class)->resetAndPublish($profile, ClientPageKeys::HOME, 1);

        $this->assertFalse($result['success']);
        $published = ClientPageSetting::query()->where('client_profile_id', $profile->id)->where('status', ClientPageSettingStatus::Published)->first();
        $this->assertSame('Untouched published', $published->content_json['hero']['headline']);
    }

    // -------------------------------------------------------------------
    // Preview (read-only)
    // -------------------------------------------------------------------

    public function test_preview_reset_writes_nothing_at_all(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->saveDefault($profile, ['hero' => ['headline' => 'Default content']]);
        $this->seedDraftHome($profile, ['hero' => ['headline' => 'Current draft, must stay']]);

        $result = app(ClientPageResetService::class)->previewReset($profile, ClientPageKeys::HOME);

        $this->assertTrue($result['success']);
        $this->assertSame('Default content', $result['content']['hero']['headline']);

        $draft = ClientPageSetting::query()->where('client_profile_id', $profile->id)->where('status', ClientPageSettingStatus::Draft)->first();
        $this->assertSame('Current draft, must stay', $draft->content_json['hero']['headline']);
        $this->assertCount(0, app(ClientPageSettingRevisionService::class)->listFor($profile, ClientPageKeys::HOME));
    }

    // -------------------------------------------------------------------
    // Reset Draft to Default — Published untouched, revision of old draft kept
    // -------------------------------------------------------------------

    public function test_reset_draft_replaces_draft_only_and_snapshots_the_prior_draft(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->saveDefault($profile, ['hero' => ['headline' => 'Default headline']]);
        $this->seedDraftHome($profile, ['hero' => ['headline' => 'Old draft headline']]);
        $this->seedPublishedHome($profile, ['hero' => ['headline' => 'Published headline — must stay']]);

        $result = app(ClientPageResetService::class)->resetDraftToDefault($profile, ClientPageKeys::HOME, 5);

        $this->assertTrue($result['success']);

        $draft = ClientPageSetting::query()->where('client_profile_id', $profile->id)->where('status', ClientPageSettingStatus::Draft)->first();
        $this->assertSame('Default headline', $draft->content_json['hero']['headline']);

        $published = ClientPageSetting::query()->where('client_profile_id', $profile->id)->where('status', ClientPageSettingStatus::Published)->first();
        $this->assertSame('Published headline — must stay', $published->content_json['hero']['headline'], 'resetDraftToDefault must never touch Published');

        $revisions = app(ClientPageSettingRevisionService::class)->listFor($profile, ClientPageKeys::HOME);
        $this->assertCount(1, $revisions);
        $this->assertSame('Old draft headline', $revisions->first()->content_json['hero']['headline']);
        $this->assertSame(\App\Models\ClientPageSettingRevision::REASON_BEFORE_RESET, $revisions->first()->revision_reason);
    }

    public function test_reset_draft_when_no_draft_previously_existed_creates_no_revision(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->saveDefault($profile, ['hero' => ['headline' => 'Default headline']]);
        // No draft seeded at all.

        $result = app(ClientPageResetService::class)->resetDraftToDefault($profile, ClientPageKeys::HOME, 1);

        $this->assertTrue($result['success']);
        $this->assertCount(0, app(ClientPageSettingRevisionService::class)->listFor($profile, ClientPageKeys::HOME));
    }

    // -------------------------------------------------------------------
    // Reset and Publish — atomic, both rows updated, both prior states snapshotted
    // -------------------------------------------------------------------

    public function test_reset_and_publish_replaces_both_draft_and_published(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->saveDefault($profile, ['hero' => ['headline' => 'Default headline']]);
        $this->seedDraftHome($profile, ['hero' => ['headline' => 'Old draft']]);
        $this->seedPublishedHome($profile, ['hero' => ['headline' => 'Old published']]);

        $result = app(ClientPageResetService::class)->resetAndPublish($profile, ClientPageKeys::HOME, 1);

        $this->assertTrue($result['success']);

        $draft = ClientPageSetting::query()->where('client_profile_id', $profile->id)->where('status', ClientPageSettingStatus::Draft)->first();
        $published = ClientPageSetting::query()->where('client_profile_id', $profile->id)->where('status', ClientPageSettingStatus::Published)->first();
        $this->assertSame('Default headline', $draft->content_json['hero']['headline']);
        $this->assertSame('Default headline', $published->content_json['hero']['headline']);
    }

    /**
     * The specific bug this task's refactor avoids: reset-and-publish must
     * snapshot the prior draft AND the prior published state, but must NOT
     * create a third, redundant revision of the published content under a
     * different label by internally calling the generic publish() method.
     */
    public function test_reset_and_publish_creates_exactly_two_revisions_not_three(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->saveDefault($profile, ['hero' => ['headline' => 'Default headline']]);
        $this->seedDraftHome($profile, ['hero' => ['headline' => 'Old draft']]);
        $this->seedPublishedHome($profile, ['hero' => ['headline' => 'Old published']]);

        app(ClientPageResetService::class)->resetAndPublish($profile, ClientPageKeys::HOME, 1);

        $revisions = app(ClientPageSettingRevisionService::class)->listFor($profile, ClientPageKeys::HOME);

        $this->assertCount(2, $revisions, 'Exactly one revision for the old draft and one for the old published state — not a third duplicate');
        $reasons = $revisions->pluck('revision_reason')->unique()->values()->all();
        $this->assertSame([\App\Models\ClientPageSettingRevision::REASON_BEFORE_RESET], $reasons, 'Both must be labeled before_reset, not before_publish');
    }

    public function test_reset_and_publish_with_unsafe_default_content_throws_and_touches_nothing(): void
    {
        $profile = $this->makeJetpkProfile();
        // Save a default with an invalid route (same origin/destination) — bypasses
        // the Admin controller's validation the same way saveDraft() can.
        $this->saveDefault($profile, [
            'routes' => ['items' => [['id' => 'bad', 'from' => 'KHI', 'to' => 'KHI', 'trip_type' => 'one_way', 'enabled' => '1', 'sort_order' => 0]]],
        ]);
        $this->seedPublishedHome($profile, ['hero' => ['headline' => 'Must survive']]);

        try {
            app(ClientPageResetService::class)->resetAndPublish($profile, ClientPageKeys::HOME, 1);
            $this->fail('Expected a ValidationException');
        } catch (\Illuminate\Validation\ValidationException) {
            // expected
        }

        $published = ClientPageSetting::query()->where('client_profile_id', $profile->id)->where('status', ClientPageSettingStatus::Published)->first();
        $this->assertSame('Must survive', $published->content_json['hero']['headline']);
        $this->assertCount(0, app(ClientPageSettingRevisionService::class)->listFor($profile, ClientPageKeys::HOME), 'A rejected reset must not create a revision either — nothing was actually overwritten');
    }

    // -------------------------------------------------------------------
    // Missing media — reported, not silently stripped
    // -------------------------------------------------------------------

    public function test_missing_media_is_reported_but_reference_is_not_stripped(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->saveDefault($profile, [
            'destinations' => ['enabled' => '1', 'items' => [
                ['id' => 'd1', 'code' => 'DXB', 'title' => 'Dubai', 'enabled' => '1', 'sort_order' => 0, 'image_asset_key' => 'destination_missing_key'],
            ]],
        ]);
        // Deliberately do NOT create a ClientPageAsset row for 'destination_missing_key'.

        $result = app(ClientPageResetService::class)->resetDraftToDefault($profile, ClientPageKeys::HOME, 1);

        $this->assertTrue($result['success']);
        $this->assertContains('destination_missing_key', $result['missing_media']);

        $draft = ClientPageSetting::query()->where('client_profile_id', $profile->id)->where('status', ClientPageSettingStatus::Draft)->first();
        $this->assertSame('destination_missing_key', $draft->content_json['destinations']['items'][0]['image_asset_key'], 'Reference must be preserved, not silently removed');
    }

    public function test_no_missing_media_warning_when_asset_actually_exists(): void
    {
        $profile = $this->makeJetpkProfile();
        ClientPageAsset::query()->create([
            'client_profile_id' => $profile->id, 'page_key' => ClientPageKeys::HOME,
            'asset_key' => 'destination_present_key', 'disk' => 'public', 'path' => 'x.jpg',
        ]);
        $this->saveDefault($profile, [
            'destinations' => ['enabled' => '1', 'items' => [
                ['id' => 'd1', 'code' => 'DXB', 'title' => 'Dubai', 'enabled' => '1', 'sort_order' => 0, 'image_asset_key' => 'destination_present_key'],
            ]],
        ]);

        $result = app(ClientPageResetService::class)->previewReset($profile, ClientPageKeys::HOME);

        $this->assertSame([], $result['missing_media']);
    }

    public function test_fixed_system_media_slots_are_not_flagged_as_missing_when_simply_unused(): void
    {
        $profile = $this->makeJetpkProfile();
        // A perfectly ordinary default that just doesn't use a custom hero image —
        // this must NOT produce a "missing media" warning for hero_background.
        $this->saveDefault($profile, ['hero' => ['headline' => 'No custom image, uses branding fallback']]);

        $result = app(ClientPageResetService::class)->previewReset($profile, ClientPageKeys::HOME);

        $this->assertSame([], $result['missing_media']);
    }
}
