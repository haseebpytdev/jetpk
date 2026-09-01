<?php

namespace App\Services\Visa\Exceptions;

final class ProviderChanged extends VisaException
{
    public function errorCode(): string
    {
        return 'PROVIDER_CHANGED';
    }
}
