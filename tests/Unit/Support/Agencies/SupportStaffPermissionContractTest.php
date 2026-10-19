<?php

namespace Tests\Unit\Support\Agencies;

use App\Enums\AgencyRole;
use App\Support\Agencies\AgencyRolePermissionMatrix;
use App\Support\Agents\AgentPermission;
use App\Support\Staff\StaffPermission;
use Tests\TestCase;

class SupportStaffPermissionContractTest extends TestCase
{
    public function test_agency_support_staff_template_excludes_wallet_ledger_staff_book_create(): void
    {
        $perms = AgencyRolePermissionMatrix::suggestedPermissions(AgencyRole::SupportStaff);

        $this->assertContains(AgentPermission::BookingsView, $perms);
        $this->assertContains(AgentPermission::SupportManage, $perms);
        $this->assertNotContains(AgentPermission::WalletView, $perms);
        $this->assertNotContains(AgentPermission::LedgerView, $perms);
        $this->assertNotContains(AgentPermission::LedgerManage, $perms);
        $this->assertNotContains(AgentPermission::StaffManage, $perms);
        $this->assertNotContains(AgentPermission::BookingsCreate, $perms);
        $this->assertNotContains(AgentPermission::PaymentsUpload, $perms);
        $this->assertNotContains(AgentPermission::ReportsView, $perms);
    }

    public function test_platform_support_preset_is_view_and_support_only(): void
    {
        $perms = StaffPermission::presetPermissions(StaffPermission::PresetSupport);

        $this->assertSame([
            StaffPermission::BookingsView,
            StaffPermission::SupportView,
            StaffPermission::SupportReply,
            StaffPermission::SupportStatus,
        ], $perms);
        $this->assertNotContains(StaffPermission::PaymentsVerify, $perms);
        $this->assertNotContains(StaffPermission::PaymentsRecord, $perms);
        $this->assertNotContains(StaffPermission::LedgerManage, $perms);
        $this->assertNotContains(StaffPermission::TicketingIssue, $perms);
        $this->assertNotContains(StaffPermission::BookingsUpdateStatus, $perms);
    }
}
