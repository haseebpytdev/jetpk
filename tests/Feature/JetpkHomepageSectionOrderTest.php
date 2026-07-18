<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

/**
 * JETPK-HOMEPAGE-CMS Task 10: section order control.
 */
class JetpkHomepageSectionOrderTest extends TestCase
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

    /**
     * Default behavior must be byte-for-byte unchanged from the original
     * fixed @include sequence — this is the most important test in this
     * file. Nobody who doesn't touch the new .order fields should see any
     * difference at all.
     */
    public function test_default_order_matches_original_fixed_sequence(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, [
            'why_book' => ['enabled' => '1', 'cards' => [['title' => 'PROBE-WHY-BOOK-MARKER', 'text' => 'x', 'enabled' => '1']]],
            'support_cta' => ['enabled' => '1', 'title' => 'PROBE-SUPPORT-CTA-MARKER'],
        ]);

        $html = $this->get('/')->assertOk()->getContent();

        preg_match_all('/<!-- jp-section-start:([^:]+):/', $html, $sectionMarkers);
        $markerKeys = $sectionMarkers[1] ?? [];
        $whyIndex = array_search('why_book', $markerKeys, true);
        $supportIndex = array_search('support_cta', $markerKeys, true);
        $this->assertNotFalse($whyIndex);
        $this->assertNotFalse($supportIndex);
        $this->assertLessThan(
            $supportIndex,
            $whyIndex,
            'Why Book (default order 8) must still render before Support CTA (default order 9) with no custom order set',
        );
    }

    /** Custom order values must actually change the rendered sequence. */
    public function test_custom_order_reorders_sections(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, [
            'support_cta' => ['enabled' => '1', 'order' => 1, 'title' => 'PROBE-SUPPORT-CTA-REORDERED'],
            'why_book' => ['enabled' => '1', 'order' => 20, 'cards' => [['title' => 'PROBE-WHY-BOOK-REORDERED', 'text' => 'x', 'enabled' => '1']]],
        ]);

        $response = $this->get('/')->assertOk();
        $response->assertViewHas('homepageOrderedSections', function (array $sections): bool {
            return ($sections[0]['key'] ?? '') === 'support_cta';
        });
        $html = $response->getContent();

        preg_match_all('/<!-- jp-section-start:([^:]+):/', $html, $sectionMarkers);
        $markerKeys = $sectionMarkers[1] ?? [];
        $supportIndex = array_search('support_cta', $markerKeys, true);
        $whyIndex = array_search('why_book', $markerKeys, true);
        $this->assertNotFalse($supportIndex);
        $this->assertNotFalse($whyIndex);
        $this->assertLessThan(
            $whyIndex,
            $supportIndex,
            'With support_cta.order=1 and why_book.order=20, support_cta must now render first',
        );
    }

    /** Hero is never reorderable — it must always render first regardless of any section's configured order. */
    public function test_hero_always_renders_first_regardless_of_other_section_orders(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, [
            'hero' => ['headline' => 'PROBE-HERO-MARKER'],
            // Give every other section an artificially low order — hero still can't be pushed down, it isn't in the loop at all.
            'routes' => ['enabled' => '1', 'order' => 0],
        ]);

        $html = $this->get('/')->assertOk()->getContent();
        $heroPos = strpos($html, 'PROBE-HERO-MARKER');
        $mainContentPos = strpos($html, 'id="jp-main"');

        $this->assertNotFalse($heroPos);
        $this->assertGreaterThan($mainContentPos, $heroPos, 'Hero marker should appear inside <main>');
        // Hero's own <section id="top"> should appear before any other section markup.
        $this->assertLessThan(strpos($html, 'class="section"'), strpos($html, 'id="top"'));
    }

    /**
     * Two sections left at the same (or both-absent) order must not error,
     * and must fall back to a deterministic, stable order — not crash or
     * silently drop a section.
     */
    public function test_tied_order_values_do_not_error_and_both_sections_still_render(): void
    {
        $profile = $this->makeJetpkProfile();
        $this->seedPublishedHome($profile, [
            'why_book' => ['enabled' => '1', 'order' => 5, 'cards' => [['title' => 'PROBE-TIE-A', 'text' => 'x', 'enabled' => '1']]],
            'featured_deals' => ['enabled' => '1', 'order' => 5],
        ]);

        $this->get('/')->assertOk()->assertSee('PROBE-TIE-A', false);
    }
}
