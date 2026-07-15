<?php

namespace Tests\Unit\GroupTicketing;

use App\Services\Suppliers\AlHaider\AlHaiderPackageNormalizer;
use App\Support\GroupTicketing\AlHaiderProviderPayloadInspector;
use Tests\TestCase;

class AlHaiderProviderPayloadInspectorTest extends TestCase
{
    public function test_inspect_reports_meal_and_leg_times_from_sample_rows(): void
    {
        $rows = [[
            'id' => '101',
            'sector' => 'SKT-SHJ',
            'dept_date' => '2026-06-21',
            'arv_date' => '2026-06-28',
            'price' => 99000,
            'meal' => 'yes',
            'baggage' => '20+10',
            'available_no_of_pax' => 8,
            'details' => [[
                'flight_no' => 'G9421',
                'dept_time' => '1430',
                'arv_time' => '1805',
                'origin' => 'SKT',
                'destination' => 'SHJ',
            ]],
        ]];

        $inspector = new AlHaiderProviderPayloadInspector(new AlHaiderPackageNormalizer);
        $report = $inspector->inspect($rows, 1);

        $this->assertTrue($report['field_matrix']['meal']['present']);
        $this->assertTrue($report['field_matrix']['departure_time']['present']);
        $this->assertTrue($report['field_matrix']['arrival_time']['present']);
        $this->assertTrue($report['field_matrix']['flight_number']['present']);
        $this->assertSame('Included', $report['normalized_samples'][0]['meal']);
        $this->assertSame('14:30', $report['normalized_samples'][0]['legs'][0]['departure_time']);
    }

    public function test_inspect_marks_missing_fields_when_absent(): void
    {
        $rows = [[
            'id' => '102',
            'sector' => 'LHE-DMM',
            'dept_date' => '2026-07-01',
            'price' => 85000,
        ]];

        $inspector = new AlHaiderProviderPayloadInspector(new AlHaiderPackageNormalizer);
        $report = $inspector->inspect($rows, 1);

        $this->assertFalse($report['field_matrix']['meal']['present']);
        $this->assertFalse($report['field_matrix']['departure_time']['present']);
        $this->assertContains('meal', $report['missing_expected']);
    }
}
