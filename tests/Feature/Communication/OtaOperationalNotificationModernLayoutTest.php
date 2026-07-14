<?php

namespace Tests\Feature\Communication;

use App\Enums\OtaNotificationEvent;
use App\Mail\BookingRequestReceivedMail;
use App\Mail\OtaOperationalNotificationMail;
use App\Mail\PaymentVerifiedMail;
use App\Mail\TicketIssuedMail;
use App\Models\Agency;
use App\Models\AgencyCommunicationSetting;
use App\Models\AgencyMessageTemplate;
use App\Models\AgencyNotificationSetting;
use App\Models\Booking;
use App\Models\CommunicationLog;
use App\Services\Communication\AdminReportMailerService;
use App\Services\Communication\OtaNotificationService;
use App\Support\Branding\CompanyEmailProfileResolver;
use App\Support\Emails\OperationalEmailDefaults;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class OtaOperationalNotificationModernLayoutTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    #[Test]
    public function operational_notification_sends_modern_layout_html(): void
    {
        Mail::fake();
        $this->seed(OtaFoundationSeeder::class);
        $agency = $this->asifAgency();
        $profile = CompanyEmailProfileResolver::resolve($agency);
        $this->enableOutbound($agency, OtaNotificationEvent::BookingManualReviewRequired->value);

        $booking = Booking::factory()->for($agency)->create();

        app(OtaNotificationService::class)->send(
            $agency,
            OtaNotificationEvent::BookingManualReviewRequired->value,
            ['note' => 'Review fare change'],
            $booking,
            fallbackSubject: 'Manual review required',
            fallbackBody: 'A booking requires manual review.',
            templateVariables: ['passenger_name' => 'Jane Doe'],
        );

        Mail::assertSent(OtaOperationalNotificationMail::class, 1);
        Mail::assertSent(OtaOperationalNotificationMail::class, function (OtaOperationalNotificationMail $mail) use ($profile): bool {
            $this->assertStringContainsString('<!DOCTYPE html>', $mail->htmlBody);
            $this->assertStringContainsString(e($profile->name), $mail->htmlBody);
            $this->assertStringContainsString('manual review', strtolower($mail->htmlBody));
            $this->assertStringContainsString('A booking requires manual review.', $mail->plainBody);

            return true;
        });
        Mail::assertNotSent(BookingRequestReceivedMail::class);
        Mail::assertNotSent(PaymentVerifiedMail::class);
        Mail::assertNotSent(TicketIssuedMail::class);
    }

    #[Test]
    public function recipient_resolution_unchanged_for_explicit_recipients(): void
    {
        Mail::fake();
        $this->seed(OtaFoundationSeeder::class);
        $agency = $this->asifAgency();
        $this->enableOutbound($agency, OtaNotificationEvent::AgentDepositSubmitted->value, [
            'recipient_emails' => ['finance.ops@example.test'],
        ]);

        app(OtaNotificationService::class)->send(
            $agency,
            OtaNotificationEvent::AgentDepositSubmitted->value,
            [],
            fallbackBody: 'Deposit submitted.',
            templateVariables: ['agent_name' => 'Agent Co', 'amount' => '5000', 'currency' => 'PKR'],
        );

        Mail::assertSent(OtaOperationalNotificationMail::class, function (OtaOperationalNotificationMail $mail): bool {
            $this->assertTrue($mail->hasTo('finance.ops@example.test'));

            return true;
        });
    }

    #[Test]
    public function cc_and_bcc_are_applied_when_configured(): void
    {
        Mail::fake();
        $this->seed(OtaFoundationSeeder::class);
        $agency = $this->asifAgency();
        $this->enableOutbound($agency, OtaNotificationEvent::SupportTicketCreated->value, [
            'recipient_emails' => ['support.primary@example.test'],
            'cc_emails' => ['support.cc@example.test'],
            'bcc_emails' => ['audit.bcc@example.test'],
        ]);

        app(OtaNotificationService::class)->send(
            $agency,
            OtaNotificationEvent::SupportTicketCreated->value,
            [],
            fallbackBody: 'New support ticket opened.',
        );

        Mail::assertSent(OtaOperationalNotificationMail::class, function (OtaOperationalNotificationMail $mail): bool {
            $this->assertTrue($mail->hasTo('support.primary@example.test'));
            $this->assertTrue($mail->hasCc('support.cc@example.test'));
            $this->assertTrue($mail->hasBcc('audit.bcc@example.test'));

            return true;
        });
    }

    #[Test]
    public function monthly_finance_ledger_preserves_csv_attachments(): void
    {
        Mail::fake();
        $this->seed(OtaFoundationSeeder::class);
        $agency = $this->asifAgency();
        $this->enableOutbound($agency, OtaNotificationEvent::MonthlyFinanceLedger->value, [
            'recipient_emails' => ['finance@example.test'],
        ]);

        app(AdminReportMailerService::class)->sendMonthlyLedgers($agency);

        Mail::assertSent(OtaOperationalNotificationMail::class, 1);
        Mail::assertSent(OtaOperationalNotificationMail::class, function (OtaOperationalNotificationMail $mail): bool {
            $this->assertCount(2, $mail->attachments());
            $names = array_map(fn ($attachment) => $attachment->as, $mail->attachments());
            $this->assertTrue(
                collect($names)->contains(fn (string $name): bool => str_contains($name, 'ledger-bookings-') && str_ends_with($name, '.csv'))
            );
            $this->assertTrue(
                collect($names)->contains(fn (string $name): bool => str_contains($name, 'ledger-payments-') && str_ends_with($name, '.csv'))
            );

            return true;
        });
    }

    #[Test]
    public function agency_message_template_subject_and_body_are_used_when_present(): void
    {
        Mail::fake();
        $this->seed(OtaFoundationSeeder::class);
        $agency = $this->asifAgency();
        $profile = CompanyEmailProfileResolver::resolve($agency);
        $this->enableOutbound($agency, OtaNotificationEvent::StaffCreated->value);

        AgencyMessageTemplate::query()->create([
            'agency_id' => $agency->id,
            'event' => OtaNotificationEvent::StaffCreated->value,
            'channel' => 'email',
            'subject' => 'New staff at {{ agency_name }}',
            'body' => "Welcome ops alert for {{ agency_name }}.\n<script>alert('xss')</script>",
            'is_enabled' => true,
        ]);

        app(OtaNotificationService::class)->send(
            $agency,
            OtaNotificationEvent::StaffCreated->value,
            [],
            fallbackSubject: 'Fallback subject',
            fallbackBody: 'Fallback body',
        );

        Mail::assertSent(OtaOperationalNotificationMail::class, function (OtaOperationalNotificationMail $mail) use ($profile): bool {
            $this->assertSame('New staff at '.$profile->name, $mail->emailSubject);
            $this->assertStringContainsString('Welcome ops alert for '.e($profile->name), $mail->htmlBody);
            $this->assertStringNotContainsString('<script>', $mail->htmlBody);
            $this->assertStringNotContainsString('Fallback body', $mail->plainBody);

            return true;
        });

        $log = CommunicationLog::query()
            ->where('event', OtaNotificationEvent::StaffCreated->value)
            ->latest('id')
            ->first();
        $this->assertTrue((bool) data_get($log->meta, 'used_db_template'));
        $this->assertTrue((bool) data_get($log->meta, 'modern_layout'));
    }

    #[Test]
    public function operational_notification_mail_sends_without_treating_plain_body_as_view(): void
    {
        config(['mail.default' => 'array']);

        $plainBody = 'A privileged user login was detected for user@example.test.';
        $htmlBody = '<!DOCTYPE html><html><body><p>Security notice</p></body></html>';

        $mailable = new OtaOperationalNotificationMail(
            $htmlBody,
            'Security login notice',
            $plainBody,
        );

        Mail::to('admin@ota.demo')->send($mailable);

        $this->assertStringContainsString('Security notice', $mailable->render());
    }

    #[Test]
    public function safe_default_subject_and_body_used_when_template_missing(): void
    {
        Mail::fake();
        $this->seed(OtaFoundationSeeder::class);
        $agency = $this->asifAgency();
        $this->enableOutbound($agency, OtaNotificationEvent::AdminLoginSuccess->value);

        app(OtaNotificationService::class)->send(
            $agency,
            OtaNotificationEvent::AdminLoginSuccess->value,
            [],
            fallbackSubject: 'Admin login alert',
            fallbackBody: 'An admin signed in successfully.',
        );

        Mail::assertSent(OtaOperationalNotificationMail::class, function (OtaOperationalNotificationMail $mail): bool {
            $this->assertSame('Admin login alert', $mail->emailSubject);
            $this->assertStringContainsString('An admin signed in successfully.', $mail->plainBody);

            return true;
        });
    }

    #[Test]
    public function business_operational_db_template_subject_never_leaks_brand_name_placeholder(): void
    {
        Mail::fake();
        $this->seed(OtaFoundationSeeder::class);
        $agency = $this->asifAgency();
        $profile = CompanyEmailProfileResolver::resolve($agency);
        $this->enableOutbound($agency, OtaNotificationEvent::BookingRequestReceived->value);

        $defaults = OperationalEmailDefaults::forEvent(OtaNotificationEvent::BookingRequestReceived->value);
        $this->assertNotNull($defaults);

        AgencyMessageTemplate::query()->create([
            'agency_id' => $agency->id,
            'event' => OtaNotificationEvent::BookingRequestReceived->value,
            'channel' => 'email',
            'subject' => $defaults['subject'],
            'body' => $defaults['body'],
            'is_enabled' => true,
        ]);

        $booking = Booking::factory()->for($agency)->create();

        app(OtaNotificationService::class)->send(
            $agency,
            OtaNotificationEvent::BookingRequestReceived->value,
            [],
            $booking,
            fallbackSubject: 'Fallback',
            fallbackBody: 'Fallback body',
            templateVariables: [
                'booking_reference' => $booking->reference_code,
                'customer_name' => 'Jane Doe',
                'route' => 'LHE — DXB',
            ],
        );

        Mail::assertSent(OtaOperationalNotificationMail::class, function (OtaOperationalNotificationMail $mail) use ($profile, $booking): bool {
            $this->assertStringNotContainsString('{{', $mail->emailSubject);
            $this->assertStringNotContainsString('{{', $mail->plainBody);
            $this->assertStringContainsString($profile->name, $mail->emailSubject);
            $this->assertStringContainsString($booking->reference_code, $mail->emailSubject);

            return true;
        });
    }

    #[Test]
    public function placeholders_are_replaced_and_secrets_are_not_included(): void
    {
        Mail::fake();
        $this->seed(OtaFoundationSeeder::class);
        $agency = $this->asifAgency();
        $this->enableOutbound($agency, OtaNotificationEvent::SupplierBookingFailed->value);

        AgencyCommunicationSetting::query()->updateOrCreate(
            ['agency_id' => $agency->id],
            [
                'email_enabled' => true,
                'smtp_password' => 'smtp-secret-password-value',
            ],
        );

        app(OtaNotificationService::class)->send(
            $agency,
            OtaNotificationEvent::SupplierBookingFailed->value,
            ['supplier_error' => 'Bearer abc123token', 'api_key' => 'duffel_live_secret'],
            fallbackSubject: 'Supplier failure for {{ booking_reference }}',
            fallbackBody: 'Booking {{ booking_reference }} failed for {{ agency_name }}.',
            templateVariables: ['booking_reference' => 'OTA-BK-TEST-001'],
        );

        Mail::assertSent(OtaOperationalNotificationMail::class, function (OtaOperationalNotificationMail $mail): bool {
            $this->assertStringContainsString('OTA-BK-TEST-001', $mail->plainBody);
            $this->assertStringNotContainsString('smtp-secret-password-value', $mail->htmlBody);
            $this->assertStringNotContainsString('duffel_live_secret', $mail->htmlBody);
            $this->assertStringNotContainsString('Bearer abc123token', $mail->htmlBody);

            return true;
        });
    }

    protected function asifAgency(): Agency
    {
        return Agency::query()->where('slug', 'asif-travels')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function enableOutbound(Agency $agency, string $eventKey, array $overrides = []): void
    {
        AgencyCommunicationSetting::query()->updateOrCreate(
            ['agency_id' => $agency->id],
            ['email_enabled' => true],
        );
        AgencyNotificationSetting::query()->updateOrCreate(
            [
                'agency_id' => $agency->id,
                'event_key' => $eventKey,
                'channel' => 'email',
            ],
            array_merge([
                'enabled' => true,
                'recipient_scope' => 'admin',
                'recipient_emails' => ['admin@ota.demo'],
            ], $overrides),
        );
    }
}
