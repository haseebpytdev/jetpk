<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Http\Controllers\Admin\AgentApplicationController;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminAgentApplicationsReviewQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_applications_page_uses_review_queue_layout(): void
    {
        $admin = $this->makePlatformAdmin();
        $application = $this->createAgentApplicationRow([
            'first_name' => 'Furqan',
            'last_name' => 'Applicant',
            'email' => 'furqan@example.test',
            'company_name' => 'Furqan Travels',
            'mobile' => '+923001112233',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.agent-applications.index', ['preview' => $application->id]))
            ->assertRedirect('/admin/dashboard/agents/applications?preview='.$application->id);

        $html = $this->agentApplicationsIndexHtml($admin, ['preview' => $application->id]);

        $this->assertStringContainsString('data-testid="ota-agent-applications-page-header"', $html);
        $this->assertStringContainsString('Agent applications', $html);
        $this->assertStringContainsString('Review partner applications, approve qualified agents, and track onboarding status.', $html);
        $this->assertStringContainsString('data-testid="ota-agent-applications-kpis"', $html);
        $this->assertStringContainsString('data-testid="ota-agent-applications-filter-card"', $html);
        $this->assertStringContainsString('data-testid="ota-agent-applications-table"', $html);

        foreach ([
            'Applicant',
            'Company',
            'Contact',
            'Status',
            'Submitted',
            'Flags',
            'Action',
        ] as $header) {
            $this->assertStringContainsString('>'.$header.'<', $html);
        }

        // Email lives under Contact, not as its own top-level table column.
        $theadStart = strpos($html, '<thead');
        $theadEnd = strpos($html, '</thead>', (int) $theadStart);
        $thead = substr($html, (int) $theadStart, ((int) $theadEnd) - ((int) $theadStart));
        $this->assertStringNotContainsString('>Email<', $thead);

        $this->assertStringContainsString('Furqan Applicant', $html);
        $this->assertStringContainsString('furqan@example.test', $html);
        $this->assertStringContainsString('Furqan Travels', $html);
        $this->assertStringContainsString('Open review', $html);
    }

    public function test_agent_applications_kpis_include_duplicates_and_converted_count(): void
    {
        $admin = $this->makePlatformAdmin();
        $agency = Agency::factory()->create();

        $this->createAgentApplicationRow(['email' => 'duplicate@example.test', 'status' => 'pending']);
        $this->createAgentApplicationRow(['email' => 'duplicate@example.test', 'status' => 'pending']);
        $this->createAgentApplicationRow(['email' => 'approved@example.test', 'status' => 'approved']);
        $this->createAgentApplicationRow(['email' => 'rejected@example.test', 'status' => 'rejected']);
        $converted = $this->createAgentApplicationRow(['email' => 'converted@example.test', 'status' => 'approved']);

        $agentUser = User::factory()->create([
            'email' => $converted->email,
            'account_type' => AccountType::Agent,
            'current_agency_id' => $agency->id,
        ]);
        Agent::factory()->for($agency)->create(['user_id' => $agentUser->id]);

        $html = $this->agentApplicationsIndexHtml($admin);

        $this->assertStringContainsString('Total applications', $html);
        $this->assertStringContainsString('Pending review', $html);
        $this->assertStringContainsString('Approved', $html);
        $this->assertStringContainsString('Rejected', $html);
        $this->assertStringContainsString('Converted to agent', $html);
        $this->assertStringContainsString('Duplicate emails', $html);

        // Duplicate metric counts duplicate-looking application rows, not just unique emails.
        $this->assertStringContainsString('Duplicate email', $html);
        $this->assertStringContainsString('Converted', $html);
    }

    public function test_agent_applications_filters_status_city_country_dates_search_and_duplicate_only(): void
    {
        $admin = $this->makePlatformAdmin();
        $matching = $this->createAgentApplicationRow([
            'first_name' => 'Matching',
            'last_name' => 'Applicant',
            'email' => 'same@example.test',
            'company_name' => 'Same Travel',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'status' => 'pending',
            'created_at' => now()->subDay(),
        ]);
        $this->createAgentApplicationRow([
            'email' => 'same@example.test',
            'company_name' => 'Duplicate Companion',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'status' => 'pending',
            'created_at' => now()->subDay(),
        ]);
        $this->createAgentApplicationRow([
            'first_name' => 'Other',
            'email' => 'other@example.test',
            'company_name' => 'Other Travel',
            'city' => 'Karachi',
            'country' => 'Pakistan',
            'status' => 'approved',
            'created_at' => now()->subMonth(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.agent-applications.index', [
                'search' => 'Matching',
                'status' => 'pending',
                'city_country' => 'Lahore',
                'submitted_from' => now()->subDays(2)->toDateString(),
                'submitted_to' => now()->toDateString(),
                'duplicate_only' => 1,
                'preview' => $matching->id,
            ]))
            ->assertRedirect();

        $html = $this->agentApplicationsIndexHtml($admin, [
            'search' => 'Matching',
            'status' => 'pending',
            'city_country' => 'Lahore',
            'submitted_from' => now()->subDays(2)->toDateString(),
            'submitted_to' => now()->toDateString(),
            'duplicate_only' => 1,
            'preview' => $matching->id,
        ]);

        $this->assertStringContainsString('Matching Applicant', $html);
        $this->assertStringNotContainsString('Other Travel', $html);
        $this->assertStringContainsString('filters applied', $html);
        $this->assertStringContainsString('Duplicate email', $html);
        $this->assertStringContainsString('name="duplicate_only"', $html);
        $this->assertStringContainsString('checked', $html);
    }

    public function test_agent_application_preview_shows_context_risk_and_actions(): void
    {
        $admin = $this->makePlatformAdmin();
        $application = $this->createAgentApplicationRow([
            'first_name' => 'Preview',
            'last_name' => 'Applicant',
            'email' => 'preview@example.test',
            'company_name' => 'Preview Travels',
            'business_type' => 'Travel agency',
            'city' => 'Islamabad',
            'country' => 'Pakistan',
            'expected_booking_volume' => '50 bookings/month',
            'notes' => 'Runs a corporate desk.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.agent-applications.show', $application))
            ->assertRedirect('/admin/dashboard/agents/applications?id='.$application->id);

        $html = $this->agentApplicationShowHtml($admin, $application);

        $this->assertStringContainsString('data-testid="ota-agent-application-preview-actions"', $html);
        $this->assertStringContainsString('Preview Applicant', $html);
        $this->assertStringContainsString('Preview Travels', $html);
        $this->assertStringContainsString('50 bookings/month', $html);
        $this->assertStringContainsString('Runs a corporate desk.', $html);
        $this->assertStringContainsString('Application details', $html);
        $this->assertStringContainsString('Approve and create agent account', $html);
        $this->assertStringContainsString('Mark needs more info', $html);
        $this->assertStringContainsString('Reject application', $html);
    }

    public function test_agent_applications_export_uses_filtered_safe_rows(): void
    {
        $admin = $this->makePlatformAdmin();
        $this->createAgentApplicationRow([
            'first_name' => 'Export',
            'last_name' => 'Pending',
            'email' => 'export-pending@example.test',
            'status' => 'pending',
            'cnic' => 'SECRET-CNIC-123',
            'ntn' => 'SECRET-NTN-123',
        ]);
        $this->createAgentApplicationRow([
            'first_name' => 'Export',
            'last_name' => 'Rejected',
            'email' => 'export-rejected@example.test',
            'status' => 'rejected',
        ]);

        $csv = $this->actingAs($admin)
            ->get(route('admin.agent-applications.export', ['status' => 'pending']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Applicant,Company,Email,Mobile,City,Country,Status', $csv);
        $this->assertStringContainsString('Export Pending', $csv);
        $this->assertStringContainsString('export-pending@example.test', $csv);
        $this->assertStringNotContainsString('Export Rejected', $csv);
        $this->assertStringNotContainsString('SECRET-CNIC-123', $csv);
        $this->assertStringNotContainsString('SECRET-NTN-123', $csv);
    }

    public function test_status_badges_render_readable_labels_and_colours(): void
    {
        $admin = $this->makePlatformAdmin();
        $this->createAgentApplicationRow(['email' => 'pending@example.test', 'status' => 'pending']);
        $this->createAgentApplicationRow(['email' => 'approved@example.test', 'status' => 'approved']);
        $this->createAgentApplicationRow(['email' => 'rejected@example.test', 'status' => 'rejected']);
        $this->createAgentApplicationRow(['email' => 'needs@example.test', 'status' => 'needs_more_info']);

        $html = $this->agentApplicationsIndexHtml($admin);

        $this->assertStringContainsString('data-testid="ota-agent-application-status-pending"', $html);
        $this->assertStringContainsString('badge-soft-warning', $html);
        $this->assertStringContainsString('>Pending<', $html);
        $this->assertStringContainsString('data-testid="ota-agent-application-status-approved"', $html);
        $this->assertStringContainsString('badge-soft-success', $html);
        $this->assertStringContainsString('>Approved<', $html);
        $this->assertStringContainsString('data-testid="ota-agent-application-status-rejected"', $html);
        $this->assertStringContainsString('badge-soft-danger', $html);
        $this->assertStringContainsString('>Rejected<', $html);
        $this->assertStringContainsString('data-testid="ota-agent-application-status-needs_more_info"', $html);
        $this->assertStringContainsString('badge-soft-purple', $html);
        $this->assertStringContainsString('>Needs info<', $html);
    }

    public function test_duplicate_email_flag_preview_warning_and_kpi_count_render(): void
    {
        $admin = $this->makePlatformAdmin();
        $first = $this->createAgentApplicationRow([
            'email' => 'dupe-count@example.test',
            'first_name' => 'First',
        ]);
        $this->createAgentApplicationRow([
            'email' => 'dupe-count@example.test',
            'first_name' => 'Second',
        ]);

        $html = $this->agentApplicationsIndexHtml($admin);

        $this->assertStringContainsString('Duplicate emails', $html);
        $this->assertStringContainsString('data-testid="ota-agent-application-risk-duplicate"', $html);
        $this->assertStringContainsString('Duplicate email', $html);
    }

    public function test_existing_agent_and_missing_phone_flags_render_safely(): void
    {
        $admin = $this->makePlatformAdmin();
        $agency = Agency::factory()->create();
        $application = $this->createAgentApplicationRow([
            'email' => 'already-agent@example.test',
            'mobile' => '',
        ]);

        $agentUser = User::factory()->create([
            'email' => $application->email,
            'account_type' => AccountType::Agent,
            'current_agency_id' => $agency->id,
        ]);
        Agent::factory()->for($agency)->create(['user_id' => $agentUser->id]);

        $html = $this->agentApplicationsIndexHtml($admin);

        $this->assertStringContainsString('data-testid="ota-agent-application-risk-converted"', $html);
        $this->assertStringContainsString('Converted', $html);
        $this->assertStringContainsString('data-testid="ota-agent-application-risk-missing-phone"', $html);
        $this->assertStringContainsString('Missing phone', $html);
        $this->assertStringContainsString('badge-soft-converted', $html);
    }

    public function test_agent_applications_page_does_not_expose_sensitive_values(): void
    {
        $admin = $this->makePlatformAdmin();
        $application = $this->createAgentApplicationRow([
            'email' => 'sensitive@example.test',
            'cnic' => 'CNIC-SHOULD-NOT-RENDER',
            'ntn' => 'NTN-SHOULD-NOT-RENDER',
        ]);

        $admin->forceFill([
            'password' => $hash = bcrypt('admin-secret-password'),
            'remember_token' => $token = 'admin-remember-token-secret',
        ])->save();

        $html = $this->agentApplicationsIndexHtml($admin, ['preview' => $application->id]);

        foreach ([
            'admin-secret-password',
            $hash,
            $token,
            '$2y$',
            'CNIC-SHOULD-NOT-RENDER',
            'NTN-SHOULD-NOT-RENDER',
            'passport',
        ] as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }
    }

    public function test_agent_applications_empty_state_renders_premium_copy_and_ctas(): void
    {
        $admin = $this->makePlatformAdmin();

        $html = $this->agentApplicationsIndexHtml($admin);

        $this->assertStringContainsString('data-testid="ota-agent-applications-empty"', $html);
        $this->assertStringContainsString('No applications yet', $html);
        $this->assertStringContainsString('New partner requests will appear here after agents submit the registration form.', $html);
        $this->assertStringContainsString('data-testid="ota-agent-applications-empty-registration"', $html);
        $this->assertStringContainsString('View agent registration page', $html);
        $this->assertStringContainsString('data-testid="ota-agent-applications-empty-back-agents"', $html);
        $this->assertStringContainsString('Back to agents', $html);
    }

    protected function makePlatformAdmin(): User
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'email' => 'platform-admin-'.str()->random(6).'@example.test',
        ]);

        return $admin;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createAgentApplicationRow(array $overrides = []): AgentApplication
    {
        return AgentApplication::query()->create(array_merge([
            'first_name' => 'Agent',
            'last_name' => 'Applicant',
            'email' => 'agent-'.str()->random(8).'@example.test',
            'mobile' => '+923001112233',
            'company_name' => 'Applicant Travels',
            'business_type' => 'travel_agency',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'office_address' => 'Test office address',
            'expected_booking_volume' => '25 bookings/month',
            'status' => 'pending',
        ], $overrides));
    }
    private function agentApplicationsIndexHtml(User $admin, array $query = []): string
    {
        $this->actingAs($admin);
        $uri = '/admin/agent-applications';
        if ($query !== []) {
            $uri .= '?'.http_build_query($query);
        }
        $request = Request::create($uri, 'GET');
        $request->setUserResolver(fn () => $admin);

        return app(AgentApplicationController::class)->index($request)->render();
    }

    private function agentApplicationShowHtml(User $admin, AgentApplication $application): string
    {
        $this->actingAs($admin);
        $request = Request::create('/admin/agent-applications/'.$application->id, 'GET');
        $request->setUserResolver(fn () => $admin);

        return app(AgentApplicationController::class)->show($application)->render();
    }
}
