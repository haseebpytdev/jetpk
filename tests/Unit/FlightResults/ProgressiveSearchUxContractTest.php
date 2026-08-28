<?php

namespace Tests\Unit\FlightResults;

use Tests\TestCase;

/**
 * Frontend progressive merge + messaging contracts (Node-free PHP mirror of key invariants).
 * JS unit behavior is additionally covered by Playwright where available.
 */
class ProgressiveSearchUxContractTest extends TestCase
{
    public function test_pending_warning_copy_is_absent_from_staged_messages(): void
    {
        $path = base_path('frontend/features/flight-results/hooks/use-flight-results.ts');
        $source = file_get_contents($path);
        $this->assertIsString($source);

        $this->assertStringContainsString('Updating fares…', $source);
        $this->assertStringNotContainsString(
            'Some airlines did not finish responding. Showing available flights.',
            $source,
        );
        $this->assertStringContainsString(
            'Some additional airline fares are temporarily unavailable.',
            $source,
        );
    }

    public function test_default_sort_is_cheapest(): void
    {
        $path = base_path('frontend/features/flight-results/utils/sorting.ts');
        $source = file_get_contents($path);
        $this->assertIsString($source);
        $this->assertStringContainsString('DEFAULT_UI_SORT: UiSortKey = "lowest_price"', $source);
        $this->assertStringContainsString('DEFAULT_LARAVEL_SORT = "cheapest"', $source);
    }

    public function test_search_payload_defaults_pair_and_cheapest(): void
    {
        $path = base_path('frontend/features/search/utils/laravel-payload.ts');
        $source = file_get_contents($path);
        $this->assertIsString($source);
        $this->assertStringContainsString('params.set("sort", "cheapest")', $source);
        $this->assertStringContainsString('params.set("view", "pair")', $source);
    }

    public function test_revalidation_accept_loop_is_bounded(): void
    {
        $path = base_path('frontend/features/flight-details/hooks/use-revalidation.ts');
        $source = file_get_contents($path);
        $this->assertIsString($source);
        $this->assertStringContainsString('MAX_FARE_CHANGE_ACCEPTS = 2', $source);
    }
}
