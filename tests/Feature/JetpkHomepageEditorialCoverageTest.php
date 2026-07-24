<?php

namespace Tests\Feature;

use App\Support\Client\ClientPageKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

/**
 * JETPK-HOMEPAGE-CMS Task 9: confirms fields that were previously dead
 * (Admin field existed, frontend never read it) now actually render.
 */
class JetpkHomepageEditorialCoverageTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['client_route_parity.enabled' => false]);
        Storage::fake('public');
        $this->seedJetpkAirports();
        $this->seedJetpkAgency();
    }

    public function test_hero_cta_buttons_never_render_even_when_legacy_content_present(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, [
            'hero' => [
                'cta_primary_text' => 'PROBE-PRIMARY-CTA',
                'cta_primary_url' => '/flights',
                'cta_secondary_text' => 'PROBE-SECONDARY-CTA',
                'cta_secondary_url' => '/group-ticketing',
            ],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('PROBE-PRIMARY-CTA', false)
            ->assertDontSee('PROBE-SECONDARY-CTA', false)
            ->assertDontSee('hero-cta-primary', false)
            ->assertDontSee('hero-cta-secondary', false);
    }

    public function test_canonical_home_renders_section_stack_without_preview_context(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, [
            'routes' => ['enabled' => '1', 'items' => [['from' => 'KHI', 'to' => 'DXB', 'enabled' => '1']]],
            'destinations' => ['enabled' => '1', 'items' => [['code' => 'DXB', 'title' => 'Dubai', 'enabled' => '1']]],
            'support_cta' => ['enabled' => '1', 'title' => 'PROBE-SUPPORT-CTA'],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('jp-section-start:routes', false)
            ->assertSee('jp-section-start:destinations', false)
            ->assertSee('PROBE-SUPPORT-CTA', false);
    }

    public function test_group_cards_subtitle_and_cta_render_from_canonical_key(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, [
            'group_cards' => [
                'enabled' => '1',
                'subtitle' => 'PROBE-GROUP-SUBTITLE',
                'cta_text' => 'PROBE-GROUP-CTA-TEXT',
                'cta_url' => '/group-ticketing',
                'items' => [['title' => 'Test Group', 'enabled' => '1', 'price' => 50000]],
            ],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('PROBE-GROUP-SUBTITLE', false)
            ->assertSee('PROBE-GROUP-CTA-TEXT', false);
    }

    public function test_legacy_groups_key_content_still_renders_via_normalizer_migration(): void
    {
        $profile = $this->makeJetpkProfile();
        // Content saved under the OLD retired key, as if from before this task's fix.
        $this->seedPublishedHome($profile, [
            'groups' => [
                'subtitle' => 'PROBE-LEGACY-SUBTITLE-VIA-ALIAS',
                'cta_text' => 'PROBE-LEGACY-CTA-VIA-ALIAS',
                'cta_url' => '/group-ticketing',
            ],
            'group_cards' => [
                'enabled' => '1',
                'items' => [['title' => 'Test Group', 'enabled' => '1', 'price' => 50000]],
            ],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('PROBE-LEGACY-SUBTITLE-VIA-ALIAS', false)
            ->assertSee('PROBE-LEGACY-CTA-VIA-ALIAS', false);
    }

    public function test_feature_board_disabled_does_not_render(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, [
            'feature_board' => ['enabled' => '0', 'items' => [['value' => 'PROBE-STAT-VALUE', 'label' => 'PROBE-STAT-LABEL']]],
        ]);

        $this->get('/')->assertOk()->assertDontSee('PROBE-STAT-VALUE', false);
    }

    public function test_feature_board_enabled_still_renders_by_default(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, [
            'feature_board' => ['items' => [['value' => 'PROBE-STAT-VALUE-2', 'label' => 'PROBE-STAT-LABEL-2']]],
        ]);

        $this->get('/')->assertOk()->assertSee('PROBE-STAT-VALUE-2', false);
    }

    public function test_destination_badge_and_description_render(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, [
            'destinations' => ['enabled' => '1', 'items' => [
                ['id' => 'd1', 'code' => 'DXB', 'title' => 'Dubai', 'enabled' => '1', 'sort_order' => 0, 'badge' => 'PROBE-DEST-BADGE', 'text' => 'PROBE-DEST-DESCRIPTION'],
            ]],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('PROBE-DEST-BADGE', false)
            ->assertSee('PROBE-DEST-DESCRIPTION', false);
    }

    public function test_trust_section_uses_single_reconciled_default_when_content_absent(): void
    {
        $profile = $this->makeJetpkProfile();
        // Publish the section enabled with no eyebrow/title/subtitle set at all —
        // exercises the reconciled default chain, not a Blade-only hardcoded fallback.
        $this->seedPublishedHome($profile, [
            'trust' => ['enabled' => '1', 'cards' => [['title' => 'A trust card', 'text' => 'x', 'enabled' => '1']]],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Why travellers stay', false)
            ->assertSee('Booking that respects your time and money.', false);
    }
}
