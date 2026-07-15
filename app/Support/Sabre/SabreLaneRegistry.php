<?php

namespace App\Support\Sabre;

/**
 * Read-only Sabre architecture lane map (Phase S1A).
 *
 * Documents which files belong to which integration lane. Does not load services,
 * invoke HTTP, or read env. Use for audits, refactor planning, and agent orientation.
 */
final class SabreLaneRegistry
{
    private const SERVICES_PREFIX = 'app/Services/Suppliers/Sabre/';

    private const DIAGNOSTICS_SERVICES_PREFIX = 'app/Services/Suppliers/Sabre/Diagnostics/';

    private const COMMANDS_PREFIX = 'app/Console/Commands/';

    /**
     * @return array<string, array{
     *     key: string,
     *     label: string,
     *     category: string,
     *     status: string,
     *     files: list<string>,
     *     notes: string,
     *     risk: string
     * }>
     */
    public static function all(): array
    {
        return self::lanes();
    }

    /**
     * @return list<string>
     */
    public static function productionCriticalFiles(): array
    {
        return [
            self::SERVICES_PREFIX.'Core/SabreClient.php',
            self::SERVICES_PREFIX.'Core/SabreBookingClient.php',
            self::SERVICES_PREFIX.'Gds/SabreFlightSearchRequestBuilder.php',
            self::SERVICES_PREFIX.'Gds/SabreFlightSearchNormalizer.php',
            self::SERVICES_PREFIX.'Gds/SabreRevalidationPayloadBuilder.php',
            self::SERVICES_PREFIX.'Booking/SabreBookingService.php',
            self::SERVICES_PREFIX.'Booking/SabreBookingPayloadBuilder.php',
            self::SERVICES_PREFIX.'PnrRetrieve/SabrePnrItinerarySyncService.php',
            self::SERVICES_PREFIX.'PnrRetrieve/SabreTripOrdersGetBookingItineraryMapper.php',
            self::SERVICES_PREFIX.'Cancel/SabreBookingCancelService.php',
        ];
    }

