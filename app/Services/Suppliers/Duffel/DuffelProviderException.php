<?php

namespace App\Services\Suppliers\Duffel;

use RuntimeException;

class DuffelProviderException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $normalizedCode,
        public readonly int $httpStatus,
        public readonly string $safeMessage,
        public readonly array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($safeMessage, $httpStatus, $previous);
    }
}
