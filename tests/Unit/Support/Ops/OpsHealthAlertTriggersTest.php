<?php

namespace Tests\Unit\Support\Ops;

use App\Support\Ops\OpsExternalAlertRouter;
use App\Support\Ops\OpsHealthAlertTriggers;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OpsHealthAlertTriggersTest extends TestCase
{
    public function test_disk_normal_and_zero_failed_jobs_yields_no_alerts(): void
    {
        $this->assertSame([], OpsHealthAlertTriggers::forDiskAndFailedJobs('NORMAL', 0));
    }

    public function test_disk_high_zero_failed_jobs_yields_disk_only(): void
    {
        $alerts = OpsHealthAlertTriggers::forDiskAndFailedJobs('HIGH', 0);

        $this->assertCount(1, $alerts);
        $this->assertSame(OpsExternalAlertRouter::LEVEL_DISK, $alerts[0]['category']);
        $this->assertSame('HIGH', $alerts[0]['level']);
    }

    public function test_disk_normal_with_failed_jobs_yields_queue_failure_independently(): void
    {
        $alerts = OpsHealthAlertTriggers::forDiskAndFailedJobs('NORMAL', 3);

        $this->assertCount(1, $alerts);
        $this->assertSame(OpsExternalAlertRouter::LEVEL_QUEUE, $alerts[0]['category']);
    }

    public function test_disk_high_with_failed_jobs_yields_both(): void
    {
        $alerts = OpsHealthAlertTriggers::forDiskAndFailedJobs('CRITICAL', 12);

        $this->assertCount(2, $alerts);
        $this->assertSame(OpsExternalAlertRouter::LEVEL_DISK, $alerts[0]['category']);
        $this->assertSame(OpsExternalAlertRouter::LEVEL_QUEUE, $alerts[1]['category']);
        $this->assertSame('HIGH', $alerts[1]['level']);
    }

    public function test_dispatch_command_routes_queue_failure_when_enabled(): void
    {
        config([
            'ops.external_alerts_enabled' => true,
            'ops.alert_email' => 'ops.alert@example.test',
            'ops.alert_dry_run' => false,
        ]);

        Mail::shouldReceive('raw')->once()->andReturnNull();

        $this->artisan('jetpk:ops-health-alert', [
            '--disk-level' => 'NORMAL',
            '--failed-jobs' => 2,
        ])->assertSuccessful();
    }

    public function test_dispatch_command_sends_nothing_when_normal_and_zero(): void
    {
        config([
            'ops.external_alerts_enabled' => true,
            'ops.alert_email' => 'ops.alert@example.test',
            'ops.alert_dry_run' => false,
        ]);

        Mail::fake();

        $this->artisan('jetpk:ops-health-alert', [
            '--disk-level' => 'NORMAL',
            '--failed-jobs' => 0,
        ])->assertSuccessful()
            ->expectsOutput('OPS_ALERT_DISPATCH=NONE');

        Mail::assertNothingOutgoing();
    }
}
