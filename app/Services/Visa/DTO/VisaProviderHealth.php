<?php

namespace App\Services\Visa\DTO;

final readonly class VisaProviderHealth
{
    public function __construct(
        public string $providerKey,
        public bool $moduleEnabled,
        public bool $providerEnabled,
        public bool $policyApproved,
        public bool $liveAllowed,
        public bool $lookupCapable,
        public bool $captchaCapable,
        public bool $documentCapable,
        public bool $pdfExportCapable,
        public bool $imageExportCapable,
        public string $status,
        public string $detail,
    ) {}
}
