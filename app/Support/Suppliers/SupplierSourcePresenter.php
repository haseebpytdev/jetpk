<?php

namespace App\Support\Suppliers;

use App\Models\SupplierConnection;

/**
 * Human-readable supplier source labels for flight result cards (admin/testing visibility).
 */
final class SupplierSourcePresenter
{
    public static function label(?string $provider): string
    {
        return match (strtolower(trim((string) $provider))) {
            'sabre' => 'Sabre',
            'iati' => 'IATI',
            'pia_ndc' => 'PIA NDC',
            'airblue' => 'AirBlue',
            'duffel' => 'Duffel',
            'airline_direct' => 'Airline Direct',
            default => 'Supplier',
        };
    }

    public static function labelForOffer(
        ?string $provider,
        ?string $sourceType = null,
        ?string $providerChannel = null,
        ?SupplierConnection $connection = null,
    ): string {
        return SabreSupplierChannelConfig::offerLabel($provider, $sourceType, $providerChannel, $connection);
    }

    public static function cssClass(?string $provider): string
    {
        return 'flight-card-source-badge';
    }
}
