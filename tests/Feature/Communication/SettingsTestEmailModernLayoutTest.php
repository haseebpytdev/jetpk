<?php

namespace Tests\Feature\Communication;

use App\Mail\BookingRequestReceivedMail;
use App\Mail\CommunicationSettingsTestMail;
use App\Mail\PaymentVerifiedMail;
use App\Mail\TicketIssuedMail;
use App\Models\Agency;
use App\Models\AgencyMessageTemplate;
use App\Models\CommunicationLog;
use App\Services\Communication\AgencyCommunicationSettingsService;
use App\Support\Branding\CompanyEmailProfileResolver;
use App\Support\Emails\SettingsTestEmailRenderer;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class SettingsTestEmailModernLayoutTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    #[Test]
    public function test_email_renders_modern_layout_html_and_company_branding(): void
    {
        Mail::fake();
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();
        $profile = CompanyEmailProfileResolver::resolve($agency);
        $admin = $this->platformAdmin();

        app(AgencyCommunicationSettingsService::class)->testEmailSettings($agency, $admin, 'ops@example.test');

        Mail::assertSent(CommunicationSettingsTestMail::class, 1);
        Mail::assertSent(CommunicationSettingsTestMail::class, function (CommunicationSettingsTestMail $mail) use ($profile): bool {
            $this->assertStringContainsString('<!DOCTYPE html>', $mail->htmlBody);
            $this->assertStringContainsString('Communication settings test', $mail->htmlBody);
            $this->assertStringContainsString(e($profile->name), $mail->htmlBody);
            $this->assertStringContainsString('test email', strtolower($mail->htmlBody));
            if ($profile->support_email) {
                $this->assertStringContainsString(e($profile->support_email), $mail->htmlBody);
            }
            $this->assertStringContainsString('Test email from '.$profile->name, $mail->emailSubject);

            return true;
        });
        Mail::assertNotSent(BookingRequestReceivedMail::class);
        Mail::assertNotSent(PaymentVerifiedMail::class);
        Mail::assertNotSent(TicketIssuedMail::class);
    }

    #[Test]
    public function test_email_sends_to_admin_entered_recipient_without_smtp_secrets_in_body(): void
    {
        Mail::fake();
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();
        $admin = $this->platformAdmin();

        $settings = app(AgencyCommunicationSettingsService::class)->getOrCreateSettings($agency);
        $settings->forceFill([
            'smtp_enabled' => true,
            'smtp_host' => 'smtp.secret-host.test',
            'smtp_username' => 'smtp-user@example.test',
            'smtp_password' => 'super-secret-smtp-password',
        ])->save();

        app(AgencyCommunicationSettingsService::class)->testEmailSettings($agency, $admin, 'delivered@example.test');

        Mail::assertSent(CommunicationSettingsTestMail::class, function (CommunicationSettingsTestMail $mail): bool {
            $body = strtolower($mail->htmlBody);
            $this->assertTrue($mail->hasTo('delivered@example.test'));
            $this->assertStringNotContainsString('super-secret-smtp-password', $mail->htmlBody);
            $this->assertStringNotContainsString('smtp.secret-host.test', $mail->htmlBody);
            $this->assertStringNotContainsString('smtp-user@example.test', $mail->htmlBody);
            $this->assertStringNotContainsString('smtp_password', $body);

            return true;
        });

        $this->assertDatabaseHas('communication_logs', [
            'agency_id' => $agency->id,
            'event' => SettingsTestEmailRenderer::EVENT,
            'recipient_email' => 'delivered@example.test',
            'status' => 'sent',
        ]);
    }

    #[Test]
    public function test_email_uses_safe_agency_template_when_present(): void
    {
        Mail::fake();
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();
        $admin = $this->platformAdmin();

        AgencyMessageTemplate::query()->create([
            'agency_id' => $agency->id,
            'event' => SettingsTestEmailRenderer::EVENT,
            'channel' => 'email',
            'subject' => 'Custom test from {{ company_name }}',
            'body' => "Custom body for {{ company_name }}.\n<script>alert(1)</script>",
            'is_enabled' => true,
        ]);

        app(AgencyCommunicationSettingsService::class)->testEmailSettings($agency, $admin, 'ops@example.test');

        Mail::assertSent(CommunicationSettingsTestMail::class, function (CommunicationSettingsTestMail $mail) use ($agency): bool {
            $profile = CompanyEmailProfileResolver::resolve($agency);
            $this->assertSame('Custom test from '.$profile->name, $mail->emailSubject);
            $this->assertStringContainsString('Custom body for '.e($profile->name), $mail->htmlBody);
            $this->assertStringNotContainsString('<script>', $mail->htmlBody);

            return true;
        });

        $log = CommunicationLog::query()
            ->where('event', SettingsTestEmailRenderer::EVENT)
            ->latest('id')
            ->first();
        $this->assertTrue((bool) data_get($log->meta, 'used_db_template'));
    }
}
