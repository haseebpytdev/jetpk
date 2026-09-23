<?php

namespace App\Services\Suppliers\AmeerEMillat;

use RuntimeException;

class AmeerEMillatProviderException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly int $httpStatus,
        string $safeMessage,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($safeMessage, $httpStatus, $previous);
    }
}
