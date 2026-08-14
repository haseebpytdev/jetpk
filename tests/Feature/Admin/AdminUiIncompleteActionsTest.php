<?php

namespace Tests\Feature\Admin;

use App\Models\Agent;
use App\Models\Booking;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AdminLegacyViewTestHelpers;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class AdminUiIncompleteActionsTest extends TestCase
{
    use AdminLegacyViewTestHelpers;
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_agent_preview_planned_features_are_not_primary_buttons(): void
    {
        $admin = $this->platformAdmin();
        $agent = Agent::query()->firstOrFail();

        $html = $this->actingAs($admin)
            ->getJson(route('admin.agents.preview', $agent))
            ->assertOk()
            ->json('html');

        $this->assertIsString($html);
        $this->assertStringContainsString('data-testid="ota-agents-planned-features"', $html);
        $this->assertStringContainsString('data-testid="ota-agents-action-edit-commission"', $html);
        $this->assertStringNotContainsString('data-testid="ota-agents-action-edit-commission" class="btn btn-primary"', $html);
        $this->assertStringNotContainsString('data-testid="ota-agents-action-deactivate" class="btn btn-outline-danger"', $html);
    }

    public function test_admin_booking_show_hides_unwired_passenger_actions_as_planned_section(): void
    {
        $booking = Booking::factory()->create();
        $admin = $this->platformAdmin();

        $html = $this->adminBookingShowHtml($admin, $booking);
        $this->assertStringContainsString('data-testid="admin-booking-planned-passenger-actions"', $html);
        $this->assertStringNotContainsString('btn btn-outline-primary w-100" disabled>Edit passenger details', $html);
    }

    public function test_admin_reports_does_not_show_active_pdf_export_button(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.reports'))
            ->assertRedirect('/admin/dashboard/reports');

        $html = $this->adminReportsHtml($admin);
        $this->assertStringContainsString('data-testid="ota-reports-pdf-unavailable"', $html);
        $this->assertStringContainsString('PDF export not enabled yet', $html);
        $this->assertStringNotContainsString('Export PDF (coming soon)', $html);
    }
}
