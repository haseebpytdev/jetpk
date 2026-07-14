<?php

namespace App\Support\Branding;

use App\Models\AgencySetting;

/**
 * Single read layer for public site contact info (agency settings → ota-client → ota-brand).
 */
class PublicAgencyContactResolver
{
    public static function resolve(?AgencySetting $settings = null): PublicAgencyContact
    {
        $client = config('ota-client', []);
        $brand = config('ota-brand', []);

        $agencyName = BrandDisplayResolver::displayName($settings);

        $phone = self::firstNonEmptyString(
            $settings?->support_phone,
            $client['support_phone'] ?? null,
            $brand['support_phone'] ?? null,
        ) ?? '';

        $email = self::firstNonEmptyString(
            $settings?->support_email,
            $client['support_email'] ?? null,
            $brand['support_email'] ?? null,
        ) ?? '';

        $whatsapp = self::firstNonEmptyString(
            $settings?->support_whatsapp,
            $client['support_whatsapp'] ?? null,
            $brand['support_whatsapp'] ?? null,
        ) ?? '';

        $city = self::firstNonEmptyString(
            $settings?->city,
            $client['office_city'] ?? null,
        ) ?? '';

        $address = trim((string) ($settings?->office_address ?? ''));

        return new PublicAgencyContact(
            agencyName: $agencyName,
            phone: $phone,
            email: $email,
            whatsapp: $whatsapp,
            city: $city,
            address: $address,
        );
    }

    protected static function firstNonEmptyString(mixed ...$candidates): ?string
    {
        foreach ($candidates as $value) {
            if (! is_string($value)) {
                continue;
            }
            $trimmed = trim($value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }
}
