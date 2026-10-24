<?php

namespace App\Services\Suppliers\AirBlue;

use App\Services\Suppliers\AirBlue\Exceptions\AirBlueValidationException;

/**
 * Fail-closed assessment of Zapways v3 AirBookModify (Type=5) / Read seat state.
 *
 * HTTP 200 + empty Errors is not enough: official v3.00 docs require seats in
 * SpecialReqDetails/SeatRequests (same shape as Read). Booking-D certification
 * observed a Success-shaped modify followed by a Read with zero SeatRequest nodes.
 */
class AirBlueSeatModifyOutcomeGate
{
    /**
     * @param  array<string, mixed>  $otaResponse  Parsed AirBlueClient::callOta payload
     * @return list<array<string, mixed>>
     */
    public function reservedSeats(array $otaResponse): array
    {
        $parsed = is_array($otaResponse['parsed'] ?? null) ? $otaResponse['parsed'] : [];
        $seats = is_array($parsed['seats'] ?? null) ? $parsed['seats'] : [];

        return array_values(array_filter(
            $seats,
            static function (mixed $seat): bool {
                if (! is_array($seat)) {
                    return false;
                }

                return trim((string) ($seat['seat_number'] ?? '')) !== ''
                    && trim((string) ($seat['row_number'] ?? '')) !== '';
            },
        ));
    }

    /**
     * @param  array<string, mixed>  $otaResponse
     */
    public function hasAttachedSeat(array $otaResponse): bool
    {
        return $this->reservedSeats($otaResponse) !== [];
    }

    /**
     * @param  array<string, mixed>  $otaResponse
     * @param  array{row_number?: string, seat_number?: string}|null  $expected
     */
    public function assertSeatAttached(array $otaResponse, ?array $expected = null): void
    {
        $seats = $this->reservedSeats($otaResponse);
        if ($seats === []) {
            throw new AirBlueValidationException(
                'seat_modify_not_applied',
                422,
                'AirBlue Zapways seat modify/read did not return an attached SeatRequest.',
            );
        }

        if ($expected === null) {
            return;
        }

        $wantRow = trim((string) ($expected['row_number'] ?? ''));
        $wantSeat = trim((string) ($expected['seat_number'] ?? ''));
        if ($wantRow === '' || $wantSeat === '') {
            return;
        }

        foreach ($seats as $seat) {
            if (trim((string) ($seat['row_number'] ?? '')) === $wantRow
                && trim((string) ($seat['seat_number'] ?? '')) === $wantSeat) {
                return;
            }
        }

        throw new AirBlueValidationException(
            'seat_modify_mismatch',
            422,
            'AirBlue Zapways seat modify/read did not include the expected seat assignment.',
        );
    }
}
