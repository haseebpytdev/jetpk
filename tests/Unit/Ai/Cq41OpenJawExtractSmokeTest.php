<?php

namespace Tests\Unit\Ai;

use App\Data\Ai\SemanticPlan;
use App\Services\Ai\Hybrid\LocationResolver;
use App\Services\Ai\Semantic\SemanticPlanValidator;
use Tests\TestCase;

class Cq41OpenJawExtractSmokeTest extends TestCase
{
    public function test_extract_and_validate_override_wrong_second_leg(): void
    {
        $locations = app(LocationResolver::class);
        $msg = 'Lahore to Jeddah then Medina to Lahore';
        $extracted = $locations->extractOpenJawLegs(mb_strtolower($msg), $msg);
        $this->assertNotNull($extracted);
        $this->assertSame('MED', $extracted[1]['origin']);
        $this->assertSame('LHE', $extracted[1]['destination']);

        $plan = SemanticPlan::fromModelArray([
            'domain' => 'travel',
            'intent' => 'open_jaw',
            'operation' => 'clarify',
            'travel' => [
                'trip_type' => 'open_jaw',
                'origin' => 'LHE',
                'destination' => 'JED',
                'legs' => [
                    ['origin' => 'LHE', 'destination' => 'JED', 'departure_date' => null],
                    ['origin' => 'JED', 'destination' => 'MED', 'departure_date' => null],
                ],
                'adults' => 1,
                'children' => 0,
                'infants' => 0,
            ],
            'missing' => ['leg1_departure_date', 'leg2_departure_date'],
            'references' => ['active_search' => false, 'pending_confirmation' => false],
            'corrections' => [],
            'response_intent' => 'need_dates',
        ]);

        $validated = app(SemanticPlanValidator::class)->validate($plan, [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'trip_type' => 'one_way',
        ], $msg);

        $this->assertTrue($validated['valid'], json_encode($validated['rejects'] ?? []));
        $this->assertNotNull($validated['intent']);
        $legs = $validated['intent']->legs;
        $this->assertIsArray($legs);
        $this->assertCount(2, $legs);
        $this->assertSame('LHE', $legs[0]['origin']);
        $this->assertSame('JED', $legs[0]['destination']);
        $this->assertSame('MED', $legs[1]['origin']);
        $this->assertSame('LHE', $legs[1]['destination']);
        $this->assertSame('open_jaw', $validated['intent']->tripType);
    }
}
