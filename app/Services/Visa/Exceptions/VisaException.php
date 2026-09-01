<?php

namespace App\Services\Visa\Exceptions;

use RuntimeException;

abstract class VisaException extends RuntimeException
{
    abstract public function errorCode(): string;
}
