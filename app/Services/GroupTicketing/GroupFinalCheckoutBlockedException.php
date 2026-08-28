<?php

namespace App\Services\GroupTicketing;

use RuntimeException;

/**
 * Thrown when final group availability/fare decision blocks payment or supplier mutation.
 *
 * @phpstan-type DecisionArray array{
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
class GroupFinalCheckoutBlockedException extends RuntimeException
{
    /**
     * @param  DecisionArray  $decision
     */
    public function __construct(
        public readonly array $decision,
    ) {
        $message = (string) (($decision['modal']['body'] ?? null) ?: 'Group checkout availability changed.');
        parent::__construct($message);
    }
}
