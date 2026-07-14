<?php

namespace Tests\Feature\Console;

use App\Services\Suppliers\Sabre\Diagnostics\SabreProdGapAuditService;
use Tests\TestCase;

class SabreProdGapAuditCommandTest extends TestCase
{
    public function test_prod_gap_audit_reports_capabilities(): void
    {
        $report = app(SabreProdGapAuditService::class)->run();

        $this->assertSame('sabre_prod_gap_audit_v2', $report['audit_version']);
        $this->assertGreaterThan(0, $report['pass']);
        $this->assertSame(0, $report['fail']);

        $keys = collect($report['capabilities'])->pluck('key')->all();
        $this->assertContains('gds_revalidation', $keys);
        $this->assertContains('ndc_reprice', $keys);
        $this->assertContains('ticket_issue', $keys);
    }
}
