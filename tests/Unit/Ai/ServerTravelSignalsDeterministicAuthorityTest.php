<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\Hybrid\ServerTravelSignals;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * CQ44-PERF-01 — deterministicAuthorityComplete gate matrix.
 */
class ServerTravelSignalsDeterministicAuthorityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-28 12:00:00', 'Asia/Karachi'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_origin_followup_complete_with_prior_destination(): void
    {
        $signals = app(ServerTravelSignals::class);
        $r = $signals->deterministicAuthorityComplete('from Lahore', [
            'destination' => 'DXB',
            'intent' => 'flight_search',
        ]);
        $this->assertTrue($r['complete']);
        $this->assertContains('origin_followup', $r['classes']);
    }

    public function test_destination_led_start_keeps_qwen(): void
    {
        $signals = app(ServerTravelSignals::class);
        $r = $signals->deterministicAuthorityComplete('I need Dubai', null);
        $this->assertFalse($r['complete']);
        $this->assertSame('no_active_travel', $r['reason']);

        // Active travel intent without prior origin must still keep dest-led Qwen.
        $r2 = $signals->deterministicAuthorityComplete('I need Dubai', [
            'intent' => 'flight_search',
            'destination' => null,
            'origin' => null,
        ]);
        $this->assertFalse($r2['complete']);
        $this->assertSame('destination_led_start', $r2['reason']);
    }

    public function test_date_pax_cabin_and_dest_correction(): void
    {
        $signals = app(ServerTravelSignals::class);
        $prior = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'intent' => 'flight_search',
            'depart_date' => '2026-10-02',
        ];

        $date = $signals->deterministicAuthorityComplete('next Friday', $prior);
        $this->assertTrue($date['complete']);
        $this->assertContains('date_refinement', $date['classes']);

        $pax = $signals->deterministicAuthorityComplete('2 adults', $prior);
        $this->assertTrue($pax['complete']);
        $this->assertContains('pax_refinement', $pax['classes']);

        $hum = $signals->deterministicAuthorityComplete('hum dono', $prior);
        $this->assertTrue($hum['complete']);
        $this->assertContains('pax_refinement', $hum['classes']);

        $cabin = $signals->deterministicAuthorityComplete('economy', $prior);
        $this->assertTrue($cabin['complete']);
        $this->assertContains('cabin_refinement', $cabin['classes']);

        $dest = $signals->deterministicAuthorityComplete('Make it Doha', $prior);
        $this->assertTrue($dest['complete']);
        $this->assertContains('destination_correction', $dest['classes']);
    }

    public function test_contextual_return_and_open_jaw_keep_qwen(): void
    {
        $signals = app(ServerTravelSignals::class);
        $prior = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-10-02',
            'intent' => 'flight_search',
        ];

        $ret = $signals->deterministicAuthorityComplete('wapis Sunday', $prior);
        $this->assertTrue($ret['complete']);
        $this->assertContains('return_refinement', $ret['classes']);

        $oj = $signals->deterministicAuthorityComplete(
            'Lahore to Jeddah then Medina to Lahore',
            $prior
        );
        $this->assertFalse($oj['complete']);
        $this->assertSame('open_jaw_multi_leg', $oj['reason']);

        $route = $signals->deterministicAuthorityComplete(
            'Now Islamabad to Dubai next Monday',
            $prior
        );
        $this->assertFalse($route['complete']);
        $this->assertSame('explicit_route_keep_qwen', $route['reason']);
    }
}
