<?php

namespace App\Services\Suppliers\Iati\Exceptions;

class IatiValidationException extends IatiException
{
    /**
     * @param  list<string>  $missingFields
     */
    public static function passengerPayloadIncomplete(
        array $missingFields,
        ?int $passengerId = null,
        ?int $passengerIndex = null,
    ): self {
        $safeSummary = [
            'error_code' => 'iati_passenger_payload_incomplete',
            'step' => 'passenger_payload_validation',
            'missing_fields' => array_values($missingFields),
        ];

        if ($passengerId !== null) {
            $safeSummary['passenger_id'] = $passengerId;
        }

        if ($passengerIndex !== null) {
            $safeSummary['passenger_index'] = $passengerIndex;
        }

        return new self(
            'iati_passenger_payload_incomplete',
            422,
            'IATI passenger payload is incomplete.',
            $safeSummary,
        );
    }
}
