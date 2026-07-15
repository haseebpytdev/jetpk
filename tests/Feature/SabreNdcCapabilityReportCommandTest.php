<?php

namespace Tests\Feature;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Ndc\SabreNdcCapabilityReportService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreNdcCapabilityReportCommandTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_CLIENT_ID = 'ndc-report-client-id';

    private const TEST_CLIENT_SECRET = 'ndc-report-client-secret-value';

    private const TEST_PCC = 'NDC1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        Config::set('suppliers.sabre.ndc.enabled', false);
        Config::set('suppliers.sabre.ndc.global_kill_switch', false);
        Http::fake();
    }

    public function test_capability_report_masks_credentials(): void
    {
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Live,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'settings' => ['sabre_ndc_enabled' => true, 'sabre_gds_enabled' => false],
            'credentials' => [
                'client_id' => self::TEST_CLIENT_ID,
                'client_secret' => self::TEST_CLIENT_SECRET,
                'pcc' => self::TEST_PCC,
            ],
        ]);

        Artisan::call('sabre:ndc-capability-report', [
            '--connection' => (string) $connection->id,
            '--json' => true,
        ]);

        $output = Artisan::output();
        $decoded = json_decode(trim($output), true);

        $this->assertIsArray($decoded);
        $this->assertSame('sabre_ndc', $decoded['lane']);
        $this->assertTrue($decoded['gds_lane_separated']);
        $this->assertTrue($decoded['lane_gate']['effective_ndc_enabled']);
        $this->assertSame(['ndc'], $decoded['lane_gate']['selected_sabre_lanes']);

        $credentials = $decoded['credentials'];
        $this->assertTrue($credentials['shared_credentials_present']);
        $this->assertTrue($credentials['credentials_shared']);
        $this->assertSame(strlen(self::TEST_PCC), $credentials['pcc_len']);
        $this->assertStringNotContainsString(self::TEST_CLIENT_SECRET, $output);
        $this->assertStringNotContainsString(self::TEST_CLIENT_ID, $output);
        $this->assertStringNotContainsString(self::TEST_PCC, $output);
        $this->assertNotContains('sabre_ndc_disabled', $decoded['status']['blockers']);

        Http::assertNothingSent();
    }

    public function test_mutation_capabilities_disabled_by_default(): void
    {
        Artisan::call('sabre:ndc-capability-report', ['--json' => true]);
        $decoded = json_decode(trim(Artisan::output()), true);

        $this->assertFalse($decoded['capabilities']['search']['enabled']);
        $this->assertFalse($decoded['capabilities']['order_create']['enabled']);
        $this->assertFalse($decoded['capabilities']['payment_ticketing_fulfillment']['enabled']);
        $this->assertTrue($decoded['mutation_gates']['gds_ticketing_blocked_for_ndc_channel']);
    }

    public function test_ndc_channel_enabled_without_legacy_env_enabled_flag(): void
    {
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Live,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'settings' => ['sabre_ndc_enabled' => true, 'sabre_gds_enabled' => false],
            'credentials' => [
                'client_id' => self::TEST_CLIENT_ID,
                'client_secret' => self::TEST_CLIENT_SECRET,
            ],
        ]);

        Config::set('suppliers.sabre.ndc.enabled', false);

        $report = app(SabreNdcCapabilityReportService::class)->report($connection);

        $this->assertTrue($report['capabilities']['ndc_channel']['enabled']);
        $this->assertFalse($report['capabilities']['search']['enabled']);
    }
}
