<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Http\Controllers\Admin\DashboardController;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingPassenger;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class AdminDashboardOperationsRedesignTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_dashboard_renders_unified_compact_overview(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->get('/admin')->assertRedirect('/admin/dashboard');

        $html = $this->adminDashboardHtml($admin);
        $this->assertStringContainsString('Admin Dashboard', $html);
        $this->assertStringContainsString('Action-first overview', $html);
        $this->assertStringContainsString('Unified Overview Layout', $html);
        $this->assertStringContainsString('data-testid="ota-dash-overview"', $html);
        $this->assertStringContainsString('data-testid="ota-dash-notice"', $html);
        $this->assertStringContainsString('data-testid="ota-action-queue"', $html);
        $this->assertStringContainsString('Supplier connections and ticketing providers may still require final API onboarding', $html);
    }

    public function test_dashboard_renders_action_queue_cards(): void
    {
        $agency = Agency::factory()->create();
        $admin = $this->platformAdmin();
        $this->createBooking($agency, BookingStatus::Pending, 'unpaid', 100_000);

        $html = $this->adminDashboardHtml($admin);
        $this->assertStringContainsString('data-testid="ota-op-kpi-row"', $html);
        $this->assertStringContainsString('data-testid="ota-op-kpi-payment_review"', $html);
        $this->assertStringContainsString('data-testid="ota-op-kpi-supplier_pnr_pending"', $html);
        $this->assertStringContainsString('data-testid="ota-op-kpi-ticketing_pending"', $html);
        $this->assertStringContainsString('data-testid="ota-op-kpi-manual_review"', $html);
        $this->assertStringContainsString('data-testid="ota-op-kpi-cancellations_pending"', $html);
        $this->assertStringContainsString('data-testid="ota-op-kpi-refunds_pending"', $html);
        $this->assertStringContainsString('data-testid="ota-op-kpi-pending_deposits"', $html);
        $this->assertStringContainsString('Pending Deposits', $html);
        $this->assertStringContainsString('Payment Review', $html);
        $this->assertStringContainsString('Manual Review', $html);
        $this->assertStringContainsString('Cancellation Requests', $html);
    }

    public function test_dashboard_hides_legacy_operational_panels(): void
    {
        $admin = $this->platformAdmin();
        $html = $this->adminDashboardHtml($admin);

        $this->assertStringNotContainsString('data-testid="ota-pnr-health-panel"', $html);
        $this->assertStringNotContainsString('data-testid="ota-payment-collection-panel"', $html);
        $this->assertStringNotContainsString('data-testid="ota-staff-workload-panel"', $html);
        $this->assertStringNotContainsString('data-testid="ota-agent-performance-panel"', $html);
        $this->assertStringNotContainsString('data-testid="ota-today-operations"', $html);
        $this->assertStringNotContainsString('data-testid="ota-recent-bookings"', $html);
        $this->assertStringNotContainsString('data-testid="ota-recent-supplier-failures"', $html);
        $this->assertStringNotContainsString('data-testid="ota-revenue-snapshot"', $html);
        $this->assertStringNotContainsString('data-testid="ota-supplier-health"', $html);
        $this->assertStringNotContainsString('data-testid="ota-command-banner"', $html);
        $this->assertStringNotContainsString('Operations detail', $html);
        $this->assertStringNotContainsString('Revenue snapshot', $html);
    }

    public function test_dashboard_renders_system_status_and_recent_activity(): void
    {
        $admin = $this->platformAdmin();

        $html = $this->adminDashboardHtml($admin);
        $this->assertStringContainsString('data-testid="ota-dash-system-status"', $html);
        $this->assertStringContainsString('data-testid="ota-dash-recent-activity"', $html);
        $this->assertStringContainsString('Sabre Connection', $html);
        $this->assertStringContainsString('Wallet Service', $html);
        $this->assertStringContainsString('API Health', $html);
        $this->assertStringContainsString('Notifications Queue', $html);
    }

    public function test_dashboard_supplier_failure_card_does_not_expose_raw_payload_or_pii(): void
    {
        $agency = Agency::factory()->create();
        $admin = $this->platformAdmin();
        $booking = $this->createBooking($agency, BookingStatus::Pending, 'paid', 80_000);
        $booking->update([
            'pnr' => null,
            'supplier_booking_status' => 'failed',
            'booking_reference' => 'REF-FAIL-1',
        ]);

        BookingPassenger::factory()->for($booking)->create([
            'first_name' => 'Secretfirst',
            'last_name' => 'Secretlast',
            'passport_number' => 'ZZ9988776',
        ]);

        SupplierBookingAttempt::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'provider' => 'sabre',
            'action' => 'create_pnr',
            'status' => 'failed',
            'error_code' => 'sabre_test_failure',
            'error_message' => 'Segment no longer available for booking',
            'request_payload' => ['passenger' => ['name' => 'Secretfirst Secretlast', 'passport' => 'ZZ9988776']],
            'response_payload' => ['raw' => 'SUPER_SECRET_SABRE_BODY'],
            'safe_summary' => ['probable_issue' => 'Flight no longer available'],
            'attempted_at' => now(),
        ]);

        $html = $this->adminDashboardHtml($admin);
        $this->assertStringContainsString('data-testid="ota-op-kpi-supplier_failures"', $html);
        $this->assertStringContainsString('Supplier Failures', $html);
        $this->assertStringNotContainsString('SUPER_SECRET_SABRE_BODY', $html);
        $this->assertStringNotContainsString('ZZ9988776', $html);
        $this->assertStringNotContainsString('Secretfirst', $html);
        $this->assertStringNotContainsString('Secretlast', $html);
    }

    public function test_dashboard_quick_shortcuts_use_operational_queues(): void
    {
        $admin = $this->platformAdmin();

        $html = $this->adminDashboardHtml($admin);
        $this->assertStringContainsString('data-testid="ota-admin-quick-actions"', $html);
        $this->assertStringContainsString('data-testid="ota-quick-action-deposits"', $html);
        $this->assertStringContainsString('data-testid="ota-quick-action-payment_review"', $html);
        $this->assertStringContainsString('data-testid="ota-quick-action-ticketing"', $html);
        $this->assertStringContainsString('data-testid="ota-quick-action-agent_applications"', $html);
        $this->assertStringContainsString('data-testid="ota-quick-action-api_settings"', $html);
        $this->assertStringContainsString('data-testid="ota-quick-action-reports"', $html);
        $this->assertStringContainsString('Review Deposits', $html);
        $this->assertStringContainsString('Approve Agencies', $html);
    }

    public function test_dashboard_does_not_render_passport_or_passenger_personal_data(): void
    {
        $agency = Agency::factory()->create();
        $admin = $this->platformAdmin();

        $booking = Booking::factory()->for($agency)->create([
            'status' => BookingStatus::Pending,
            'payment_status' => 'unpaid',
            'route' => 'LHE-DXB',
            'booking_reference' => 'REF-PII-1',
        ]);
        $booking->fareBreakdown()->create([
            'base_fare' => 90_000,
            'taxes' => 5000,
            'fees' => 1000,
            'markup' => 4000,
            'discount' => 0,
            'total' => 100_000,
            'currency' => 'PKR',
        ]);

        BookingPassenger::factory()->for($booking)->create([
            'first_name' => 'Privatename',
            'last_name' => 'Privatesurname',
            'passport_number' => 'AB1234567',
            'national_id_number' => '99999-9999999-9',
            'date_of_birth' => '1990-01-01',
        ]);

        $html = $this->adminDashboardHtml($admin);
        $this->assertStringNotContainsString('AB1234567', $html);
        $this->assertStringNotContainsString('99999-9999999-9', $html);
        $this->assertStringNotContainsString('Privatename', $html);
        $this->assertStringNotContainsString('Privatesurname', $html);
    }

    public function test_dashboard_layout_keeps_collapsible_booking_submenu(): void
    {
        $admin = $this->platformAdmin();
        $html = $this->adminDashboardHtml($admin);
        $this->assertStringContainsString('id="sidebar-bookings-queues"', $html);
        $this->assertStringContainsString('data-bs-toggle="collapse"', $html);
        $this->assertStringContainsString('All bookings', $html);
        $this->assertStringContainsString('Booking queues', $html);
        $this->assertStringContainsString('Needs action', $html);
        $this->assertStringContainsString('Payment review', $html);
        $this->assertStringContainsString('Ticketing', $html);
    }

    public function test_dashboard_pending_deposits_links_to_submitted_queue(): void
    {
        $admin = $this->platformAdmin();

        $html = $this->adminDashboardHtml($admin);
        $this->assertStringContainsString(route('admin.agent-deposits.index', ['status' => 'submitted']), $html);
        $this->assertStringContainsString('data-testid="ota-command-banner-pending-deposits"', $html);
    }

    public function test_dashboard_system_status_reflects_sabre_connection(): void
    {
        $agency = Agency::factory()->create();
        $admin = $this->platformAdmin();

        SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Sabre,
            'name' => 'Sabre',
            'display_name' => 'Sabre',
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
        ]);

        $html = $this->adminDashboardHtml($admin);
        $this->assertStringContainsString('Connected', $html);
        $this->assertStringContainsString('Sabre Connection', $html);
    }

    protected function createBooking(
        Agency $agency,
        BookingStatus $status,
        string $paymentStatus,
        int $total,
    ): Booking {
        $booking = Booking::factory()->for($agency)->create([
            'status' => $status,
            'payment_status' => $paymentStatus,
            'supplier' => 'duffel',
            'route' => 'LHE-DXB',
            'airline' => 'Test Air',
            'booking_reference' => 'REF-'.strtoupper(bin2hex(random_bytes(3))),
        ]);

        $booking->fareBreakdown()->create([
            'base_fare' => max(0, $total - 10_000),
            'taxes' => 7000,
            'fees' => 1000,
            'markup' => 2000,
            'discount' => 0,
            'total' => $total,
            'currency' => 'PKR',
        ]);

        return $booking;
    }

    private function adminDashboardHtml(User $admin): string
    {
        $this->actingAs($admin);
        $request = Request::create('/admin', 'GET');
        $request->setUserResolver(fn () => $admin);

        return app(DashboardController::class)->index()->render();
    }
}
