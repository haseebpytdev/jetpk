<?php

namespace Tests\Unit\Audits;

use App\Support\Audits\HaseebMasterRouteSafetyAuditService;
use Tests\TestCase;

class HaseebMasterRouteSafetyAuditServiceTest extends TestCase
{
    public function test_audit_reports_missing_when_route_name_is_unknown(): void
    {
        $rows = app(HaseebMasterRouteSafetyAuditService::class)->run('haseeb-master');

        $this->assertNotEmpty($rows);
        $this->assertSame(
            0,
            collect($rows)->whereIn('status', ['missing', 'collision-risk'])->count(),
        );

        $this->assertTrue(
            collect($rows)->contains(
                fn (array $row): bool => $row['name'] === 'home' && $row['status'] === 'OK',
            ),
        );
    }
}
