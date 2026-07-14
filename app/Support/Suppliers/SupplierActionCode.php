<?php

namespace App\Support\Suppliers;

/**
 * Canonical supplier lifecycle action codes (provider-neutral).
 */
final class SupplierActionCode
{
    public const SEARCH = 'search';

    public const FARE_OPTIONS = 'fare_options';

    public const REVALIDATE = 'revalidate';

    public const CREATE_PNR = 'create_pnr';

    public const CREATE_ORDER = 'create_order';

    public const RETRIEVE = 'retrieve';

    public const CANCEL_UNTICKETED = 'cancel_unticketed';

    public const TICKET = 'ticket';

    public const VOID = 'void';

    public const REFUND = 'refund';

    public static function key(string $provider, string $action): string
    {
        return strtolower(trim($provider)).':'.strtolower(trim($action));
    }
}
