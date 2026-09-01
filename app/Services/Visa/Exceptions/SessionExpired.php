<?php

namespace App\Services\Visa\Exceptions;

final class SessionExpired extends VisaException
{
    public function errorCode(): string
    {
        return 'SESSION_EXPIRED';
    }
}
