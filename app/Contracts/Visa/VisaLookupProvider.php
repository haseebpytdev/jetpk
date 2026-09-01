<?php

namespace App\Contracts\Visa;

use App\Services\Visa\DTO\VisaCaptchaChallenge;
use App\Services\Visa\DTO\VisaDocument;
use App\Services\Visa\DTO\VisaLookupRequest;
use App\Services\Visa\DTO\VisaLookupResult;
use App\Services\Visa\DTO\VisaLookupSession;
use App\Services\Visa\DTO\VisaProviderCapabilities;
use App\Services\Visa\DTO\VisaProviderHealth;

/**
 * Country-agnostic visa lookup provider. Public layers depend on this contract only.
 */
interface VisaLookupProvider
{
    public function key(): string;

    public function countryCode(): string;

    public function capabilities(): VisaProviderCapabilities;

    public function health(): VisaProviderHealth;

    public function startLookup(): VisaLookupSession;

    public function refreshCaptcha(VisaLookupSession $session): VisaCaptchaChallenge;

    public function captcha(VisaLookupSession $session): VisaCaptchaChallenge;

    public function lookup(VisaLookupSession $session, VisaLookupRequest $request): VisaLookupResult;

    public function getDocument(VisaLookupSession $session, string $documentRef): VisaDocument;
}
