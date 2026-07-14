<?php

namespace Tests\Unit\Support\Suppliers;

use App\Data\FlightSearchRequestData;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Adapters\SabreFlightSupplierAdapter;
use App\Services\Suppliers\Sabre\Ndc\SabreNdcCapabilityReportService;
use App\Services\Suppliers\Sabre\Ndc\SabreNdcStatusService;
use App\Support\Suppliers\SabreChannelGateResolver;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreChannelGateResolverTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_CLIENT_ID = 'gate-client-id';

    private const TEST_CLIENT_SECRET = 'gate-client-secret';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        Config::set('suppliers.sabre.ndc.enabled', false);
        Config::set('suppliers.sabre.ndc.search_enabled', false);
        Config::set('suppliers.sabre.ndc.global_kill_switch', false);
        Config::set('suppliers.sabre.gds_global_kill_switch', false);
    }

    public function test_ndc_on_gds_off_selects_ndc_lane_only(): void
    {
        $connection = $this->sabreConnection([
            'sabre_gds_enabled' => false,
            'sabre_ndc_enabled' => true,
        ]);

        $gate = app(SabreChannelGateResolver::class);
        $diag = $gate->diagnostics($connection);

        $this->assertSame(['ndc'], $diag['selected_sabre_lanes']);
        $this->assertTrue($diag['effective_ndc_enabled']);
        $this->assertFalse($diag['effective_gds_enabled']);
        $this->assertTrue($diag['gds_suppressed']);
        $this->assertTrue($diag['gds_results_suppressed']);
        $this->assertTrue($diag['ndc_allowed']);
        $this->assertTrue($diag['shared_credentials_present']);
        $this->assertTrue($diag['credentials_shared']);
    }

    public function test_gds_disabled_does_not_remove_shared_credentials(): void
    {
        $connection = $this->sabreConnection([
            'sabre_gds_enabled' => false,
            'sabre_ndc_enabled' => true,
        ]);

        $status = app(SabreNdcStatusService::class)->status($connection);

        $this->assertTrue($status['shared_credentials_present']);
        $this->assertSame('supplier_connection_shared_with_gds', $status['credentials_source']);
        $this->assertNotContains('credentials_missing', $status['blockers']);
    }

    public function test_capability_report_no_sabre_ndc_disabled_when_connection_ndc_on(): void
    {
        $connection = $this->sabreConnection([
            'sabre_gds_enabled' => false,
            'sabre_ndc_enabled' => true,
        ]);

        $report = app(SabreNdcCapabilityReportService::class)->report($connection);

        $this->assertTrue($report['lane_gate']['effective_ndc_enabled']);
        $this->assertTrue($report['credentials']['shared_credentials_present']);
        $this->assertNotContains('sabre_ndc_disabled', $report['status']['blockers']);
        $this->assertNotContains('global_ndc_kill_switch_active', $report['status']['blockers']);
    }

    public function test_global_ndc_kill_switch_blocks_effective_ndc(): void
    {
        Config::set('suppliers.sabre.ndc.global_kill_switch', true);

        $connection = $this->sabreConnection([
            'sabre_gds_enabled' => false,
            'sabre_ndc_enabled' => true,
        ]);

        $gate = app(SabreChannelGateResolver::class);

        $this->assertFalse($gate->effectiveNdcEnabled($connection));
        $this->assertContains('global_ndc_kill_switch_active', $gate->ndcLaneBlockers($connection));
    }

    public function test_adapter_ndc_only_does_not_call_gds_bfm(): void
    {
        Http::fake();

        $connection = $this->sabreConnection([
            'sabre_gds_enabled' => false,
            'sabre_ndc_enabled' => true,
        ]);

        $adapter = app(SabreFlightSupplierAdapter::class);
        $result = $adapter->search(
            new FlightSearchRequestData(
                origin: 'KHI',
                destination: 'DXB',
                departure_date: '2026-12-01',
                adults: 1,
            ),
            $connection,
        );

        $this->assertSame(['ndc'], $result->meta['selected_sabre_lanes'] ?? []);
        $this->assertTrue($result->meta['gds_suppressed'] ?? false);
        $this->assertTrue($result->meta['gds_results_suppressed'] ?? false);
        $this->assertSame('sabre_ndc_live_search_http_disabled', $result->meta['ndc_search']['reason_code'] ?? null);
        Http::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function sabreConnection(array $settings): SupplierConnection
    {
        return SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Live,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'settings' => $settings,
            'credentials' => [
                'client_id' => self::TEST_CLIENT_ID,
                'client_secret' => self::TEST_CLIENT_SECRET,
                'pcc' => 'GATE',
            ],
        ]);
    }
}
