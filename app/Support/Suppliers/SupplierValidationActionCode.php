<?php

namespace App\Support\Suppliers;

/**
 * Canonical supplier validation / freshness action codes (provider-neutral).
 */
final class SupplierValidationActionCode
{
    public const GDS_OFFER_REFRESH = 'gds_offer_refresh';

    public const GDS_FARE_REVALIDATION = 'gds_fare_revalidation';

    public const GDS_PRE_PNR_FRESHNESS = 'gds_pre_pnr_freshness';

    public const NDC_OFFER_PRICE = 'ndc_offer_price';

    public const NDC_ORDER_REPRICE = 'ndc_order_reprice';

    /** @var list<string> */
    public const ALL = [
        self::GDS_OFFER_REFRESH,
        self::GDS_FARE_REVALIDATION,
        self::GDS_PRE_PNR_FRESHNESS,
        self::NDC_OFFER_PRICE,
        self::NDC_ORDER_REPRICE,
    ];

    public static function isSupported(string $action): bool
    {
        return in_array(strtolower(trim($action)), self::ALL, true);
    }
}
