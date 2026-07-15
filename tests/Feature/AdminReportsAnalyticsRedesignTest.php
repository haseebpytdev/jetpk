<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentCommissionEntry;
use App\Models\Booking;
use App\Models\BookingPassenger;
use App\Models\BookingRefund;
use App\Models\SupplierConnection;
use App\Models\SupplierDiagnosticLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportsAnalyticsRedesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_page_renders_toolbar_and_all_tabs(): void
    {
        [, $admin] = $this->makeAgencyAdmin();

        $response = $this->actingAs($admin)->get('/admin/reports')->assertOk();

        $response->assertSee('data-testid="ota-reports-toolbar"', false);
        $response->assertSee('data-testid="ota-reports-tabs"', false);
        foreach (['overview', 'sales', 'payments', 'bookings', 'suppliers', 'agents', 'routes', 'refunds', 'documents', 'exports'] as $tab) {
            $response->assertSee('data-testid="ota-tab-'.$tab.'"', false);
        }
        $response->assertSee('Platform Reports', false);
        $response->assertSee('Today', false);
        $response->assertSee('30 days', false);
    }

    public function test_reports_overview_tab_shows_financial_operational_and_agent_kpis(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $this->createBooking($agency, BookingStatus::Pending, 'unpaid', null, 100_000, 'duffel');
        $this->createBooking($agency, BookingStatus::Ticketed, 'paid', null, 200_000, 'duffel');

        $response = $this->actingAs($admin)->get('/admin/reports')->assertOk();
        $response->assertSee('data-testid="ota-financial-kpis"', false);
        $response->assertSee('data-testid="ota-operational-kpis"', false);
        $response->assertSee('data-testid="ota-agent-kpis"', false);
        $response->assertSee('Gross sales', false);
        $response->assertSee('Net revenue', false);
        $response->assertSee('Markup revenue', false);
        $response->assertSee('Outstanding balance', false);
        $response->assertSee('Total bookings', false);
        $response->assertSee('Pending bookings', false);
        $response->assertSee('Approved commission', false);
    }

    public function test_payments_tab_renders_rows_and_export_link(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $b = $this->createBooking($agency, BookingStatus::Pending, 'partial', null, 100_000, 'duffel');
        $b->forceFill(['amount_paid' => 40_000, 'balance_due' => 60_000])->save();

        $response = $this->actingAs($admin)->get('/admin/reports?tab=payments')->assertOk();
        $response->assertSee('data-testid="ota-pane-payments"', false);
        $response->assertSee('Payments report', false);
        $response->assertSee('Outstanding balance', false);
        $response->assertSee('data-testid="ota-export-payments"', false);
        $response->assertSee($b->booking_reference);
    }

    public function test_supplier_report_renders_diagnostics_for_duffel(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $connection = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Duffel,
            'name' => 'Duffel',
            'display_name' => 'Duffel',
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
        ]);
        SupplierDiagnosticLog::query()->create([
            'agency_id' => $agency->id,
            'supplier_connection_id' => $connection->id,
            'provider' => 'duffel',
            'action' => 'search',
            'status' => 'ok',
            'duration_ms' => 240,
            'safe_message' => 'search ok',
            'meta' => null,
        ]);
        SupplierDiagnosticLog::query()->create([
            'agency_id' => $agency->id,
            'supplier_connection_id' => $connection->id,
            'provider' => 'duffel',
            'action' => 'search',
            'status' => 'failed',
            'duration_ms' => 5_000,
            'safe_message' => 'offer_unavailable from supplier',
            'meta' => null,
        ]);
        SupplierDiagnosticLog::query()->create([
            'agency_id' => $agency->id,
            'supplier_connection_id' => $connection->id,
            'provider' => 'duffel',
            'action' => 'readiness_check',
            'status' => 'failed',
            'duration_ms' => 1_200,
            'safe_message' => 'validation failed',
            'meta' => null,
        ]);

        $response = $this->actingAs($admin)->get('/admin/reports?tab=suppliers')->assertOk();
        $response->assertSee('data-testid="ota-pane-suppliers"', false);
        $response->assertSee('data-testid="ota-supplier-perf-duffel"', false);
        $response->assertSee('Connected', false);
        $response->assertSee('Duffel', false);
        $response->assertSee('data-testid="ota-view-supplier-errors-duffel"', false);
    }

    public function test_supplier_diagnostics_drilldown_renders_safe_error_fields(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $connection = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Duffel,
            'name' => 'Duffel',
            'display_name' => 'Duffel',
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
        ]);

        SupplierDiagnosticLog::query()->create([
            'agency_id' => $agency->id,
            'supplier_connection_id' => $connection->id,
            'provider' => 'duffel',
            'action' => 'search',
            'status' => 'failed',
            'duration_ms' => 5_000,
            'safe_message' => 'Offer unavailable. Authorization: Bearer duffel_test_SECRET_TOKEN_123',
            'meta' => [
                'reason_code' => 'offer_unavailable',
                'error_code' => 'offer_unavailable',
                'http_status' => 503,
                'endpoint' => '/air/offer_requests',
                'authorization' => 'Bearer duffel_test_SECRET_TOKEN_123',
                'raw_payload' => ['passport_number' => 'PA1234567', 'token' => 'raw-secret-token'],
                'duffel_errors' => [[
                    'code' => 'offer_unavailable',
                    'title' => 'Offer unavailable',
                    'detail' => 'The selected offer is no longer available',
                    'source' => ['pointer' => '/data/offers/0'],
                ]],
            ],
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/reports/supplier-diagnostics?provider=duffel&status=errors')
            ->assertOk();

        $response->assertSee('data-testid="ota-supplier-diagnostics-page"', false);
        $response->assertSee('offer_unavailable');
        $response->assertSee('503');
        $response->assertSee('/air/offer_requests');
        $response->assertSee('The selected offer is no longer available');
        $response->assertSee('/data/offers/0');
        $response->assertDontSee('SECRET_TOKEN_123');
        $response->assertDontSee('raw-secret-token');
        $response->assertDontSee('PA1234567');
        $response->assertDontSee('raw_payload');
        $response->assertDontSee('authorization');
    }

    public function test_supplier_diagnostics_filters_by_action_status_and_date(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $connection = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Duffel,
            'name' => 'Duffel',
            'display_name' => 'Duffel',
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
        ]);

        $matching = SupplierDiagnosticLog::query()->create([
            'agency_id' => $agency->id,
            'supplier_connection_id' => $connection->id,
            'provider' => 'duffel',
            'action' => 'search',
            'status' => 'failed',
            'duration_ms' => 500,
            'safe_message' => 'Matching diagnostic row',
            'meta' => ['http_status' => 502],
        ]);
        $matching->forceFill(['created_at' => now()->subDay()])->save();

        SupplierDiagnosticLog::query()->create([
            'agency_id' => $agency->id,
            'supplier_connection_id' => $connection->id,
            'provider' => 'duffel',
            'action' => 'readiness_check',
            'status' => 'failed',
            'duration_ms' => 500,
            'safe_message' => 'Wrong action row',
            'meta' => ['http_status' => 422],
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/reports/supplier-diagnostics?provider=duffel&action=search&status=failed&date_from='.now()->subDays(2)->toDateString())
            ->assertOk();

        $response->assertSee('Matching diagnostic row');
        $response->assertDontSee('Wrong action row');
    }

    public function test_supplier_diagnostics_are_platform_wide_for_platform_admin(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $otherAgency = Agency::factory()->create();
        $connection = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Duffel,
            'name' => 'Duffel',
            'display_name' => 'Duffel',
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
        ]);
        $otherConnection = SupplierConnection::factory()->create([
            'agency_id' => $otherAgency->id,
            'provider' => SupplierProvider::Duffel,
            'name' => 'Duffel',
            'display_name' => 'Duffel',
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
        ]);

        SupplierDiagnosticLog::query()->create([
            'agency_id' => $agency->id,
            'supplier_connection_id' => $connection->id,
            'provider' => 'duffel',
            'action' => 'search',
            'status' => 'failed',
            'duration_ms' => 500,
            'safe_message' => 'Visible agency diagnostic',
            'meta' => ['http_status' => 503],
        ]);
        SupplierDiagnosticLog::query()->create([
            'agency_id' => $otherAgency->id,
            'supplier_connection_id' => $otherConnection->id,
            'provider' => 'duffel',
            'action' => 'search',
            'status' => 'failed',
            'duration_ms' => 500,
            'safe_message' => 'Other agency diagnostic',
            'meta' => ['http_status' => 503],
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/reports/supplier-diagnostics?provider=duffel')
            ->assertOk();

        $response->assertSee('Visible agency diagnostic');
        $response->assertSee('Other agency diagnostic');
    }

    public function test_route_report_renders_top_route(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $this->createBooking($agency, BookingStatus::Ticketed, 'paid', null, 100_000, 'duffel', 'LHE-DXB');
        $this->createBooking($agency, BookingStatus::Ticketed, 'paid', null, 200_000, 'duffel', 'LHE-DXB');

        $response = $this->actingAs($admin)->get('/admin/reports?tab=routes')->assertOk();
        $response->assertSee('data-testid="ota-pane-routes"', false);
        $response->assertSee('Route performance', false);
        $response->assertSee('LHE-DXB');
    }

    public function test_agent_report_shows_empty_state_when_no_agent_data(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $this->createBooking($agency, BookingStatus::Pending, 'unpaid', null, 100_000, 'duffel');

        $response = $this->actingAs($admin)->get('/admin/reports?tab=agents')->assertOk();
        $response->assertSee('data-testid="ota-pane-agents"', false);
        $response->assertSee('No agent activity yet', false);
    }

    public function test_agent_report_renders_agent_with_commissions(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $agent = Agent::factory()->for($agency)->create(['commission_percent' => 10]);
        $booking = $this->createBooking($agency, BookingStatus::Ticketed, 'paid', $agent, 100_000, 'duffel');
        AgentCommissionEntry::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'booking_id' => $booking->id,
            'type' => 'earned',
            'status' => 'approved',
            'calculation_basis' => 'percentage',
            'rate' => 10,
            'base_amount' => 100_000,
            'commission_amount' => 10_000,
            'currency' => 'PKR',
        ]);

        $response = $this->actingAs($admin)->get('/admin/reports?tab=agents')->assertOk();
        $response->assertSee($agent->code ?: ('AGENT-'.$agent->id));
        $response->assertSee('Approved comm.', false);
    }

    public function test_refunds_tab_renders_kpis_and_rows(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $booking = $this->createBooking($agency, BookingStatus::Cancelled, 'paid', null, 100_000, 'duffel');
        BookingRefund::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'amount' => 80_000,
            'currency' => 'PKR',
            'method' => 'bank_transfer',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->get('/admin/reports?tab=refunds')->assertOk();
        $response->assertSee('data-testid="ota-refund-kpis"', false);
        $response->assertSee('Refund liability', false);
        $response->assertSee($booking->booking_reference);
    }

    public function test_documents_tab_renders_kpis(): void
    {
        [, $admin] = $this->makeAgencyAdmin();

        $response = $this->actingAs($admin)->get('/admin/reports?tab=documents')->assertOk();
        $response->assertSee('data-testid="ota-document-kpis"', false);
        $response->assertSee('Invoices generated', false);
        $response->assertSee('Itineraries generated', false);
    }

    public function test_exports_tab_renders_export_cards_with_links(): void
    {
        [, $admin] = $this->makeAgencyAdmin();

        $response = $this->actingAs($admin)->get('/admin/reports?tab=exports')->assertOk();
        $response->assertSee('data-testid="ota-pane-exports"', false);
        foreach (['sales', 'payments', 'bookings', 'agents', 'refunds', 'supplier_diagnostics', 'documents'] as $type) {
            $response->assertSee('data-testid="ota-export-card-'.$type.'"', false);
            $response->assertSee(route('admin.reports.export', ['type' => $type]), false);
        }
        $response->assertSee('data-testid="ota-export-card-pdf-note"', false);
    }

    public function test_csv_export_streams_for_each_supported_type(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $this->createBooking($agency, BookingStatus::Pending, 'unpaid', null, 100_000, 'duffel');

        foreach (['sales', 'payments', 'bookings', 'agents', 'refunds', 'supplier_diagnostics', 'documents'] as $type) {
            $response = $this->actingAs($admin)->get('/admin/reports/export/'.$type);
            $response->assertOk();
            $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
            $this->assertStringContainsString('attachment; filename=', (string) $response->headers->get('Content-Disposition'));
            $this->assertStringContainsString('reports-'.$type, (string) $response->headers->get('Content-Disposition'));
        }
    }

    public function test_csv_export_rejects_unknown_type(): void
    {
        [, $admin] = $this->makeAgencyAdmin();
        $this->actingAs($admin)->get('/admin/reports/export/unknown')->assertNotFound();
    }

    public function test_csv_export_does_not_expose_passenger_passport(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $booking = $this->createBooking($agency, BookingStatus::Pending, 'unpaid', null, 100_000, 'duffel');
        BookingPassenger::factory()->for($booking)->create([
            'first_name' => 'PaxFirstName',
            'last_name' => 'PaxLastName',
            'passport_number' => 'XX9876543',
            'national_id_number' => '11111-1111111-1',
        ]);

        $csv = $this->actingAs($admin)->get('/admin/reports/export/bookings')->assertOk()->streamedContent();
        $this->assertStringNotContainsString('XX9876543', $csv);
        $this->assertStringNotContainsString('11111-1111111-1', $csv);
        $this->assertStringNotContainsString('PaxFirstName', $csv);
    }

    public function test_supplier_csv_export_does_not_expose_credentials_or_tokens(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $connection = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Duffel,
            'name' => 'Duffel',
            'display_name' => 'Duffel',
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => ['access_token' => 'duffel_test_TOKENSECRETVALUE12345'],
        ]);
        SupplierDiagnosticLog::query()->create([
            'agency_id' => $agency->id,
            'supplier_connection_id' => $connection->id,
            'provider' => 'duffel',
            'action' => 'search',
            'status' => 'ok',
            'duration_ms' => 100,
            'safe_message' => 'ok',
            'meta' => null,
        ]);

        $csv = $this->actingAs($admin)->get('/admin/reports/export/supplier_diagnostics')->assertOk()->streamedContent();
        $this->assertStringNotContainsString('TOKENSECRETVALUE12345', $csv);
        $this->assertStringNotContainsString('duffel_test_TOKENSECRETVALUE12345', $csv);
    }

    public function test_filters_apply_safely_with_status_payment_supplier(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $this->createBooking($agency, BookingStatus::Pending, 'unpaid', null, 100_000, 'duffel');
        $this->createBooking($agency, BookingStatus::Ticketed, 'paid', null, 200_000, 'sabre');

        $this->actingAs($admin)
            ->get('/admin/reports?status=ticketed&payment_status=paid&supplier=sabre')
            ->assertOk()
            ->assertViewHas('summary', fn (array $summary): bool => $summary['total_bookings'] === 1);
    }

    public function test_preset_today_normalizes_date_range(): void
    {
        [, $admin] = $this->makeAgencyAdmin();
        $today = now()->toDateString();

        $this->actingAs($admin)
            ->get('/admin/reports?preset=today')
            ->assertOk()
            ->assertViewHas('filters', fn (array $filters): bool => $filters['date_from'] === $today && $filters['date_to'] === $today);
    }

    public function test_no_passport_is_rendered_on_reports_page(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $booking = $this->createBooking($agency, BookingStatus::Pending, 'unpaid', null, 100_000, 'duffel');
        BookingPassenger::factory()->for($booking)->create([
            'first_name' => 'Sensitive',
            'last_name' => 'PaxLast',
            'passport_number' => 'AA1112223',
            'national_id_number' => '22222-2222222-2',
        ]);

        $response = $this->actingAs($admin)->get('/admin/reports')->assertOk();
        $response->assertDontSee('AA1112223');
        $response->assertDontSee('22222-2222222-2');
        $response->assertDontSee('Sensitive');
    }

    public function test_reports_page_uses_polished_shell_and_responsive_markers(): void
    {
        [, $admin] = $this->makeAgencyAdmin();

        $response = $this->actingAs($admin)->get('/admin/reports')->assertOk();

        $response->assertSee('data-testid="ota-reports-shell"', false);
        $response->assertSee('class="reports-toolbar', false);
        $response->assertSee('reports-presets', false);
        $response->assertSee('reports-toolbar-actions', false);
        $response->assertSee('reports-filter-grid', false);
        $response->assertSee('ota-rep-tabs', false);
        $response->assertSee('ota-kpi-responsive-row', false);
        $response->assertSee('ota-kpi-responsive-row--six', false);
        $response->assertSee('ota-kpi-responsive-row--four', false);
        $response->assertSee('admin-table-scroll', false);
        $response->assertSee('ota-rep-chart-card', false);
        $response->assertSee('ota-report-chart-row', false);
        $response->assertSee('ota-report-section-heading', false);
    }

    public function test_reports_overview_section_headings_are_present(): void
    {
        [, $admin] = $this->makeAgencyAdmin();

        $response = $this->actingAs($admin)->get('/admin/reports')->assertOk();

        $response->assertSee('Financial performance', false);
        $response->assertSee('Operational workload', false);
        $response->assertSee('Agent performance', false);
        $response->assertSee('Trend and payment mix', false);
        $response->assertSee('Routes, agents, and payments', false);
    }

    public function test_reports_filter_card_renders_three_row_hierarchy(): void
    {
        [, $admin] = $this->makeAgencyAdmin();

        $response = $this->actingAs($admin)->get('/admin/reports')->assertOk();

        $response->assertSee('data-testid="ota-reports-presets-row"', false);
        $response->assertSee('data-testid="ota-reports-filters-row"', false);
        $response->assertSee('data-testid="ota-reports-actions-row"', false);
        $response->assertSee('Quick range', false);
        $response->assertSee('Filters', false);
        $response->assertSee('Apply filters', false);
        $response->assertSee('Export sales CSV', false);
        $response->assertSee('Export payments CSV', false);
        $response->assertSee('Export PDF (coming soon)', false);
    }

    public function test_reports_kpi_groups_use_premium_tile_class(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $this->createBooking($agency, BookingStatus::Pending, 'unpaid', null, 100_000, 'duffel');

        $response = $this->actingAs($admin)->get('/admin/reports')->assertOk();

        $response->assertSee('ota-kpi-tile', false);
        $response->assertSee('ota-kpi-tile-label', false);
        $response->assertSee('ota-kpi-tile-value', false);
        $response->assertSee('ota-kpi-tile-helper', false);
    }

    public function test_reports_charts_render_inside_padded_chart_cards(): void
    {
        [, $admin] = $this->makeAgencyAdmin();

        $response = $this->actingAs($admin)->get('/admin/reports')->assertOk();

        $response->assertSee('data-testid="ota-sales-trend-chart"', false);
        $response->assertSee('data-testid="ota-payment-status-chart"', false);
        $response->assertSee('Sales trend', false);
        $response->assertSee('Payment status', false);
        $response->assertSee('ota-rep-chart-subtitle', false);
        $response->assertSee('ota-chart-svg', false);
    }

    public function test_reports_tables_have_admin_table_scroll_wrapper_and_min_width_table(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $this->createBooking($agency, BookingStatus::Pending, 'unpaid', null, 100_000, 'duffel');

        $response = $this->actingAs($admin)->get('/admin/reports?tab=payments')->assertOk();

        $response->assertSee('admin-table-scroll', false);
        $response->assertSee('ota-rep-table', false);
    }

    public function test_reports_empty_states_use_structured_premium_layout(): void
    {
        [, $admin] = $this->makeAgencyAdmin();

        $response = $this->actingAs($admin)->get('/admin/reports?tab=agents')->assertOk();
        $response->assertSee('data-testid="ota-empty-agents"', false);
        $response->assertSee('ota-empty-state', false);
        $response->assertSee('ota-empty-state-icon', false);
        $response->assertSee('ota-empty-state-title', false);
        $response->assertSee('ota-empty-state-help', false);
        $response->assertSee('No agent activity yet', false);

        $response2 = $this->actingAs($admin)->get('/admin/reports?tab=routes')->assertOk();
        $response2->assertSee('data-testid="ota-empty-routes"', false);
        $response2->assertSee('No route data yet', false);

        $response3 = $this->actingAs($admin)->get('/admin/reports?tab=refunds')->assertOk();
        $response3->assertSee('data-testid="ota-empty-refunds"', false);
        $response3->assertSee('No refund activity', false);

        $response4 = $this->actingAs($admin)->get('/admin/reports?tab=documents')->assertOk();
        $response4->assertSee('data-testid="ota-empty-documents"', false);
        $response4->assertSee('No documents generated yet', false);
    }

    public function test_reports_export_buttons_render_minimum_height_classes(): void
    {
        [, $admin] = $this->makeAgencyAdmin();

        $response = $this->actingAs($admin)->get('/admin/reports')->assertOk();

        $response->assertSee('data-testid="ota-export-sales"', false);
        $response->assertSee('data-testid="ota-export-payments"', false);
        $response->assertSee('reports-toolbar-actions', false);
    }

    public function test_reports_page_does_not_expose_supplier_credentials_or_passport(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $connection = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Duffel,
            'name' => 'Duffel',
            'display_name' => 'Duffel',
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => ['access_token' => 'duffel_test_PHASE23B61_LEAK_CHECK_TOKEN'],
        ]);
        SupplierDiagnosticLog::query()->create([
            'agency_id' => $agency->id,
            'supplier_connection_id' => $connection->id,
            'provider' => 'duffel',
            'action' => 'search',
            'status' => 'failed',
            'duration_ms' => 1_200,
            'safe_message' => 'failed search',
            'meta' => [
                'http_status' => 503,
                'authorization' => 'Bearer duffel_test_PHASE23B61_LEAK_CHECK_TOKEN',
                'raw_payload' => ['passport_number' => 'ZZ987654321'],
            ],
        ]);
        $booking = $this->createBooking($agency, BookingStatus::Pending, 'unpaid', null, 100_000, 'duffel');
        BookingPassenger::factory()->for($booking)->create([
            'first_name' => 'PolishedPaxFirst',
            'last_name' => 'PaxLast',
            'passport_number' => 'ZZ987654321',
            'national_id_number' => '33333-3333333-3',
        ]);

        $response = $this->actingAs($admin)->get('/admin/reports?tab=suppliers')->assertOk();
        $response->assertDontSee('PHASE23B61_LEAK_CHECK_TOKEN');
        $response->assertDontSee('ZZ987654321');
        $response->assertDontSee('33333-3333333-3');
        $response->assertDontSee('PolishedPaxFirst');
        $response->assertDontSee('raw_payload');
    }

    public function test_reports_keep_existing_summary_keys_for_back_compat(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $this->createBooking($agency, BookingStatus::Pending, 'unpaid', null, 120_000, 'duffel');
        $this->createBooking($agency, BookingStatus::Ticketed, 'paid', null, 180_000, 'sabre');

        $this->actingAs($admin)
            ->get('/admin/reports')
            ->assertOk()
            ->assertViewHas('summary', function (array $summary): bool {
                return $summary['total_bookings'] === 2
                    && $summary['pending_bookings'] === 1
                    && $summary['ticketed_bookings'] === 1
                    && (int) $summary['gross_sales'] === 300000;
            });
    }

    /**
     * @return array{Agency, User}
     */
    protected function makeAgencyAdmin(): array
    {
        $agency = Agency::factory()->create();
        $admin = User::factory()->create([
            'current_agency_id' => $agency->id,
            'account_type' => AccountType::PlatformAdmin,
        ]);
        $agency->users()->attach($admin->id, ['role' => AccountType::PlatformAdmin->value]);

        return [$agency, $admin];
    }

    protected function createBooking(
        Agency $agency,
        BookingStatus $status,
        string $paymentStatus,
        ?Agent $agent,
        int $total,
        string $supplier,
        string $route = 'LHE-DXB',
    ): Booking {
        $booking = Booking::factory()->for($agency)->create([
            'status' => $status,
            'payment_status' => $paymentStatus,
            'agent_id' => $agent?->id,
            'supplier' => $supplier,
            'route' => $route,
            'airline' => 'Test Air',
            'booking_reference' => 'REF-'.strtoupper(bin2hex(random_bytes(3))),
        ]);

        $booking->fareBreakdown()->create([
            'base_fare' => max(0, $total - 10000),
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
