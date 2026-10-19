<?php

namespace Tests\Unit\Support\Ops;

use App\Support\Ops\OpsExternalAlertRouter;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OpsExternalAlertRouterTest extends TestCase
{
    public function test_skips_when_destination_missing_without_throwing(): void
    {
        config([
            'ops.external_alerts_enabled' => true,
            'ops.alert_email' => null,
            'ops.alert_dry_run' => false,
        ]);

        Mail::fake();
        $result = app(OpsExternalAlertRouter::class)->route(
            OpsExternalAlertRouter::LEVEL_DISK,
            'Disk HIGH',
            'body',
        );

        $this->assertFalse($result['delivered']);
        $this->assertSame('log_only', $result['channel']);
        Mail::assertNothingSent();
    }

    public function test_dry_run_does_not_send_mail(): void
    {
        config([
            'ops.external_alerts_enabled' => true,
            'ops.alert_email' => 'ops.alert@example.test',
            'ops.alert_dry_run' => false,
        ]);

        Mail::fake();
        $result = app(OpsExternalAlertRouter::class)->route(
            OpsExternalAlertRouter::LEVEL_QUEUE,
            'Queue failure',
            'failed_jobs=1',
            dryRun: true,
        );

        $this->assertTrue($result['dry_run']);
        $this->assertFalse($result['delivered']);
        Mail::assertNothingSent();
    }

    public function test_enabled_destination_sends_ops_mail(): void
    {
        config([
            'ops.external_alerts_enabled' => true,
            'ops.alert_email' => 'ops.alert@example.test',
            'ops.alert_dry_run' => false,
        ]);

        Mail::shouldReceive('raw')
            ->once()
            ->withArgs(function (string $body, callable $callback): bool {
                $this->assertStringContainsString('auth failure spike', $body);

                return true;
            })
            ->andReturnNull();

        $result = app(OpsExternalAlertRouter::class)->route(
            OpsExternalAlertRouter::LEVEL_SECURITY,
            'Security anomaly',
            'auth failure spike',
        );

        $this->assertTrue($result['delivered']);
        $this->assertSame('mail', $result['channel']);
    }

    public function test_transport_failure_is_non_fatal(): void
    {
        config([
            'ops.external_alerts_enabled' => true,
            'ops.alert_email' => 'ops.alert@example.test',
            'ops.alert_dry_run' => false,
        ]);

        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('smtp down'));

        $result = app(OpsExternalAlertRouter::class)->route(
            OpsExternalAlertRouter::LEVEL_APP,
            'App failure',
            'exception',
        );

        $this->assertFalse($result['delivered']);
        $this->assertSame('transport_failed', $result['reason'] ?? null);
    }
}
