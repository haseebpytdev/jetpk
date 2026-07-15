<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\PublicFlightSearchRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PublicFlightSearchRequestFiltersTest extends TestCase
{
    public function test_criteria_maps_direct_and_nearby_checkboxes(): void
    {
        $request = PublicFlightSearchRequest::create('/flights/results', 'GET', [
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'from' => 'LHE',
            'to' => 'JED',
            'depart' => now()->addDays(10)->format('Y-m-d'),
            'stops' => 'direct',
            'include_nearby' => '1',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));

        Validator::make($request->all(), $request->rules())->validate();

        $criteria = $request->criteria();

        $this->assertTrue($criteria['direct_only']);
        $this->assertTrue($criteria['nearby_airports']);
        $this->assertSame('LHE', $criteria['origin']);
        $this->assertSame('JED', $criteria['destination']);
    }

    public function test_criteria_defaults_filters_to_false_when_absent(): void
    {
        $request = PublicFlightSearchRequest::create('/flights/results', 'GET', [
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'from' => 'LHE',
            'to' => 'JED',
            'depart' => now()->addDays(10)->format('Y-m-d'),
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));

        Validator::make($request->all(), $request->rules())->validate();

        $criteria = $request->criteria();

        $this->assertFalse($criteria['direct_only']);
        $this->assertFalse($criteria['nearby_airports']);
    }
}
