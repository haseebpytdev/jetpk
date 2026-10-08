<?php

namespace Tests\Feature\Console;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JetpkDashboardProdCertQaCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        putenv('JP_DASH_03_QA_ADMIN_PASSWORD=QaAdminPass!234');
        putenv('JP_DASH_03_QA_STAFF_PASSWORD=QaStaffPass!234');
        putenv('JP_DASH_03_QA_AGENT_PASSWORD=QaAgentPass!234');
        putenv('JP_DASH_03_QA_CUSTOMER_PASSWORD=QaCustomerPass!234');
        putenv('JP_DASH_03_QA_AGENT_STAFF_PASSWORD=QaAgentStaffPass!234');
    }

    public function test_reconcile_creates_production_qa_agency_and_fixtures(): void
    {
        $this->artisan('jetpk:dash-03-qa-identities', ['role' => 'all', 'action' => 'create'])->assertSuccessful();
        $this->artisan('jetpk:dash-03-qa-staff', ['action' => 'create'])->assertSuccessful();

        $this->artisan('jetpk:dashboard-prod-cert-qa', ['action' => 'reconcile'])
            ->expectsOutputToContain('QA_AGENCY_SLUG=jetpk-production-qa')
            ->expectsOutputToContain('QA_RECONCILE=PASS')
            ->assertSuccessful();

        $agency = Agency::query()->where('slug', 'jetpk-production-qa')->first();
        $this->assertNotNull($agency);
        $this->assertTrue((bool) data_get($agency->settings, 'qa_only'));

        $admin = User::query()->where('username', 'jp-dash-03-qa-admin')->first();
        $this->assertNotNull($admin);
        $this->assertSame($agency->id, $admin->current_agency_id);
        $this->assertSame(UserAccountStatus::Active, $admin->status);
        $this->assertFalse((bool) $admin->must_change_password);

        $this->artisan('jetpk:dashboard-prod-cert-qa', ['action' => 'create-fixtures'])
            ->expectsOutputToContain('QA_FIXTURES=PASS')
            ->assertSuccessful();

        $this->assertTrue(
            Booking::query()->where('booking_reference', 'JPQA-20261008-BOOKING')->exists()
        );
    }

    public function test_sync_password_never_prints_secret(): void
    {
        $this->artisan('jetpk:dash-03-qa-identities', ['role' => 'admin', 'action' => 'create'])->assertSuccessful();

        $exit = $this->artisan('jetpk:dashboard-prod-cert-qa', [
            'action' => 'sync-password',
            '--role' => 'admin',
        ])->run();

        $this->assertSame(0, $exit);
        $output = $this->artisan('jetpk:dashboard-prod-cert-qa', ['action' => 'status'])->run();
        $this->assertSame(0, $output);
    }
}
