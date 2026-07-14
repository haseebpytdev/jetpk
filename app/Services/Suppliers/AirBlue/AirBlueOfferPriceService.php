<?php

namespace App\Services\Suppliers\AirBlue;

use App\Data\NormalizedFlightOfferData;
use App\Data\OfferValidationResultData;
use App\Enums\AirBlueApiChannel;
use App\Models\SupplierConnection;
use Illuminate\Support\Facades\Log;

/**
 * DoOfferPrice is not available in sample payloads — internal no-op with logging.
 */
class AirBlueOfferPriceService
{
    public function revalidate(NormalizedFlightOfferData $offer, SupplierConnection $connection): OfferValidationResultData
    {
        $channel = is_array($offer->raw_payload['provider_context'] ?? null)
            ? (string) ($offer->raw_payload['provider_context']['api_channel'] ?? '')
            : '';

        if ($channel === AirBlueApiChannel::ZapwaysOta->value) {
            Log::channel('air-blue')->info('airblue.offer_price.skipped', [
                'supplier_connection_id' => $connection->id,
                'offer_id' => $offer->offer_id,
                'reason' => 'Zapways OTA has no separate offer-price step.',
            ]);
        } else {
            Log::channel('air-blue')->info('airblue.offer_price.skipped', [
                'supplier_connection_id' => $connection->id,
                'offer_id' => $offer->offer_id,
                'reason' => 'DoOfferPrice sample unavailable; using AirShopping context.',
            ]);
        }

        $context = is_array($offer->raw_payload['provider_context'] ?? null)
            ? $offer->raw_payload['provider_context']
            : [];

        return new OfferValidationResultData(
            is_valid: $context !== [],
            status: $context !== [] ? 'validated' : 'invalid_offer',
            original_offer_id: $offer->offer_id,
            validated_offer: $offer,
            warnings: [],
            meta: ['offer_price_supported' => false],
        );
    }
}
