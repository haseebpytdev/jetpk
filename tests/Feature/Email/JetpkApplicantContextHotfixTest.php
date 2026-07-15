<?php

namespace Tests\Feature\Email;

use App\Enums\AccountType;
use App\Enums\OtaNotificationEvent;
use App\Models\Agency;
use App\Models\AgentApplication;
use App\Models\CommunicationLog;
use App\Models\User;
use App\Services\Communication\NotificationRecipientResolver;
use App\Support\Emails\JetpkEmailBrandingLeakageAuditor;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class JetpkApplicantContextHotfixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['jetpk_operational_email.client_slug' => 'jetpk']);
        config(['jetpk_email.client_slug' => 'jetpk']);
    }

    public function test_applicant_bucket_resolves_intended_applicant_email(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->firstOrFail();
        $resolver = app(NotificationRecipientResolver::class);

        $resolved = $resolver->resolveBucket(
            $agency,
            'applicant',
            null,
            null,
            ['applicant_email' => 'applicant-only@example.test'],
        );

        $this->assertFalse($resolved['skipped']);
        $this->assertSame(['applicant-only@example.test'], $resolved['emails']);
    }

    public function test_admin_bucket_does_not_replace_applicant_recipient(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->firstOrFail();
        $resolver = app(NotificationRecipientResolver::class);

        $applicant = $resolver->resolveBucket(
            $agency,
            'applicant',
            null,
            null,
            ['applicant_email' => 'applicant-only@example.test'],
        );
        $admin = $resolver->resolveBucket($agency, 'admin', null, null, [
            'applicant_email' => 'applicant-only@example.test',
        ]);

        $this->assertSame(['applicant-only@example.test'], $applicant['emails']);
        $this->assertNotContains('applicant-only@example.test', $admin['emails']);
        $this->assertContains('admin@ota.demo', $admin['emails']);
    }

    public function test_agent_application_submitted_sends_separate_applicant_and_admin_emails(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->seed(OtaFoundationSeeder::class);

        $this->post(route('agent.register.store'), [
            'first_name' => 'Hotfix',
            'last_name' => 'Applicant',
            'email' => 'hotfix-applicant@example.test',
            'mobile' => '+923001112233',
            'company_name' => 'Hotfix Travels',
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

        $applicantLog = $logs->first(fn ($log) => $log->recipient_email === 'hotfix-applicant@example.test');
        $adminLog = $logs->first(fn ($log) => $log->recipient_email === 'admin@ota.demo');
        $this->assertNotNull($applicantLog);
        $this->assertNotNull($adminLog);
        $this->assertSame('applicant', data_get($applicantLog->meta, 'delivery_bucket'));
        $this->assertSame('admin', data_get($adminLog->meta, 'delivery_bucket'));
        $this->assertStringContainsString('We received your agent application', (string) $applicantLog->subject);
        $this->assertStringContainsString('New agent application', (string) $adminLog->subject);
    }

    public function test_unrelated_agent_does_not_receive_applicant_email(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->seed(OtaFoundationSeeder::class);

        $this->post(route('agent.register.store'), [
            'first_name' => 'Scoped',
            'last_name' => 'Applicant',
            'email' => 'scoped-applicant@example.test',
            'mobile' => '+923001112233',
            'company_name' => 'Scoped Travels',
            'business_type' => 'travel_agency',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'office_address' => 'Office 2',
            'expected_booking_volume' => '5 bookings/month',
            'terms' => '1',
        ])->assertRedirect();

        foreach (CommunicationLog::query()
            ->where('event', OtaNotificationEvent::AgentApplicationSubmitted->value)
            ->pluck('recipient_email') as $recipient) {
            $this->assertNotSame('agent@ota.demo', strtolower((string) $recipient));
            $this->assertNotSame('agent.sana@ota.demo', strtolower((string) $recipient));
        }
    }

    public function test_active_code_has_zero_applican_email_typo(): void
    {
        $auditor = new JetpkEmailBrandingLeakageAuditor;
        $this->assertSame([], $auditor->scanMisspelledApplicantEmailKey());
    }

    public function test_denylist_config_entries_do_not_trigger_template_scan_failure(): void
    {
        $auditor = new JetpkEmailBrandingLeakageAuditor;

        $this->assertNotSame([], $auditor->denylistConfigFragments());

        foreach ($auditor->scanActiveBladeTemplates() as $hit) {
            $this->assertStringNotContainsString('config/', $hit['file']);
        }
    }

    public function test_rendered_forbidden_branding_triggers_leakage_failure(): void
    {
        config(['jetpk_operational_email.forbidden_brand_fragments' => ['Parwaaz']]);

        $auditor = new JetpkEmailBrandingLeakageAuditor;

        $hits = $auditor->scanRenderedContent(
            '<html><body>Welcome to Parwaaz Travels</body></html>',
            'test_html',
        );

        $this->assertNotSame([], $hits);
        $this->assertSame('Parwaaz', $hits[0]['fragment']);
    }

    public function test_clean_jetpakistan_preview_passes_leakage_scan(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $this->artisan('jetpk:email-preview', [
            '--event' => OtaNotificationEvent::AgentApplicationSubmitted->value,
            '--role' => 'applicant',
        ])->assertExitCode(0);

        Mail::assertNothingSent();

        $html = (string) file_get_contents(
            storage_path('app/email-previews/jetpk/agent_application_submitted_applicant.html'),
        );
        $auditor = new JetpkEmailBrandingLeakageAuditor;
        $this->assertSame([], $auditor->scanRenderedContent($html, 'preview_html'));
    }

    public function test_agent_application_approved_uses_applicant_email_context(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$admin] = $this->platformAdmin();

        $application = AgentApplication::query()->create([
            'first_name' => 'Ctx',
            'last_name' => 'Applicant',
            'email' => 'ctx-applicant@example.test',
            'mobile' => '+923001112233',
            'company_name' => 'Ctx Travels',
            'business_type' => 'travel_agency',
            'city' => 'Islamabad',
            'country' => 'Pakistan',
            'office_address' => 'Office',
            'expected_booking_volume' => '10',
            'status' => 'pending',
        ]);

        Mail::fake();

        $this->actingAs($admin)
            ->patch(route('admin.agent-applications.approve', $application))
            ->assertRedirect();

        $applicantSent = CommunicationLog::query()
            ->where('event', OtaNotificationEvent::AgentApplicationApproved->value)
            ->where('recipient_email', 'ctx-applicant@example.test')
            ->exists();

        $this->assertTrue($applicantSent);
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
