<?php

namespace App\Support\Suppliers;

use App\Services\Suppliers\Sabre\Ndc\SabreNdcOfferPriceService;
use App\Services\Suppliers\Sabre\Ndc\SabreNdcOfferSearchService;
use App\Services\Suppliers\Sabre\Ndc\SabreNdcOrderCreateService;
use App\Services\Suppliers\Sabre\Ticketing\SabreGdsTicketingService;

/**
 * Independent Sabre GDS/NDC capability truth from installed adapters, not provider labels.
 */
final class SabreCapabilityTruth
{
    public static function gdsSupported(): bool
    {
        return class_exists(SabreGdsTicketingService::class);
    }

    public static function ndcSupported(): bool
    {
        return class_exists(SabreNdcOfferSearchService::class)
            && class_exists(SabreNdcOfferPriceService::class)
            && class_exists(SabreNdcOrderCreateService::class);
    }
}
