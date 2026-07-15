<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\PublicFlightSearchRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicFlightSearchRequestMulticityTest extends TestCase
{
    #[Test]
    public function test_multicity_requires_at_least_two_slices(): void
    {
        $data = [
            'trip_type' => 'multi_city',
            'multi_from' => ['LHE'],
            'multi_to' => ['DOH'],
            'multi_depart' => [now()->addDays(14)->format('Y-m-d')],
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ];
        $request = PublicFlightSearchRequest::create('/flights/results', 'GET', $data);
        $validator = Validator::make($request->all(), $request->rules());
        $request->withValidator($validator);

        $this->assertTrue($validator->fails());
    }

    #[Test]
    public function test_multicity_rejects_non_chronological_dates(): void
    {
        $data = [
            'trip_type' => 'multi_city',
            'multi_from' => ['LHE', 'DOH'],
            'multi_to' => ['DOH', 'LHE'],
            'multi_depart' => [
                now()->addDays(21)->format('Y-m-d'),
                now()->addDays(14)->format('Y-m-d'),
            ],
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ];
        $request = PublicFlightSearchRequest::create('/flights/results', 'GET', $data);
        $validator = Validator::make($request->all(), $request->rules());
        $request->withValidator($validator);

        $this->assertTrue($validator->fails());
    }
}
