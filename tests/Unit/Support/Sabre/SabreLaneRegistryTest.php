<?php

namespace Tests\Unit\Support\Sabre;

use App\Support\Sabre\SabreLaneRegistry;
use Tests\TestCase;

class SabreLaneRegistryTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function expectedProductionCritical(): array
    {
        return [
            'app/Services/Suppliers/Sabre/Core/SabreClient.php',
            'app/Services/Suppliers/Sabre/Core/SabreBookingClient.php',
            'app/Services/Suppliers/Sabre/Gds/SabreFlightSearchRequestBuilder.php',
            'app/Services/Suppliers/Sabre/Gds/SabreFlightSearchNormalizer.php',
            'app/Services/Suppliers/Sabre/Gds/SabreRevalidationPayloadBuilder.php',
            'app/Services/Suppliers/Sabre/Booking/SabreBookingService.php',
            'app/Services/Suppliers/Sabre/Booking/SabreBookingPayloadBuilder.php',
            'app/Services/Suppliers/Sabre/PnrRetrieve/SabrePnrItinerarySyncService.php',
            'app/Services/Suppliers/Sabre/PnrRetrieve/SabreTripOrdersGetBookingItineraryMapper.php',
            'app/Services/Suppliers/Sabre/Cancel/SabreBookingCancelService.php',
        ];
    }

    public function test_all_returns_twelve_lanes_with_required_shape(): void
    {
        $lanes = SabreLaneRegistry::all();

        $this->assertCount(14, $lanes);
        $this->assertArrayHasKey('core_auth_connection', $lanes);
        $this->assertArrayHasKey('ndc_search_offer_price', $lanes);
        $this->assertArrayHasKey('ndc_diagnostics', $lanes);
        $this->assertArrayHasKey('ndc_reprice_order_change_retrieve', $lanes);
        $this->assertArrayHasKey('ndc_cancel', $lanes);
        $this->assertArrayHasKey('experimental_obsolete', $lanes);
        $this->assertArrayNotHasKey('ndc_search_order_cancel', $lanes);

        foreach ($lanes as $lane) {
            $this->assertNotEmpty($lane['key']);
            $this->assertNotEmpty($lane['label']);
            $this->assertContains($lane['category'], ['core', 'gds', 'ndc', 'diagnostics', 'experimental', 'obsolete']);
            $this->assertContains($lane['status'], [
                'production',
                'env_gated_evidence_pending',
                'scaffold_env_gated',
                'provider_unsupported_manual',
                'diagnostic',
                'disabled_not_implemented',
                'obsolete_candidate',
            ]);
            $this->assertIsArray($lane['files']);
            if ($lane['status'] !== 'disabled_not_implemented') {
                $this->assertNotEmpty($lane['files']);
            }
            $this->assertNotEmpty($lane['notes']);
            $this->assertContains($lane['risk'], ['low', 'medium', 'high', 'very_high']);
        }
    }

    public function test_production_critical_files_are_listed(): void
    {
        $critical = SabreLaneRegistry::productionCriticalFiles();

        $this->assertEqualsCanonicalizing($this->expectedProductionCritical(), $critical);
        $this->assertCount(10, $critical);
    }

    public function test_diagnostics_only_files_are_separated_from_production_critical(): void
    {
        $diagnostics = SabreLaneRegistry::diagnosticsOnlyFiles();
        $critical = SabreLaneRegistry::productionCriticalFiles();

        $this->assertNotEmpty($diagnostics);
        $this->assertEmpty(array_intersect($diagnostics, $critical));

        $this->assertContains('app/Services/Suppliers/Sabre/SabreInspectGate.php', $diagnostics);
        $this->assertContains('app/Console/Commands/SabreCertEntitlementMatrixCommand.php', $diagnostics);
        $this->assertContains('app/Console/Commands/SabreCompareBookingEndpointsCommand.php', $diagnostics);
    }

    public function test_sabre_flight_supplier_is_obsolete_candidate_not_production_critical(): void
    {
        $obsolete = SabreLaneRegistry::obsoleteCandidates();
        $critical = SabreLaneRegistry::productionCriticalFiles();

        $this->assertSame(['app/Services/Suppliers/Sabre/SabreFlightSupplier.php'], $obsolete);
        $this->assertNotContains('app/Services/Suppliers/Sabre/SabreFlightSupplier.php', $critical);

        $lane = SabreLaneRegistry::all()['experimental_obsolete'];
        $this->assertSame('obsolete_candidate', $lane['status']);
        $this->assertSame('obsolete', $lane['category']);
    }

    public function test_gds_cancellation_lane_is_env_gated_evidence_pending(): void
    {
        $lane = SabreLaneRegistry::all()['gds_cancellation'];

        $this->assertSame('env_gated_evidence_pending', $lane['status']);
        $this->assertStringContainsString('pending_cancel_retrieve_confirmation', $lane['notes']);
        $this->assertContains('app/Services/Suppliers/Sabre/Cancel/SabreBookingCancelService.php', $lane['files']);
        $this->assertContains('app/Console/Commands/SabreProductionCancelEvidenceCommand.php', $lane['files']);
    }

    public function test_gds_ticketing_lane_is_env_gated_evidence_pending(): void
    {
        $lane = SabreLaneRegistry::all()['gds_ticketing'];

        $this->assertSame('env_gated_evidence_pending', $lane['status']);
        $this->assertContains('app/Services/Suppliers/Sabre/Ticketing/SabreGdsTicketingService.php', $lane['files']);
        $this->assertContains('app/Console/Commands/SabreGdsIssueTicketCommand.php', $lane['files']);
    }

    public function test_ndc_lanes_split_implemented_vs_not_implemented(): void
    {
        $implemented = SabreLaneRegistry::all()['ndc_reprice_order_change_retrieve'];
        $cancel = SabreLaneRegistry::all()['ndc_cancel'];

        $this->assertSame('ndc', $implemented['category']);
        $this->assertSame('env_gated_evidence_pending', $implemented['status']);
        $this->assertContains('app/Services/Suppliers/Sabre/Ndc/SabreNdcRepriceOrderService.php', $implemented['files']);

        $this->assertSame('ndc', $cancel['category']);
        $this->assertSame('disabled_not_implemented', $cancel['status']);
        $this->assertStringContainsString('Not implemented', $cancel['notes']);
    }

    public function test_lane_for_file_maps_known_files_correctly(): void
    {
        $this->assertSame(
            'core_auth_connection',
            SabreLaneRegistry::laneForFile('app/Services/Suppliers/Sabre/Core/SabreClient.php'),
        );
        $this->assertSame(
            'core_auth_connection',
            SabreLaneRegistry::laneForFile('app/Services/Suppliers/Sabre/Core/SabreBookingClient.php'),
        );
        $this->assertSame(
            'gds_normalizer',
            SabreLaneRegistry::laneForFile('SabreFlightSearchNormalizer.php'),
        );
        $this->assertSame(
            'gds_search',
            SabreLaneRegistry::laneForFile('app/Services/Suppliers/Sabre/Gds/SabreSegmentFreshShopSellabilityService.php'),
        );
        $this->assertSame(
            'gds_search',
            SabreLaneRegistry::laneForFile('app/Services/Suppliers/Sabre/Gds/SabreBookingOfferRefreshService.php'),
        );
        $this->assertSame(
            'gds_pnr_creation',
            SabreLaneRegistry::laneForFile('app/Services/Suppliers/Sabre/Booking/SabreBookingService.php'),
        );
        $this->assertSame(
            'gds_pnr_creation',
            SabreLaneRegistry::laneForFile('app/Services/Suppliers/Sabre/Booking/SabreBookingPayloadBuilder.php'),
        );
        $this->assertSame(
            'gds_cancellation',
            SabreLaneRegistry::laneForFile('app/Services/Suppliers/Sabre/Cancel/SabreBookingCancelService.php'),
        );
        $this->assertSame(
            'gds_ticketing',
            SabreLaneRegistry::laneForFile('app/Services/Suppliers/Sabre/Ticketing/SabreGdsTicketingService.php'),
        );
        $this->assertSame(
            'ndc_reprice_order_change_retrieve',
            SabreLaneRegistry::laneForFile('app/Services/Suppliers/Sabre/Ndc/SabreNdcRepriceOrderService.php'),
        );
        $this->assertSame(
            'diagnostics_probes',
            SabreLaneRegistry::laneForFile('app/Console/Commands/SabreCompareRevalidateStylesCommand.php'),
        );
        $this->assertSame(
            'experimental_obsolete',
            SabreLaneRegistry::laneForFile('app/Services/Suppliers/Sabre/SabreFlightSupplier.php'),
        );
        $this->assertNull(SabreLaneRegistry::laneForFile('app/Services/Suppliers/Duffel/DuffelClient.php'));
    }
}
