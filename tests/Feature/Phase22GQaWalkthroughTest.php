<?php

namespace Tests\Feature;

use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase22GQaWalkthroughTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_fares_band_has_no_numeric_fare_amounts(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $html = $this->get('/')->assertOk()->getContent();
        if (preg_match('/id="fares"[\s\S]*?<\/section>/', $html, $matches)) {
            $section = $matches[0];
            $this->assertDoesNotMatchRegularExpression('/Rs\.?\s*[0-9]{2,}/', $section);
            $this->assertDoesNotMatchRegularExpression('/PKR\s*[0-9]{2,}/i', $section);
        }
    }

    public function test_standalone_flight_search_form_has_no_lhe_dxb_prefill_without_query(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $html = $this->get(route('flights.search'))->assertOk()->getContent();
        $this->assertStringNotContainsString('value="LHE"', $html);
        $this->assertStringNotContainsString('value="DXB"', $html);
    }

    public function test_airport_autocomplete_returns_nonempty_labels(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $rows = $this->getJson('/airports/search?q=LHE')->assertOk()->json();
        $this->assertNotSame([], $rows);
        foreach ($rows as $row) {
            $this->assertNotSame('', trim((string) ($row['label'] ?? '')));
            $this->assertMatchesRegularExpression('/\S/', (string) ($row['label'] ?? ''));
        }
    }

    public function test_multi_city_rejects_past_segment_dates(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $past = now()->subDays(3)->format('Y-m-d');
        $this->get('/flights/results?trip_type=multi_city&cabin=economy&adults=1&children=0&infants=0&multi_from[]=LHE&multi_from[]=DXB&multi_to[]=DXB&multi_to[]=JED&multi_depart[]='.$past.'&multi_depart[]='.now()->addDays(10)->format('Y-m-d'))
            ->assertRedirect(route('flights.search'));
    }
}
