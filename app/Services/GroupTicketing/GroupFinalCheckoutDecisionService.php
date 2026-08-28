<?php

namespace App\Services\GroupTicketing;

/**
 * Pure final-availability / fare decision before group payment or supplier allocation.
 *
 * Does not call suppliers. Callers must pass freshly revalidated seat and fare values.
 */
class GroupFinalCheckoutDecisionService
{
    public const DECISION_OK = 'ok';

    public const DECISION_SOLD_OUT = 'sold_out';

    public const DECISION_REDUCE_SEATS = 'reduce_seats';

    public const DECISION_FARE_CHANGED = 'fare_changed';

    public const DECISION_SEATS_AND_FARE_CHANGED = 'seats_and_fare_changed';

    /**
     * @return array{
     *     decision: string,
     *     requested_seats: int,
     *     available_seats: int,
     *     currency: string,
     *     quoted_unit_price: float,
     *     fresh_unit_price: float,
     *     revised_total: float|null,
     *     allow_supplier_mutation: bool,
     *     allow_payment: bool,
     *     require_explicit_passenger_reduction: bool,
     *     modal: array{title: string, body: string, primary_action: string|null, secondary_action: string}|null
     * }
     */
    public function decide(
        int $requestedSeats,
        int $availableSeats,
        float $quotedUnitPrice,
        float $freshUnitPrice,
        string $currency = 'PKR',
        bool $acceptFareChange = false,
    ): array {
        $requestedSeats = max(0, $requestedSeats);
        $availableSeats = max(0, $availableSeats);
        $quotedUnitPrice = round($quotedUnitPrice, 2);
        $freshUnitPrice = round($freshUnitPrice, 2);
        $fareChanged = abs($quotedUnitPrice - $freshUnitPrice) > 0.009;
        $currency = strtoupper(trim($currency) !== '' ? $currency : 'PKR');

        if ($availableSeats === 0 || $requestedSeats < 1) {
            return $this->result(
                self::DECISION_SOLD_OUT,
                $requestedSeats,
                $availableSeats,
                $currency,
                $quotedUnitPrice,
                $freshUnitPrice,
                null,
                false,
                false,
                false,
                [
                    'title' => 'Availability changed',
                    'body' => 'Sorry, this group has just sold out.',
                    'primary_action' => null,
                    'secondary_action' => 'Choose another group',
                ],
            );
        }

        if ($availableSeats < $requestedSeats) {
            $decision = $fareChanged && ! $acceptFareChange
                ? self::DECISION_SEATS_AND_FARE_CHANGED
                : self::DECISION_REDUCE_SEATS;
            $revisedTotal = round($freshUnitPrice * $availableSeats, 2);
            $seatLabel = $availableSeats === 1 ? 'seat' : 'seats';
            $body = $decision === self::DECISION_SEATS_AND_FARE_CHANGED
                ? sprintf(
                    'Only %d %s are now available, and the fare changed from %s %s to %s %s per seat. Revised total for %d %s is %s %s. Would you like to continue with the remaining seats or choose another group?',
                    $availableSeats,
                    $seatLabel,
                    $currency,
                    number_format($quotedUnitPrice, 0),
                    $currency,
                    number_format($freshUnitPrice, 0),
                    $availableSeats,
                    $seatLabel,
                    $currency,
                    number_format($revisedTotal, 0),
                )
                : sprintf(
                    'Only %d %s are now available for this group. We\'re sorry for the inconvenience. Would you like to book the remaining seats or choose another group?',
                    $availableSeats,
                    $seatLabel,
                );

            return $this->result(
                $decision,
                $requestedSeats,
                $availableSeats,
                $currency,
                $quotedUnitPrice,
                $freshUnitPrice,
                $revisedTotal,
                false,
                false,
                true,
                [
                    'title' => 'Availability changed',
                    'body' => $body,
                    'primary_action' => sprintf('Book remaining %d %s', $availableSeats, $seatLabel),
                    'secondary_action' => 'Choose another group',
                ],
            );
        }

        if ($fareChanged && ! $acceptFareChange) {
            $revisedTotal = round($freshUnitPrice * $requestedSeats, 2);

            return $this->result(
                self::DECISION_FARE_CHANGED,
                $requestedSeats,
                $availableSeats,
                $currency,
                $quotedUnitPrice,
                $freshUnitPrice,
                $revisedTotal,
                false,
                false,
                false,
                [
                    'title' => 'Fare updated',
                    'body' => sprintf(
                        'The fare changed from %s %s to %s %s per seat. New total for %d seat(s) is %s %s.',
                        $currency,
                        number_format($quotedUnitPrice, 0),
                        $currency,
                        number_format($freshUnitPrice, 0),
                        $requestedSeats,
                        $currency,
                        number_format($revisedTotal, 0),
                    ),
                    'primary_action' => 'Accept updated fare',
                    'secondary_action' => 'Choose another group',
                ],
            );
        }

        return $this->result(
            self::DECISION_OK,
            $requestedSeats,
            $availableSeats,
            $currency,
            $quotedUnitPrice,
            $freshUnitPrice,
            round($freshUnitPrice * $requestedSeats, 2),
            true,
            true,
            false,
            null,
        );
    }

    /**
     * @param  array{title: string, body: string, primary_action: string|null, secondary_action: string}|null  $modal
     * @return array{
     *     decision: string,
     *     requested_seats: int,
     *     available_seats: int,
     *     currency: string,
     *     quoted_unit_price: float,
     *     fresh_unit_price: float,
     *     revised_total: float|null,
     *     allow_supplier_mutation: bool,
     *     allow_payment: bool,
     *     require_explicit_passenger_reduction: bool,
     *     modal: array{title: string, body: string, primary_action: string|null, secondary_action: string}|null
     * }
     */
    private function result(
        string $decision,
        int $requestedSeats,
        int $availableSeats,
        string $currency,
        float $quotedUnitPrice,
        float $freshUnitPrice,
        ?float $revisedTotal,
        bool $allowSupplierMutation,
        bool $allowPayment,
        bool $requireExplicitPassengerReduction,
        ?array $modal,
    ): array {
        return [
            'decision' => $decision,
            'requested_seats' => $requestedSeats,
            'available_seats' => $availableSeats,
            'currency' => $currency,
            'quoted_unit_price' => $quotedUnitPrice,
            'fresh_unit_price' => $freshUnitPrice,
            'revised_total' => $revisedTotal,
            'allow_supplier_mutation' => $allowSupplierMutation,
            'allow_payment' => $allowPayment,
            'require_explicit_passenger_reduction' => $requireExplicitPassengerReduction,
            'modal' => $modal,
        ];
    }
}
