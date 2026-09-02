<?php

namespace Tests\Unit\Support\FlightSearch;

use App\Support\FlightSearch\SearchPerfTrace;
use Tests\TestCase;

class SearchPerfTraceTest extends TestCase
{
    public function test_component_timers_are_non_negative_and_include_search_perf_id(): void
    {
        $perf = new SearchPerfTrace('test-perf-id');
        $perf->mark('T1_REQUEST_VALIDATION_COMPLETE');
        usleep(1000);
        $perf->mark('T2_SEARCH_CONTEXT_READY');
        $perf->mark('T3_AUTH_CONTEXT_READY');
        $perf->mark('T4_FEATURE_FLAGS_READY');
        $perf->mark('T5_AIRPORT_ROUTE_NORMALIZATION_READY');
        $perf->mark('T6_PROVIDER_REGISTRY_READY');
        $perf->mark('T7_PROVIDER_ELIGIBILITY_COMPLETE');
        $perf->mark('T8_COMMERCIAL_RULES_READY');
        $perf->mark('T9_MARKUPS_READY');
        $perf->mark('T10_CURRENCY_CONTEXT_READY');
        $perf->mark('T11_PROVIDER_REQUEST_BUILD_COMPLETE');
        $perf->mark('T12_ORCHESTRATOR_DISPATCH_START');
        $perf->mark('T13_FIRST_SUPPLIER_NETWORK_CALL_START');
        $perf->recordProvider([
            'provider' => 'sabre',
            'eligible' => true,
            'network_start_offset_ms' => 12.5,
            'dispatch_start_offset_ms' => 12.0,
        ]);

        $summary = $perf->summary();
        $this->assertSame('test-perf-id', $summary['search_perf_id']);
        $this->assertGreaterThanOrEqual(0, $summary['REQUEST_VALIDATION_MS']);
        $this->assertGreaterThanOrEqual(0, $summary['TOTAL_PRE_SUPPLIER_MS']);
        $this->assertSame('SEQUENTIAL', $summary['SUPPLIER_DISPATCH_MODE']);
        $this->assertSame(12.5, $summary['FIRST_PROVIDER_NETWORK_START_MS']);

        $public = $perf->publicMeta();
        $this->assertArrayHasKey('search_perf_id', $public);
        $this->assertArrayNotHasKey('queries', $public);
    }
}
