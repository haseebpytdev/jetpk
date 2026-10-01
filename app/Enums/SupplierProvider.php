<?php

namespace App\Enums;

/**
 * Active supplier / group-ticketing connection providers.
 * SMTP and Google OAuth are platform integrations, not suppliers.
 * Retired values (airline_direct, amadeus, travelport, smtp, google_oauth) hydrate via cast as strings.
 */
enum SupplierProvider: string
{
    case Sabre = 'sabre';
    case PiaNdc = 'pia_ndc';
    case Airblue = 'airblue';
    case Duffel = 'duffel';
    case Iati = 'iati';
    case OneApi = 'one_api';
    case AlHaider = 'al_haider';
    case AmeerEMillat = 'ameer_e_millat';

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Sabre => 'Sabre',
            self::PiaNdc => 'PIA NDC',
            self::Airblue => 'AirBlue / Zapways',
            self::Duffel => 'Duffel',
            self::Iati => 'IATI',
            self::OneApi => 'One API',
            self::AlHaider => 'Al-Haider',
            self::AmeerEMillat => 'Ameer-e-Millat',
        };
    }

    public function isGroupSupplier(): bool
    {
        return match ($this) {
            self::AlHaider, self::AmeerEMillat => true,
            default => false,
        };
    }
}
