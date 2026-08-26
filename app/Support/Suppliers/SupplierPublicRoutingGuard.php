<?php

namespace App\Support\Suppliers;

use App\Enums\SupplierEnvironment;
use App\Models\SupplierConnection;

/**
 * Environment-aware public/production fanout gate for supplier connections.
 * Sandbox/demo/cert rows must not serve ordinary guest/customer/agent searches.
 */
final class SupplierPublicRoutingGuard
{
    /** @var list<string> */
    public const PRODUCTION_FANOUT_CHANNELS = [
        'public_guest',
        'public_customer',
        'customer',
        'customer_portal',
        'customer_staff',
        'customer_website',
        'customer_b2b',
        'customer_api',
        'customer_search',
        'customer_booking',
        'customer_checkout',
        'customer_hold',
        'customer_ticket',
        'customer_void',
        'agent_refund',
        'customer_cancel',
        'customer_amend',
        'customer_report',
        'agent_wallet',
        'customer_deposit',
        'agent_support',
        'customer_staff_portal',
    ];

    public static function isProductionFanoutChannel(string $sourceChannel): bool
    {
        $channel = strtolower(trim($sourceChannel));
        if ($channel === '') {
            return true;
        }

        if (in_array($channel, self::PRODUCTION_FANOUT_CHANNELS, true)) {
            return true;
        }

        // Treat unknown public/agent-style channels as production fanout unless explicitly QA.
        if (str_starts_with($channel, 'public_') || str_starts_with($channel, 'agent')) {
            return true;
        }

        return false;
    }

    public static function allowsPublicProductionFanout(SupplierConnection $connection): bool
    {
        $settings = is_array($connection->settings) ? $connection->settings : [];
        $meta = is_array($connection->meta) ? $connection->meta : [];

        if (($settings['public_customer_routing'] ?? null) === true
            || ($meta['public_customer_routing'] ?? null) === true) {
            // Explicit override still requires Live environment — never fanout sandbox as "public".
            return $connection->environment === SupplierEnvironment::Live;
        }

        if (($settings['qa_sandbox_only'] ?? null) === true
            || ($meta['qa_sandbox_only'] ?? null) === true
            || ($settings['production_default_routing'] ?? null) === false
            || ($meta['production_default_routing'] ?? null) === false
            || ($settings['public_customer_routing'] ?? null) === false
            || ($meta['public_customer_routing'] ?? null) === false) {
            return false;
        }

        return $connection->environment === SupplierEnvironment::Live;
    }

    public static function shouldSkipForChannel(SupplierConnection $connection, string $sourceChannel): bool
    {
        if (! self::isProductionFanoutChannel($sourceChannel)) {
            return false;
        }

        return ! self::allowsPublicProductionFanout($connection);
    }
}
