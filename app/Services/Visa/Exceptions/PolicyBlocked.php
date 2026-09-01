<?php

namespace App\Services\Visa\Exceptions;

final class PolicyBlocked extends VisaException
{
    public function errorCode(): string
    {
        return 'PROVIDER_UNAVAILABLE';
    }
}
