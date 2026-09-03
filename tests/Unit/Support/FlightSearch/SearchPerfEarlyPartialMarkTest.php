<?php

namespace Tests\Unit\Support\FlightSearch;

use App\Support\FlightSearch\SearchPerfTrace;
use Tests\TestCase;

class SearchPerfEarlyPartialMarkTest extends TestCase
{
    public function test_early_partial_publish_mark_appears_in_public_meta(): void
    {
        $perf = new SearchPerfTrace('early-partial-test');
        $perf->mark('T13_FIRST_SUPPLIER_NETWORK_CALL_START');
        usleep(1000);
        $perf->mark('T_FIRST_EARLY_PARTIAL_PUBLISH');

        $public = $perf->publicMeta();

        $this->assertArrayHasKey('FIRST_EARLY_PARTIAL_PUBLISH_MS', $public);
        $this->assertNotNull($public['FIRST_EARLY_PARTIAL_PUBLISH_MS']);
        $this->assertGreaterThan(0, (float) $public['FIRST_EARLY_PARTIAL_PUBLISH_MS']);
    }
}
