<?php

namespace App\Services\Visa\Exceptions;

final class CaptchaInvalid extends VisaException
{
    public function errorCode(): string
    {
        return 'CAPTCHA_INVALID';
    }
}
