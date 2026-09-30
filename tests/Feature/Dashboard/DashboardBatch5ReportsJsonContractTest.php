<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountType;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class DashboardBatch5ReportsJsonContractTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_sales_report_returns_monthly_sales_rows_not_agent_rows(): void
    {
        $admin = $this->platformAdmin();

        $response = $this->actingAs($admin)
            ->getJson(route('api.dashboard.reports.sales', ['currency' => 'PKR']))
            ->assertOk()
            ->assertJsonPath('data.section', 'sales');

        $rows = $response->json('data.tableRows') ?? [];
        $this->assertIsArray($rows);
        foreach ($rows as $row) {
            $this->assertArrayHasKey('label', $row);
            $this->assertArrayNotHasKey('agent_name', $row);
        }
    }

    public function test_operations_report_returns_operational_kpi_rows(): void
    {
        $admin = $this->platformAdmin();

        $response = $this->actingAs($admin)
            ->getJson(route('api.dashboard.reports.operations', ['currency' => 'PKR']))
            ->assertOk()
            ->assertJsonPath('data.section', 'operations');

        $metrics = collect($response->json('data.metrics') ?? []);
        $this->assertTrue($metrics->contains(fn ($m) => ($m['key'] ?? null) === 'ticketing_pending'));
    }

    public function test_reports_summary_has_live_data_flag_and_no_fixture_strings(): void
    {
        $admin = $this->platformAdmin();

        $payload = $this->actingAs($admin)
            ->getJson(route('api.dashboard.reports.summary', ['currency' => 'PKR']))
            ->assertOk()
            ->json();

        $encoded = strtolower((string) json_encode($payload));
        $this->assertStringNotContainsString('preview-only fixture', $encoded);
        $this->assertStringNotContainsString('jp-fixture', $encoded);
        $this->assertArrayHasKey('hasLiveData', $payload['data'] ?? []);
    }
}
