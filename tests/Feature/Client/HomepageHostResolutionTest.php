<?php

namespace Tests\Feature\Client;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageSetting;
use App\Support\Client\ClientPageKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

/**
 * JETPK-CMS-ROOT-CAUSE-ANALYSIS, H2/H4 coverage.
 *
 * The spec for this work item explicitly asked for "request-bound feature
 * tests using host jetpakistan.pk" to help confirm or rule out host-based
 * tenant-context resolution as a root cause of "publish changed nothing".
 *
 * Source tracing (see JETPK_CMS_ROOT_CAUSE_ANALYSIS.md) already established
 * there is NO host-based resolution anywhere in ClientProfileResolver or
 * CurrentClientContext — tenant resolution is driven entirely by
 * config('ota_client.slug'). These tests make that finding empirically
 * verifiable rather than just asserted from reading the source: they prove
 * published content is visible regardless of request Host, and that the
 * same Draft/Publish/public-read path used by Admin is what a real HTTP
 * request exercises end to end.
 */
class HomepageHostResolutionTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    public function test_published_content_is_visible_when_request_host_is_jetpakistan_pk(): void
    {
        config(['ota_client.slug' => 'jetpk']);
        $profile = $this->makeJetpkProfile();

        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => [
                'hero' => [
                    'eyebrow' => 'H2-ROOT-CAUSE-PROBE-EYEBROW',
                    'headline' => 'Host resolution regression probe headline',
                ],
            ],
        ]);

        $this->withHeaders(['Host' => 'jetpakistan.pk'])
            ->get('/')
            ->assertOk()
            ->assertSee('H2-ROOT-CAUSE-PROBE-EYEBROW', false);
    }

    /**
     * The load-bearing assertion for H2: tenant resolution must be identical
     * regardless of what Host header the request arrives with, because
     * nothing in the resolution path reads the request at all. If this test
     * ever starts failing after someone adds host-aware logic, that's a sign
     * the architecture has changed and this document needs revisiting.
     */
    public function test_published_content_is_identical_regardless_of_request_host(): void
    {
        config(['ota_client.slug' => 'jetpk']);
        $profile = $this->makeJetpkProfile();

        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => [
                'hero' => ['eyebrow' => 'HOST-INDEPENDENCE-PROBE'],
            ],
        ]);

        $responseForRealHost = $this->withHeaders(['Host' => 'jetpakistan.pk'])->get('/');
        $responseForUnrelatedHost = $this->withHeaders(['Host' => 'totally-unrelated-domain.example'])->get('/');

        $responseForRealHost->assertOk()->assertSee('HOST-INDEPENDENCE-PROBE', false);
        $responseForUnrelatedHost->assertOk()->assertSee('HOST-INDEPENDENCE-PROBE', false);
    }

    /**
     * H2's actual risk mechanism: if OTA_CLIENT_SLUG is unset, resolution
     * must fall back to the jetpk profile (post-migration), not silently
     * resolve to no profile / a different profile. Confirms the fallback
     * fixed in JETPK_MASTER_CLIENT_MIGRATION.md actually reaches the public
     * page, not just the resolver's return value in isolation.
     */
    public function test_homepage_still_resolves_jetpk_profile_when_client_slug_env_is_missing(): void
    {
        config(['ota_client.slug' => '']);
        $profile = $this->makeJetpkProfile();

        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::HOME,
            'status' => ClientPageSettingStatus::Published,
            'content_json' => [
                'hero' => ['eyebrow' => 'ENV-FALLBACK-PROBE'],
            ],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('ENV-FALLBACK-PROBE', false);
    }
}
