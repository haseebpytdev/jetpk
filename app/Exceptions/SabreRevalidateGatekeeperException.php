<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a Sabre revalidate request payload fails pre-flight production gatekeeper checks.
 */
final class SabreRevalidateGatekeeperException extends RuntimeException
{
    /**
     * @param  list<string>  $violations  Safe, customer-free violation codes/messages
     */
    public function __construct(
        string $failureClass,
        public readonly array $violations = [],
    ) {
        parent::__construct('Sabre revalidation gatekeeper blocked: '.$failureClass);
    }
}
