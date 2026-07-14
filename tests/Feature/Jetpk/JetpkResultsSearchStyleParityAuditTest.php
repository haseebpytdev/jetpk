<?php

namespace Tests\Feature\Jetpk;

use App\Support\Audits\JetpkResultsSearchStyleParityAudit;
use Tests\TestCase;

class JetpkResultsSearchStyleParityAuditTest extends TestCase
{
    public function test_results_search_style_parity_audit_passes(): void
    {
        $this->artisan('jetpk:results-search-style-parity-audit')
            ->assertSuccessful();
    }

    public function test_audit_detects_unscoped_bootstrap_btn_rules(): void
    {
        $audit = new JetpkResultsSearchStyleParityAudit;
        $result = $audit->run();

        $this->assertTrue($result['bootstrap_scoped']);
        $this->assertSame(0, $result['fail'], implode("\n", $result['violations']));
    }

    public function test_results_and_home_both_reference_canonical_home_flights_search(): void
    {
        $resultsPartial = file_get_contents(resource_path('views/frontend/flights/partials/results-page.blade.php'));
        $hero = file_get_contents(resource_path('views/themes/frontend/jetpakistan/sections/hero.blade.php'));
        $canonical = file_get_contents(resource_path('views/themes/frontend/jetpakistan/components/search/home-flights-search.blade.php'));

        $this->assertIsString($resultsPartial);
        $this->assertIsString($hero);
        $this->assertIsString($canonical);
        $this->assertStringContainsString('components.search.home-flights-search', $resultsPartial);
        $this->assertStringContainsString('components.search.home-flights-search', $hero);
        $this->assertStringContainsString('components.search.search-shell', $canonical);
        $this->assertStringNotContainsString('components.search.search-shell', $hero);
        $this->assertStringNotContainsString('jp-results-search"', $resultsPartial);
        $this->assertStringContainsString('jp-results-search-placement', $resultsPartial);
    }

    public function test_canonical_reuse_audit_passes(): void
    {
        $audit = new JetpkResultsSearchStyleParityAudit;
        $result = $audit->runCanonicalReuse();

        $this->assertSame(0, $result['fail'], implode("\n", $result['violations']));
    }

    public function test_compiled_results_search_uses_canonical_dom_markers(): void
    {
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);

        $html = view('themes.frontend.jetpakistan.components.search.home-flights-search', [
            'context' => 'results',
            'criteria' => [
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => '2026-07-31',
                'trip_type' => 'one_way',
                'adults' => 1,
                'children' => 0,
                'infants' => 0,
                'cabin' => 'economy',
            ],
            'inlineDisplay' => [
                'origin_subtitle' => 'Lahore, Pakistan',
                'destination_subtitle' => 'Dubai, United Arab Emirates',
            ],
            'minDate' => '2026-07-13',
        ])->render();

        $this->assertSame(1, substr_count($html, 'id="jp-flight-search"'));
        $this->assertStringContainsString('jp-date-field', $html);
        $this->assertStringContainsString('jp-pax-field', $html);
        $this->assertStringContainsString('btn-search', $html);
        $this->assertStringContainsString('data-jp-flight-form', $html);
        $this->assertStringNotContainsString('data-inline-search', $html);
        $this->assertStringNotContainsString('ota-hero-search-card', $html);
    }
}
