<?php

namespace Tests\Feature\Communication;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Mail\AbandonedFlightSearchMail;
use App\Mail\AdminNewCustomerSignupMail;
use App\Mail\BookingRequestReceivedMail;
use App\Mail\CustomerWelcomeMail;
use App\Mail\ManualBookingCommunicationMail;
use App\Mail\OtaOperationalNotificationMail;
use App\Mail\PaymentVerifiedMail;
use App\Models\Agency;
use App\Models\AgencyCommunicationSetting;
use App\Models\AgencyMessageTemplate;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\CommunicationLog;
use App\Models\User;
use App\Support\Branding\BrandDisplayResolver;
use App\Support\Branding\CompanyEmailProfileResolver;
use App\Support\Emails\EmailBodySanitizer;
use App\Support\Emails\EmailTemplateRegistry;
use App\Support\Emails\ManualBookingCommunicationEmailRenderer;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class I8EmailModernizationTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    #[Test]
    public function abandoned_flight_search_mail_renders_modern_layout_and_cta(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = $this->asifAgency();
        $profile = CompanyEmailProfileResolver::resolve($agency);

        $mail = new AbandonedFlightSearchMail(
            subjectLine: 'Still interested in your flight search?',
            brandName: $profile->name,
            supportEmail: 'support@example.test',
            supportPhone: '+92000000000',
            routeLabel: 'KHI → DXB',
            tripTypeLabel: 'One way',
            departDate: '2026-07-01',
            returnDate: null,
            passengerSummary: '1 adult · Economy',
            offers: [[
                'airline_name' => 'Emirates',
                'airline_code' => 'EK',
                'origin' => 'KHI',
                'destination' => 'DXB',
                'departure_at' => '01 Jul 2026 08:00',
                'arrival_at' => '01 Jul 2026 10:30',
                'duration' => '2h 30m',
                'stops_label' => 'Direct',
                'price_label' => 'PKR 45,000',
            ]],
            ctaUrl: 'https://example.test/flights/results?from=KHI&to=DXB',
            agency: $agency,
        );

        $this->assertStringContainsString('<!DOCTYPE html>', $mail->htmlBody);
        $this->assertStringContainsString(e($profile->name), $mail->htmlBody);
        $this->assertStringContainsString('Search again / View latest fares', $mail->htmlBody);
        $this->assertStringContainsString('https://example.test/flights/results?from=KHI&amp;to=DXB', $mail->htmlBody);
        $this->assertStringContainsString('Emirates', $mail->htmlBody);
        $this->assertStringContainsString('PKR 45,000', $mail->htmlBody);
    }

    #[Test]
    public function customer_registration_welcome_mail_preserves_recipient_and_safe_content(): void
    {
        Mail::fake();
        $this->seed(OtaFoundationSeeder::class);
        $agency = $this->asifAgency();
        $user = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
            'name' => 'Jane Customer',
            'email' => 'jane.customer@example.test',
        ]);

        Mail::to($user->email)->send(CustomerWelcomeMail::forUser($user));

        Mail::assertSent(CustomerWelcomeMail::class, function (CustomerWelcomeMail $mail) use ($user): bool {
            return $mail->hasTo($user->email)
                && str_contains($mail->htmlBody, '<!DOCTYPE html>')
                && str_contains($mail->htmlBody, 'Jane Customer')
                && str_contains($mail->htmlBody, 'verify your email')
                && str_contains($mail->envelope()->subject, BrandDisplayResolver::displayName())
                && ! str_contains($mail->htmlBody, 'password')
                && ! str_contains($mail->htmlBody, 'token');
        });
    }

    #[Test]
    public function admin_new_customer_signup_mail_preserves_admin_recipient_and_safe_content(): void
    {
        Mail::fake();
        $this->seed(OtaFoundationSeeder::class);
        $agency = $this->asifAgency();
        $user = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
            'name' => 'New Signup',
            'email' => 'new.signup@example.test',
        ]);
        $adminRecipient = 'ops.alert@example.test';

        Mail::to($adminRecipient)->send(new AdminNewCustomerSignupMail($user, '+923001234567'));

        Mail::assertSent(AdminNewCustomerSignupMail::class, function (AdminNewCustomerSignupMail $mail) use ($adminRecipient): bool {
            return $mail->hasTo($adminRecipient)
                && str_contains($mail->htmlBody, '<!DOCTYPE html>')
                && str_contains($mail->htmlBody, 'New customer signup')
                && str_contains($mail->htmlBody, 'New Signup')
                && str_contains($mail->htmlBody, 'new.signup@example.test')
                && str_contains($mail->htmlBody, '+923001234567')
                && ! str_contains($mail->htmlBody, 'SECRET-SABRE')
                && ! str_contains($mail->htmlBody, '$2y$')
                && ! str_contains($mail->htmlBody, 'reset_token=');
        });
    }

    #[Test]
    public function manual_booking_communication_preserves_subject_body_recipient_and_sanitizes_script_tags(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = $this->asifAgency();
        $subject = 'Payment reminder for booking ASIF-2026-009001';
        $body = "Dear customer,\n\n<script>alert('xss')</script>Please pay soon.\n\nThank you.";

        $wrapped = app(ManualBookingCommunicationEmailRenderer::class)->render(
            $agency,
            'payment_reminder_manual',
            $subject,
            $body,
        );

        $this->assertStringContainsString('<!DOCTYPE html>', $wrapped->html);
        $this->assertStringContainsString('Please pay soon.', $wrapped->html);
        $this->assertStringNotContainsString('<script>', $wrapped->html);
        $this->assertStringNotContainsString('alert(', $wrapped->html);
        $this->assertSame($subject, $wrapped->subject);
        $this->assertStringContainsString('Please pay soon.', $wrapped->plainBody);
        $this->assertStringNotContainsString('<script>', $wrapped->plainBody);

        Mail::fake();
        Mail::to('customer@example.test')->send(new ManualBookingCommunicationMail(
            $wrapped->html,
            $wrapped->subject,
            $wrapped->plainBody,
        ));

        Mail::assertSent(ManualBookingCommunicationMail::class, function (ManualBookingCommunicationMail $mail): bool {
            return $mail->hasTo('customer@example.test')
                && $mail->emailSubject === 'Payment reminder for booking ASIF-2026-009001'
                && str_contains($mail->plainBody, 'Please pay soon.');
        });
    }

    #[Test]
    public function failed_communication_resend_uses_modern_layout_and_preserves_original_content(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = $this->asifAgency();
        $admin = $this->platformAdmin();
        $booking = $this->bookingWithContact($agency, 'ASIF-2026-009002');
        $subject = 'Invoice update for booking ASIF-2026-009002';
        $message = "Dear customer,\n\nYour invoice is ready.";

        AgencyCommunicationSetting::query()->firstOrCreate(
            ['agency_id' => $agency->id],
            ['email_enabled' => true],
        );
        AgencyMessageTemplate::query()->create([
            'agency_id' => $agency->id,
            'event' => 'invoice_sent_manual',
            'channel' => 'email',
            'subject' => $subject,
            'body' => $message,
            'is_enabled' => true,
        ]);

        $failedLog = CommunicationLog::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'channel' => 'email',
            'event' => 'invoice_sent_manual',
            'recipient_email' => 'customer@example.test',
            'subject' => $subject,
            'message' => $message,
            'status' => 'failed',
            'provider' => 'log',
        ]);

        Mail::fake();

        $this->actingAs($admin)
            ->post(route('admin.bookings.communication.resend', [$booking, $failedLog]))
            ->assertRedirect()
            ->assertSessionHas('status', 'Failed communication resent.');

        Mail::assertSent(ManualBookingCommunicationMail::class, function (ManualBookingCommunicationMail $mail) use ($subject): bool {
            return $mail->hasTo('customer@example.test')
                && $mail->emailSubject === $subject
                && str_contains($mail->htmlBody, '<!DOCTYPE html>')
                && str_contains($mail->plainBody, 'Your invoice is ready.');
        });
    }

    #[Test]
    public function password_reset_and_email_verification_registry_labels_show_framework_managed(): void
    {
        $verification = EmailTemplateRegistry::find('auth-email-verification');
        $passwordReset = EmailTemplateRegistry::find('auth-password-reset');

        $this->assertNotNull($verification);
        $this->assertNotNull($passwordReset);
        $this->assertSame('framework_notification', $verification->sendPath);
        $this->assertSame('framework_notification', $passwordReset->sendPath);
        $this->assertSame('Framework-managed', EmailTemplateRegistry::connectionLabelFor($verification));
        $this->assertSame('Framework-managed', EmailTemplateRegistry::connectionLabelFor($passwordReset));
    }

    #[Test]
    public function converted_email_bodies_do_not_expose_secrets_or_supplier_internals(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = $this->asifAgency();
        $user = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
            'name' => 'Secret Test',
            'email' => 'secret.test@example.test',
            'meta' => ['sabre_payload' => 'SECRET-SABRE', 'reset_token' => 'abc123token'],
        ]);

        $welcome = CustomerWelcomeMail::forUser($user);
        $admin = new AdminNewCustomerSignupMail($user, '+92000000001');

        foreach ([$welcome->htmlBody, $welcome->plainBody, $admin->htmlBody, $admin->plainBody] as $body) {
            $this->assertStringNotContainsString('SECRET-SABRE', $body);
            $this->assertStringNotContainsString('abc123token', $body);
            $this->assertStringNotContainsString('smtp_password', strtolower($body));
        }

        $sanitized = EmailBodySanitizer::toSafePlainBody('<script>steal()</script>Safe text only');
        $this->assertSame('Safe text only', $sanitized);
    }

    #[Test]
    public function existing_i3_i7_email_tests_still_pass_registry_expectations(): void
    {
        $customer = EmailTemplateRegistry::find('customer-booking_request_received');
        $manual = EmailTemplateRegistry::find('manual-payment_reminder_manual');
        $abandoned = EmailTemplateRegistry::find('marketing-abandoned-search');

        $this->assertSame('Modern layout', EmailTemplateRegistry::connectionLabelFor($customer));
        $this->assertSame('Modern layout', EmailTemplateRegistry::connectionLabelFor(EmailTemplateRegistry::find('ops-booking_request_received')));
        $this->assertSame('Editable · Modern layout', EmailTemplateRegistry::connectionLabelFor($manual));
        $this->assertSame('modern_layout', $abandoned->sendPath);
        $this->assertSame('Modern layout', EmailTemplateRegistry::connectionLabelFor($abandoned));

        Mail::fake();
        $this->assertFalse(class_exists(BookingRequestReceivedMail::class) === false);
        $this->assertFalse(class_exists(PaymentVerifiedMail::class) === false);
        $this->assertFalse(class_exists(OtaOperationalNotificationMail::class) === false);
        Mail::assertNothingSent();
    }

    protected function asifAgency(): Agency
    {
        return Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();
    }

    protected function bookingWithContact(Agency $agency, string $reference): Booking
    {
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'booking_reference' => $reference,
            'route' => 'KHI → DXB',
            'travel_date' => now()->addDays(14),
            'status' => BookingStatus::Pending,
        ]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'customer@example.test',
            'phone' => '+92000000001',
            'meta' => ['name' => 'Test Customer'],
        ]);

        return $booking->fresh(['agency.agencySetting', 'contact']);
    }
}
