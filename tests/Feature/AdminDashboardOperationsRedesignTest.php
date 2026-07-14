<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingPassenger;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class AdminDashboardOperationsRedesignTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_dashboard_renders_unified_compact_overview(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Admin Dashboard', false)
            ->assertSee('Action-first overview', false)
            ->assertSee('Unified Overview Layout', false)
            ->assertSee('data-testid="ota-dash-overview"', false)
            ->assertSee('data-testid="ota-dash-notice"', false)
            ->assertSee('data-testid="ota-action-queue"', false)
            ->assertSee('Supplier connections and ticketing providers may still require final API onboarding', false);
    }

    public function test_dashboard_renders_action_queue_cards(): void
    {
        $agency = Agency::factory()->create();
        $admin = $this->platformAdmin();
        $this->createBooking($agency, BookingStatus::Pending, 'unpaid', 100_000);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('data-testid="ota-op-kpi-row"', false)
            ->assertSee('data-testid="ota-op-kpi-payment_review"', false)
            ->assertSee('data-testid="ota-op-kpi-supplier_pnr_pending"', false)
            ->assertSee('data-testid="ota-op-kpi-ticketing_pending"', false)
            ->assertSee('data-testid="ota-op-kpi-manual_review"', false)
            ->assertSee('data-testid="ota-op-kpi-cancellations_pending"', false)
            ->assertSee('data-testid="ota-op-kpi-refunds_pending"', false)
            ->assertSee('data-testid="ota-op-kpi-pending_deposits"', false)
            ->assertSee('Pending Deposits', false)
            ->assertSee('Payment Review', false)
            ->assertSee('Manual Review', false)
            ->assertSee('Cancellation Requests', false);
    }

    public function test_dashboard_hides_legacy_operational_panels(): void
    {
        $admin = $this->platformAdmin();
        $response = $this->actingAs($admin)->get('/admin')->assertOk();

        $response->assertDontSee('data-testid="ota-pnr-health-panel"', false);
        $response->assertDontSee('data-testid="ota-payment-collection-panel"', false);
        $response->assertDontSee('data-testid="ota-staff-workload-panel"', false);
        $response->assertDontSee('data-testid="ota-agent-performance-panel"', false);
        $response->assertDontSee('data-testid="ota-today-operations"', false);
        $response->assertDontSee('data-testid="ota-recent-bookings"', false);
        $response->assertDontSee('data-testid="ota-recent-supplier-failures"', false);
        $response->assertDontSee('data-testid="ota-revenue-snapshot"', false);
        $response->assertDontSee('data-testid="ota-supplier-health"', false);
        $response->assertDontSee('data-testid="ota-command-banner"', false);
        $response->assertDontSee('Operations detail', false);
        $response->assertDontSee('Revenue snapshot', false);
    }

    public function test_dashboard_renders_system_status_and_recent_activity(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('data-testid="ota-dash-system-status"', false)
            ->assertSee('data-testid="ota-dash-recent-activity"', false)
            ->assertSee('Sabre Connection', false)
            ->assertSee('Wallet Service', false)
            ->assertSee('API Health', false)
            ->assertSee('Notifications Queue', false);
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

        $response = $this->actingAs($admin)->get('/admin')->assertOk();
        $response->assertSee('data-testid="ota-op-kpi-supplier_failures"', false);
        $response->assertSee('Supplier Failures', false);
        $response->assertDontSee('SUPER_SECRET_SABRE_BODY');
        $response->assertDontSee('ZZ9988776');
        $response->assertDontSee('Secretfirst');
        $response->assertDontSee('Secretlast');
    }

    public function test_dashboard_quick_shortcuts_use_operational_queues(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('data-testid="ota-admin-quick-actions"', false)
            ->assertSee('data-testid="ota-quick-action-deposits"', false)
            ->assertSee('data-testid="ota-quick-action-payment_review"', false)
            ->assertSee('data-testid="ota-quick-action-ticketing"', false)
            ->assertSee('data-testid="ota-quick-action-agent_applications"', false)
            ->assertSee('data-testid="ota-quick-action-api_settings"', false)
            ->assertSee('data-testid="ota-quick-action-reports"', false)
            ->assertSee('Review Deposits', false)
            ->assertSee('Approve Agencies', false);
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

        $response = $this->actingAs($admin)->get('/admin')->assertOk();
        $response->assertDontSee('AB1234567');
        $response->assertDontSee('99999-9999999-9');
        $response->assertDontSee('Privatename');
        $response->assertDontSee('Privatesurname');
    }

    public function test_dashboard_layout_keeps_collapsible_booking_submenu(): void
    {
        $admin = $this->platformAdmin();
        $response = $this->actingAs($admin)->get('/admin')->assertOk();
        $response->assertSee('id="sidebar-bookings-queues"', false);
        $response->assertSee('data-bs-toggle="collapse"', false);
        $response->assertSee('All bookings', false);
        $response->assertSee('Booking queues', false);
        $response->assertSee('Needs action', false);
        $response->assertSee('Payment review', false);
        $response->assertSee('Ticketing', false);
    }

    public function test_dashboard_pending_deposits_links_to_submitted_queue(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee(route('admin.agent-deposits.index', ['status' => 'submitted']), false)
            ->assertSee('data-testid="ota-command-banner-pending-deposits"', false);
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

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Connected', false)
            ->assertSee('Sabre Connection', false);
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
}
