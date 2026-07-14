<?php

namespace App\Support\Branding;

/**
 * Client-scoped visible mail identity (from name, reply-to, support) for branded mailables.
 *
 * SMTP transport credentials remain platform-wide; only per-message envelope fields differ.
 */
final class ClientMailBrandingProfile
{
    public function __construct(
        public string $clientSlug,
        public string $companyName,
        public string $mailFromName,
        public ?string $replyToEmail,
        public ?string $supportEmail,
        public ?string $logoUrl,
    ) {}

    public function loginOtpSubject(): string
    {
        return 'Your '.$this->companyName.' login OTP';
    }
}
