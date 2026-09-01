<?php

namespace App\Services\Visa\DTO;

final readonly class VisaLookupResult
{
    /**
     * @param  array<string, string|null>  $fields  Normalized semantic field map (values may be empty)
     */
    public function __construct(
        public string $lookupSessionId,
        public string $providerKey,
        public string $countryCode,
        public string $status,
        public array $fields,
        public ?string $documentRef,
        public string $sourceAttribution,
        public int $expiresAt,
    ) {}
}
