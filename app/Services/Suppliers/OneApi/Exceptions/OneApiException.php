<?php

namespace App\Services\Suppliers\OneApi\Exceptions;

use Exception;
use Throwable;

class OneApiException extends Exception
{
    public function __construct(
        public readonly string $normalizedCode,
        public readonly int $httpStatus,
        public readonly string $safeMessage,
        public readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($safeMessage, $httpStatus, $previous);
    }
}
