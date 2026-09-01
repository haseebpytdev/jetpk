<?php

namespace App\Services\Visa\DTO;

final readonly class VisaProviderCapabilities
{
    /**
     * @param  list<array{key:string,label:string}>  $criteria
     * @param  list<string>  $exportFormats
     */
    public function __construct(
        public string $providerKey,
        public string $countryCode,
        public string $countryLabel,
        public string $serviceLabel,
        public array $criteria,
        public bool $nationalityRequired,
        public bool $captchaRequired,
        public string $documentSourceType,
        public array $exportFormats,
        public string $officialFallbackUrl,
    ) {}
}
