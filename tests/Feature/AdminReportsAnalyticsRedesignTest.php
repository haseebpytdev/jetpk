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
use App\Http\Controllers\Admin\AdminSectionController;
use App\Services\Reports\BookingReportService;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminReportsAnalyticsRedesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_page_renders_toolbar_and_all_tabs(): void
    {
        [, $admin] = $this->makeAgencyAdmin();

        $this->assertLegacyReportsRedirect($admin);
    }

    public function test_reports_overview_tab_shows_financial_operational_and_agent_kpis(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $this->createBooking($agency, BookingStatus::Pending, 'unpaid', null, 100_000, 'duffel');
        $this->createBooking($agency, BookingStatus::Ticketed, 'paid', null, 200_000, 'duffel');

        $html = $this->reportsHtml($admin);
        $this->assertStringContainsString('data-testid="ota-financial-kpis"', $html);
        $this->assertStringContainsString('data-testid="ota-operational-kpis"', $html);
        $this->assertStringContainsString('data-testid="ota-agent-kpis"', $html);
        $this->assertStringContainsString('Gross sales', $html);
        $this->assertStringContainsString('Net revenue', $html);
        $this->assertStringContainsString('Markup revenue', $html);
        $this->assertStringContainsString('Outstanding balance', $html);
        $this->assertStringContainsString('Total bookings', $html);
        $this->assertStringContainsString('Pending bookings', $html);
        $this->assertStringContainsString('Approved commission', $html);
    }

    public function test_payments_tab_renders_rows_and_export_link(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $b = $this->createBooking($agency, BookingStatus::Pending, 'partial', null, 100_000, 'duffel');
        $b->forceFill(['amount_paid' => 40_000, 'balance_due' => 60_000])->save();

        $html = $this->reportsHtml($admin, ['tab' => 'payments']);
        $this->assertStringContainsString('data-testid="ota-pane-payments"', $html);
        $this->assertStringContainsString('Payments report', $html);
        $this->assertStringContainsString('Outstanding balance', $html);
        $this->assertStringContainsString('data-testid="ota-export-payments"', $html);
        $this->assertStringContainsString($b->booking_reference, $html);
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

        $html = $this->reportsHtml($admin, ['tab' => 'suppliers']);
        $this->assertStringContainsString('data-testid="ota-pane-suppliers"', $html);
        $this->assertStringContainsString('data-testid="ota-supplier-perf-duffel"', $html);
        $this->assertStringContainsString('Connected', $html);
        $this->assertStringContainsString('Duffel', $html);
        $this->assertStringContainsString('data-testid="ota-view-supplier-errors-duffel"', $html);
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

        $html = $this->supplierDiagnosticsHtml($admin, ['provider' => 'duffel', 'status' => 'errors']);

        $this->assertStringContainsString('data-testid="ota-supplier-diagnostics-page"', $html);
        $this->assertStringContainsString('offer_unavailable', $html);
        $this->assertStringContainsString('503', $html);
        $this->assertStringContainsString('/air/offer_requests', $html);
        $this->assertStringContainsString('The selected offer is no longer available', $html);
        $this->assertStringContainsString('/data/offers/0', $html);
        $this->assertStringNotContainsString('SECRET_TOKEN_123', $html);
        $this->assertStringNotContainsString('raw-secret-token', $html);
        $this->assertStringNotContainsString('PA1234567', $html);
        $this->assertStringNotContainsString('raw_payload', $html);
        $this->assertStringNotContainsString('authorization', $html);
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

        $html = $this->supplierDiagnosticsHtml($admin, [
            'provider' => 'duffel',
            'action' => 'search',
            'status' => 'failed',
            'date_from' => now()->subDays(2)->toDateString(),
        ]);

        $this->assertStringContainsString('Matching diagnostic row', $html);
        $this->assertStringNotContainsString('Wrong action row', $html);
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

        $html = $this->supplierDiagnosticsHtml($admin, ['provider' => 'duffel']);

        $this->assertStringContainsString('Visible agency diagnostic', $html);
        $this->assertStringContainsString('Other agency diagnostic', $html);
    }

    public function test_route_report_renders_top_route(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $this->createBooking($agency, BookingStatus::Ticketed, 'paid', null, 100_000, 'duffel', 'LHE-DXB');
        $this->createBooking($agency, BookingStatus::Ticketed, 'paid', null, 200_000, 'duffel', 'LHE-DXB');

        $html = $this->reportsHtml($admin, ['tab' => 'routes']);
        $this->assertStringContainsString('data-testid="ota-pane-routes"', $html);
        $this->assertStringContainsString('Route performance', $html);
        $this->assertStringContainsString('LHE-DXB', $html);
    }

    public function test_agent_report_shows_empty_state_when_no_agent_data(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $this->createBooking($agency, BookingStatus::Pending, 'unpaid', null, 100_000, 'duffel');

        $html = $this->reportsHtml($admin, ['tab' => 'agents']);
        $this->assertStringContainsString('data-testid="ota-pane-agents"', $html);
        $this->assertStringContainsString('No agent activity yet', $html);
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

        $html = $this->reportsHtml($admin, ['tab' => 'agents']);
        $this->assertStringContainsString($agent->code ?: ('AGENT-'.$agent->id), $html);
        $this->assertStringContainsString('Approved comm.', $html);
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

        $html = $this->reportsHtml($admin, ['tab' => 'refunds']);
        $this->assertStringContainsString('data-testid="ota-refund-kpis"', $html);
        $this->assertStringContainsString('Refund liability', $html);
        $this->assertStringContainsString($booking->booking_reference, $html);
    }

    public function test_documents_tab_renders_kpis(): void
    {
        [, $admin] = $this->makeAgencyAdmin();

        $html = $this->reportsHtml($admin, ['tab' => 'documents']);
        $this->assertStringContainsString('data-testid="ota-document-kpis"', $html);
        $this->assertStringContainsString('Invoices generated', $html);
        $this->assertStringContainsString('Itineraries generated', $html);
    }

    public function test_exports_tab_renders_export_cards_with_links(): void
    {
        [, $admin] = $this->makeAgencyAdmin();

        $html = $this->reportsHtml($admin, ['tab' => 'exports']);
        $this->assertStringContainsString('data-testid="ota-pane-exports"', $html);
        foreach (['sales', 'payments', 'bookings', 'agents', 'refunds', 'supplier_diagnostics', 'documents'] as $type) {
            $this->assertStringContainsString('data-testid="ota-export-card-'.$type.'"', $html);
            $this->assertStringContainsString(route('admin.reports.export', ['type' => $type]), $html);
        }
        $this->assertStringContainsString('data-testid="ota-export-card-pdf-note"', $html);
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

        $summary = $this->reportsPayload($admin, ['status' => 'ticketed', 'payment_status' => 'paid', 'supplier' => 'sabre'])['summary'];
        $this->assertSame(1, $summary['total_bookings']);
    }

    public function test_preset_today_normalizes_date_range(): void
    {
        [, $admin] = $this->makeAgencyAdmin();
        $today = now()->toDateString();

        $filters = $this->reportsPayload($admin, ['preset' => 'today'])['filters'];
        $this->assertSame($today, $filters['date_from']);
        $this->assertSame($today, $filters['date_to']);
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

        $html = $this->reportsHtml($admin);
        $this->assertStringNotContainsString('AA1112223', $html);
        $this->assertStringNotContainsString('22222-2222222-2', $html);
        $this->assertStringNotContainsString('Sensitive', $html);
    }

    public function test_reports_page_uses_polished_shell_and_responsive_markers(): void
    {
        [, $admin] = $this->makeAgencyAdmin();

        $this->assertLegacyReportsRedirect($admin);
    }

    public function test_reports_overview_section_headings_are_present(): void
    {
        [, $admin] = $this->makeAgencyAdmin();

        $this->assertLegacyReportsRedirect($admin);
    }

    public function test_reports_filter_card_renders_three_row_hierarchy(): void
    {
        [, $admin] = $this->makeAgencyAdmin();

        $this->assertLegacyReportsRedirect($admin);
    }

    public function test_reports_kpi_groups_use_premium_tile_class(): void
    {
        [, $admin] = $this->makeAgencyAdmin();

        $this->assertLegacyReportsRedirect($admin);
    }

    public function test_reports_charts_render_inside_padded_chart_cards(): void
    {
        [, $admin] = $this->makeAgencyAdmin();

        $this->assertLegacyReportsRedirect($admin);
    }

    public function test_reports_tables_have_admin_table_scroll_wrapper_and_min_width_table(): void
    {
        [, $admin] = $this->makeAgencyAdmin();

        $this->assertLegacyReportsRedirect($admin);
    }

    public function test_reports_empty_states_use_structured_premium_layout(): void
    {
        [, $admin] = $this->makeAgencyAdmin();

        $this->assertLegacyReportsRedirect($admin);
    }

    public function test_reports_export_buttons_render_minimum_height_classes(): void
    {
        [, $admin] = $this->makeAgencyAdmin();

        $this->assertLegacyReportsRedirect($admin);
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

        $html = $this->reportsHtml($admin, ['tab' => 'suppliers']);
        $this->assertStringNotContainsString('PHASE23B61_LEAK_CHECK_TOKEN', $html);
        $this->assertStringNotContainsString('ZZ987654321', $html);
        $this->assertStringNotContainsString('33333-3333333-3', $html);
        $this->assertStringNotContainsString('PolishedPaxFirst', $html);
        $this->assertStringNotContainsString('raw_payload', $html);
    }

    public function test_reports_keep_existing_summary_keys_for_back_compat(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $this->createBooking($agency, BookingStatus::Pending, 'unpaid', null, 120_000, 'duffel');
        $this->createBooking($agency, BookingStatus::Ticketed, 'paid', null, 180_000, 'sabre');

        $summary = $this->reportsPayload($admin)['summary'];
        $this->assertSame(2, $summary['total_bookings']);
        $this->assertSame(1, $summary['pending_bookings']);
        $this->assertSame(1, $summary['ticketed_bookings']);
        $this->assertSame(300000, (int) $summary['gross_sales']);
    }

    protected function assertLegacyReportsRedirect(User $admin, string $uri = '/admin/reports'): void
    {
        $response = $this->actingAs($admin)->get($uri);
        $response->assertRedirect();
        $target = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/admin/dashboard/reports', $target);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function reportsHtml(User $admin, array $query = []): string
    {
        $this->actingAs($admin);
        $request = Request::create('/admin/reports', 'GET', $query);
        $request->setUserResolver(fn () => $admin);

        return app(AdminSectionController::class)->reports($request)->render();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function supplierDiagnosticsHtml(User $admin, array $query = []): string
    {
        $this->actingAs($admin);
        $request = Request::create('/admin/reports/supplier-diagnostics', 'GET', $query);
        $request->setUserResolver(fn () => $admin);

        return app(AdminSectionController::class)->supplierDiagnostics($request)->render();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function reportsPayload(User $admin, array $query = []): array
    {
        $this->actingAs($admin);
        $request = Request::create('/admin/reports', 'GET', $query);
        $request->setUserResolver(fn () => $admin);

        return app(BookingReportService::class)->build($admin, $request);
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
