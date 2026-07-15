<?php

namespace App\Support\Branding;

/**
 * Admin-managed platform branding read model (default agency settings + communication).
 */
final class PlatformBranding
{
    public function __construct(
        public string $companyName,
        public string $companyPrefix,
        public string $customerPrefix,
        public string $agentPrefix,
        public string $emailFromName,
        public ?string $supportEmail,
        public ?string $supportPhone,
        public ?string $supportWhatsapp,
    ) {}

    public function companyName(): string
    {
        return $this->companyName;
    }

    public function companyPrefix(): string
    {
        return $this->companyPrefix;
    }

    public function customerPrefix(): string
    {
        return $this->customerPrefix;
    }

    public function agentPrefix(): string
    {
        return $this->agentPrefix;
    }

    public function emailFromName(): string
    {
        return $this->emailFromName;
    }

    public function supportEmail(): ?string
    {
        return $this->supportEmail;
    }

    public function phone(): ?string
    {
        return $this->supportPhone;
    }

    public function whatsapp(): ?string
    {
        return $this->supportWhatsapp;
    }
}
