<?php

namespace App\Services\Visa\Exceptions;

final class CaptchaExpired extends VisaException
{
    public function errorCode(): string
    {
        return 'CAPTCHA_EXPIRED';
    }
}
