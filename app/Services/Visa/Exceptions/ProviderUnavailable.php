<?php

namespace App\Services\Visa\Exceptions;

final class ProviderUnavailable extends VisaException
{
    public function errorCode(): string
    {
        return 'PROVIDER_UNAVAILABLE';
    }
}
