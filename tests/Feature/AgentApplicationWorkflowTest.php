<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentApplication;
use App\Models\CommunicationLog;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentApplicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_approve_application_and_create_active_agency(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        [$admin] = $this->platformAdmin();
        $application = $this->createApplicationRow([
            'company_name' => 'New Partner Travels',
            'email' => 'owner@newpartner.test',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.agent-applications.approve', $application), [
                'internal_note' => 'Approved in QA sprint',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'application-approved');

        $application->refresh();
        $this->assertSame('approved', $application->status);

        $owner = User::query()->where('email', 'owner@newpartner.test')->firstOrFail();
        $this->assertSame(AccountType::Agent, $owner->account_type);

        $agent = Agent::query()->where('user_id', $owner->id)->firstOrFail();
        $this->assertTrue($agent->is_active);

        $agency = Agency::query()->findOrFail($agent->agency_id);
        $this->assertSame('New Partner Travels', $agency->name);
        $this->assertSame($agency->id, $owner->current_agency_id);

        $this->actingAs($admin)
            ->get(route('admin.agencies.index', ['status' => 'active']))
            ->assertOk()
            ->assertSee('New Partner Travels', false);
    }

    public function test_approval_rejection_and_needs_info_send_communication_logs(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin] = $this->platformAdmin();

        $approveApp = $this->createApplicationRow(['email' => 'approve-mail@test']);
        $this->actingAs($admin)->patch(route('admin.agent-applications.approve', $approveApp))->assertRedirect();
        $this->assertTrue(
            CommunicationLog::query()->where('event', 'agent_application_approved')->exists()
        );

        $needsApp = $this->createApplicationRow(['email' => 'needs-mail@test']);
        $this->actingAs($admin)->patch(route('admin.agent-applications.needs-more-info', $needsApp), [
            'internal_note' => 'Please upload NTN certificate.',
        ])->assertRedirect();
        $this->assertTrue(
            CommunicationLog::query()->where('event', 'agent_application_needs_more_info')->exists()
        );

        $rejectApp = $this->createApplicationRow(['email' => 'reject-mail@test']);
        $this->actingAs($admin)->patch(route('admin.agent-applications.reject', $rejectApp), [
            'internal_note' => 'Incomplete documentation.',
        ])->assertRedirect();
        $this->assertTrue(
            CommunicationLog::query()->where('event', 'agent_application_rejected')->exists()
        );
    }

    public function test_review_page_renders_action_forms_and_flash_messages(): void
    {
        [$admin] = $this->platformAdmin();
        $application = $this->createApplicationRow();

        $this->actingAs($admin)
            ->get(route('admin.agent-applications.show', $application))
            ->assertOk()
            ->assertSee('data-testid="ota-agent-application-preview-actions"', false)
            ->assertSee(route('admin.agent-applications.approve', $application), false)
            ->assertSee(route('admin.agent-applications.needs-more-info', $application), false)
            ->assertSee(route('admin.agent-applications.reject', $application), false)
            ->assertSee('Approve and create agent account', false);
    }

    public function test_staff_access_wording_uses_default_staff_access_active(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        [$admin] = $this->platformAdmin();
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.users.show', $staff))
            ->assertOk()
            ->assertSee('Default staff access active', false)
            ->assertDontSee('Legacy full access', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createApplicationRow(array $overrides = []): AgentApplication
    {
        return AgentApplication::query()->create(array_merge([
            'first_name' => 'Applicant',
            'last_name' => 'Owner',
            'email' => 'applicant-'.str()->random(6).'@example.test',
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

    /**
     * @return array{0: User}
     */
    protected function platformAdmin(): array
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        if ($admin->account_type !== AccountType::PlatformAdmin) {
            $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();
            $admin = $admin->fresh();
        }

        return [$admin];
    }
}
