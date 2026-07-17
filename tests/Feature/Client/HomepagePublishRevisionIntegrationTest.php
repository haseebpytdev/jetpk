<?php

namespace Tests\Feature\Client;

use App\Models\ClientPageSettingRevision;
use App\Services\Client\ClientPageContentResolver;
use App\Services\Client\ClientPageSettingRevisionService;
use App\Support\Client\ClientPageKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

/**
 * JETPK-HOMEPAGE-CMS Task 11: confirms publish() actually creates a
 * before_publish revision through the real pipeline — not just that the
 * revision service works correctly in isolation (see
 * ClientPageSettingRevisionServiceTest for that).
 */
class HomepagePublishRevisionIntegrationTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedJetpkAirports();
        $this->seedCmsTestUsers(1);
    }

    public function test_first_ever_publish_creates_no_revision(): void
    {
        $profile = $this->makeJetpkProfile();
        $resolver = app(ClientPageContentResolver::class);
        $resolver->saveDraft($profile, ClientPageKeys::HOME, ['hero' => ['headline' => 'First publish']], 1);

        $resolver->publish($profile, ClientPageKeys::HOME, 1);

        $revisions = app(ClientPageSettingRevisionService::class)->listFor($profile, ClientPageKeys::HOME);
        $this->assertCount(0, $revisions, 'Nothing existed to snapshot on the very first publish');
    }

    public function test_second_publish_snapshots_the_prior_published_state(): void
    {
        $profile = $this->makeJetpkProfile();
        $resolver = app(ClientPageContentResolver::class);

        $resolver->saveDraft($profile, ClientPageKeys::HOME, ['hero' => ['headline' => 'Version 1']], 1);
        $resolver->publish($profile, ClientPageKeys::HOME, 1);

        $resolver->saveDraft($profile, ClientPageKeys::HOME, ['hero' => ['headline' => 'Version 2']], 1);
        $resolver->publish($profile, ClientPageKeys::HOME, 1);

        $revisions = app(ClientPageSettingRevisionService::class)->listFor($profile, ClientPageKeys::HOME);

        $this->assertCount(1, $revisions);
        $this->assertSame('Version 1', $revisions->first()->content_json['hero']['headline'], 'The revision must capture what was published BEFORE, not the new content');
        $this->assertSame(ClientPageSettingRevision::REASON_BEFORE_PUBLISH, $revisions->first()->revision_reason);

        // And the live published content must now be the new version.
        $this->assertSame('Version 2', data_get($resolver->contentFor(ClientPageKeys::HOME), 'hero.headline'));
    }

    public function test_three_publishes_accumulate_two_revisions_in_correct_order(): void
    {
        $profile = $this->makeJetpkProfile();
        $resolver = app(ClientPageContentResolver::class);

        foreach (['V1', 'V2', 'V3'] as $version) {
            $resolver->saveDraft($profile, ClientPageKeys::HOME, ['hero' => ['headline' => $version]], 1);
            $resolver->publish($profile, ClientPageKeys::HOME, 1);
        }

        $revisions = app(ClientPageSettingRevisionService::class)->listFor($profile, ClientPageKeys::HOME);

        $this->assertCount(2, $revisions);
        // listFor() orders newest-first.
        $this->assertSame('V2', $revisions->first()->content_json['hero']['headline']);
        $this->assertSame('V1', $revisions->last()->content_json['hero']['headline']);
    }

    /** A failed publish (invalid draft content) must not create a spurious revision either. */
    public function test_failed_publish_creates_no_revision(): void
    {
        $profile = $this->makeJetpkProfile();
        $resolver = app(ClientPageContentResolver::class);

        $resolver->saveDraft($profile, ClientPageKeys::HOME, ['hero' => ['headline' => 'Good version']], 1);
        $resolver->publish($profile, ClientPageKeys::HOME, 1);

        // Save an invalid draft (same-origin/destination route) and attempt to publish it.
        $resolver->saveDraft($profile, ClientPageKeys::HOME, [
            'routes' => ['items' => [['id' => 'bad', 'from' => 'KHI', 'to' => 'KHI', 'trip_type' => 'one_way', 'enabled' => '1', 'sort_order' => 0]]],
        ], 1);

        try {
            $resolver->publish($profile, ClientPageKeys::HOME, 1);
        } catch (\Illuminate\Validation\ValidationException) {
            // expected
        }

        $revisions = app(ClientPageSettingRevisionService::class)->listFor($profile, ClientPageKeys::HOME);
        $this->assertCount(0, $revisions, 'A failed publish must not snapshot anything, since nothing was actually overwritten');
    }
}
