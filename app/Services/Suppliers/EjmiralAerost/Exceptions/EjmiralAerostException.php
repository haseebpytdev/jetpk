<?php

namespace App\Services\Suppliers\EjmiralAerost\Exceptions;

use RuntimeException;
use Throwable;

class EjmiralAerostException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $errorCode,
        int $httpStatus = 500,
        string $message = '',
        public readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : $errorCode, $httpStatus, $previous);
    }
}
