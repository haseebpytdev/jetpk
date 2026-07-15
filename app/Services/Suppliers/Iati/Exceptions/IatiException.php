<?php

namespace App\Services\Suppliers\Iati\Exceptions;

use RuntimeException;

class IatiException extends RuntimeException
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
