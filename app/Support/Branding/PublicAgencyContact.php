<?php

namespace App\Support\Branding;

/**
 * Public-facing agency contact channels for header, footer, checkout, and support UI.
 *
 * Populated by {@see PublicAgencyContactResolver} from agency settings + config fallbacks.
 */
class PublicAgencyContact
{
    public function __construct(
        public string $agencyName,
        public string $phone,
        public string $email,
        public string $whatsapp,
        public string $city,
        public string $address,
    ) {}

    public function hasPhone(): bool
    {
        return trim($this->phone) !== '';
    }

    public function hasEmail(): bool
    {
        return trim($this->email) !== '';
    }

    public function hasWhatsapp(): bool
    {
        return $this->whatsappDigits() !== '';
    }

    public function whatsappDigits(): string
    {
        return preg_replace('/\D+/', '', $this->whatsapp) ?? '';
    }

    public function whatsappUrl(): ?string
    {
        $digits = $this->whatsappDigits();

        return $digits !== '' ? 'https://wa.me/'.$digits : null;
    }

    public function telHref(): ?string
    {
        if (! $this->hasPhone()) {
            return null;
        }

        return 'tel:'.preg_replace('/\s+/', '', $this->phone);
    }

    public function mailtoHref(): ?string
    {
        return $this->hasEmail() ? 'mailto:'.$this->email : null;
    }

    /**
     * @return array{
     *     agency_name: string,
     *     phone: string,
     *     email: string,
     *     whatsapp: string,
     *     city: string,
     *     address: string
     * }
     */
    public function toArray(): array
    {
        return [
            'agency_name' => $this->agencyName,
            'phone' => $this->phone,
            'email' => $this->email,
            'whatsapp' => $this->whatsapp,
            'city' => $this->city,
            'address' => $this->address,
        ];
    }
}
