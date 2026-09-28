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
            'trip_type' => 'one_way',
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

        // CQ44-PERF-02: dated clear A→B completes even with prior active travel.
        $route = $signals->deterministicAuthorityComplete(
            'Now Islamabad to Dubai next Monday',
            $prior
        );
        $this->assertTrue($route['complete']);
        $this->assertContains('explicit_route_complete', $route['classes']);
    }

    public function test_fresh_dated_explicit_route_complete_without_active_travel(): void
    {
        $signals = app(ServerTravelSignals::class);
        $r = $signals->deterministicAuthorityComplete('Now Islamabad to Dubai next Monday', null);
        $this->assertTrue($r['complete']);
        $this->assertContains('explicit_route_complete', $r['classes']);
    }

    public function test_via_and_through_block_explicit_route_complete(): void
    {
        $signals = app(ServerTravelSignals::class);
        foreach ([
            'Lahore to Dubai via Doha next Monday',
            'LHE to DXB via DOH next Monday',
            'Lahore to Dubai through Doha next Monday',
            'Lahore to Dubai connecting in Doha next Monday',
            'Lahore to Dubai with a stopover in Doha next Monday',
        ] as $msg) {
            $r = $signals->deterministicAuthorityComplete($msg, null);
            $this->assertFalse($r['complete'], "Expected incomplete for multi-location: {$msg}");
            $this->assertSame('multi_location_requires_semantic', $r['reason'], $msg);
            $this->assertNotContains('explicit_route_complete', $r['classes'] ?? []);
        }
    }

    public function test_airline_and_direct_tokens_do_not_block_explicit_route_complete(): void
    {
        $signals = app(ServerTravelSignals::class);
        foreach ([
            'Lahore to Dubai on Emirates next Monday',
            'Lahore to Dubai direct next Monday',
        ] as $msg) {
            $r = $signals->deterministicAuthorityComplete($msg, null);
            $this->assertTrue($r['complete'], "Expected complete for: {$msg}");
            $this->assertContains('explicit_route_complete', $r['classes']);
        }
    }

    public function test_explicit_route_without_date_keeps_qwen(): void
    {
        $signals = app(ServerTravelSignals::class);
        $r = $signals->deterministicAuthorityComplete('Lahore to Dubai', [
            'origin' => 'KHI',
            'destination' => 'JED',
            'intent' => 'flight_search',
        ]);
        $this->assertFalse($r['complete']);
        $this->assertSame('explicit_route_keep_qwen', $r['reason']);
    }

    public function test_complete_explicit_route_overrides_prior_open_jaw(): void
    {
        $signals = app(ServerTravelSignals::class);
        $prior = [
            'origin' => 'LHE',
            'destination' => 'JED',
            'intent' => 'flight_search',
            'trip_type' => 'open_jaw',
            'legs' => [
                ['origin' => 'LHE', 'destination' => 'JED'],
                ['origin' => 'MED', 'destination' => 'LHE'],
            ],
        ];
        $r = $signals->deterministicAuthorityComplete('Now Islamabad to Dubai next Monday', $prior);
        $this->assertTrue($r['complete']);
        $this->assertContains('explicit_route_complete', $r['classes']);
    }

    public function test_prior_open_jaw_blocks_bare_refinement_short_circuit(): void
    {
        $signals = app(ServerTravelSignals::class);
        $prior = [
            'origin' => 'LHE',
            'destination' => 'JED',
            'intent' => 'flight_search',
            'trip_type' => 'open_jaw',
            'depart_date' => '2026-10-02',
            'legs' => [
                ['origin' => 'LHE', 'destination' => 'JED', 'departure_date' => '2026-10-02'],
                ['origin' => 'MED', 'destination' => 'LHE', 'departure_date' => null],
            ],
        ];

        foreach (['next Friday', 'from Lahore', 'Make it Doha', 'wapis Sunday'] as $msg) {
            $r = $signals->deterministicAuthorityComplete($msg, $prior);
            $this->assertFalse($r['complete'], "Expected incomplete for prior multi-leg: {$msg}");
            $this->assertSame(
                'prior_multi_leg_requires_semantic',
                $r['reason'],
                "Expected prior_multi_leg_requires_semantic for: {$msg}"
            );
        }
    }

    public function test_prior_legs_count_alone_blocks_short_circuit(): void
    {
        $signals = app(ServerTravelSignals::class);
        $prior = [
            'origin' => 'LHE',
            'destination' => 'JED',
            'intent' => 'flight_search',
            // trip_type may be missing/stale; legs>=2 is enough.
            'trip_type' => 'one_way',
            'legs' => [
                ['origin' => 'LHE', 'destination' => 'JED'],
                ['origin' => 'MED', 'destination' => 'LHE'],
            ],
        ];

        $r = $signals->deterministicAuthorityComplete('next Friday', $prior);
        $this->assertFalse($r['complete']);
        $this->assertSame('prior_multi_leg_requires_semantic', $r['reason']);
    }

    public function test_simple_return_trip_type_still_allows_contextual_date(): void
    {
        $signals = app(ServerTravelSignals::class);
        $prior = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-10-02',
            'intent' => 'flight_search',
            'trip_type' => 'return',
            'legs' => [
                ['origin' => 'LHE', 'destination' => 'DXB', 'departure_date' => '2026-10-02'],
            ],
        ];

        $ret = $signals->deterministicAuthorityComplete('wapis Sunday', $prior);
        $this->assertTrue($ret['complete']);
        $this->assertContains('return_refinement', $ret['classes']);
        $this->assertNotSame('prior_multi_leg_requires_semantic', $ret['reason']);
    }
}
