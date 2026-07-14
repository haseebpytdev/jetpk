<?php

namespace Tests\Unit;

use App\Services\Suppliers\Sabre\SabreBookingPayloadBuilder;
use App\Support\Suppliers\SabreTraditionalCpnrIatiWireStructureDiagnostic;
use Tests\TestCase;

class SabreTraditionalCpnrIatiWireStructureDiagnosticTest extends TestCase
{
    public function test_iati_template_includes_special_service_paths_missing_from_ota_wire(): void
    {
        /** @var SabreBookingPayloadBuilder $builder */
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = [
            '_valid' => true,
            'supplier_connection_id' => 0,
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2030-01-01T08:00:00Z',
                'arrival_at' => '2030-01-01T10:00:00Z',
                'carrier' => 'EK',
                'operating_airline_code' => 'EK',
                'flight_number' => '615',
                'booking_class' => 'K',
            ]],
            'passengers' => [[
                'first_name' => 'Test',
                'last_name' => 'User',
                'type' => 'adult',
            ]],
            'contact' => ['email' => 't@example.com', 'phone' => '1234567890'],
            'fare' => ['amount' => 100, 'currency' => 'USD'],
        ];
        $wire = $builder->buildTraditionalPnrCreatePassengerNameRecordV1Wire($draft, []);
        $cpnr = $wire['CreatePassengerNameRecordRQ'];
        $out = SabreTraditionalCpnrIatiWireStructureDiagnostic::analyze($cpnr);

        $onlyIati = $out['key_paths_only_in_iati_template'];
        $this->assertNotEmpty($onlyIati);
        $this->assertTrue(
            count(array_filter($onlyIati, static fn (string $p): bool => str_starts_with($p, 'SpecialReqDetails.SpecialService'))) > 3
        );

        $onlyOta = $out['key_paths_only_in_ota_wire'];
        $this->assertTrue(
            count(array_filter($onlyOta, static fn (string $p): bool => str_contains($p, 'MarriageGrp') || str_contains($p, 'OperatingAirline'))) >= 1
        );

        $this->assertFalse($out['enhanced_airbook']['present_in_ota_wire']);
        $this->assertFalse($out['enhanced_airbook']['present_in_iati_reference']);
        $this->assertSame('2.5.0', $out['cpnr_version']['ota_wire_value']);
        $this->assertSame('2.4.0', $out['cpnr_version']['iati_operational_template']);
    }
}
