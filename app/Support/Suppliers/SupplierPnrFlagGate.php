<?php

namespace App\Support\Suppliers;

use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabreOperationalPnrReadiness;

/**
 * Canonical PNR / ticketing / cancel flag taxonomy (Sabre-first; other suppliers extend via config).
 *
 * Ticketing flags must never block PNR create, retrieve, or unticketed cancel.
 */
final class SupplierPnrFlagGate
{
    /**
     * @return array<string, bool>
     */
    public function sabreFlags(): array
    {
        $bookingEnabled = (bool) config('suppliers.sabre.booking_enabled', false);
        $bookingLive = (bool) config('suppliers.sabre.booking_live_call_enabled', false);
        $gdsEnabled = SabreCertifiedRouteSelector::isConnectingSameCarrierGdsEnabled();
        $publicCheckoutPnr = SabreCertifiedRouteSelector::isConnectingSameCarrierPublicCheckoutEnabled();
        $operationalAuto = SabreOperationalPnrReadiness::isOperationalAutoPnrEnabled();
        $pnrCreate = $this->resolvePnrCreateEnabled($bookingEnabled, $bookingLive);
        $adminManual = (bool) config('suppliers.sabre.admin_manual_pnr_enabled', $bookingEnabled);
        $retrieve = (bool) config('suppliers.sabre.pnr_retrieve_enabled', $bookingEnabled);
        $unticketedCancel = (bool) config(
            'suppliers.sabre.unticketed_cancel_enabled',
            (bool) config('suppliers.sabre.admin_cancel_live_call_enabled', false),
        );

        return [
            'supplier_enabled' => $gdsEnabled || (bool) config('suppliers.sabre.ndc.search_enabled', false),
            'gds_search_enabled' => $gdsEnabled,
            'ndc_search_enabled' => (bool) config('suppliers.sabre.ndc.search_enabled', false),
            'pnr_create_enabled' => $pnrCreate,
            'public_auto_pnr_enabled' => $publicCheckoutPnr,
            'operational_auto_pnr_enabled' => $operationalAuto,
            'admin_manual_pnr_enabled' => $adminManual,
            'pnr_retrieve_enabled' => $retrieve,
            'unticketed_cancel_enabled' => $unticketedCancel,
            'ticketing_enabled' => (bool) config('suppliers.sabre.ticketing_enabled', false),
            'void_enabled' => (bool) config('suppliers.sabre.void_enabled', false),
            'refund_enabled' => (bool) config('suppliers.sabre.refund_enabled', false),
        ];
    }

    public function sabrePnrCreateAllowed(): bool
    {
        return $this->sabreFlags()['pnr_create_enabled'];
    }

    /**
     * PNR create feature on (dry-run or live). Does not require {@code booking_live_call_enabled}.
     */
    public function sabrePnrCreateFeatureEnabled(): bool
    {
        $explicit = config('suppliers.sabre.pnr_create_enabled');
        if ($explicit !== null) {
            return (bool) $explicit;
        }

        return (bool) config('suppliers.sabre.booking_enabled', false);
    }

    /**
     * Admin/operator explicit command only: missing config defaults to enabled (safe for confirmed manual lane).
     */
    public function sabreAdminManualPnrEnabledForCommand(): bool
    {
        $explicit = config('suppliers.sabre.admin_manual_pnr_enabled');
        if ($explicit !== null) {
            return (bool) $explicit;
        }

        return true;
    }

    public function sabreTicketingEnabled(): bool
    {
        return $this->sabreFlags()['ticketing_enabled'];
    }

    protected function resolvePnrCreateEnabled(bool $bookingEnabled, bool $bookingLive): bool
    {
        $explicit = config('suppliers.sabre.pnr_create_enabled');
        if ($explicit !== null) {
            return (bool) $explicit;
        }

        return $bookingEnabled && $bookingLive;
    }
}
