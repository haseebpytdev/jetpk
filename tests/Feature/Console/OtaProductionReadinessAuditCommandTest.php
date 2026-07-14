<?php

namespace Tests\Feature\Console;

use App\Support\Audits\ProductionReadinessAuditService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OtaProductionReadinessAuditCommandTest extends TestCase
{
    public function test_command_runs_and_reports_read_only(): void
    {
        $this->artisan('ota:production-readiness-audit')
            ->expectsOutputToContain('Classification: READ-ONLY')
            ->expectsOutputToContain('live_supplier_call_attempted=false')
            ->expectsOutputToContain('Audit summary:')
            ->assertSuccessful();
    }

    public function test_output_does_not_contain_secret_patterns(): void
    {
        Artisan::call('ota:production-readiness-audit');
        $lower = strtolower(Artisan::output());

        $this->assertStringNotContainsString('app_key=', $lower);
        $this->assertStringNotContainsString('db_password', $lower);
        $this->assertStringNotContainsString('client_secret', $lower);
        $this->assertStringNotContainsString('bearer eyj', $lower);
        $this->assertStringNotContainsString('smtp_password', $lower);
    }

    public function test_app_debug_unsafe_when_debug_true(): void
    {
        config(['app.debug' => true, 'app.env' => 'local']);

        $result = app(ProductionReadinessAuditService::class)->run();
        $debug = collect($result['findings'])->firstWhere('label', 'APP_DEBUG');

        $this->assertNotNull($debug);
        $this->assertSame('unsafe', $debug['detail']);
        $this->assertSame('warn', $debug['status']);
    }

    public function test_app_debug_fail_when_debug_true_in_production_env(): void
    {
        config(['app.debug' => true, 'app.env' => 'production']);

        $result = app(ProductionReadinessAuditService::class)->run();
        $debug = collect($result['findings'])->firstWhere('label', 'APP_DEBUG');

        $this->assertNotNull($debug);
        $this->assertSame('unsafe', $debug['detail']);
        $this->assertSame('fail', $debug['status']);

        $this->artisan('ota:production-readiness-audit')
            ->expectsOutputToContain('[FAIL] APP_DEBUG: unsafe')
            ->assertExitCode(1);
    }

    public function test_no_supplier_http_call_attempted(): void
    {
        Http::fake();

        $this->artisan('ota:production-readiness-audit')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_required_f6_commands_are_reported(): void
    {
        $this->artisan('ota:production-readiness-audit')
            ->expectsOutputToContain('ota:smoke-live-routes')
            ->expectsOutputToContain('ota:audit-sabre-status')
            ->expectsOutputToContain('devcp:seed-default-packages')
            ->assertSuccessful();
    }
}
