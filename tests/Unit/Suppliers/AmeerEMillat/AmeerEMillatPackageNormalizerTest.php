<?php

namespace Tests\Unit\Suppliers\AmeerEMillat;

use App\Services\Suppliers\AmeerEMillat\AmeerEMillatPackageNormalizer;
use Tests\TestCase;

class AmeerEMillatPackageNormalizerTest extends TestCase
{
    public function test_normalizes_group_row_with_airline_map_and_seats(): void
    {
        $normalizer = new AmeerEMillatPackageNormalizer;

        $package = $normalizer->normalize([
            'id' => '501',
            'sector' => 'SKT-SHJ',
            'dept_date' => '2026-06-21',
            'arv_date' => '2026-06-28',
            'type' => 'Umrah',
            'price' => 99000,
            'price_child' => 85000,
            'available_no_of_pax' => 8,
            'meal' => 'yes',
            'baggage' => '20+10',
            'airline_id' => 3,
            'details' => [[
                'flight_no' => 'G9421',
                'dept_time' => '1430',
                'arv_time' => '1805',
                'origin' => 'SKT',
                'destination' => 'SHJ',
            ]],
        ], [
            3 => ['name' => 'Air Arabia', 'short_name' => 'G9', 'logo' => 'https://cdn.example/air.png'],
        ]);

        $this->assertSame('ameer_e_millat', $package->supplier);
        $this->assertSame('AEM-501', $package->public_id);
        $this->assertSame('SKT', $package->departure_city);
        $this->assertSame('SHJ', $package->destination);
        $this->assertSame(8, $package->seats_available);
        $this->assertSame('Included', $package->meal);
        $this->assertSame('Air Arabia', $package->airline);
        $this->assertSame('14:30', $package->legs[0]['departure_time']);
    }

    public function test_seat_count_uses_minimum_leg_seats_when_row_field_missing(): void
    {
        $normalizer = new AmeerEMillatPackageNormalizer;

        $package = $normalizer->normalize([
            'id' => '502',
            'sector' => 'LHE-DXB',
            'dept_date' => '2026-07-01',
            'price' => 120000,
            'available_seats' => 4,
            'details' => [
                ['flight_no' => 'PK301'],
                ['flight_no' => 'PK302'],
            ],
        ]);

        $this->assertSame(4, $package->seats_available);
        $this->assertSame('available', $package->availability_status);
    }
}
