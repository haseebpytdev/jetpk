<?php

namespace Tests\Unit\Support\Sabre;

use App\Support\Sabre\SabreControlledPnrFinalReadinessDiagnostics;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SabreControlledPnrFinalReadinessDiagnosticsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ota.controlled_final_pnr_freshness.max_minutes' => 15]);
    }

    public function test_evaluate_final_freshness_blocks_when_older_than_threshold(): void
    {
        Carbon::setTestNow('2026-06-18 15:00:00');

        $result = SabreControlledPnrFinalReadinessDiagnostics::evaluateFinalFreshness(
            '2026-06-18T13:54:41+00:00',
            '2026-06-18T13:54:41+00:00',
            '2026-06-18T13:54:41+00:00',
        );

        $this->assertFalse($result['final_freshness_ready']);
        $this->assertSame(['final_refresh_required'], $result['final_freshness_blockers']);
        $this->assertSame(65, $result['minutes_since_revalidation']);

        Carbon::setTestNow();
    }

    public function test_evaluate_final_freshness_passes_inside_threshold(): void
    {
        Carbon::setTestNow('2026-06-18 14:10:00');

        $result = SabreControlledPnrFinalReadinessDiagnostics::evaluateFinalFreshness(
            '2026-06-18T14:00:00+00:00',
            null,
            null,
        );

        $this->assertTrue($result['final_freshness_ready']);
        $this->assertSame([], $result['final_freshness_blockers']);
        $this->assertSame(10, $result['minutes_since_revalidation']);

        Carbon::setTestNow();
    }

    public function test_evaluate_final_freshness_blocks_when_anchor_missing(): void
    {
        $result = SabreControlledPnrFinalReadinessDiagnostics::evaluateFinalFreshness(null, null, null);

        $this->assertFalse($result['final_freshness_ready']);
        $this->assertSame(['final_refresh_required'], $result['final_freshness_blockers']);
    }
}
