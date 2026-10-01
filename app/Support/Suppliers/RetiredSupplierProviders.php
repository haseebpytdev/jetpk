<?php

namespace App\Support\Suppliers;

/**
 * Retired supplier_connection.provider strings that must fail closed.
 * Kept for cast-safe hydration of legacy rows without mapping to another supplier.
 */
final class RetiredSupplierProviders
{
    /**
     * @var list<string>
     */
    public const VALUES = [
        'airline_direct',
        'amadeus',
        'travelport',
        'smtp',
        'google_oauth',
    ];

    public static function isRetired(?string $provider): bool
    {
        if ($provider === null || $provider === '') {
            return false;
        }

        return in_array(strtolower(trim($provider)), self::VALUES, true);
    }
}
