<?php

namespace App\Support\Bookings;

use App\Data\NormalizedFlightOfferData;
use InvalidArgumentException;

/**
 * Ensures checkout carries the same supplier identity as the validated search offer
 * (no silent default to another GDS).
 */
final class CheckoutSupplierIdentity
{
    /**
     * @param  array<string, mixed>  $normalizedValidatedOffer  Output of {@see NormalizedFlightOfferData::toArray()} after validation
     * @return array{supplier_provider: string, supplier_connection_id: int}
     */
    public static function fromNormalizedValidatedOffer(array $normalizedValidatedOffer): array
    {
        $provider = strtolower(trim((string) ($normalizedValidatedOffer['supplier_provider'] ?? '')));
        $connectionId = (int) ($normalizedValidatedOffer['supplier_connection_id'] ?? 0);

        if ($provider === '') {
            throw new InvalidArgumentException('Validated offer is missing supplier_provider.');
        }

        if ($connectionId <= 0) {
            throw new InvalidArgumentException('Validated offer is missing supplier_connection_id.');
        }

        return [
            'supplier_provider' => $provider,
            'supplier_connection_id' => $connectionId,
        ];
    }
}
