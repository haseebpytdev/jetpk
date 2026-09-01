<?php

namespace App\Services\Visa\Exceptions;

final class VisaNotFound extends VisaException
{
    public function errorCode(): string
    {
        return 'VISA_NOT_FOUND';
    }
}
