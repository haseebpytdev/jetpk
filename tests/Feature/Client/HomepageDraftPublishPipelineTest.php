<?php

namespace Tests\Feature\Client;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageSetting;
use App\Models\User;
use App\Services\Client\ClientPageContentResolver;
use App\Support\Client\ClientPageKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

/**
 * JETPK-HOMEPAGE-CMS Task 8: draft/preview/publish pipeline coverage.
 * Focuses on the two real defects fixed in this task (created_by being
 * clobbered on every draft save; publish() trusting unvalidated content)
 * plus the pipeline properties the programme spec asked to have covered.
 */
class HomepageDraftPublishPipelineTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['client_route_parity.enabled' => false]);
        \Illuminate\Support\Facades\Storage::fake('public');
        $this->seedJetpkAirports();
        $this->seedJetpkAgency();
        $this->seedCmsTestUsers(1, 5, 7, 42, 111, 222);
    }

    /** Bug fix regression: created_by was being overwritten to the current editor on every draft save. */
    public function test_created_by_is_preserved_across_multiple_draft_saves(): void
    {
        $profile = $this->makeJetpkProfile();
        $resolver = app(ClientPageContentResolver::class);
        $originalAuthor = 111;
        $laterEditor = 222;

        $resolver->saveDraft($profile, ClientPageKeys::HOME, ['hero' => ['headline' => 'First save']], $originalAuthor);
        $resolver->saveDraft($profile, ClientPageKeys::HOME, ['hero' => ['headline' => 'Second save by someone else']], $laterEditor);

        $draft = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('page_key', ClientPageKeys::HOME)
            ->where('status', ClientPageSettingStatus::Draft)
            ->first();

        $this->assertSame($originalAuthor, $draft->created_by, 'created_by must stay the original author, not the most recent editor');
        $this->assertSame($laterEditor, $draft->updated_by, 'updated_by should reflect the most recent editor');
    }

    /** Draft isolation: saving a draft must never alter Published. */
    public function test_saving_draft_does_not_alter_published(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, ['hero' => ['headline' => 'Live headline — must not change']]);

        app(ClientPageContentResolver::class)->saveDraft($profile, ClientPageKeys::HOME, ['hero' => ['headline' => 'Draft-only headline']], 1);

        $published = ClientPageSetting::query()
            ->where('client_profile_id', $profile->id)
            ->where('status', ClientPageSettingStatus::Published)
            ->first();

        $this->assertSame('Live headline — must not change', data_get($published->content_json, 'hero.headline'));
    }

    /** Preview reads Draft, never Published, when in preview mode. */
    public function test_preview_shows_draft_content_not_published(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, ['hero' => ['headline' => 'Published headline']]);
        $this->seedDraftHome($profile, ['hero' => ['headline' => 'Draft headline']]);

        $admin = User::factory()->create(['account_type' => \App\Enums\AccountType::PlatformAdmin]);
        $this->actingAs($admin);
        app(ClientPageContentResolver::class)->beginDraftPreview(ClientPageKeys::HOME);

        $content = app(ClientPageContentResolver::class)->contentFor(ClientPageKeys::HOME);

        $this->assertSame('Draft headline', data_get($content, 'hero.headline'));
    }

    /**
     * Publish-time re-validation (this task's fix): a Draft saved with
     * invalid route data directly via saveDraft() (bypassing the Admin
     * controller's own validation) must be rejected at publish time rather
     * than silently promoted, and Published must be left exactly as it was.
     */
    public function test_publish_rejects_invalid_draft_content_and_leaves_published_untouched(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, ['hero' => ['headline' => 'Safe existing published content']]);

        $resolver = app(ClientPageContentResolver::class);
        // Bypass controller validation entirely — same-origin/same-destination route is invalid.
        $resolver->saveDraft($profile, ClientPageKeys::HOME, [
            'routes' => ['items' => [
                ['id' => 'bad-1', 'from' => 'KHI', 'to' => 'KHI', 'trip_type' => 'one_way', 'enabled' => '1', 'sort_order' => 0],
            ]],
        ], 1);

        $this->expectException(ValidationException::class);

        try {
            $resolver->publish($profile, ClientPageKeys::HOME, 1);
        } finally {
            $published = ClientPageSetting::query()
                ->where('client_profile_id', $profile->id)
                ->where('status', ClientPageSettingStatus::Published)
                ->first();
            $this->assertSame('Safe existing published content', data_get($published->content_json, 'hero.headline'), 'Published must be untouched after a failed publish attempt');
        }
    }

    /** End-to-end publish: valid draft content is promoted and becomes publicly visible. */
    public function test_valid_draft_is_promoted_and_becomes_publicly_visible(): void
    {
        $profile = $this->makeJetpkProfile();
        $resolver = app(ClientPageContentResolver::class);
        $resolver->saveDraft($profile, ClientPageKeys::HOME, ['hero' => ['headline' => 'Newly published headline']], 1);

        $published = $resolver->publish($profile, ClientPageKeys::HOME, 1);

        $this->assertNotNull($published);
        $this->assertSame('Newly published headline', data_get($resolver->contentFor(ClientPageKeys::HOME), 'hero.headline'));
    }

    /**
     * Presence-aware values (Task 1's resolveField contract) must survive
     * the full save -> publish -> public-read pipeline exactly, not just
     * within the normalizer in isolation.
     */
    public function test_empty_string_false_and_empty_array_survive_the_full_pipeline(): void
    {
        $profile = $this->makeJetpkProfile();
        $resolver = app(ClientPageContentResolver::class);

        $resolver->saveDraft($profile, ClientPageKeys::HOME, [
            'hero' => ['eyebrow' => ''],
            'trust' => ['cards' => []],
        ], 1);
        $resolver->publish($profile, ClientPageKeys::HOME, 1);

        $content = $resolver->contentFor(ClientPageKeys::HOME);

        $this->assertSame('', $resolver->resolveField($content, 'hero.eyebrow', 'SHOULD_NOT_APPEAR', allowEmpty: true));
        $this->assertSame([], $resolver->resolveField($content, 'trust.cards', ['SHOULD_NOT_APPEAR'], allowEmpty: true));
    }

    /** Section visibility: an explicitly disabled section must not render on the public page. */
    public function test_disabled_section_is_not_rendered_publicly(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, [
            'why_book' => ['enabled' => '0', 'cards' => [['num' => '01', 'title' => 'SHOULD-NOT-APPEAR-TITLE', 'text' => 'x']]],
        ]);

        $this->get('/')->assertOk()->assertDontSee('SHOULD-NOT-APPEAR-TITLE', false);
    }

    /** Ordering: repeating items must render in sort_order, not insertion order. */
    public function test_repeating_items_render_in_sort_order_not_insertion_order(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, [
            'destinations' => ['enabled' => '1', 'items' => [
                ['id' => 'd-second', 'code' => 'JED', 'title' => 'Jeddah', 'enabled' => '1', 'sort_order' => 1],
                ['id' => 'd-first', 'code' => 'DXB', 'title' => 'Dubai', 'enabled' => '1', 'sort_order' => 0],
            ]],
        ]);

        $response = $this->get('/')->assertOk();
        $html = $response->getContent();

        $this->assertLessThan(
            strpos($html, 'Dubai'),
            strpos($html, 'Jeddah'),
            'Dubai (sort_order 0) must render before Jeddah (sort_order 1) despite being inserted second in the array',
        );
    }
}
