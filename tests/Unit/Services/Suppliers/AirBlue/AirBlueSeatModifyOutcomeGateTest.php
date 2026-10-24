<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Services\Suppliers\AirBlue\AirBlueOtaXmlParser;
use App\Services\Suppliers\AirBlue\AirBlueSeatModifyOutcomeGate;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueValidationException;
use Tests\TestCase;

class AirBlueSeatModifyOutcomeGateTest extends TestCase
{
    public function test_success_with_seat_request_is_attached(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/airblue/zapways/v3/ota_air_book_modify_seat_success.xml'));
        $this->assertIsString($xml);
        $parsed = (new AirBlueOtaXmlParser)->parse($xml);
        $gate = new AirBlueSeatModifyOutcomeGate;

        $this->assertTrue($gate->hasAttachedSeat($parsed));
        $gate->assertSeatAttached($parsed, ['row_number' => '27', 'seat_number' => 'F']);
        $this->assertSame('F', $gate->reservedSeats($parsed)[0]['seat_number']);
    }

    public function test_success_without_seat_request_fails_closed(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/airblue/zapways/v3/ota_air_book_modify_seat_false_success.xml'));
        $this->assertIsString($xml);
        $parsed = (new AirBlueOtaXmlParser)->parse($xml);
        $gate = new AirBlueSeatModifyOutcomeGate;

        $this->assertFalse($gate->hasAttachedSeat($parsed));
        $this->expectException(AirBlueValidationException::class);
        $this->expectExceptionMessage('seat modify/read did not return an attached SeatRequest');
        $gate->assertSeatAttached($parsed);
    }

    public function test_expected_seat_mismatch_fails_closed(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/airblue/zapways/v3/ota_air_book_modify_seat_success.xml'));
        $this->assertIsString($xml);
        $parsed = (new AirBlueOtaXmlParser)->parse($xml);
        $gate = new AirBlueSeatModifyOutcomeGate;

        $this->expectException(AirBlueValidationException::class);
        $gate->assertSeatAttached($parsed, ['row_number' => '1', 'seat_number' => 'A']);
    }
}
