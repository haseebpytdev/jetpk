<?php

namespace App\Support\Branding;

/**
 * Platform company identity for system/transactional emails (I2 read model).
 *
 * Populated by {@see CompanyEmailProfileResolver}; not tied to individual admin users.
 */
class CompanyEmailProfile
{
    public function __construct(
        public string $name,
        public ?string $legal_name,
        public ?string $logo_url,
        public ?string $support_email,
        public ?string $support_phone,
        public ?string $website_url,
        public ?string $address,
        public string $primary_color,
        public string $secondary_color,
        public string $mail_from_name,
        public string $mail_from_email,
        public ?string $reply_to_email,
        public ?string $footer_text,
    ) {}

    /**
     * Safe public fields for templates and tests (no SMTP or token secrets).
     *
     * @return array{
     *     name: string,
     *     legal_name: string|null,
     *     logo_url: string|null,
     *     support_email: string|null,
     *     support_phone: string|null,
     *     website_url: string|null,
     *     address: string|null,
     *     primary_color: string,
     *     secondary_color: string,
     *     mail_from_name: string,
     *     mail_from_email: string,
     *     reply_to_email: string|null,
     *     footer_text: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'legal_name' => $this->legal_name,
            'logo_url' => $this->logo_url,
            'support_email' => $this->support_email,
            'support_phone' => $this->support_phone,
            'website_url' => $this->website_url,
            'address' => $this->address,
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'mail_from_name' => $this->mail_from_name,
            'mail_from_email' => $this->mail_from_email,
            'reply_to_email' => $this->reply_to_email,
            'footer_text' => $this->footer_text,
        ];
    }

    /**
     * @return array{name: string, address: string}
     */
    public function mailFrom(): array
    {
        return [
            'name' => $this->mail_from_name,
            'address' => $this->mail_from_email,
        ];
    }
}
