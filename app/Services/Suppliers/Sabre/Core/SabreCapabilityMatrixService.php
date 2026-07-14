<?php

namespace App\Services\Suppliers\Sabre\Core;

use App\Support\Sabre\SabreLaneRegistry;

/**
 * Read-only Sabre capability posture matrix (Phase S1B + prod-gap alignment).
 *
 * Distinguishes code implementation, production/live HTTP gates, evidence posture,
 * and provider-manual lanes. Does not read env or perform HTTP.
 * Lane keys align with {@see SabreLaneRegistry}.
 */
final class SabreCapabilityMatrixService
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $capabilities = null;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return self::capabilityMap();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array
    {
        return self::capabilityMap()[$key] ?? null;
    }

    public function isEnabled(string $key): bool
    {
        $cap = $this->get($key);

        return $cap !== null && ($cap['code_implemented'] ?? 'no') === 'yes';
    }

    public function requiresManualHandling(string $key): bool
    {
        $cap = $this->get($key);

        return $cap !== null && ($cap['manual'] ?? 'no') === 'yes';
    }

    public function productionAllowed(string $key): bool
    {
        $cap = $this->get($key);

        return $cap !== null && ($cap['production'] ?? 'no') === 'yes';
    }

    public function liveSupplierCallAllowed(string $key): bool
    {
        $cap = $this->get($key);

        return $cap !== null && ($cap['live_http'] ?? 'no') === 'yes';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function evidencePending(): array
    {
        return array_values(array_filter(
            self::capabilityMap(),
            static fn (array $cap): bool => in_array($cap['evidence'] ?? '', [
                'pending',
                'pending_cancel_retrieve_confirmation',
            ], true),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function unresolved(): array
    {
        return $this->evidencePending();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function disabled(): array
    {
        return array_values(array_filter(
            self::capabilityMap(),
            static fn (array $cap): bool => ($cap['code_implemented'] ?? 'no') === 'no'
                || ($cap['evidence'] ?? '') === 'disabled',
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function providerUnsupportedManual(): array
    {
        return array_values(array_filter(
            self::capabilityMap(),
            static fn (array $cap): bool => ($cap['evidence'] ?? '') === 'provider_unsupported_manual',
        ));
    }

    /**
     * Prod-gap audit key → matrix capability key.
     *
     * @return array<string, string>
     */
    public static function prodGapMatrixKeyMap(): array
    {
        return [
            'gds_revalidation' => 'gds_revalidation',
            'multi_city_revalidation' => 'gds_revalidation',
            'multi_city_booking' => 'gds_pnr_create',
            'gds_pnr_create' => 'gds_pnr_create',
            'ticket_issue' => 'gds_ticketing',
            'ticket_documents' => 'gds_ticket_documents',
            'void' => 'gds_void',
            'refund' => 'gds_refund',
            'cancel' => 'gds_cancel',
            'ndc_reprice' => 'ndc_reprice',
            'ndc_order_change' => 'ndc_order_change',
            'ndc_retrieve' => 'ndc_order_retrieve',
            'multi_city_search' => 'gds_search',
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function capabilityMap(): array
    {
        if (self::$capabilities !== null) {
            return self::$capabilities;
        }

        self::$capabilities = [
            'gds_search' => self::row(
                'gds_search',
                'gds_search',
                'GDS search (BFM shop)',
                codeImplemented: 'yes',
                production: 'yes',
                liveHttp: 'yes',
                evidence: 'certified',
                manualRequired: false,
                command: 'sabre:multicity-search-probe',
                notes: 'Production BFM v4/v5 shop via SabreClient. Platform module and connection gates apply at runtime.',
            ),
            'gds_normalizer' => self::row(
                'gds_normalizer',
                'gds_normalizer',
                'GDS search normalizer',
                codeImplemented: 'yes',
                production: 'yes',
                liveHttp: 'no',
                evidence: 'n/a',
                manualRequired: false,
                command: null,
                notes: 'Transforms shop JSON to NormalizedFlightOfferData offline after search HTTP. No separate supplier endpoint.',
            ),
            'gds_revalidation' => self::row(
                'gds_revalidation',
                'gds_revalidation',
                'GDS revalidation',
                codeImplemented: 'yes',
                production: 'yes',
                liveHttp: 'env_gated',
                evidence: 'pending',
                manualRequired: false,
                command: 'sabre:gds-revalidate',
                notes: 'SabreGdsRevalidationService + checkout enforcement. Live call env-gated; multi-city via sabre:gds-revalidate-multicity.',
            ),
            'gds_pnr_create' => self::row(
                'gds_pnr_create',
                'gds_pnr_creation',
                'GDS PNR creation',
                codeImplemented: 'yes',
                production: 'env_gated',
                liveHttp: 'env_gated',
                evidence: 'pending',
                manualRequired: false,
                command: 'sabre:gds-create-pnr-production',
                notes: 'SabreBookingService createSupplierBooking; Trip Orders + CPNR paths. Live POST env-gated and certified-route limited.',
            ),
            'gds_pnr_retrieve_sync' => self::row(
                'gds_pnr_retrieve_sync',
                'gds_pnr_retrieve_sync',
                'GDS PNR retrieve / sync',
                codeImplemented: 'yes',
                production: 'yes',
                liveHttp: 'yes',
                evidence: 'certified',
                manualRequired: false,
                command: null,
                notes: 'Trip Orders getBooking sync to sanitized meta.pnr_itinerary_snapshot. Admin/staff and Artisan sync paths.',
            ),
            'gds_cancel' => self::row(
                'gds_cancel',
                'gds_cancellation',
                'GDS cancellation',
                codeImplemented: 'yes',
                production: 'env_gated',
                liveHttp: 'env_gated',
                evidence: 'pending_cancel_retrieve_confirmation',
                manualRequired: false,
                command: 'sabre:production-cancel-evidence',
                notes: 'SabreBookingCancelService cancelBooking (Binham parity). HTTP 200 requires delayed getBooking segment confirmation; ticketed bookings need separate void/refund.',
            ),
            'gds_ticketing' => self::row(
                'gds_ticketing',
                'gds_ticketing',
                'GDS ticketing',
                codeImplemented: 'yes',
                production: 'env_gated',
                liveHttp: 'env_gated',
                evidence: 'pending',
                manualRequired: false,
                command: 'sabre:gds-issue-ticket',
                notes: 'SabreGdsTicketingService + Enhanced Air Ticket REST (/v1.3.0/air/ticket). Live env-gated; not present in Binham reference.',
            ),
            'gds_ticket_documents' => self::row(
                'gds_ticket_documents',
                'gds_ticketing',
                'GDS ticket documents',
                codeImplemented: 'yes',
                production: 'env_gated',
                liveHttp: 'env_gated',
                evidence: 'pending',
                manualRequired: false,
                command: 'sabre:gds-ticket-documents',
                notes: 'SabreGdsTicketDocumentService via getBooking flightTickets[]; live env-gated.',
            ),
            'gds_void' => self::row(
                'gds_void',
                'gds_ticketing',
                'GDS void',
                codeImplemented: 'yes',
                production: 'env_gated',
                liveHttp: 'env_gated',
                evidence: 'provider_unsupported_manual',
                manualRequired: true,
                command: 'sabre:gds-void-ticket',
                notes: 'SabreGdsVoidTicketService when voidFlightTickets configured; Binham/iati.pk void used cancelBooking only (VOID IS CANCELBOOKING).',
            ),
            'gds_refund' => self::row(
                'gds_refund',
                'gds_ticketing',
                'GDS refund',
                codeImplemented: 'yes',
                production: 'env_gated',
                liveHttp: 'env_gated',
                evidence: 'provider_unsupported_manual',
                manualRequired: true,
                command: 'sabre:gds-refund-ticket',
                notes: 'SabreGdsRefundTicketService when refundFlightTickets configured; otherwise manual Red Workspace / DB workflow (Binham REFUND IS MANUAL).',
            ),
            'ndc_reprice' => self::row(
                'ndc_reprice',
                'ndc_reprice_order_change_retrieve',
                'NDC reprice order',
                codeImplemented: 'yes',
                production: 'env_gated',
                liveHttp: 'env_gated',
                evidence: 'pending',
                manualRequired: false,
                command: 'sabre:ndc-reprice-order',
                notes: 'SabreNdcRepriceOrderService POST /v1/offers/repriceOrder; env-gated.',
            ),
            'ndc_order_change' => self::row(
                'ndc_order_change',
                'ndc_reprice_order_change_retrieve',
                'NDC order change',
                codeImplemented: 'yes',
                production: 'env_gated',
                liveHttp: 'env_gated',
                evidence: 'pending',
                manualRequired: false,
                command: 'sabre:ndc-order-change',
                notes: 'SabreNdcOrderChangeService POST /v1/orders/change acceptOffers shape; env-gated.',
            ),
            'ndc_search' => self::row(
                'ndc_search',
                'ndc_reprice_order_change_retrieve',
                'NDC search',
                codeImplemented: 'yes',
                production: 'no',
                liveHttp: 'env_gated',
                evidence: 'pending',
                manualRequired: false,
                command: null,
                notes: 'v5 shop may be configured but NDC channel selection is not a certified production checkout path. Entitlement probes only.',
            ),
            'ndc_order_create' => self::row(
                'ndc_order_create',
                'ndc_reprice_order_change_retrieve',
                'NDC order create',
                codeImplemented: 'yes',
                production: 'env_gated',
                liveHttp: 'env_gated',
                evidence: 'pending',
                manualRequired: false,
                command: null,
                notes: 'SabreNdcOrderCreateService scaffold with env-gated live path.',
            ),
            'ndc_order_retrieve' => self::row(
                'ndc_order_retrieve',
                'ndc_reprice_order_change_retrieve',
                'NDC order retrieve',
                codeImplemented: 'yes',
                production: 'env_gated',
                liveHttp: 'env_gated',
                evidence: 'pending',
                manualRequired: false,
                command: 'sabre:ndc-retrieve-order',
                notes: 'SabreNdcOrderRetrieveService — orders/view + ndc/orders/retrieve fallback; env-gated.',
            ),
            'ndc_cancel' => self::row(
                'ndc_cancel',
                'ndc_cancel',
                'NDC order cancel',
                codeImplemented: 'no',
                production: 'no',
                liveHttp: 'no',
                evidence: 'disabled',
                manualRequired: false,
                command: null,
                notes: 'Not implemented. Future lane; do not enable in production without certification.',
            ),
            'diagnostics' => self::row(
                'diagnostics',
                'diagnostics_probes',
                'Diagnostics / probes',
                codeImplemented: 'yes',
                production: 'no',
                liveHttp: 'no',
                evidence: 'n/a',
                manualRequired: false,
                command: 'sabre:prod-gap-audit',
                notes: 'Artisan inspect/cert/compare commands and probe services. SSH/local only; not customer-facing.',
            ),
        ];

        return self::$capabilities;
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(
        string $key,
        string $lane,
        string $label,
        string $codeImplemented,
        string $production,
        string $liveHttp,
        string $evidence,
        bool $manualRequired,
        ?string $command,
        string $notes,
    ): array {
        return [
            'key' => $key,
            'lane' => $lane,
            'label' => $label,
            'code_implemented' => $codeImplemented,
            'production' => $production,
            'live_http' => $liveHttp,
            'evidence' => $evidence,
            'manual' => $manualRequired ? 'yes' : 'no',
            'command' => $command,
            'notes' => $notes,
            'status' => self::deriveLegacyStatus($codeImplemented, $evidence, $lane),
            'production_allowed' => $production === 'yes',
            'live_supplier_call_allowed' => $liveHttp === 'yes',
            'manual_required' => $manualRequired,
        ];
    }

    private static function deriveLegacyStatus(string $codeImplemented, string $evidence, string $lane): string
    {
        if ($lane === 'diagnostics_probes') {
            return 'diagnostic_only';
        }

        if ($codeImplemented === 'no' || $evidence === 'disabled') {
            return 'disabled';
        }

        if ($evidence === 'provider_unsupported_manual') {
            return 'enabled';
        }

        return 'enabled';
    }
}
