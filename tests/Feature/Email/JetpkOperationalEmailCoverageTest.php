<?php

namespace Tests\Feature\Email;

use App\Enums\AccountType;
use App\Enums\OtaNotificationEvent;
use App\Enums\UserAccountStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentApplication;
use App\Models\CommunicationLog;
use App\Models\User;
use App\Services\Email\JetpkOperationalEmailService;
use App\Support\Emails\JetpkOperationalEmailEventRegistry;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class JetpkOperationalEmailCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['jetpk_operational_email.client_slug' => 'jetpk']);
        config(['jetpk_email.client_slug' => 'jetpk']);
    }

    public function test_agent_registration_sends_separate_applicant_and_admin_emails(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->seed(OtaFoundationSeeder::class);

        $this->post(route('agent.register.store'), [
            'first_name' => 'Apply',
            'last_name' => 'Agent',
            'email' => 'new-agent@example.test',
            'mobile' => '+923001112233',
            'company_name' => 'Apply Travels',
            'business_type' => 'travel_agency',
            'city' => 'Karachi',
            'country' => 'Pakistan',
            'office_address' => 'Office 1',
            'expected_booking_volume' => '10 bookings/month',
            'terms' => '1',
        ])->assertRedirect();

        Mail::assertSentCount(2);

        $logs = CommunicationLog::query()
            ->where('event', OtaNotificationEvent::AgentApplicationSubmitted->value)
            ->get();

        $this->assertCount(2, $logs);
        $recipients = $logs->pluck('recipient_email')->map(fn ($e) => strtolower(trim((string) $e)))->all();
        $this->assertContains('new-agent@example.test', $recipients);
        $this->assertContains('admin@ota.demo', $recipients);
    }

    public function test_agent_application_approval_notifies_applicant_with_welcome_copy(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin] = $this->platformAdmin();

        $application = AgentApplication::query()->create([
            'first_name' => 'Approved',
            'last_name' => 'Agent',
            'email' => 'approved-agent@example.test',
            'mobile' => '+923001112233',
            'company_name' => 'Approved Travels',
            'business_type' => 'travel_agency',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'office_address' => 'Office',
            'expected_booking_volume' => '20',
            'status' => 'pending',
        ]);

        Mail::fake();

        $this->actingAs($admin)
            ->patch(route('admin.agent-applications.approve', $application))
            ->assertRedirect();

        $this->assertTrue(
            CommunicationLog::query()->where('event', OtaNotificationEvent::AgentApplicationApproved->value)->exists()
        );
        Mail::assertSentCount(2);
    }

    public function test_user_suspension_sends_user_and_admin_notifications(): void
    {
        [$admin] = $this->platformAdmin();
        $agency = Agency::query()->firstOrFail();
        $staff = User::factory()->create([
            'account_type' => AccountType::Staff,
            'status' => UserAccountStatus::Active,
            'current_agency_id' => $agency->id,
            'email' => 'staff-suspend@example.test',
        ]);
        $staff->agencies()->syncWithoutDetaching([$agency->id => ['role' => AccountType::Staff->value]]);

        Mail::fake();

        $this->actingAs($admin)
            ->patch(route('admin.users.suspend', $staff))
            ->assertRedirect();

        $this->assertTrue(
            CommunicationLog::query()->where('event', OtaNotificationEvent::UserSuspended->value)->exists()
        );
        Mail::assertSentCount(2);
    }

    public function test_staff_creation_includes_designation_in_template_variables(): void
    {
        [$admin] = $this->platformAdmin();
        $agency = Agency::query()->firstOrFail();
        $agent = Agent::query()->where('agency_id', $agency->id)->firstOrFail();

        Mail::fake();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Reservation Staff Member',
                'email' => 'res-staff@example.test',
                'account_type' => AccountType::AgentStaff->value,
                'status' => UserAccountStatus::Active->value,
                'agency_id' => $agency->id,
                'owner_agent_id' => $agent->id,
                'permissions' => ['bookings.view'],
                'send_invite' => false,
            ])
            ->assertRedirect();

        $this->assertTrue(
            CommunicationLog::query()->where('event', OtaNotificationEvent::StaffCreated->value)->exists()
        );
    }

    public function test_unknown_event_key_fails_safely_in_registry(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        JetpkOperationalEmailEventRegistry::assertKnownEvent('not_a_real_event_key');
    }

    public function test_coverage_audit_command_exits_zero(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $this->artisan('jetpk:email-coverage-audit', ['--write-matrix' => true])
            ->assertExitCode(0);
    }

    public function test_preview_command_renders_without_sending(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Mail::fake();

        $this->artisan('jetpk:email-preview', [
            '--event' => OtaNotificationEvent::AgentApplicationSubmitted->value,
            '--role' => 'applicant',
        ])->assertExitCode(0);

        Mail::assertNothingSent();

        $path = storage_path('app/email-previews/jetpk/agent_application_submitted_applicant.html');
        $this->assertFileExists($path);
        $this->assertStringNotContainsString('Parwaaz', (string) file_get_contents($path));
    }

    public function test_operational_email_service_renders_optional_fields_without_null_literals(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->firstOrFail();
        $renderer = app(JetpkOperationalEmailService::class);

        $rendered = $renderer->render(
            agency: $agency,
            eventKey: OtaNotificationEvent::AgentApplicationSubmitted->value,
            templateVariables: [
                'applicant_name' => 'Test Applicant',
                'company_name' => 'Test Co',
                'city' => 'Islamabad',
            ],
            deliveryVariant: 'applicant',
            recipientRole: 'applicant',
        );

        $this->assertStringNotContainsString('null', strtolower($rendered['html']));
        $this->assertStringContainsString('Application received', $rendered['html']);
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
