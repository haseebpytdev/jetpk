<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $response = $this->actingAs($admin)
            ->get(route('admin.agent-applications.index', ['preview' => $application->id]))
            ->assertOk();

        $response->assertSee('data-testid="ota-agent-applications-page-header"', false);
        $response->assertSee('Agent applications', false);
        $response->assertSee('Review partner applications, approve qualified agents, and track onboarding status.', false);
        $response->assertSee('data-testid="ota-agent-applications-kpis"', false);
        $response->assertSee('data-testid="ota-agent-applications-filter-card"', false);
        $response->assertSee('data-testid="ota-agent-applications-table"', false);

        foreach ([
            'Applicant',
            'Company',
            'Contact',
            'Status',
            'Submitted',
            'Flags',
            'Action',
        ] as $header) {
            $response->assertSee('>'.$header.'<', false);
        }

        // Email lives under Contact, not as its own top-level table column.
        $html = $response->getContent();
        $theadStart = strpos($html, '<thead');
        $theadEnd = strpos($html, '</thead>', (int) $theadStart);
        $thead = substr($html, (int) $theadStart, ((int) $theadEnd) - ((int) $theadStart));
        $this->assertStringNotContainsString('>Email<', $thead);

        $response->assertSee('Furqan Applicant', false);
        $response->assertSee('furqan@example.test', false);
        $response->assertSee('Furqan Travels', false);
        $response->assertSee('Open review', false);
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

        $response = $this->actingAs($admin)
            ->get(route('admin.agent-applications.index'))
            ->assertOk();

        $response->assertSee('Total applications', false);
        $response->assertSee('Pending review', false);
        $response->assertSee('Approved', false);
        $response->assertSee('Rejected', false);
        $response->assertSee('Converted to agent', false);
        $response->assertSee('Duplicate emails', false);

        // Duplicate metric counts duplicate-looking application rows, not just unique emails.
        $response->assertSee('Duplicate email', false);
        $response->assertSee('Converted', false);
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

        $response = $this->actingAs($admin)
            ->get(route('admin.agent-applications.index', [
                'search' => 'Matching',
                'status' => 'pending',
                'city_country' => 'Lahore',
                'submitted_from' => now()->subDays(2)->toDateString(),
                'submitted_to' => now()->toDateString(),
                'duplicate_only' => 1,
                'preview' => $matching->id,
            ]))
            ->assertOk();

        $response->assertSee('Matching Applicant', false);
        $response->assertDontSee('Other Travel', false);
        $response->assertSee('filters applied', false);
        $response->assertSee('Duplicate email', false);
        $response->assertSee('name="duplicate_only"', false);
        $response->assertSee('checked', false);
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

        $response = $this->actingAs($admin)
            ->get(route('admin.agent-applications.show', $application))
            ->assertOk();

        $response->assertSee('data-testid="ota-agent-application-preview-actions"', false);
        $response->assertSee('Preview Applicant', false);
        $response->assertSee('Preview Travels', false);
        $response->assertSee('50 bookings/month', false);
        $response->assertSee('Runs a corporate desk.', false);
        $response->assertSee('Application details', false);
        $response->assertSee('Approve and create agent account', false);
        $response->assertSee('Mark needs more info', false);
        $response->assertSee('Reject application', false);
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

        $response = $this->actingAs($admin)
            ->get(route('admin.agent-applications.index'))
            ->assertOk();

        $response->assertSee('data-testid="ota-agent-application-status-pending"', false);
        $response->assertSee('badge-soft-warning', false);
        $response->assertSee('>Pending<', false);
        $response->assertSee('data-testid="ota-agent-application-status-approved"', false);
        $response->assertSee('badge-soft-success', false);
        $response->assertSee('>Approved<', false);
        $response->assertSee('data-testid="ota-agent-application-status-rejected"', false);
        $response->assertSee('badge-soft-danger', false);
        $response->assertSee('>Rejected<', false);
        $response->assertSee('data-testid="ota-agent-application-status-needs_more_info"', false);
        $response->assertSee('badge-soft-purple', false);
        $response->assertSee('>Needs info<', false);
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

        $response = $this->actingAs($admin)
            ->get(route('admin.agent-applications.index'))
            ->assertOk();

        $response->assertSee('Duplicate emails', false);
        $response->assertSee('data-testid="ota-agent-application-risk-duplicate"', false);
        $response->assertSee('Duplicate email', false);
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

        $response = $this->actingAs($admin)
            ->get(route('admin.agent-applications.index'))
            ->assertOk();

        $response->assertSee('data-testid="ota-agent-application-risk-converted"', false);
        $response->assertSee('Converted', false);
        $response->assertSee('data-testid="ota-agent-application-risk-missing-phone"', false);
        $response->assertSee('Missing phone', false);
        $response->assertSee('badge-soft-converted', false);
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

        $response = $this->actingAs($admin)
            ->get(route('admin.agent-applications.index', ['preview' => $application->id]))
            ->assertOk();

        foreach ([
            'admin-secret-password',
            $hash,
            $token,
            '$2y$',
            'CNIC-SHOULD-NOT-RENDER',
            'NTN-SHOULD-NOT-RENDER',
            'passport',
        ] as $secret) {
            $response->assertDontSee($secret, false);
        }
    }

    public function test_agent_applications_empty_state_renders_premium_copy_and_ctas(): void
    {
        $admin = $this->makePlatformAdmin();

        $response = $this->actingAs($admin)
            ->get(route('admin.agent-applications.index'))
            ->assertOk();

        $response->assertSee('data-testid="ota-agent-applications-empty"', false);
        $response->assertSee('No applications yet', false);
        $response->assertSee('New partner requests will appear here after agents submit the registration form.', false);
        $response->assertSee('data-testid="ota-agent-applications-empty-registration"', false);
        $response->assertSee('View agent registration page', false);
        $response->assertSee('data-testid="ota-agent-applications-empty-back-agents"', false);
        $response->assertSee('Back to agents', false);
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
}
