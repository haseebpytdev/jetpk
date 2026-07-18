<?php

namespace Tests\Feature\Client;

use App\Services\Client\ClientPageContentResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

/**
 * Confirms HomepageContentNormalizer is actually wired into
 * ClientPageContentResolver::contentFor('home') — not just correct in
 * isolation (see HomepageContentNormalizerTest for the unit-level checks).
 */
class HomepageContentNormalizationIntegrationTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    public function test_published_stale_groups_content_is_migrated_on_read(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, [
            'groups' => ['enabled' => '1', 'title' => 'Legacy Groups Title'],
        ]);

        $content = app(ClientPageContentResolver::class)->contentFor(\App\Support\Client\ClientPageKeys::HOME);

        $this->assertSame('Legacy Groups Title', Arr::get($content, 'group_cards.title'));
        $this->assertFalse(Arr::has($content, 'groups.title'));
    }

    public function test_normalization_does_not_affect_other_page_keys(): void
    {
        $profile = $this->makeJetpkProfile();

        \App\Models\ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => \App\Support\Client\ClientPageKeys::FOOTER,
            'status' => \App\Enums\ClientPageSettingStatus::Published,
            'content_json' => ['groups' => ['title' => 'Should not be touched — footer has no such alias']],
        ]);

        $content = app(ClientPageContentResolver::class)->contentFor(\App\Support\Client\ClientPageKeys::FOOTER);

        // Footer is out of the homepage normalizer's scope entirely — the raw key survives untouched.
        $this->assertSame('Should not be touched — footer has no such alias', Arr::get($content, 'groups.title'));
    }
}
