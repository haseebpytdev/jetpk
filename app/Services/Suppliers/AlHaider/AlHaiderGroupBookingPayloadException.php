<?php

namespace App\Services\Suppliers\AlHaider;

use InvalidArgumentException;

/**
 * Fail-closed validation error before any Al-Haider create/booking mutation.
 */
class AlHaiderGroupBookingPayloadException extends InvalidArgumentException
{
    public function __construct(
        public readonly string $errorCode,
        string $userSafeMessage,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($userSafeMessage, 0, $previous);
    }
}
