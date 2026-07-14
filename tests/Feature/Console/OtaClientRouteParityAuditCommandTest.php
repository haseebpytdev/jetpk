<?php

namespace Tests\Feature\Console;

use App\Support\Audits\ClientRouteParityAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

class OtaClientRouteParityAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $exportDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exportDir = storage_path('framework/testing/client-route-parity-audit');
        if (File::isDirectory($this->exportDir)) {
            File::cleanDirectory($this->exportDir);
        }
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->exportDir)) {
            File::cleanDirectory($this->exportDir);
        }

        parent::tearDown();
    }

    public function test_command_scans_routes_and_prints_read_only_banner(): void
    {
        $this->artisan('ota:client-route-parity-audit', [
            '--client' => 'haseeb-master',
            '--target' => 'jetpk',
            '--export-dir' => $this->exportDir,
        ])
            ->expectsOutputToContain('Classification: READ-ONLY client route parity audit (MC-7A).')
            ->expectsOutputToContain('live_supplier_call_attempted=false')
            ->expectsOutputToContain('Audit summary:')
            ->assertSuccessful();
    }

    public function test_known_login_route_is_marked_prefixable(): void
    {
        $service = app(ClientRouteParityAuditService::class);
        $rows = $service->scanRoutes('jetpk');

        $loginGet = collect($rows)->first(
            static fn (array $row): bool => $row['route_name'] === 'login' && $row['method'] === 'GET',
        );

        $this->assertNotNull($loginGet, 'Expected login GET route in scan results');
        $this->assertSame('auth_page', $loginGet['classification']);
        $this->assertSame('yes', $loginGet['should_have_client_prefix']);
        $this->assertSame('/jetpk/login', $loginGet['suggested_prefixed_uri']);
    }

    public function test_dev_cp_route_is_not_prefixable(): void
    {
        $service = app(ClientRouteParityAuditService::class);
        $rows = $service->scanRoutes('jetpk');

        $devCp = collect($rows)->first(
            static fn (array $row): bool => $row['route_name'] === 'dev.cp.index' && $row['method'] === 'GET',
        );

        $this->assertNotNull($devCp, 'Expected dev.cp.index GET route in scan results');
        $this->assertSame('dev_cp', $devCp['classification']);
        $this->assertSame('no', $devCp['should_have_client_prefix']);
    }

    public function test_destructive_supplier_booking_route_is_high_risk_and_not_prefixable(): void
    {
        $this->assertTrue(RouteFacade::has('admin.bookings.supplier-booking'));

        $service = app(ClientRouteParityAuditService::class);
        $rows = $service->scanRoutes('jetpk');

        $supplierBooking = collect($rows)->first(
            static fn (array $row): bool => $row['route_name'] === 'admin.bookings.supplier-booking',
        );

        $this->assertNotNull($supplierBooking);
        $this->assertSame('supplier_api_action', $supplierBooking['classification']);
        $this->assertSame('no', $supplierBooking['should_have_client_prefix']);
        $this->assertSame('high', $supplierBooking['risk_level']);
    }

    public function test_audit_writes_json_and_markdown_exports(): void
    {
        $this->artisan('ota:client-route-parity-audit', [
            '--client' => 'haseeb-master',
            '--target' => 'jetpk',
            '--export-dir' => $this->exportDir,
        ])->assertSuccessful();

        $jsonFiles = glob($this->exportDir.DIRECTORY_SEPARATOR.'client-route-parity-*.json');
        $mdFiles = glob($this->exportDir.DIRECTORY_SEPARATOR.'client-route-parity-*.md');

        $this->assertNotEmpty($jsonFiles);
        $this->assertNotEmpty($mdFiles);

        $json = json_decode(File::get($jsonFiles[0]), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('routes', $json);
        $this->assertNotEmpty($json['routes']);

        $firstRow = $json['routes'][0];
        foreach ([
            'route_name',
            'method',
            'uri',
            'action',
            'middleware',
            'classification',
            'should_have_client_prefix',
            'suggested_prefixed_uri',
            'risk_level',
            'notes',
        ] as $key) {
            $this->assertArrayHasKey($key, $firstRow, 'Missing key: '.$key);
        }

        $mdContent = File::get($mdFiles[0]);
        $this->assertStringContainsString('# Client Route Parity Audit (MC-7A)', $mdContent);
        $this->assertStringContainsString('| Total route rows |', $mdContent);
    }

    public function test_fail_on_high_risk_passes_when_no_conflicts(): void
    {
        $this->artisan('ota:client-route-parity-audit', [
            '--client' => 'haseeb-master',
            '--target' => 'jetpk',
            '--export-dir' => $this->exportDir,
            '--fail-on-high-risk' => true,
        ])->assertSuccessful();
    }

    public function test_fail_on_high_risk_exits_one_when_conflicts_exist(): void
    {
        $this->mock(ClientRouteParityAuditService::class, function ($mock): void {
            $mock->shouldReceive('run')->once()->andReturn([
                'rows' => [],
                'summary' => [
                    'total_rows' => 0,
                    'prefixable_yes' => 0,
                    'high_risk' => 1,
                    'high_risk_prefixable_conflicts' => 1,
                    'by_classification' => [],
                ],
                'json_path' => $this->exportDir.'/client-route-parity-test.json',
                'md_path' => $this->exportDir.'/client-route-parity-test.md',
                'high_risk_prefixable_conflicts' => [[
                    'route_name' => 'test.conflict',
                    'method' => 'POST',
                    'uri' => 'test/conflict',
                    'classification' => 'booking_flow',
                    'should_have_client_prefix' => 'yes',
                    'risk_level' => 'high',
                    'notes' => 'synthetic test conflict',
                ]],
            ]);
        });

        $this->artisan('ota:client-route-parity-audit', [
            '--client' => 'haseeb-master',
            '--target' => 'jetpk',
            '--export-dir' => $this->exportDir,
            '--fail-on-high-risk' => true,
        ])
            ->expectsOutputToContain('High-risk prefixable conflicts detected')
            ->assertExitCode(1);
    }

    public function test_empty_client_option_fails(): void
    {
        $this->artisan('ota:client-route-parity-audit', [
            '--client' => '',
            '--target' => 'jetpk',
        ])
            ->expectsOutputToContain('Option --client must not be empty.')
            ->assertExitCode(1);
    }

    public function test_no_supplier_http_call_attempted(): void
    {
        Http::fake();

        $this->artisan('ota:client-route-parity-audit', [
            '--client' => 'haseeb-master',
            '--target' => 'jetpk',
            '--export-dir' => $this->exportDir,
        ])->assertSuccessful();

        Http::assertNothingSent();
    }
}