    /**
     * Files used only for inspect/cert/probe tooling — not on public checkout or admin booking HTTP paths.
     *
     * @return list<string>
     */
    public static function diagnosticsOnlyFiles(): array
    {
        $files = [];

        foreach (self::lanes() as $lane) {
            if ($lane['category'] !== 'diagnostics') {
                continue;
            }
            foreach ($lane['files'] as $file) {
                $files[] = $file;
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * @return list<string>
     */
    public static function obsoleteCandidates(): array
    {
        return [
            self::SERVICES_PREFIX.'SabreFlightSupplier.php',
        ];
    }

    /**
     * Resolve a file path to its primary lane key (normalized relative path).
     */
    public static function laneForFile(string $path): ?string
    {
        $normalized = self::normalizePath($path);

        foreach (self::lanes() as $key => $lane) {
            foreach ($lane['files'] as $file) {
                if ($normalized === $file || str_ends_with($normalized, '/'.$file) || str_ends_with($normalized, '\\'.$file)) {
                    return $key;
                }
            }
        }

        $basename = basename(str_replace('\\', '/', $normalized));
        foreach (self::lanes() as $key => $lane) {
            foreach ($lane['files'] as $file) {
                if ($basename === basename($file)) {
                    return $key;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, array{
     *     key: string,
     *     label: string,
     *     category: string,
     *     status: string,
     *     files: list<string>,
     *     notes: string,
     *     risk: string
     * }>
     */
    private static function lanes(): array
    {
        static $lanes = null;

        if ($lanes !== null) {
            return $lanes;
        }

        $cmd = static fn (string $name): string => self::COMMANDS_PREFIX.$name;

        $lanes = [
            'core_auth_connection' => [
                'key' => 'core_auth_connection',
                'label' => 'Core / auth / client / connection',
                'category' => 'core',
                'status' => 'production',
                'files' => [
                    self::SERVICES_PREFIX.'Core/SabreClient.php',
                    self::SERVICES_PREFIX.'Core/SabreEprEncodedCredentials.php',
                    self::SERVICES_PREFIX.'Core/SabreBookingClient.php',
                    'config/suppliers.php',
                    $cmd('SabreCertTokenProbeCommand.php'),
                    $cmd('SabreCheckServicesCommand.php'),
                ],
                'notes' => 'OAuth, endpoint resolution, shared HTTP adapters, and supplier connection config surface. SabreBookingClient also posts booking/cancel payloads.',
                'risk' => 'very_high',
            ],
            'gds_search' => [
                'key' => 'gds_search',
                'label' => 'GDS search',
                'category' => 'gds',
                'status' => 'production',
                'files' => [
                    self::SERVICES_PREFIX.'Gds/SabreFlightSearchRequestBuilder.php',
                    self::SERVICES_PREFIX.'Gds/SabreSegmentFreshShopSellabilityService.php',
                    self::SERVICES_PREFIX.'Gds/SabreBookingOfferRefreshService.php',
                    $cmd('SabreInspectShopPayloadCommand.php'),
                    $cmd('SabreInspectRawItinerariesCommand.php'),
                    $cmd('SabreVerifyFaresCommand.php'),
                    $cmd('SabreDiagnoseBookingSegmentSellabilityCommand.php'),
                    $cmd('SabreRefreshBookingOfferCommand.php'),
                    $cmd('SabreAcceptRefreshedOfferCommand.php'),
                ],
                'notes' => 'BFM shop request build, fresh-shop guards, and offer refresh before PNR. Adapters resolve through SabreFlightSupplierAdapter → SabreClient.',
                'risk' => 'high',
            ],
            'gds_normalizer' => [
                'key' => 'gds_normalizer',
                'label' => 'GDS normalizer',
                'category' => 'gds',
                'status' => 'production',
                'files' => [
                    self::SERVICES_PREFIX.'Gds/SabreFlightSearchNormalizer.php',
                    self::SERVICES_PREFIX.'Gds/SabreStoredPricingContextDigest.php',
                    $cmd('SabreCertGdsLinkageReportCommand.php'),
                    $cmd('SabreInspectBookingPricingContextCommand.php'),
                ],
                'notes' => 'GIR → NormalizedFlightOfferData; preserves sabre_shop_context and pricing linkage for revalidate/booking. Digest is offline inspect support.',
                'risk' => 'very_high',
            ],
            'gds_revalidation' => [
                'key' => 'gds_revalidation',
                'label' => 'GDS revalidation',
                'category' => 'gds',
                'status' => 'production',
                'files' => [
                    self::SERVICES_PREFIX.'Gds/SabreRevalidationPayloadBuilder.php',
                    $cmd('SabreInspectBookingRevalidateCommand.php'),
                    $cmd('SabreCheckRevalidateEndpointsCommand.php'),
                    $cmd('SabreCertGdsRevalidateReportCommand.php'),
                    $cmd('SabreCertGdsRevalidateMatrixCommand.php'),
                ],
                'notes' => 'Pre-booking fare linkage via BFM revalidate. Compare/matrix Artisan commands are cert-only; production uses a single certified path.',
                'risk' => 'high',
            ],
            'gds_pnr_creation' => [
                'key' => 'gds_pnr_creation',
                'label' => 'GDS PNR creation',
                'category' => 'gds',
                'status' => 'production',
                'files' => [
                    self::SERVICES_PREFIX.'Booking/SabreBookingService.php',
                    self::SERVICES_PREFIX.'Booking/SabreBookingPayloadBuilder.php',
                    $cmd('SabreInspectBookingPayloadCommand.php'),
                    $cmd('SabreInspectBookingConfigCommand.php'),
                    $cmd('SabreInspectBookingAttemptCommand.php'),
                    $cmd('SabreClassifyPnrFailureCommand.php'),
                    $cmd('SabreBookingCapabilityReportCommand.php'),
                    $cmd('SabreCheckBookingEndpointsCommand.php'),
                    $cmd('SabreDiscoverBookingEndpointsCommand.php'),
                    $cmd('SabreCertifyPnrCommand.php'),
                    $cmd('SabreCertGdsCpnrReportCommand.php'),
                    $cmd('SabreDiagnoseCpnrVsIatiStructureCommand.php'),
                ],
                'notes' => 'Trip Orders createBooking and Passenger Records CPNR paths. SabreBookingService orchestrates gates, revalidation, and attempt logging.',
                'risk' => 'very_high',
            ],
            'gds_pnr_retrieve_sync' => [
                'key' => 'gds_pnr_retrieve_sync',
                'label' => 'GDS PNR retrieve / sync',
                'category' => 'gds',
                'status' => 'production',
                'files' => [
                    self::SERVICES_PREFIX.'PnrRetrieve/SabrePnrItinerarySyncService.php',
                    self::SERVICES_PREFIX.'PnrRetrieve/SabreTripOrdersGetBookingItineraryMapper.php',
                    self::SERVICES_PREFIX.'PnrRetrieve/SabrePnrRetrieveProbe.php',
                    self::SERVICES_PREFIX.'PnrRetrieve/SabreTripOrdersGetBookingInspectSummary.php',
                    $cmd('SabreInspectPnrRetrieveCommand.php'),
                    $cmd('SabreSyncPnrItineraryCommand.php'),
                ],
                'notes' => 'Trip Orders getBooking → sanitized meta.pnr_itinerary_snapshot. Probe class is dual-use (production fetch + CLI diagnostics).',
                'risk' => 'high',
            ],
            'gds_cancellation' => [
                'key' => 'gds_cancellation',
                'label' => 'GDS cancellation',
                'category' => 'gds',
                'status' => 'env_gated_evidence_pending',
                'files' => [
                    self::SERVICES_PREFIX.'Cancel/SabreBookingCancelService.php',
                    self::SERVICES_PREFIX.'Cancel/SabreCancelPayloadBuilder.php',
                    self::SERVICES_PREFIX.'Cancel/SabreCancelBookingContext.php',
                    self::SERVICES_PREFIX.'Cancel/SabreTripOrderCancelContext.php',
                    self::SERVICES_PREFIX.'Cancel/SabreCancelBookingInspectProbe.php',
                    self::SERVICES_PREFIX.'Cancel/SabreCancelProbeDiagnostics.php',
                    $cmd('SabreInspectCancelBookingCommand.php'),
                    $cmd('SabreProductionCancelEvidenceCommand.php'),
                ],
                'notes' => 'SabreBookingCancelService + cancelBooking (Binham parity). Code implemented; live HTTP env-gated. Evidence pending_cancel_retrieve_confirmation — HTTP 200 requires delayed getBooking segment confirmation.',
                'risk' => 'very_high',
            ],
            'gds_ticketing' => [
                'key' => 'gds_ticketing',
                'label' => 'GDS ticketing / documents / void / refund',
                'category' => 'gds',
                'status' => 'env_gated_evidence_pending',
                'files' => [
                    self::SERVICES_PREFIX.'Ticketing/SabreGdsTicketingService.php',
                    self::SERVICES_PREFIX.'Ticketing/SabreGdsTicketDocumentService.php',
                    self::SERVICES_PREFIX.'Ticketing/SabreGdsVoidTicketService.php',
                    self::SERVICES_PREFIX.'Ticketing/SabreGdsRefundTicketService.php',
                    $cmd('SabreGdsIssueTicketCommand.php'),
                    $cmd('SabreGdsTicketDocumentsCommand.php'),
                    $cmd('SabreGdsVoidTicketCommand.php'),
                    $cmd('SabreGdsRefundTicketCommand.php'),
                    $cmd('SabreTicketingCapabilityReportCommand.php'),
                    $cmd('SabreDiscoverTicketingEndpointsCommand.php'),
                ],
                'notes' => 'Ticketing + ticket documents implemented and env-gated (sabre:gds-issue-ticket, sabre:gds-ticket-documents). Void/refund services exist but provider_unsupported_manual where Binham used cancelBooking / Red Workspace only.',
                'risk' => 'medium',
            ],
            'ndc_search_offer_price' => [
                'key' => 'ndc_search_offer_price',
                'label' => 'NDC search / offer price',
                'category' => 'ndc',
                'status' => 'scaffold_env_gated',
                'files' => [
                    self::SERVICES_PREFIX.'Ndc/SabreNdcOfferSearchService.php',
                    self::SERVICES_PREFIX.'Ndc/SabreNdcOfferPriceService.php',
                    self::SERVICES_PREFIX.'Ndc/SabreNdcPayloadBuilder.php',
                    self::SERVICES_PREFIX.'Ndc/SabreNdcResponseNormalizer.php',
                    $cmd('SabreNdcOfferPriceCommand.php'),
                ],
                'notes' => 'NDC v5 shop and offer price scaffolds; search not on public checkout. Separate from GDS BFM shop/revalidate.',
                'risk' => 'medium',
            ],
            'ndc_reprice_order_change_retrieve' => [
                'key' => 'ndc_reprice_order_change_retrieve',
                'label' => 'NDC reprice / order change / retrieve / create',
                'category' => 'ndc',
                'status' => 'env_gated_evidence_pending',
                'files' => [
                    self::SERVICES_PREFIX.'Ndc/SabreNdcRepriceOrderService.php',
                    self::SERVICES_PREFIX.'Ndc/SabreNdcOrderChangeService.php',
                    self::SERVICES_PREFIX.'Ndc/SabreNdcOrderRetrieveService.php',
                    self::SERVICES_PREFIX.'Ndc/SabreNdcOrderCreateService.php',
                    self::DIAGNOSTICS_SERVICES_PREFIX.'SabreCertEntitlementMatrix.php',
                    $cmd('SabreNdcRepriceOrderCommand.php'),
                    $cmd('SabreNdcOrderChangeCommand.php'),
                    $cmd('SabreNdcRetrieveOrderCommand.php'),
                    $cmd('SabreNdcCreateOrderCommand.php'),
                    $cmd('SabreCertEntitlementMatrixCommand.php'),
                ],
                'notes' => 'NDC reprice, order change, retrieve, and order-create scaffolds implemented; live HTTP env-gated. Must not route into GDS PNR/ticketing/cancel paths.',
                'risk' => 'medium',
            ],
            'ndc_diagnostics' => [
                'key' => 'ndc_diagnostics',
                'label' => 'NDC diagnostics / capability',
                'category' => 'ndc',
                'status' => 'diagnostic',
                'files' => [
                    self::SERVICES_PREFIX.'Ndc/SabreNdcStatusService.php',
                    self::SERVICES_PREFIX.'Ndc/SabreNdcCapabilityReportService.php',
                    self::SERVICES_PREFIX.'Ndc/SabreNdcConnectionProbeService.php',
                    $cmd('SabreNdcStatusCommand.php'),
                    $cmd('SabreNdcCapabilityReportCommand.php'),
                    $cmd('SabreNdcConnectionProbeCommand.php'),
                ],
                'notes' => 'Read-only NDC status, capability report, and OAuth-only connection probe. No shop/order/ticketing/cancel mutations.',
                'risk' => 'low',
            ],
            'ndc_cancel' => [
                'key' => 'ndc_cancel',
                'label' => 'NDC order cancel',
                'category' => 'ndc',
                'status' => 'disabled_not_implemented',
                'files' => [],
                'notes' => 'Not implemented. Future lane NDC-CANCEL-VOID-REFUND-1; capability matrix ndc_cancel code=no.',
                'risk' => 'medium',
            ],
            'diagnostics_probes' => [
                'key' => 'diagnostics_probes',
                'label' => 'Diagnostics / probes',
                'category' => 'diagnostics',
                'status' => 'diagnostic',
                'files' => [
                    self::SERVICES_PREFIX.'SabreInspectGate.php',
                    self::DIAGNOSTICS_SERVICES_PREFIX.'SabreInspectSanitizer.php',
                    self::DIAGNOSTICS_SERVICES_PREFIX.'SabreCertTokenProbe.php',
                    self::DIAGNOSTICS_SERVICES_PREFIX.'SabreTicketingEndpointDiscovery.php',
                    self::DIAGNOSTICS_SERVICES_PREFIX.'SabreCertEntitlementMatrix.php',
                    self::DIAGNOSTICS_SERVICES_PREFIX.'SabrePccCapabilityMatrix.php',
                    $cmd('SabreCertEntitlementMatrixCommand.php'),
                    $cmd('SabrePccCapabilityMatrixCommand.php'),
                    $cmd('SabreCompareRevalidateStylesCommand.php'),
                    $cmd('SabreCompareRevalidateEndpointsCommand.php'),
                    $cmd('SabreCompareCreatebookingStylesCommand.php'),
                    $cmd('SabreCompareBookingEndpointsCommand.php'),
                    $cmd('SabreCertifyAlternativeBookingPathCommand.php'),
                ],
                'notes' => 'SSH/local inspect, entitlement matrices, compare runners, and redaction helpers. Must not affect production checkout when gates are respected.',
                'risk' => 'low',
            ],
            'experimental_obsolete' => [
                'key' => 'experimental_obsolete',
                'label' => 'Experimental / obsolete',
                'category' => 'obsolete',
                'status' => 'obsolete_candidate',
                'files' => [
                    self::SERVICES_PREFIX.'SabreFlightSupplier.php',
                ],
                'notes' => 'SabreFlightSupplier is an empty placeholder superseded by SabreFlightSupplierAdapter. Do not delete until container references are confirmed absent.',
                'risk' => 'low',
            ],
        ];

        return $lanes;
    }

    private static function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');

        if (str_starts_with($path, 'C:')) {
            $pos = strpos($path, '/');
            if ($pos !== false) {
                $path = substr($path, $pos + 1);
            }
        }

        return $path;
    }
}
