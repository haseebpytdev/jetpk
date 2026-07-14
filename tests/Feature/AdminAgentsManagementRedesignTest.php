<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentApplication;
use App\Models\AgentCommissionEntry;
use App\Models\Booking;
use App\Models\BookingPassenger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAgentsManagementRedesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_agents_page_uses_booking_management_layout_shell(): void
    {
        [, $admin] = $this->makeAgencyAdmin();

        $response = $this->actingAs($admin)->get('/admin/agents')->assertOk();

        $response->assertSee('data-agents-page', false);
        $response->assertSee('data-agents-kpis', false);
        $response->assertSee('data-agents-tabs', false);
        $response->assertSee('data-agents-filter-bar', false);
        $response->assertSee('data-agents-list', false);
        $response->assertSee('data-agents-preview', false);
    }

    public function test_agents_page_header_renders_simplified_subtitle_and_actions(): void
    {
        [, $admin] = $this->makeAgencyAdmin();

        $response = $this->actingAs($admin)->get('/admin/agents')->assertOk();

        $response->assertSee('data-testid="ota-agents-page-header"', false);
        $response->assertSee('data-testid="ota-agents-page-actions"', false);
        $response->assertSee('Agents management', false);
        $response->assertSee('Manage agent accounts, commission rates, sales performance, and booking activity.', false);
        $response->assertSee('data-testid="ota-agents-action-review-applications"', false);
        $response->assertSee('data-testid="ota-agents-action-add-agent"', false);
        $response->assertSee('Review applications', false);
        $response->assertSee('Add agent', false);
        $response->assertSee(route('admin.agent-applications.index'), false);
        $response->assertSee(route('admin.users.create'), false);
    }

    public function test_agents_page_renders_six_recommended_kpi_cards(): void
    {
        [, $admin] = $this->makeAgencyAdmin();

        $response = $this->actingAs($admin)->get('/admin/agents')->assertOk();

        foreach ([
            'ota-agents-kpi-total',
            'ota-agents-kpi-active',
            'ota-agents-kpi-pending-applications',
            'ota-agents-kpi-monthly',
            'ota-agents-kpi-pending-commission',
            'ota-agents-kpi-unpaid-balance',
        ] as $testId) {
            $response->assertSee('data-testid="'.$testId.'"', false);
        }

        $response->assertSee('Total agents', false);
        $response->assertSee('Active agents', false);
        $response->assertSee('Pending applications', false);
        $response->assertSee('Monthly agent sales', false);
        $response->assertSee('Pending commission', false);
        $response->assertSee('Unpaid agent balance', false);

        $response->assertDontSee('data-testid="ota-agents-kpi-approved-this-month"', false);
        $response->assertDontSee('data-testid="ota-agents-kpi-rejected-this-month"', false);
        $response->assertDontSee('Approved this month', false);
        $response->assertDontSee('Rejected this month', false);
    }

    public function test_agents_page_renders_queue_tabs(): void
    {
        [, $admin] = $this->makeAgencyAdmin();

        $response = $this->actingAs($admin)->get('/admin/agents')->assertOk();

        foreach (['all', 'active', 'inactive', 'with_balance', 'recent_onboards'] as $queue) {
            $response->assertSee('data-testid="ota-agents-queue-'.$queue.'"', false);
        }
        $response->assertSee('All agents', false);
        $response->assertSee('With balance', false);
        $response->assertSee('Recently onboarded', false);
    }

    public function test_agents_filter_card_renders_requested_fields_and_export_action(): void
    {
        [, $admin] = $this->makeAgencyAdmin();

        $response = $this->actingAs($admin)->get('/admin/agents')->assertOk();

        foreach ([
            'name="search"',
            'name="status"',
            'name="city"',
            'name="commission_filter"',
            'name="sales_from"',
            'name="sales_to"',
            'name="created_from"',
            'name="created_to"',
        ] as $field) {
            $response->assertSee($field, false);
        }

        // The two-row filter redesign collapses "Commission type/rate" to a
        // shorter "Commission" label that's still uniquely identifiable.
        $response->assertSee('Commission</label>', false);
        $response->assertSee('Apply filters', false);
        $response->assertSee('Reset', false);
        $response->assertSee('data-testid="ota-agents-export-csv"', false);
        $response->assertSee('Export agents CSV', false);
    }

    public function test_agents_pending_applications_kpi_links_to_application_queue(): void
    {
        [, $admin] = $this->makeAgencyAdmin();
        $this->createAgentApplication('pending');
        $approved = $this->createAgentApplication('approved');
        $approved->forceFill(['reviewed_at' => now()->subDays(3)])->save();
        $rejected = $this->createAgentApplication('rejected');
        $rejected->forceFill(['reviewed_at' => now()->subDays(2)])->save();

        $response = $this->actingAs($admin)->get('/admin/agents')->assertOk();

        $response->assertSee('data-testid="ota-agents-kpi-pending-applications"', false);
        $response->assertSee('Pending applications', false);
        $response->assertSee(route('admin.agent-applications.index', ['status' => 'pending']), false);

        $response->assertDontSee('Approved this month', false);
        $response->assertDontSee('Rejected this month', false);
    }

    public function test_agents_queue_filter_active_only_returns_active_rows(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        [$activeAgent] = $this->createAgent($agency, 'AGT-ACTIVE-101', isActive: true);
        [$inactiveAgent] = $this->createAgent($agency, 'AGT-INACTIVE-202', isActive: false);

        $response = $this->actingAs($admin)->get('/admin/agents?queue=active')->assertOk();
        $response->assertSee('AGT-ACTIVE-101');
        $response->assertDontSee('AGT-INACTIVE-202');

        $response2 = $this->actingAs($admin)->get('/admin/agents?queue=inactive')->assertOk();
        $response2->assertSee('AGT-INACTIVE-202');
        $response2->assertDontSee('AGT-ACTIVE-101');
    }

    public function test_agents_commission_and_created_filters_apply_to_rows(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        [$matching] = $this->createAgent($agency, 'AGT-RATE-MATCH', isActive: true, commissionPercent: 12.5);
        [$nonMatching] = $this->createAgent($agency, 'AGT-RATE-OLD', isActive: true, commissionPercent: 3.0);
        $matching->forceFill(['created_at' => now()->subDays(2)])->save();
        $nonMatching->forceFill(['created_at' => now()->subMonths(2)])->save();

        $response = $this->actingAs($admin)
            ->get('/admin/agents?commission_filter=above_10&created_from='.now()->subDays(7)->toDateString())
            ->assertOk();

        $response->assertSee('AGT-RATE-MATCH');
        $response->assertDontSee('AGT-RATE-OLD');
    }

    public function test_agents_sales_filters_apply_to_rows(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        [$matching] = $this->createAgent($agency, 'AGT-SALES-MATCH', isActive: true);
        [$nonMatching] = $this->createAgent($agency, 'AGT-SALES-LOW', isActive: true);
        $this->createAgentBooking($agency, $matching, 150_000);
        $this->createAgentBooking($agency, $nonMatching, 20_000);

        $response = $this->actingAs($admin)
            ->get('/admin/agents?sales_from=100000&sales_to=200000')
            ->assertOk();

        $response->assertSee('AGT-SALES-MATCH');
        $response->assertDontSee('AGT-SALES-LOW');
    }

    public function test_agents_with_balance_queue_returns_only_agents_with_outstanding_commission(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        [$agentWithBalance] = $this->createAgent($agency, 'AGT-BALANCE-501', isActive: true, commissionPercent: 10.0);
        [$agentNoBalance] = $this->createAgent($agency, 'AGT-NOBALANCE-502', isActive: true);

        $booking = Booking::factory()->for($agency)->create([
            'agent_id' => $agentWithBalance->id,
            'status' => BookingStatus::Ticketed,
            'payment_status' => 'paid',
            'supplier' => 'duffel',
            'route' => 'LHE-DXB',
            'booking_reference' => 'REF-AGT-BAL',
        ]);
        $booking->fareBreakdown()->create([
            'base_fare' => 90_000, 'taxes' => 5000, 'fees' => 1000, 'markup' => 4000,
            'discount' => 0, 'total' => 100_000, 'currency' => 'PKR',
        ]);
        AgentCommissionEntry::query()->create([
            'agency_id' => $agency->id,
            'agent_id' => $agentWithBalance->id,
            'booking_id' => $booking->id,
            'type' => 'earned',
            'status' => 'approved',
            'calculation_basis' => 'percentage',
            'rate' => 10,
            'base_amount' => 100_000,
            'commission_amount' => 10_000,
            'currency' => 'PKR',
        ]);

        $response = $this->actingAs($admin)->get('/admin/agents?queue=with_balance')->assertOk();
        $response->assertSee('AGT-BALANCE-501');
        $response->assertDontSee('AGT-NOBALANCE-502');
    }

    public function test_agents_main_table_renders_operational_columns(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        [$agent] = $this->createAgent($agency, 'AGT-METRICS-700', isActive: true, commissionPercent: 7.5);

        $response = $this->actingAs($admin)->get('/admin/agents')->assertOk();

        $response->assertSee('data-testid="ota-agents-table"', false);
        $response->assertSee('AGT-METRICS-700');

        foreach ([
            '>Agent<',
            '>Contact<',
            '>Status<',
            '>Commission<',
            '>Bookings<',
            '>Monthly sales<',
            '>Action<',
        ] as $header) {
            $response->assertSee($header, false);
        }

        $response->assertSee('7.5%');
        $response->assertSee('0 bookings', false);
    }

    public function test_agents_main_table_drops_columns_now_owned_by_preview_panel(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        [$agent] = $this->createAgent($agency, 'AGT-SLIM-700', isActive: true, commissionPercent: 7.5);

        $response = $this->actingAs($admin)->get('/admin/agents')->assertOk();
        $html = $response->getContent();

        // Scope the assertion strictly to the <thead> of ota-agents-table — these
        // labels are also legitimately used in KPI cards / preview panel and must
        // not be flagged there.
        $tableMarker = 'data-testid="ota-agents-table"';
        $tableStart = strpos($html, $tableMarker);
        $this->assertNotFalse($tableStart, 'Agents table not rendered.');
        $theadStart = strpos($html, '<thead', $tableStart);
        $this->assertNotFalse($theadStart);
        $theadEnd = strpos($html, '</thead>', $theadStart);
        $this->assertNotFalse($theadEnd);
        $theadHtml = substr($html, $theadStart, $theadEnd - $theadStart);

        foreach (['Pending commission', 'Balance', 'Last booking', 'City', 'Created'] as $header) {
            $this->assertStringNotContainsString(
                $header,
                $theadHtml,
                'Removed column header still rendered in agents-table <thead>: '.$header
            );
        }

        // The exact 7 slim columns must remain.
        foreach (['Agent', 'Contact', 'Status', 'Commission', 'Bookings', 'Monthly sales', 'Action'] as $header) {
            $this->assertStringContainsString(
                '>'.$header.'<',
                $theadHtml,
                'Expected slim-table column missing from <thead>: '.$header
            );
        }
        $thCount = preg_match_all('/<th(\\s|>)/i', $theadHtml);
        $this->assertSame(7, $thCount, 'Agents table must have exactly 7 columns after the slim-table redesign.');

        // And the per-row markers for the removed cells must be gone.
        $row = $this->extractRow($html, $agent->id);
        $this->assertStringNotContainsString('agent-cell-pending', $row);
        $this->assertStringNotContainsString('agent-cell-balance', $row);
        $this->assertStringNotContainsString('agent-cell-last', $row);
    }

    public function test_agents_main_table_action_column_uses_open_button_only(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        [$agent] = $this->createAgent($agency, 'AGT-OPEN-303', isActive: true);

        $response = $this->actingAs($admin)->get('/admin/agents')->assertOk();
        $html = $response->getContent();

        $this->assertNotFalse(strpos($html, 'aria-label="Open agent AGT-OPEN-303"'));
        $rowMarker = 'data-agent-id="'.$agent->id.'"';
        $rowStart = strpos($html, $rowMarker);
        $this->assertNotFalse($rowStart);
        $rowEnd = strpos($html, '</tr>', $rowStart);
        $this->assertNotFalse($rowEnd);
        $rowHtml = substr($html, $rowStart, $rowEnd - $rowStart);

        $this->assertStringContainsString('>Open<', $rowHtml);
        $this->assertStringNotContainsString('>View<', $rowHtml);
        $this->assertStringNotContainsString('>Edit<', $rowHtml);
        $this->assertStringNotContainsString('>Statement<', $rowHtml);
        $this->assertStringNotContainsString('>Commissions<', $rowHtml);
    }

    public function test_agents_main_table_does_not_force_horizontal_scroll(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $this->createAgent($agency, 'AGT-NOSCROLL-001', isActive: true);

        $response = $this->actingAs($admin)->get('/admin/agents')->assertOk();
        $html = $response->getContent();

        // Wrapper element still exists so the responsive CSS hooks have a target,
        // but it must not pin a min-width that forces a horizontal scrollbar.
        $response->assertSee('data-testid="ota-agents-table-wrap"', false);
        $response->assertSee('agents-table-wrap', false);

        $this->assertStringNotContainsString(
            'min-width: 1080px',
            $html,
            'Slim-table redesign must not force a 1080px desktop minimum on the agents table.'
        );
        $this->assertStringNotContainsString(
            '.agents-table { min-width: 880px; }',
            $html,
            'Slim-table redesign must not force a mobile minimum that would re-introduce horizontal scroll.'
        );
    }

    public function test_agents_main_table_rows_carry_data_labels_for_stacked_card_mode(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        [$agent] = $this->createAgent($agency, 'AGT-STACKED-001', isActive: true);

        $response = $this->actingAs($admin)->get('/admin/agents')->assertOk();
        $html = $response->getContent();
        $row = $this->extractRow($html, $agent->id);

        // CSS pseudo-elements pull these labels in via attr() when the table
        // collapses to stacked cards on tablet/mobile, so they must be on every cell.
        foreach ([
            'data-label="Agent"',
            'data-label="Contact"',
            'data-label="Status"',
            'data-label="Commission"',
            'data-label="Bookings"',
            'data-label="Monthly sales"',
            'data-label="Action"',
        ] as $label) {
            $this->assertStringContainsString($label, $row, 'Missing responsive data-label: '.$label);
        }

        // Mobile/tablet card transform lives behind a media query; the rules
        // need to be present in the rendered <style> block for the page to be responsive.
        $this->assertStringContainsString('@media (max-width: 991.98px)', $html);
        $this->assertStringContainsString('content: attr(data-label)', $html);
    }

    public function test_agent_preview_panel_renders_focus_sections(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        [$agent] = $this->createAgent($agency, 'AGT-PREVIEW-900', isActive: true, commissionPercent: 12.5);

        $response = $this->actingAs($admin)->get('/admin/agents?preview='.$agent->id)->assertOk();

        $response->assertSee('data-testid="ota-agents-preview"', false);
        $response->assertSee('data-testid="ota-agents-preview-onboarded"', false);
        $response->assertSee('data-testid="ota-agents-preview-performance"', false);
        $response->assertSee('data-testid="ota-agents-preview-commission"', false);
        $response->assertSee('data-testid="ota-agents-preview-recent"', false);
        $response->assertSee('Total bookings', false);
        $response->assertSee('Monthly sales', false);
        $response->assertSee('Pending commission', false);
        $response->assertSee('Paid commission', false);
        $response->assertSee('Balance', false);
        $response->assertSee('Commission rate', false);
        $response->assertSee('Recent bookings', false);
        $response->assertSee('Onboarded', false);
    }

    public function test_agent_preview_panel_renders_seven_mini_profile_sections(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        [$agent] = $this->createAgent($agency, 'AGT-MINI-707', isActive: true, commissionPercent: 8.0);

        $response = $this->actingAs($admin)->get('/admin/agents?preview='.$agent->id)->assertOk();

        foreach ([
            'ota-agents-preview-section-profile',
            'ota-agents-preview-section-contact',
            'ota-agents-preview-section-commission-setup',
            'ota-agents-preview-section-performance',
            'ota-agents-preview-section-recent',
            'ota-agents-preview-section-notes',
            'ota-agents-preview-section-actions',
        ] as $sectionTestId) {
            $response->assertSee('data-testid="'.$sectionTestId.'"', false);
        }

        $response->assertSee('Agent profile</h6>', false);
        $response->assertSee('Contact</h6>', false);
        $response->assertSee('Commission setup</h6>', false);
        $response->assertSee('Performance</h6>', false);
        $response->assertSee('Recent bookings</h6>', false);
        $response->assertSee('Notes</h6>', false);
        $response->assertSee('Actions</h6>', false);
    }

    public function test_agent_preview_commission_setup_renders_status_and_next_payout(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        [$agent] = $this->createAgent($agency, 'AGT-CSETUP-808', isActive: true, commissionPercent: 9.25);

        $response = $this->actingAs($admin)->get('/admin/agents?preview='.$agent->id)->assertOk();

        $response->assertSee('data-testid="ota-agents-preview-commission-status"', false);
        $response->assertSee('data-testid="ota-agents-preview-next-payout"', false);
        $response->assertSee('Commission status', false);
        $response->assertSee('Next payout', false);
        $response->assertSee('Not scheduled', false);
        $response->assertSee('9.25%', false);
    }

    public function test_agent_preview_panel_actions_render_six_buttons_with_states(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        [$agent, $user] = $this->createAgent($agency, 'AGT-ACTIONS-808', isActive: true, commissionPercent: 8.0);

        $response = $this->actingAs($admin)->get('/admin/agents?preview='.$agent->id)->assertOk();

        foreach ([
            'ota-agents-action-open-profile',
            'ota-agents-action-edit-commission',
            'ota-agents-action-view-bookings',
            'ota-agents-action-generate-statement',
            'ota-agents-action-record-payment',
            'ota-agents-action-deactivate',
        ] as $actionTestId) {
            $response->assertSee('data-testid="'.$actionTestId.'"', false);
        }

        $response->assertSee('Open full profile', false);
        $response->assertSee('Edit commission', false);
        $response->assertSee('View bookings', false);
        $response->assertSee('Generate statement', false);
        $response->assertSee('Record commission payment', false);
        $response->assertSee('Deactivate agent', false);

        $response->assertSee(route('admin.users.show', ['user' => $user->id]), false);
        $response->assertSee(route('admin.commissions.show', ['agent' => $agent->id]).'#statement', false);
        $response->assertSee(route('admin.commissions.show', ['agent' => $agent->id]).'#payouts', false);

        $html = $response->getContent();
        $editStart = strpos($html, 'data-testid="ota-agents-action-edit-commission"');
        $this->assertNotFalse($editStart);
        $editButtonOpen = strrpos(substr($html, 0, $editStart), '<button');
        $editButtonClose = strpos($html, '</button>', $editStart);
        $editHtml = substr($html, (int) $editButtonOpen, (int) $editButtonClose - (int) $editButtonOpen);
        $this->assertStringContainsString('aria-disabled="true"', $editHtml);
        $this->assertStringContainsString('Coming soon', $editHtml);
    }

    public function test_agent_preview_panel_mutes_deactivate_when_agent_already_inactive(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        [$agent] = $this->createAgent($agency, 'AGT-INACTIVE-606', isActive: false);

        $response = $this->actingAs($admin)->get('/admin/agents?preview='.$agent->id)->assertOk();

        $html = $response->getContent();

        $deactivateStart = strpos($html, 'data-testid="ota-agents-action-deactivate"');
        $this->assertNotFalse($deactivateStart);
        $buttonOpen = strrpos(substr($html, 0, $deactivateStart), '<button');
        $buttonClose = strpos($html, '</button>', $deactivateStart);
        $deactivateHtml = substr($html, (int) $buttonOpen, (int) $buttonClose - (int) $buttonOpen);

        $this->assertStringContainsString('aria-disabled="true"', $deactivateHtml);
        $this->assertStringContainsString('Inactive', $deactivateHtml);
    }

    public function test_agents_empty_state_uses_premium_copy_and_review_applications_cta(): void
    {
        [, $admin] = $this->makeAgencyAdmin();

        $response = $this->actingAs($admin)->get('/admin/agents')->assertOk();

        $response->assertSee('No agents yet', false);
        $response->assertSee('Agents and partner agencies will appear here after approval or manual creation.', false);
        $response->assertSee('data-testid="ota-agents-empty-review-applications"', false);
        $response->assertSee('Review applications', false);
        $response->assertSee(route('admin.agent-applications.index'), false);
    }

    public function test_agents_filtered_view_shows_friendlier_empty_state(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $this->createAgent($agency, 'AGT-EXISTING-001', isActive: true);

        $response = $this->actingAs($admin)->get('/admin/agents?queue=with_balance')->assertOk();

        // Phase 23B.7.1 spec copy: "No agents match your filters." (was "these filters").
        $response->assertSee('No agents match your filters', false);
        $response->assertSee('data-testid="ota-agents-empty"', false);
    }

    public function test_agents_status_badge_renders_active_and_inactive_correctly(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        [$activeAgent] = $this->createAgent($agency, 'AGT-STATUS-ACTIVE', isActive: true);
        [$inactiveAgent] = $this->createAgent($agency, 'AGT-STATUS-INACTIVE', isActive: false);

        $response = $this->actingAs($admin)->get('/admin/agents')->assertOk();
        $html = $response->getContent();

        $activeRow = $this->extractRow($html, $activeAgent->id);
        $inactiveRow = $this->extractRow($html, $inactiveAgent->id);

        $this->assertStringContainsString('data-testid="ota-agent-status-active"', $activeRow);
        $this->assertStringContainsString('>Active<', $activeRow);
        $this->assertStringContainsString('data-testid="ota-agent-status-inactive"', $inactiveRow);
        $this->assertStringContainsString('>Inactive<', $inactiveRow);
    }

    public function test_agents_table_renders_phone_in_contact_column_when_available(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        [$agentWithPhone, $userWithPhone] = $this->createAgent($agency, 'AGT-PHONE-101', isActive: true);
        $userWithPhone->forceFill(['meta' => array_merge((array) $userWithPhone->meta, ['phone' => '+923001234567'])])->save();

        [$agentNoPhone, $userNoPhone] = $this->createAgent($agency, 'AGT-NOPHONE-102', isActive: true);
        $userNoPhone->forceFill(['meta' => array_merge((array) $userNoPhone->meta, ['phone' => null])])->save();

        $response = $this->actingAs($admin)->get('/admin/agents')->assertOk();
        $html = $response->getContent();

        $rowWithPhone = $this->extractRow($html, $agentWithPhone->id);
        $this->assertStringContainsString('+923001234567', $rowWithPhone);
        $this->assertStringContainsString('agent-cell-phone', $rowWithPhone);
        // email and phone share the same compact contact line (email · phone).
        $this->assertStringContainsString('agent-cell-contactline', $rowWithPhone);
        $this->assertStringContainsString('agent-cell-sep', $rowWithPhone);

        $rowNoPhone = $this->extractRow($html, $agentNoPhone->id);
        $this->assertStringNotContainsString('agent-cell-phone', $rowNoPhone);
        // Contact line is still rendered (carries the email) but the separator
        // dot must not appear when there is no phone to follow it.
        $this->assertStringContainsString('agent-cell-contactline', $rowNoPhone);
        $this->assertStringNotContainsString('agent-cell-sep', $rowNoPhone);
    }

    public function test_agents_page_does_not_expose_user_passwords_or_tokens(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        [$agent, $user] = $this->createAgent($agency, 'AGT-SECRET-001', isActive: true);

        $plainPassword = 'super-secret-password-XYZ123';
        $passwordHash = bcrypt($plainPassword);
        $rememberToken = 'remember_token_super_secret_zzz';

        $user->forceFill([
            'password' => $passwordHash,
            'remember_token' => $rememberToken,
        ])->save();

        $response = $this->actingAs($admin)->get('/admin/agents?preview='.$agent->id)->assertOk();

        $response->assertDontSee($plainPassword);
        $response->assertDontSee($passwordHash);
        $response->assertDontSee($rememberToken);

        // Bcrypt hashes start with $2y$ — make sure no bcrypt prefix bleeds through anywhere.
        $this->assertStringNotContainsString('$2y$', $response->getContent());
    }

    public function test_agents_page_does_not_expose_passenger_passport(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        [$agent] = $this->createAgent($agency, 'AGT-PII-001', isActive: true);
        $booking = Booking::factory()->for($agency)->create([
            'agent_id' => $agent->id,
            'status' => BookingStatus::Pending,
            'payment_status' => 'unpaid',
            'supplier' => 'duffel',
            'route' => 'LHE-DXB',
            'booking_reference' => 'REF-PII-AGT',
        ]);
        BookingPassenger::factory()->for($booking)->create([
            'first_name' => 'AgentSecretFirst',
            'last_name' => 'AgentSecretLast',
            'passport_number' => 'AG9988776',
            'national_id_number' => '44444-4444444-4',
        ]);

        $response = $this->actingAs($admin)->get('/admin/agents?preview='.$agent->id)->assertOk();
        $response->assertDontSee('AG9988776');
        $response->assertDontSee('44444-4444444-4');
        $response->assertDontSee('AgentSecretFirst');
        $response->assertDontSee('AgentSecretLast');
    }

    public function test_agents_csv_export_uses_safe_filtered_agent_rows(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        [$matching] = $this->createAgent($agency, 'AGT-CSV-MATCH', isActive: true, commissionPercent: 7.5);
        [$other] = $this->createAgent($agency, 'AGT-CSV-OTHER', isActive: true, commissionPercent: 2.0);
        $this->createAgentBooking($agency, $matching, 120_000);
        $this->createAgentBooking($agency, $other, 15_000);

        $csv = $this->actingAs($admin)
            ->get('/admin/agents/export?sales_from=100000')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('"Agent code",Agency,Contact,Email,City,Status', $csv);
        $this->assertStringContainsString('AGT-CSV-MATCH', $csv);
        $this->assertStringNotContainsString('AGT-CSV-OTHER', $csv);
    }

    public function test_agents_table_rows_advertise_ajax_preview_endpoint(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        [$agent] = $this->createAgent($agency, 'AGT-AJAX-001', isActive: true);

        $response = $this->actingAs($admin)->get('/admin/agents')->assertOk();
        $html = $response->getContent();
        $row = $this->extractRow($html, $agent->id);

        $this->assertStringContainsString(
            'data-preview-ajax-url="'.route('admin.agents.preview', ['agent' => $agent->id]).'"',
            $row,
            'Row must advertise the AJAX preview endpoint so JS can swap previews without reload.'
        );
        $this->assertStringContainsString('data-agent-code="AGT-AJAX-001"', $row);
        $this->assertStringContainsString('data-preview-url="', $row);

        $this->assertStringNotContainsString('onclick="window.location.href', $row, 'Row should not hard-navigate on click; AJAX swap is preferred.');
        $this->assertStringNotContainsString('onkeydown=', $row, 'Row keyboard handler is now JS-driven.');
    }

    public function test_agents_page_includes_ajax_preview_swap_script_and_target_nodes(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $this->createAgent($agency, 'AGT-AJAX-WIRE-001', isActive: true);

        $response = $this->actingAs($admin)->get('/admin/agents')->assertOk();

        $response->assertSee('id="agents-preview-body"', false);
        $response->assertSee('id="agents-preview-subtitle"', false);
        $response->assertSee('data-agents-preview-body', false);
        $response->assertSee('fetchPreviewForRow', false);
        $response->assertSee('agents-preview-loading', false);
    }

    public function test_agent_preview_ajax_endpoint_returns_rendered_partial_for_authorised_admin(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        [$agent] = $this->createAgent($agency, 'AGT-AJAX-PAYLOAD-001', isActive: true, commissionPercent: 7.5);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.agents.preview', ['agent' => $agent->id]))
            ->assertOk()
            ->assertJsonStructure(['agent_id', 'agent_code', 'preview_url', 'html']);

        $payload = $response->json();
        $this->assertSame($agent->id, $payload['agent_id']);
        $this->assertSame('AGT-AJAX-PAYLOAD-001', $payload['agent_code']);
        $this->assertSame(
            route('admin.agents', ['preview' => $agent->id]),
            $payload['preview_url']
        );

        $html = (string) $payload['html'];
        foreach ([
            'ota-agents-preview-section-profile',
            'ota-agents-preview-section-contact',
            'ota-agents-preview-section-commission-setup',
            'ota-agents-preview-section-performance',
            'ota-agents-preview-section-recent',
            'ota-agents-preview-section-notes',
            'ota-agents-preview-section-actions',
        ] as $sectionTestId) {
            $this->assertStringContainsString('data-testid="'.$sectionTestId.'"', $html);
        }
        $this->assertStringContainsString('AGT-AJAX-PAYLOAD-001', $html);
        $this->assertStringContainsString('7.50%', $html);
    }

    public function test_agent_preview_ajax_endpoint_rejects_cross_agency_admin(): void
    {
        [$ownAgency, $foreignAgent] = (function () {
            $agency = Agency::factory()->create();
            [$agent] = $this->createAgent($agency, 'AGT-AJAX-FOREIGN-001', isActive: true);

            return [$agency, $agent];
        })();

        [, $intruderAdmin] = $this->makeAgencyAdmin();

        $this->actingAs($intruderAdmin)
            ->getJson(route('admin.agents.preview', ['agent' => $foreignAgent->id]))
            ->assertForbidden();
    }

    public function test_agent_preview_ajax_endpoint_returns_404_for_missing_agent(): void
    {
        [, $admin] = $this->makeAgencyAdmin();

        $this->actingAs($admin)
            ->getJson('/admin/agents/9999999/preview')
            ->assertNotFound();
    }

    public function test_agent_preview_ajax_endpoint_redirects_when_unauthenticated(): void
    {
        [$agency] = (function () {
            $agency = Agency::factory()->create();
            $this->createAgent($agency, 'AGT-AJAX-AUTH-001', isActive: true);

            return [$agency];
        })();

        $agent = Agent::query()->where('code', 'AGT-AJAX-AUTH-001')->firstOrFail();

        $this->getJson(route('admin.agents.preview', ['agent' => $agent->id]))
            ->assertUnauthorized();
    }

    public function test_agent_preview_ajax_response_does_not_leak_secrets_or_pii(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        [$agent, $user] = $this->createAgent($agency, 'AGT-AJAX-SECURE-001', isActive: true);

        $plainPassword = 'ajax-only-secret-Q9z!';
        $passwordHash = bcrypt($plainPassword);
        $rememberToken = 'ajax_remember_token_yyy';
        $user->forceFill([
            'password' => $passwordHash,
            'remember_token' => $rememberToken,
        ])->save();

        $booking = Booking::factory()->for($agency)->create([
            'agent_id' => $agent->id,
            'status' => BookingStatus::Pending,
            'payment_status' => 'unpaid',
            'supplier' => 'duffel',
            'route' => 'LHE-DXB',
            'booking_reference' => 'REF-AJAX-PII',
        ]);
        BookingPassenger::factory()->for($booking)->create([
            'first_name' => 'AjaxSecretFirst',
            'last_name' => 'AjaxSecretLast',
            'passport_number' => 'AJ1122334',
            'national_id_number' => '99999-9999999-9',
        ]);

        $payload = $this->actingAs($admin)
            ->getJson(route('admin.agents.preview', ['agent' => $agent->id]))
            ->assertOk()
            ->json();

        $body = json_encode($payload);
        $this->assertIsString($body);

        foreach ([
            $plainPassword,
            $passwordHash,
            $rememberToken,
            'AJ1122334',
            '99999-9999999-9',
            'AjaxSecretFirst',
            'AjaxSecretLast',
        ] as $secret) {
            $this->assertStringNotContainsString($secret, $body, 'AJAX preview leaked sensitive value: '.$secret);
        }
        $this->assertStringNotContainsString('$2y$', $body, 'AJAX preview leaked a bcrypt hash prefix.');
    }

    public function test_agents_filter_card_restructured_into_two_rows_plus_actions(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $this->createAgent($agency, 'AGT-LAYOUT-001', isActive: true);

        $response = $this->actingAs($admin)->get('/admin/agents')->assertOk();

        $response->assertSee('data-testid="ota-agents-filter-row-search"', false);
        $response->assertSee('data-testid="ota-agents-filter-row-fields"', false);
        $response->assertSee('data-testid="ota-agents-filter-actions"', false);
        $response->assertSee('Search agent', false);
        $response->assertSee('Sales range', false);
        $response->assertSee('Created date', false);
        $response->assertSee('aria-controls="agents-search-suggestions"', false);
        $response->assertSee('id="agents-search-suggestions"', false);
        // Hidden agent_id companion field used by the typeahead.
        $response->assertSee('id="agents-filter-agent-id"', false);
    }

    public function test_agents_page_includes_ajax_filter_swap_wiring(): void
    {
        [, $admin] = $this->makeAgencyAdmin();

        $response = $this->actingAs($admin)->get('/admin/agents')->assertOk();

        $response->assertSee('id="agents-table-body"', false);
        $response->assertSee('data-agents-table-body', false);
        $response->assertSee('data-agents-table-loading', false);
        // Phase 23B.7.1 spec mandates exact loading copy: "Loading agents...".
        $response->assertSee('Loading agents', false);
        // Endpoint URLs are emitted via @json() which JSON-escapes the slashes
        // (admin\/agents\/data). Match the escaped form so the assertion is stable.
        $response->assertSee('admin\\/agents\\/data', false);
        $response->assertSee('admin\\/agents\\/suggestions', false);
        $response->assertSee('debouncedFilterFetch', false);
        $response->assertSee('fetchAgentsData', false);
        $response->assertSee('fetchSuggestions', false);
    }

    public function test_agents_data_endpoint_returns_rows_and_preview_html(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        [$agent] = $this->createAgent($agency, 'AGT-DATA-001', isActive: true, commissionPercent: 8.0);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.agents.data', ['queue' => 'all']))
            ->assertOk()
            ->assertJsonStructure([
                'rows_html',
                'preview_html',
                'listed_count',
                'total_count',
                'has_filters_applied',
                'selected_agent_id',
                'selected_agent_code',
                'queue_label',
            ]);

        $payload = $response->json();
        $this->assertGreaterThanOrEqual(1, $payload['listed_count']);
        $this->assertGreaterThanOrEqual(1, $payload['total_count']);
        $this->assertSame('All agents', $payload['queue_label']);
        $this->assertStringContainsString('ota-agents-table', $payload['rows_html']);
        $this->assertStringContainsString('AGT-DATA-001', $payload['rows_html']);
        $this->assertStringContainsString('ota-agents-preview-section-profile', $payload['preview_html']);
        $this->assertStringContainsString('AGT-DATA-001', $payload['preview_html']);
        $this->assertFalse($payload['has_filters_applied']);
    }

    public function test_agents_data_endpoint_filters_by_search_and_signals_filters_applied(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $this->createAgent($agency, 'AGT-MATCH-101', isActive: true);
        $this->createAgent($agency, 'AGT-OTHER-202', isActive: true);

        $payload = $this->actingAs($admin)
            ->getJson(route('admin.agents.data', ['search' => 'MATCH-101']))
            ->assertOk()
            ->json();

        $this->assertSame(1, $payload['listed_count']);
        $this->assertTrue($payload['has_filters_applied']);
        $this->assertStringContainsString('AGT-MATCH-101', $payload['rows_html']);
        $this->assertStringNotContainsString('AGT-OTHER-202', $payload['rows_html']);
    }

    public function test_agents_data_endpoint_renders_empty_state_for_no_matches(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $this->createAgent($agency, 'AGT-NOMATCH-101', isActive: true);

        $payload = $this->actingAs($admin)
            ->getJson(route('admin.agents.data', ['search' => 'zzzzz-no-such-agent']))
            ->assertOk()
            ->json();

        $this->assertSame(0, $payload['listed_count']);
        $this->assertTrue($payload['has_filters_applied']);
        $this->assertStringContainsString('data-testid="ota-agents-empty"', $payload['rows_html']);
        $this->assertStringContainsString('No agents match your filters', $payload['rows_html']);
    }

    public function test_agents_data_endpoint_redacts_secrets_in_rendered_html(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        [$agent, $user] = $this->createAgent($agency, 'AGT-DATA-SECURE-001', isActive: true);
        $hash = bcrypt('data-endpoint-secret-XYZ');
        $token = 'data_remember_token_zzz';
        $user->forceFill(['password' => $hash, 'remember_token' => $token])->save();

        $payload = $this->actingAs($admin)
            ->getJson(route('admin.agents.data'))
            ->assertOk()
            ->json();

        $body = (string) json_encode($payload);
        foreach (['data-endpoint-secret-XYZ', $hash, $token, '$2y$'] as $secret) {
            $this->assertStringNotContainsString($secret, $body, 'Data endpoint leaked: '.$secret);
        }
    }

    public function test_agents_suggestions_endpoint_returns_matches_for_min_two_chars(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        [$agent] = $this->createAgent($agency, 'AGT-SUG-AAA-001', isActive: true);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.agents.suggestions', ['q' => 'AGT-SUG']))
            ->assertOk()
            ->assertJsonStructure(['suggestions' => [['id', 'code', 'agency', 'email', 'city', 'status', 'primary_line', 'secondary_line', 'preview_url']]]);

        $first = $response->json('suggestions.0');
        $this->assertSame($agent->id, $first['id']);
        $this->assertSame('AGT-SUG-AAA-001', $first['code']);
        $this->assertStringContainsString('AGT-SUG-AAA-001', $first['primary_line']);
        $this->assertSame(route('admin.agents.preview', ['agent' => $agent->id]), $first['preview_url']);
    }

    public function test_agents_suggestions_endpoint_returns_empty_for_short_query(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $this->createAgent($agency, 'AGT-SHORTQ-101', isActive: true);

        // Empty query
        $this->actingAs($admin)
            ->getJson(route('admin.agents.suggestions'))
            ->assertOk()
            ->assertExactJson(['suggestions' => []]);

        // Single-character query — must not return results.
        $this->actingAs($admin)
            ->getJson(route('admin.agents.suggestions', ['q' => 'A']))
            ->assertOk()
            ->assertExactJson(['suggestions' => []]);
    }

    public function test_agents_suggestions_endpoint_matches_email_and_phone(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        [$emailAgent, $emailUser] = $this->createAgent($agency, 'AGT-EM-001', isActive: true);
        $emailUser->forceFill(['email' => 'email-typeahead@example.test'])->save();

        [$phoneAgent, $phoneUser] = $this->createAgent($agency, 'AGT-PH-002', isActive: true);
        $phoneUser->forceFill(['meta' => array_merge((array) $phoneUser->meta, ['phone' => '+923009998877'])])->save();

        $byEmail = $this->actingAs($admin)
            ->getJson(route('admin.agents.suggestions', ['q' => 'email-typeahead']))
            ->assertOk()
            ->json('suggestions');
        $this->assertGreaterThanOrEqual(1, count($byEmail));
        $this->assertSame($emailAgent->id, $byEmail[0]['id']);

        $byPhone = $this->actingAs($admin)
            ->getJson(route('admin.agents.suggestions', ['q' => '923009998877']))
            ->assertOk()
            ->json('suggestions');
        $this->assertGreaterThanOrEqual(1, count($byPhone));
        $this->assertSame($phoneAgent->id, $byPhone[0]['id']);
    }

    public function test_agents_suggestions_endpoint_does_not_leak_secrets_or_pii(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        [, $user] = $this->createAgent($agency, 'AGT-SUG-SECURE-001', isActive: true);
        $hash = bcrypt('suggest-secret-XYZ');
        $token = 'suggest_remember_token';
        $user->forceFill(['password' => $hash, 'remember_token' => $token])->save();

        $response = $this->actingAs($admin)
            ->getJson(route('admin.agents.suggestions', ['q' => 'AGT-SUG-SECURE']))
            ->assertOk();

        $body = $response->getContent();
        foreach (['suggest-secret-XYZ', $hash, $token, '$2y$'] as $secret) {
            $this->assertStringNotContainsString($secret, $body, 'Suggestions endpoint leaked: '.$secret);
        }
    }

    public function test_agents_data_endpoint_rejects_users_without_view_any(): void
    {
        $response = $this->getJson(route('admin.agents.data'));
        $response->assertUnauthorized();
    }

    public function test_agents_suggestions_endpoint_scopes_to_user_agency(): void
    {
        $foreignAgency = Agency::factory()->create();
        [$foreignAgent] = $this->createAgent($foreignAgency, 'AGT-FOREIGN-SUG-001', isActive: true);

        [, $intruder] = $this->makeAgencyAdmin();

        $payload = $this->actingAs($intruder)
            ->getJson(route('admin.agents.suggestions', ['q' => 'FOREIGN-SUG']))
            ->assertOk()
            ->json();

        $codes = array_column((array) ($payload['suggestions'] ?? []), 'code');
        $this->assertNotContains('AGT-FOREIGN-SUG-001', $codes, 'Cross-agency agent must not appear in suggestions for an agency admin from a different agency.');
    }

    // ============================================================
    //  Phase 23B.7.1 — added/updated coverage
    // ============================================================

    /**
     * Phase 23B.7.1 PART E — the documented endpoint name is
     * /admin/agents/search; verify the alias resolves to the same handler.
     */
    public function test_agents_search_endpoint_alias_matches_suggestions(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        [$agent] = $this->createAgent($agency, 'AGT-SEARCH-ALIAS-001', isActive: true, commissionPercent: 6.5);

        $this->assertSame(
            url('/admin/agents/search'),
            route('admin.agents.search'),
            'admin.agents.search must point at /admin/agents/search per Phase 23B.7.1 spec.'
        );

        $payload = $this->actingAs($admin)
            ->getJson(route('admin.agents.search', ['q' => 'SEARCH-ALIAS']))
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('suggestions', $payload);
        $this->assertGreaterThanOrEqual(1, count($payload['suggestions']));
        $first = $payload['suggestions'][0];
        foreach ([
            'id', 'agent_code', 'agency_name', 'contact_person', 'email',
            'phone', 'city', 'status', 'commission_rate', 'monthly_sales',
            'total_bookings',
        ] as $key) {
            $this->assertArrayHasKey($key, $first, 'Missing documented suggestion key: '.$key);
        }
        $this->assertSame('AGT-SEARCH-ALIAS-001', $first['agent_code']);
        $this->assertSame(6.5, (float) $first['commission_rate']);
    }

    /**
     * Phase 23B.7.1 PART F — data endpoint must additionally expose a typed
     * payload (rows[], selected_agent, counts, pagination) so other
     * consumers can drive UI without re-parsing rendered HTML.
     */
    public function test_agents_data_endpoint_returns_documented_typed_payload(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        [$agent] = $this->createAgent($agency, 'AGT-TYPED-001', isActive: true, commissionPercent: 7.5);

        $payload = $this->actingAs($admin)
            ->getJson(route('admin.agents.data'))
            ->assertOk()
            ->assertJsonStructure([
                'rows' => [
                    [
                        'id', 'agent_code', 'agency_name', 'contact_person',
                        'email', 'phone', 'city', 'status', 'commission_rate',
                        'monthly_sales', 'total_bookings',
                    ],
                ],
                'selected_agent' => [
                    'id', 'agent_code', 'agency_name', 'contact_person',
                    'email', 'phone', 'city', 'status', 'commission_rate',
                    'monthly_sales', 'total_bookings', 'outstanding_balance',
                    'commission_pending', 'last_booking_at',
                ],
                'counts' => [
                    'total', 'active', 'inactive', 'pending_commission', 'monthly_sales',
                ],
                'pagination' => [
                    'current_page', 'per_page', 'total', 'last_page', 'from', 'to',
                ],
            ])
            ->json();

        $this->assertSame('AGT-TYPED-001', $payload['rows'][0]['agent_code']);
        $this->assertSame(7.5, (float) $payload['rows'][0]['commission_rate']);
        $this->assertSame($agent->id, $payload['selected_agent']['id']);
        $this->assertGreaterThanOrEqual(1, $payload['counts']['total']);
        $this->assertGreaterThanOrEqual(1, $payload['counts']['active']);
        $this->assertSame(1, $payload['pagination']['current_page']);
    }

    public function test_agents_data_endpoint_filters_by_status_only(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $this->createAgent($agency, 'AGT-STATUS-A-001', isActive: true);
        $this->createAgent($agency, 'AGT-STATUS-I-002', isActive: false);

        $payload = $this->actingAs($admin)
            ->getJson(route('admin.agents.data', ['status' => 'active']))
            ->assertOk()
            ->json();

        $codes = array_column($payload['rows'], 'agent_code');
        $this->assertContains('AGT-STATUS-A-001', $codes);
        $this->assertNotContains('AGT-STATUS-I-002', $codes);
        $this->assertTrue($payload['has_filters_applied']);
    }

    public function test_agents_data_endpoint_filters_by_city_only(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        [$lhrAgent] = $this->createAgent($agency, 'AGT-CITY-LHR-001', isActive: true);
        [$khiAgent] = $this->createAgent($agency, 'AGT-CITY-KHI-002', isActive: true);
        $lhrAgent->forceFill(['meta' => array_merge((array) $lhrAgent->meta, ['city' => 'Lahore'])])->save();
        $khiAgent->forceFill(['meta' => array_merge((array) $khiAgent->meta, ['city' => 'Karachi'])])->save();

        $payload = $this->actingAs($admin)
            ->getJson(route('admin.agents.data', ['city' => 'Lahore']))
            ->assertOk()
            ->json();

        $codes = array_column($payload['rows'], 'agent_code');
        $this->assertContains('AGT-CITY-LHR-001', $codes);
        $this->assertNotContains('AGT-CITY-KHI-002', $codes);
        $this->assertTrue($payload['has_filters_applied']);
    }

    /**
     * Phase 23B.7.1 PART F — rows[] is a strict whitelist projection. It
     * must never carry password hashes, remember tokens, or passenger PII.
     */
    public function test_agents_data_endpoint_rows_array_does_not_leak_secrets_or_pii(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        [$agent, $user] = $this->createAgent($agency, 'AGT-DATA-PII-001', isActive: true);

        $hash = bcrypt('rows-array-secret-XYZ');
        $token = 'rows_array_token_zzz';
        $user->forceFill(['password' => $hash, 'remember_token' => $token])->save();

        $booking = Booking::factory()->for($agency)->create([
            'agent_id' => $agent->id,
            'status' => BookingStatus::Pending,
            'payment_status' => 'unpaid',
            'supplier' => 'duffel',
            'route' => 'LHE-DXB',
            'booking_reference' => 'REF-DATA-PII',
        ]);
        BookingPassenger::factory()->for($booking)->create([
            'first_name' => 'RowsArrayFirst',
            'last_name' => 'RowsArrayLast',
            'passport_number' => 'RA1122334',
            'national_id_number' => '11111-2222222-3',
        ]);

        $payload = $this->actingAs($admin)
            ->getJson(route('admin.agents.data'))
            ->assertOk()
            ->json();

        $body = (string) json_encode($payload['rows']);
        foreach ([
            'rows-array-secret-XYZ',
            $hash,
            $token,
            'RA1122334',
            '11111-2222222-3',
            'RowsArrayFirst',
            'RowsArrayLast',
            '$2y$',
        ] as $secret) {
            $this->assertStringNotContainsString($secret, $body, 'Data endpoint rows[] leaked: '.$secret);
        }

        $bodySelected = (string) json_encode($payload['selected_agent'] ?? []);
        foreach ([$hash, $token, 'RA1122334', 'RowsArrayFirst', '$2y$'] as $secret) {
            $this->assertStringNotContainsString($secret, $bodySelected, 'selected_agent leaked: '.$secret);
        }
    }

    /**
     * Phase 23B.7.1 PART I — non-JS fallback. The plain GET form must still
     * filter the page even if the agent disables JavaScript (or AJAX errors).
     */
    public function test_agents_page_supports_non_js_filter_form_fallback(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $this->createAgent($agency, 'AGT-NOJS-ACTIVE-001', isActive: true);
        $this->createAgent($agency, 'AGT-NOJS-INACTIVE-002', isActive: false);

        $response = $this->actingAs($admin)
            ->get('/admin/agents?status=active')
            ->assertOk();

        $response->assertSee('AGT-NOJS-ACTIVE-001');
        $response->assertDontSee('AGT-NOJS-INACTIVE-002');

        $response->assertSee('id="agents-filter-form"', false);
        $response->assertSee('action="'.route('admin.agents').'"', false);
        // Laravel emits the form attribute lowercase (method="get"); both
        // browsers and the spec treat it as a GET, no JavaScript needed.
        $response->assertSee('method="get"', false);
    }

    /**
     * Phase 23B.7.1 PART J — adopt the spec table layout: table-layout: fixed
     * with explicit % column widths so the slim table never overflows.
     */
    public function test_agents_table_uses_spec_fixed_layout_and_percentage_columns(): void
    {
        [$agency, $admin] = $this->makeAgencyAdmin();
        $this->createAgent($agency, 'AGT-CSS-001', isActive: true);

        $response = $this->actingAs($admin)->get('/admin/agents')->assertOk();

        $response->assertSee('table-layout: fixed', false);
        $response->assertSee('.agents-table .col-agent      { width: 22%; }', false);
        $response->assertSee('.agents-table .col-contact    { width: 26%; }', false);
        $response->assertSee('.agents-table .col-status     { width: 10%; }', false);
        $response->assertSee('.agents-table .col-commission { width: 12%; }', false);
        $response->assertSee('.agents-table .col-bookings   { width: 10%; }', false);
        $response->assertSee('.agents-table .col-sales      { width: 12%; text-align: right; }', false);
        $response->assertSee('.agents-table .col-action     { width:  8%; text-align: right; }', false);

        $html = $response->getContent();
        $tableMarker = 'data-testid="ota-agents-table"';
        $tableStart = strpos($html, $tableMarker);
        $this->assertNotFalse($tableStart);
        $theadStart = strpos($html, '<thead', $tableStart);
        $theadEnd = strpos($html, '</thead>', $theadStart);
        $theadHtml = substr($html, (int) $theadStart, ((int) $theadEnd) - ((int) $theadStart));

        foreach (['col-agent', 'col-contact', 'col-status', 'col-commission', 'col-bookings', 'col-sales', 'col-action'] as $cls) {
            $this->assertStringContainsString('class="'.$cls.'"', $theadHtml, 'Missing column class on <th>: '.$cls);
        }
    }

    /**
     * Phase 23B.7.1 PART G — interim "Searching..." indicator inside the
     * suggestions dropdown while the AJAX call is in flight.
     */
    public function test_agents_search_indicator_is_wired_in_suggestions_flow(): void
    {
        [, $admin] = $this->makeAgencyAdmin();

        $response = $this->actingAs($admin)->get('/admin/agents')->assertOk();

        $response->assertSee('showSearchingIndicator', false);
        $response->assertSee('data-agents-suggest-loading', false);
        $response->assertSee('Searching...', false);
    }

    /**
     * Phase 23B.7.1 PART H — preview empty state copy.
     */
    public function test_agents_preview_empty_state_uses_spec_copy(): void
    {
        [, $admin] = $this->makeAgencyAdmin();

        $response = $this->actingAs($admin)->get('/admin/agents')->assertOk();

        $response->assertSee('data-testid="ota-agents-preview-empty"', false);
        $response->assertSee('Select an agent to view profile, commission, and performance.', false);
    }

    /**
     * @return array{Agency, User}
     */
    protected function makeAgencyAdmin(): array
    {
        $agency = Agency::factory()->create();
        $admin = User::factory()->agencyAdmin()->create([
            'current_agency_id' => $agency->id,
        ]);
        $agency->users()->attach($admin->id, ['role' => AccountType::AgencyAdmin->value]);

        return [$agency, $admin];
    }

    /**
     * @return array{Agent, User}
     */
    protected function createAgent(Agency $agency, string $code, bool $isActive = true, float $commissionPercent = 5.0): array
    {
        $user = User::factory()->create([
            'current_agency_id' => $agency->id,
            'account_type' => AccountType::Agent,
        ]);
        $agent = Agent::factory()->for($agency)->create([
            'user_id' => $user->id,
            'code' => $code,
            'is_active' => $isActive,
            'commission_percent' => $commissionPercent,
        ]);

        return [$agent, $user];
    }

    protected function createAgentBooking(Agency $agency, Agent $agent, int $total): Booking
    {
        $booking = Booking::factory()->for($agency)->create([
            'agent_id' => $agent->id,
            'status' => BookingStatus::Ticketed,
            'payment_status' => 'paid',
            'supplier' => 'duffel',
            'route' => 'LHE-DXB',
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

    protected function extractRow(string $html, int $agentId): string
    {
        $marker = 'data-agent-id="'.$agentId.'"';
        $start = strpos($html, $marker);
        $this->assertNotFalse($start, "Row for agent {$agentId} not found");
        $end = strpos($html, '</tr>', $start);
        $this->assertNotFalse($end, "Row close tag for agent {$agentId} not found");

        return substr($html, $start, $end - $start);
    }

    protected function createAgentApplication(string $status): AgentApplication
    {
        return AgentApplication::query()->create([
            'first_name' => 'Agent',
            'last_name' => ucfirst($status),
            'email' => $status.'-agent@example.test',
            'mobile' => '+9200000000',
            'company_name' => ucfirst($status).' Travel',
            'business_type' => 'travel_agency',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'office_address' => 'Test address',
            'status' => $status,
        ]);
    }
}
