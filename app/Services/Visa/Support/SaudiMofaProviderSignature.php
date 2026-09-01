<?php

namespace App\Services\Visa\Support;

use App\Services\Visa\Exceptions\ProviderChanged;

final class SaudiMofaProviderSignature
{
    public const LOOKUP_PATH = '/visaservices/searchvisa';

    public const CAPTCHA_PATH_PREFIX = '/Base/GetRandomCaptchaImage';

    public const RESULT_PATH = '/Home/PrintedUmrahVisa';

    /** @var list<string> */
    public const REQUIRED_FORM_FIELDS = [
        'ddlFirstValue',
        'tbFirstValue',
        'ddlSecondValue',
        'tbSecondValue',
        'NationalityId',
        'Captcha',
        '__RequestVerificationToken',
    ];

    /** @var list<string> */
    public const RESULT_MARKERS = [
        'Visa No.',
        'Passport No.',
        'Application No.',
        'Type Of Visa',
    ];

    public function assertLookupPage(string $html): void
    {
        foreach (self::REQUIRED_FORM_FIELDS as $field) {
            if (! str_contains($html, 'name="'.$field.'"') && ! str_contains($html, "name='{$field}'") && ! str_contains($html, 'id="'.$field.'"')) {
                throw new ProviderChanged('MOFA lookup form signature mismatch.');
            }
        }
        if (! str_contains($html, self::CAPTCHA_PATH_PREFIX)) {
            throw new ProviderChanged('MOFA captcha route signature mismatch.');
        }
    }

    public function assertResultPage(string $html, string $path): void
    {
        if ($path !== self::RESULT_PATH) {
            throw new ProviderChanged('Unexpected MOFA result route.');
        }
        foreach (self::RESULT_MARKERS as $marker) {
            if (! str_contains($html, $marker)) {
                throw new ProviderChanged('MOFA result page signature mismatch.');
            }
        }
    }
}
